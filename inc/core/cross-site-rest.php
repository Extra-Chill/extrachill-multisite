<?php
/**
 * Cross-site REST request helper.
 *
 * Makes internal HTTP requests to other subsites in the network via localhost,
 * bypassing Cloudflare and external DNS. Uses the correct Host header so nginx
 * routes to the right WordPress site with the correct plugins loaded.
 *
 * Auth is handled two ways:
 * 1. Cookie forwarding — for browser-originated requests (cookies + nonce).
 * 2. Internal trust — for server-to-server requests (bridge, CLI, chat tools).
 *    Uses an HMAC-signed X-EC-Internal-User header verified by the target site.
 *
 * @package ExtraChillMultisite
 * @since 1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Make a REST API request to another subsite via internal HTTP.
 *
 * Routes through 127.0.0.1 with the correct Host header so nginx dispatches
 * to the right virtual host. The target site bootstraps its own plugins,
 * meaning abilities registered by site-specific plugins are available.
 *
 * @param string $site_key Logical site key (e.g. 'community', 'artist', 'events').
 * @param string $method   HTTP method (GET, POST, PUT, DELETE).
 * @param string $path     REST path without namespace (e.g. '/community/topics').
 * @param array  $args     Optional. Request arguments:
 *                         - 'body'    => array|string  Request body for POST/PUT.
 *                         - 'query'   => array         Query parameters for GET.
 *                         - 'headers' => array         Additional headers.
 *                         - 'timeout' => int           Request timeout in seconds. Default 15.
 *                         - 'user_id' => int           Override user ID for auth. Default: current user.
 * @return array|WP_Error  Decoded JSON response body, or WP_Error on failure.
 */
function ec_cross_site_rest_request( string $site_key, string $method, string $path, array $args = array() ) {
	$site_url = ec_get_site_url( $site_key );

	if ( ! $site_url ) {
		return new WP_Error(
			'ec_unknown_site',
			sprintf( 'Unknown site key: %s', $site_key ),
			array( 'status' => 400 )
		);
	}

	$host = wp_parse_url( $site_url, PHP_URL_HOST );

	if ( ! $host ) {
		return new WP_Error(
			'ec_invalid_site_url',
			sprintf( 'Could not parse host from site URL: %s', $site_url ),
			array( 'status' => 500 )
		);
	}

	// Build the localhost URL — route through 127.0.0.1 via HTTPS.
	$rest_path = '/wp-json/extrachill/v1' . $path;

	// Append query parameters for GET requests.
	if ( ! empty( $args['query'] ) && is_array( $args['query'] ) ) {
		$rest_path .= '?' . http_build_query( $args['query'] );
	}

	$url = 'https://127.0.0.1' . $rest_path;

	// Build headers.
	$headers = array(
		'Host'         => $host,
		'Content-Type' => 'application/json',
		'Accept'       => 'application/json',
	);

	// Auth: determine user ID and build auth headers.
	$user_id     = $args['user_id'] ?? get_current_user_id();
	$auth_headers = ec_cross_site_build_auth_headers( $user_id );
	$headers      = array_merge( $headers, $auth_headers );

	// Merge any additional headers.
	if ( ! empty( $args['headers'] ) && is_array( $args['headers'] ) ) {
		$headers = array_merge( $headers, $args['headers'] );
	}

	$timeout = $args['timeout'] ?? 15;
	$method  = strtoupper( $method );

	$request_args = array(
		'method'    => $method,
		'headers'   => $headers,
		'timeout'   => $timeout,
		'sslverify' => false, // Localhost — skip certificate verification.
	);

	// Attach body for POST/PUT/PATCH/DELETE.
	if ( in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) && isset( $args['body'] ) ) {
		$request_args['body'] = wp_json_encode( $args['body'] );
	}

	$response = wp_remote_request( $url, $request_args );

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'ec_cross_site_request_failed',
			sprintf( 'Cross-site request to %s failed: %s', $site_key, $response->get_error_message() ),
			array( 'status' => 502 )
		);
	}

	$status_code = wp_remote_retrieve_response_code( $response );
	$body        = wp_remote_retrieve_body( $response );
	$decoded     = json_decode( $body, true );

	// If the target returned an error status, wrap it.
	if ( $status_code >= 400 ) {
		$error_message = 'Cross-site request failed';
		$error_code    = 'ec_cross_site_error';

		if ( is_array( $decoded ) ) {
			if ( ! empty( $decoded['message'] ) ) {
				$error_message = $decoded['message'];
			}
			if ( ! empty( $decoded['code'] ) ) {
				$error_code = $decoded['code'];
			}
		}

		return new WP_Error( $error_code, $error_message, array( 'status' => $status_code ) );
	}

	// Return decoded JSON, or the raw body if JSON parsing failed.
	return is_array( $decoded ) ? $decoded : $body;
}

/**
 * Build auth headers for cross-site requests.
 *
 * Uses two strategies:
 * 1. If the current request has cookies (browser context), forward them.
 * 2. Always include a signed internal user header for server-to-server trust.
 *
 * The target site's `ec_cross_site_authenticate_internal_request` hook
 * validates the HMAC and sets the current user.
 *
 * @param int $user_id User ID to authenticate as.
 * @return array Headers array.
 */
