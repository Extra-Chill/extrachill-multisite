<?php
/**
 * Cloudflare Turnstile Integration
 *
 * Network-wide captcha configuration accessible from all sites.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

function ec_get_turnstile_site_key() {
	return get_site_option( 'ec_turnstile_site_key', '' );
}

function ec_get_turnstile_secret_key() {
	return get_site_option( 'ec_turnstile_secret_key', '' );
}

function ec_update_turnstile_site_key( $site_key ) {
	return update_site_option( 'ec_turnstile_site_key', sanitize_text_field( $site_key ) );
}

function ec_update_turnstile_secret_key( $secret_key ) {
	return update_site_option( 'ec_turnstile_secret_key', sanitize_text_field( $secret_key ) );
}

/**
 * Verify Cloudflare Turnstile response via API with comprehensive error logging.
 *
 * Filterable via 'extrachill_bypass_turnstile_verification' for dev environments.
 */
function ec_verify_turnstile_response( $response ) {
	$is_local_environment = defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local';
	$bypass               = $is_local_environment || (bool) apply_filters( 'extrachill_bypass_turnstile_verification', false );
	if ( true === $bypass ) {
		return true;
	}

	$response = sanitize_text_field( wp_unslash( $response ) );

	if ( empty( $response ) ) {
		error_log( 'ExtraChill Turnstile: Empty response token received' );
		return false;
	}

	$secret_key = ec_get_turnstile_secret_key();
	if ( empty( $secret_key ) ) {
		error_log( 'ExtraChill Turnstile: Secret key not configured in network settings' );
		return false;
	}

	$verification_url  = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
	$verification_data = array(
		'secret'   => $secret_key,
		'response' => $response,
		'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
	);

	$http_response = wp_remote_post(
		$verification_url,
		array(
			'body'    => $verification_data,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $http_response ) ) {
		error_log( 'ExtraChill Turnstile Verification Error: ' . $http_response->get_error_message() );
		return false;
	}

	$response_code = wp_remote_retrieve_response_code( $http_response );
	if ( 200 !== $response_code ) {
		error_log( 'ExtraChill Turnstile Verification HTTP Error: Code ' . $response_code . ' Body: ' . wp_remote_retrieve_body( $http_response ) );
		return false;
	}

	$response_body = wp_remote_retrieve_body( $http_response );
	$result        = json_decode( $response_body, true );

	if ( null === $result ) {
		error_log( 'ExtraChill Turnstile Verification JSON Decode Error: Body - ' . $response_body );
		return false;
	}

	if ( isset( $result['success'] ) && true === $result['success'] ) {
		error_log( 'ExtraChill Turnstile: Verification successful' );
		return true;
	}

	if ( isset( $result['error-codes'] ) && is_array( $result['error-codes'] ) ) {
		error_log( 'ExtraChill Turnstile Verification Failed: ' . implode( ', ', $result['error-codes'] ) );
	} else {
		error_log( 'ExtraChill Turnstile Verification Unexpected Response: ' . $response_body );
	}

	return false;
}

function ec_is_turnstile_configured() {
	$site_key   = ec_get_turnstile_site_key();
	$secret_key = ec_get_turnstile_secret_key();

	return ! empty( $site_key ) && ! empty( $secret_key );
}

function ec_render_turnstile_widget( $args = array() ) {
	if ( ! ec_is_turnstile_configured() ) {
		return '';
	}

	$site_key = ec_get_turnstile_site_key();
	$defaults = array(
		'data-sitekey'    => $site_key,
		'data-size'       => 'normal',
		'data-theme'      => 'auto',
		'data-appearance' => 'interaction-only',
		'class'           => 'cf-turnstile',
	);

	$args = wp_parse_args( $args, $defaults );

	$attributes = '';
	foreach ( $args as $key => $value ) {
		if ( 'class' === $key ) {
			$attributes .= sprintf( ' class="%s"', esc_attr( $value ) );
		} else {
			$attributes .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}
	}

	return sprintf( '<div%s></div>', $attributes );
}

function ec_enqueue_turnstile_script( $handle = 'cloudflare-turnstile' ) {
	if ( ec_is_turnstile_configured() ) {
		wp_enqueue_script( $handle, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
	}
}

/**
 * Verify a Turnstile token from a REST request or raw value.
 *
 * Reusable check for any form-handling code that has either a WP_REST_Request
 * (reads the 'turnstile_response' parameter automatically) or a raw token
 * string (passed directly).
 *
 * Honors the same bypass conditions as ec_verify_turnstile_response()
 * (local environment + 'extrachill_bypass_turnstile_verification' filter).
 *
 * @param WP_REST_Request|string $request_or_token Request object or raw token.
 * @return true|WP_Error True on success, WP_Error on missing/invalid token.
 */
function ec_turnstile_check_request( $request_or_token ) {
	$is_local_environment = defined( 'WP_ENVIRONMENT_TYPE' ) && WP_ENVIRONMENT_TYPE === 'local';
	$bypass               = $is_local_environment || (bool) apply_filters( 'extrachill_bypass_turnstile_verification', false );
	if ( true === $bypass ) {
		return true;
	}

	if ( ! function_exists( 'ec_verify_turnstile_response' ) ) {
		return new WP_Error(
			'turnstile_missing',
			__( 'Security verification unavailable.', 'extrachill-multisite' ),
			array( 'status' => 500 )
		);
	}

	if ( $request_or_token instanceof WP_REST_Request ) {
		$token = (string) $request_or_token->get_param( 'turnstile_response' );
	} else {
		$token = (string) $request_or_token;
	}

	if ( '' === $token ) {
		return new WP_Error(
			'turnstile_missing_token',
			__( 'Security verification required.', 'extrachill-multisite' ),
			array( 'status' => 403 )
		);
	}

	if ( ! ec_verify_turnstile_response( $token ) ) {
		return new WP_Error(
			'turnstile_failed',
			__( 'Security verification failed. Please try again.', 'extrachill-multisite' ),
			array( 'status' => 403 )
		);
	}

	return true;
}

/**
 * Factory: build a REST permission_callback that requires a valid Turnstile token.
 *
 * Use in register_rest_route():
 *
 *     'permission_callback' => ec_turnstile_permission_callback(),
 *
 * Or, to compose with an additional capability check:
 *
 *     'permission_callback' => ec_turnstile_permission_callback( function( $request ) {
 *         return current_user_can( 'edit_posts' );
 *     } ),
 *
 * The Turnstile check runs first; if it passes, the optional secondary
 * callback decides authorization. Both must return truthy for the request
 * to be authorized; a WP_Error from either short-circuits with that error.
 *
 * Note: route registrations must declare 'turnstile_response' in 'args' so
 * the token is sanitized through the standard REST args pipeline.
 *
 * @param callable|null $also Optional secondary permission callback.
 * @return callable Permission callback suitable for register_rest_route().
 */
function ec_turnstile_permission_callback( $also = null ) {
	return function ( WP_REST_Request $request ) use ( $also ) {
		$check = ec_turnstile_check_request( $request );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( is_callable( $also ) ) {
			return $also( $request );
		}

		return true;
	};
}
