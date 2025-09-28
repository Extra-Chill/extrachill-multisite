<?php
/**
 * ExtraChill Turnstile Security Module
 *
 * Centralized Cloudflare Turnstile integration for the ExtraChill multisite network.
 * Provides network-wide configuration storage and verification functions accessible
 * from all sites in the multisite installation.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get Turnstile site key from network options
 *
 * Retrieves the Cloudflare Turnstile site key stored at the network level,
 * making it accessible from all sites in the multisite installation.
 *
 * @since 1.0.0
 * @return string Turnstile site key or empty string if not configured
 */
function ec_get_turnstile_site_key() {
    return get_site_option( 'ec_turnstile_site_key', '' );
}

/**
 * Get Turnstile secret key from network options
 *
 * Retrieves the Cloudflare Turnstile secret key stored at the network level,
 * making it accessible from all sites in the multisite installation.
 *
 * @since 1.0.0
 * @return string Turnstile secret key or empty string if not configured
 */
function ec_get_turnstile_secret_key() {
    return get_site_option( 'ec_turnstile_secret_key', '' );
}

/**
 * Update Turnstile site key at network level
 *
 * @since 1.0.0
 * @param string $site_key Turnstile site key
 * @return bool True on success, false on failure
 */
function ec_update_turnstile_site_key( $site_key ) {
    return update_site_option( 'ec_turnstile_site_key', sanitize_text_field( $site_key ) );
}

/**
 * Update Turnstile secret key at network level
 *
 * @since 1.0.0
 * @param string $secret_key Turnstile secret key
 * @return bool True on success, false on failure
 */
function ec_update_turnstile_secret_key( $secret_key ) {
    return update_site_option( 'ec_turnstile_secret_key', sanitize_text_field( $secret_key ) );
}

/**
 * Verify Cloudflare Turnstile response
 *
 * Centralized Turnstile verification function using network-configured keys.
 * Validates the response token with Cloudflare's API and returns verification status.
 *
 * @since 1.0.0
 * @param string $response Turnstile response token from form submission
 * @return bool True if verification successful, false otherwise
 */
function ec_verify_turnstile_response( $response ) {
    if ( empty( $response ) ) {
        return false;
    }

    $secret_key = ec_get_turnstile_secret_key();
    if ( empty( $secret_key ) ) {
        error_log( 'ExtraChill Turnstile: Secret key not configured in network settings' );
        return false;
    }

    $verification_url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $verification_data = array(
        'secret' => $secret_key,
        'response' => $response,
        'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '',
    );

    $http_response = wp_remote_post( $verification_url, array(
        'body' => $verification_data,
        'timeout' => 15,
    ) );

    // Handle connection errors
    if ( is_wp_error( $http_response ) ) {
        error_log( 'ExtraChill Turnstile Verification Error: ' . $http_response->get_error_message() );
        return false;
    }

    // Validate HTTP response
    $response_code = wp_remote_retrieve_response_code( $http_response );
    if ( $response_code !== 200 ) {
        error_log( 'ExtraChill Turnstile Verification HTTP Error: Code ' . $response_code . ' Body: ' . wp_remote_retrieve_body( $http_response ) );
        return false;
    }

    // Parse and validate JSON response
    $response_body = wp_remote_retrieve_body( $http_response );
    $result = json_decode( $response_body, true );

    if ( $result === null ) {
        error_log( 'ExtraChill Turnstile Verification JSON Decode Error: Body - ' . $response_body );
        return false;
    }

    // Check verification result
    if ( isset( $result['success'] ) && $result['success'] === true ) {
        return true;
    }

    // Log verification failures
    if ( isset( $result['error-codes'] ) && is_array( $result['error-codes'] ) ) {
        error_log( 'ExtraChill Turnstile Verification Failed: ' . implode( ', ', $result['error-codes'] ) );
    } else {
        error_log( 'ExtraChill Turnstile Verification Unexpected Response: ' . $response_body );
    }

    return false;
}

/**
 * Check if Turnstile is properly configured
 *
 * Verifies that both site key and secret key are configured at the network level.
 *
 * @since 1.0.0
 * @return bool True if both keys are configured, false otherwise
 */
function ec_is_turnstile_configured() {
    $site_key = ec_get_turnstile_site_key();
    $secret_key = ec_get_turnstile_secret_key();

    return ! empty( $site_key ) && ! empty( $secret_key );
}

/**
 * Render Turnstile widget HTML
 *
 * Outputs the Turnstile div element with the configured site key.
 * Only renders if Turnstile is properly configured.
 *
 * @since 1.0.0
 * @param array $args Optional arguments for the widget
 * @return string Turnstile widget HTML or empty string if not configured
 */
function ec_render_turnstile_widget( $args = array() ) {
    if ( ! ec_is_turnstile_configured() ) {
        return '';
    }

    $site_key = ec_get_turnstile_site_key();
    $defaults = array(
        'data-sitekey' => $site_key,
        'class' => 'cf-turnstile',
    );

    $args = wp_parse_args( $args, $defaults );

    $attributes = '';
    foreach ( $args as $key => $value ) {
        if ( $key === 'class' ) {
            $attributes .= sprintf( ' class="%s"', esc_attr( $value ) );
        } else {
            $attributes .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $value ) );
        }
    }

    return sprintf( '<div%s></div>', $attributes );
}

/**
 * Enqueue Turnstile script
 *
 * Conditionally enqueues the Cloudflare Turnstile JavaScript if Turnstile is configured.
 * Should be called from the appropriate hook in individual plugins.
 *
 * @since 1.0.0
 * @param string $handle Script handle (optional)
 */
function ec_enqueue_turnstile_script( $handle = 'cloudflare-turnstile' ) {
    if ( ec_is_turnstile_configured() ) {
        wp_enqueue_script( $handle, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
    }
}