function ec_cross_site_build_auth_headers( int $user_id ): array {
	$headers = array();

	// Strategy 1: Forward cookies from browser requests.
	if ( ! empty( $_SERVER['HTTP_COOKIE'] ) ) {
		$headers['Cookie'] = $_SERVER['HTTP_COOKIE'];
	}

	// Forward the nonce if present in the original request.
	if ( ! empty( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
		$headers['X-WP-Nonce'] = $_SERVER['HTTP_X_WP_NONCE'];
	}

	// Strategy 2: Signed internal user header (works without cookies).
	if ( $user_id > 0 ) {
		$timestamp = time();
		$signature = ec_cross_site_sign_request( $user_id, $timestamp );

		$headers['X-EC-Internal-User']      = (string) $user_id;
		$headers['X-EC-Internal-Timestamp'] = (string) $timestamp;
		$headers['X-EC-Internal-Signature'] = $signature;
	}

	return $headers;
}

/**
 * Generate an HMAC signature for internal cross-site auth.
 *
 * Uses the WordPress AUTH_SALT as the shared secret — it's the same
 * across all sites in the multisite network (shared wp-config.php).
 *
 * @param int $user_id   User ID to sign.
 * @param int $timestamp Unix timestamp.
 * @return string HMAC-SHA256 hex signature.
 */
function ec_cross_site_sign_request( int $user_id, int $timestamp ): string {
	$secret  = defined( 'AUTH_SALT' ) ? AUTH_SALT : wp_salt( 'auth' );
	$payload = sprintf( 'ec-internal:%d:%d', $user_id, $timestamp );

	return hash_hmac( 'sha256', $payload, $secret );
}

/**
 * Verify an HMAC signature from an internal cross-site request.
 *
 * @param int    $user_id   Claimed user ID.
 * @param int    $timestamp Request timestamp.
 * @param string $signature HMAC signature to verify.
 * @return bool True if valid and not expired (5 minute window).
 */
function ec_cross_site_verify_signature( int $user_id, int $timestamp, string $signature ): bool {
	// Reject requests older than 5 minutes.
	if ( abs( time() - $timestamp ) > 300 ) {
		return false;
	}

	$expected = ec_cross_site_sign_request( $user_id, $timestamp );

	return hash_equals( $expected, $signature );
}

/**
 * Authenticate internal cross-site requests.
 *
 * Hooked early into `rest_authentication_errors` to check for the
 * X-EC-Internal-* headers and set the current user if valid.
 *
 * Only trusts requests from localhost (127.0.0.1 / ::1).
 *
 * @param WP_Error|null|true $result Existing auth result.
 * @return WP_Error|null|true Auth result.
 */
function ec_cross_site_authenticate_internal_request( $result ) {
	// Don't override if already authenticated.
	if ( null !== $result ) {
		return $result;
	}

	// Check for internal headers.
	$user_id   = isset( $_SERVER['HTTP_X_EC_INTERNAL_USER'] ) ? (int) $_SERVER['HTTP_X_EC_INTERNAL_USER'] : 0;
	$timestamp = isset( $_SERVER['HTTP_X_EC_INTERNAL_TIMESTAMP'] ) ? (int) $_SERVER['HTTP_X_EC_INTERNAL_TIMESTAMP'] : 0;
	$signature = isset( $_SERVER['HTTP_X_EC_INTERNAL_SIGNATURE'] ) ? sanitize_text_field( $_SERVER['HTTP_X_EC_INTERNAL_SIGNATURE'] ) : '';

	if ( ! $user_id || ! $timestamp || ! $signature ) {
		return $result;
	}

	// Only trust requests from localhost.
	$remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
	if ( ! in_array( $remote_ip, array( '127.0.0.1', '::1' ), true ) ) {
		return $result;
	}

	// Verify the HMAC signature.
	if ( ! ec_cross_site_verify_signature( $user_id, $timestamp, $signature ) ) {
		return new WP_Error(
			'ec_internal_auth_failed',
			'Internal cross-site authentication failed.',
			array( 'status' => 403 )
		);
	}

	// Verify user exists.
	$user = get_user_by( 'id', $user_id );
	if ( ! $user ) {
		return new WP_Error(
			'ec_internal_user_not_found',
			'Internal auth user not found.',
			array( 'status' => 403 )
		);
	}

	// Set the current user — this request is trusted.
	wp_set_current_user( $user_id );

	return true;
}
add_filter( 'rest_authentication_errors', 'ec_cross_site_authenticate_internal_request', 5 );

/**
 * Get the site key for a given REST route path prefix.
 *
 * Used by the API route affinity middleware to determine which site
 * a route belongs to.
 *
 * @param string $route The REST route path (e.g. '/extrachill/v1/community/topics').
 * @return string|null  Site key (e.g. 'community') or null if route has no affinity.
 */
function ec_get_route_site_affinity( string $route ): ?string {
	/**
	 * Filters the route-to-site affinity map.
	 *
	 * Keys are REST path prefixes (after /wp-json/), values are site keys.
	 * The middleware checks if the current route starts with any prefix.
	 *
	 * @param array $affinity_map Route prefix => site key mapping.
	 */
	$affinity_map = apply_filters(
		'ec_route_site_affinity_map',
		array(
			'/extrachill/v1/blog/'      => 'main',
			'/extrachill/v1/community/' => 'community',
			'/extrachill/v1/artists/'   => 'artist',
			'/extrachill/v1/events/'    => 'events',
			'/extrachill/v1/shop/'      => 'shop',
			'/extrachill/v1/wire/'      => 'wire',
			'/extrachill/v1/docs/'      => 'docs',
		)
	);

	foreach ( $affinity_map as $prefix => $site_key ) {
		if ( str_starts_with( $route, $prefix ) ) {
			return $site_key;
		}
	}

	return null;
}
