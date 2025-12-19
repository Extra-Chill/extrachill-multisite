<?php
/**
 * ExtraChill Multisite Network Admin Menu
 *
 * Top-level network admin menu for ExtraChill Platform settings.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'EXTRACHILL_MULTISITE_MENU_SLUG' ) ) {
	define( 'EXTRACHILL_MULTISITE_MENU_SLUG', 'extrachill-multisite' );
}

add_action( 'network_admin_menu', 'ec_add_network_multisite_menu', 5 );

function ec_add_network_multisite_menu() {
	add_menu_page(
		'Extra Chill Multisite',
		'Extra Chill Multisite',
		'manage_network_options',
		EXTRACHILL_MULTISITE_MENU_SLUG,
		null,
		'dashicons-admin-multisite',
		3
	);
}
