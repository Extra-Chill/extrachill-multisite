<?php
/**
 * Legacy path redirects.
 *
 * @package ExtraChillMultisite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'ec_handle_legacy_path_redirects', 1 );

function ec_handle_legacy_path_redirects() {
	$main_blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( 'main' ) : null;
	if ( $main_blog_id === null || (int) $main_blog_id !== (int) get_current_blog_id() ) {
		return;
	}

	if ( empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$request_uri = esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
	$path = wp_parse_url( $request_uri, PHP_URL_PATH );
	if ( ! is_string( $path ) ) {
		return;
	}

	if ( ! preg_match( '#^/festival-wire(?:/|$)#', $path ) ) {
		return;
	}

	$wire_url = function_exists( 'ec_get_site_url' ) ? ec_get_site_url( 'wire' ) : null;
	if ( ! $wire_url ) {
		return;
	}

	wp_safe_redirect( $wire_url . $request_uri, 301 );
	exit;
}
