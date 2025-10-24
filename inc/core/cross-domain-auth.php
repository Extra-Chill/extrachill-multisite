<?php
/**
 * Cross-Domain Authentication for extrachill.link
 *
 * Sets authentication cookies on both .extrachill.com and extrachill.link domains
 * to enable seamless authentication across the mapped domain.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set extrachill.link auth cookie when WordPress sets its auth cookie
 */
function extrachill_set_extrachill_link_auth_cookie( $logged_in_cookie, $expire, $expiration, $user_id, $scheme, $token ) {
	$auth_cookie = wp_generate_auth_cookie( $user_id, $expiration, 'auth', $token );

	$secure = is_ssl();
	$httponly = true;

	$result_auth = setcookie( AUTH_COOKIE, $auth_cookie, $expiration, PLUGINS_COOKIE_PATH, 'extrachill.link', $secure, $httponly );
	error_log( sprintf(
		'[Cross-Domain Auth] AUTH_COOKIE for extrachill.link - success=%s, user_id=%d, path=%s',
		$result_auth ? 'YES' : 'NO',
		$user_id,
		PLUGINS_COOKIE_PATH
	) );

	if ( is_admin() || 'admin' === $scheme ) {
		$result_secure = setcookie( SECURE_AUTH_COOKIE, $auth_cookie, $expiration, ADMIN_COOKIE_PATH, 'extrachill.link', $secure, $httponly );
		error_log( sprintf(
			'[Cross-Domain Auth] SECURE_AUTH_COOKIE for extrachill.link - success=%s',
			$result_secure ? 'YES' : 'NO'
		) );
	}
}
add_action( 'set_auth_cookie', 'extrachill_set_extrachill_link_auth_cookie', 10, 6 );

/**
 * Set extrachill.link logged-in cookie when WordPress sets its logged-in cookie
 */
function extrachill_set_extrachill_link_logged_in_cookie( $logged_in_cookie, $expire, $expiration, $user_id, $logged_in ) {
	$secure = is_ssl();
	$httponly = true;

	$result = setcookie( LOGGED_IN_COOKIE, $logged_in_cookie, $expiration, COOKIEPATH, 'extrachill.link', $secure, $httponly );
	error_log( sprintf(
		'[Cross-Domain Auth] LOGGED_IN_COOKIE for extrachill.link - success=%s, user_id=%d, path=%s, cookie_name=%s',
		$result ? 'YES' : 'NO',
		$user_id,
		COOKIEPATH,
		LOGGED_IN_COOKIE
	) );
}
add_action( 'set_logged_in_cookie', 'extrachill_set_extrachill_link_logged_in_cookie', 10, 5 );

/**
 * Clear extrachill.link cookies when WordPress clears its cookies
 */
function extrachill_clear_extrachill_link_auth_cookies() {
	$past = time() - YEAR_IN_SECONDS;

	setcookie( LOGGED_IN_COOKIE, ' ', $past, COOKIEPATH, 'extrachill.link' );
	setcookie( AUTH_COOKIE, ' ', $past, PLUGINS_COOKIE_PATH, 'extrachill.link' );
	setcookie( SECURE_AUTH_COOKIE, ' ', $past, ADMIN_COOKIE_PATH, 'extrachill.link' );
}
add_action( 'clear_auth_cookie', 'extrachill_clear_extrachill_link_auth_cookies' );

/**
 * Manually authenticate users on extrachill.link by reading .extrachill.com cookies
 *
 * WordPress cookies contain domain-specific HMAC signatures that fail validation
 * when the domain doesn't match. This function bypasses domain validation by
 * manually parsing and validating the logged_in cookie from .extrachill.com.
 */
function extrachill_link_authenticate_user() {
	// Only run on extrachill.link domain
	if ( ! isset( $_SERVER['HTTP_HOST'] ) || $_SERVER['HTTP_HOST'] !== 'extrachill.link' ) {
		return;
	}

	// If already authenticated, don't override
	if ( get_current_user_id() > 0 ) {
		return;
	}

	// Debug: Log all available cookies
	error_log( '[Cross-Domain Auth] Available cookies: ' . implode( ', ', array_keys( $_COOKIE ) ) );

	// Find the logged_in cookie (name varies by site)
	$logged_in_cookie = null;
	$logged_in_cookie_name = null;
	foreach ( $_COOKIE as $name => $value ) {
		error_log( '[Cross-Domain Auth] Checking cookie: ' . $name );
		if ( strpos( $name, 'wordpress_logged_in_' ) === 0 ) {
			$logged_in_cookie = $value;
			$logged_in_cookie_name = $name;
			break;
		}
	}

	if ( ! $logged_in_cookie ) {
		error_log( '[Cross-Domain Auth] No logged_in cookie found on extrachill.link' );
		return;
	}

	error_log( sprintf(
		'[Cross-Domain Auth] Found cookie: %s, value: %s',
		$logged_in_cookie_name,
		substr( $logged_in_cookie, 0, 50 ) . '...'
	) );

	// Parse cookie: username|expiration|token|hmac
	$cookie_elements = explode( '|', $logged_in_cookie );
	if ( count( $cookie_elements ) !== 4 ) {
		error_log( '[Cross-Domain Auth] Invalid cookie format' );
		return;
	}

	list( $username, $expiration, $token, $hmac ) = $cookie_elements;
	$username = rawurldecode( $username );

	// Check expiration
	if ( $expiration < time() ) {
		error_log( '[Cross-Domain Auth] Cookie expired' );
		return;
	}

	// Get user
	$user = get_user_by( 'login', $username );
	if ( ! $user ) {
		error_log( sprintf( '[Cross-Domain Auth] User not found: %s', $username ) );
		return;
	}

	// Validate session token
	$session_manager = WP_Session_Tokens::get_instance( $user->ID );
	$session = $session_manager->get( $token );

	if ( ! $session ) {
		error_log( sprintf( '[Cross-Domain Auth] Invalid session token for user: %s', $username ) );
		return;
	}

	// Successfully authenticated - set current user
	wp_set_current_user( $user->ID );
	error_log( sprintf( '[Cross-Domain Auth] Successfully authenticated user_id=%d via .extrachill.com cookie', $user->ID ) );
}
add_action( 'init', 'extrachill_link_authenticate_user', 1 );
