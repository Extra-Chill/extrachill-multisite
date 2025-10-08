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
 * Verify Cloudflare Turnstile response
 *
 * @since 1.0.0
 * @param string $response Turnstile response token
 * @return bool True if verification successful
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

    if ( is_wp_error( $http_response ) ) {
        error_log( 'ExtraChill Turnstile Verification Error: ' . $http_response->get_error_message() );
        return false;
    }

    $response_code = wp_remote_retrieve_response_code( $http_response );
    if ( $response_code !== 200 ) {
        error_log( 'ExtraChill Turnstile Verification HTTP Error: Code ' . $response_code . ' Body: ' . wp_remote_retrieve_body( $http_response ) );
        return false;
    }

    $response_body = wp_remote_retrieve_body( $http_response );
    $result = json_decode( $response_body, true );

    if ( $result === null ) {
        error_log( 'ExtraChill Turnstile Verification JSON Decode Error: Body - ' . $response_body );
        return false;
    }

    if ( isset( $result['success'] ) && $result['success'] === true ) {
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
    $site_key = ec_get_turnstile_site_key();
    $secret_key = ec_get_turnstile_secret_key();

    return ! empty( $site_key ) && ! empty( $secret_key );
}

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

function ec_enqueue_turnstile_script( $handle = 'cloudflare-turnstile' ) {
    if ( ec_is_turnstile_configured() ) {
        wp_enqueue_script( $handle, 'https://challenges.cloudflare.com/turnstile/v0/api.js', array(), null, true );
    }
}