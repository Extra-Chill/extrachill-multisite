<?php
/**
 * ExtraChill Multisite Network Admin Menu
 *
 * Registers top-level network admin menu for centralized ExtraChill Platform settings.
 * Other plugins can hook into this menu by using the parent slug 'extrachill-multisite'.
 *
 * @package ExtraChill\Multisite
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Define menu slug constant for other plugins to use
if ( ! defined( 'EXTRACHILL_MULTISITE_MENU_SLUG' ) ) {
	define( 'EXTRACHILL_MULTISITE_MENU_SLUG', 'extrachill-multisite' );
}

add_action( 'network_admin_menu', 'ec_add_network_multisite_menu' );

/**
 * Add top-level network admin menu for ExtraChill Multisite
 *
 * @since 1.0.0
 */
function ec_add_network_multisite_menu() {
	add_menu_page(
		'Extra Chill Multisite',
		'Extra Chill Multisite',
		'manage_network_options',
		EXTRACHILL_MULTISITE_MENU_SLUG,
		'ec_render_multisite_menu_redirect',
		'dashicons-admin-multisite',
		3
	);
}

/**
 * Redirect to first submenu page
 *
 * Since the top-level menu is just a container, redirect to the first submenu item.
 *
 * @since 1.0.0
 */
function ec_render_multisite_menu_redirect() {
	wp_safe_redirect( network_admin_url( 'admin.php?page=extrachill-security' ) );
	exit;
}
