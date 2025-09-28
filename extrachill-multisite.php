<?php
/**
 * Plugin Name: ExtraChill Multisite
 * Plugin URI: https://extrachill.com
 * Description: Centralized multisite functionality for the ExtraChill Platform
 * Version: 1.0.0
 * Author: Chris Huber
 * Author URI: https://chubes.net
 * Network: true
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Text Domain: extrachill-multisite
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'EXTRACHILL_MULTISITE_VERSION', '1.0.0' );
define( 'EXTRACHILL_MULTISITE_PLUGIN_FILE', __FILE__ );
define( 'EXTRACHILL_MULTISITE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EXTRACHILL_MULTISITE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( EXTRACHILL_MULTISITE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'vendor/autoload.php';
}

register_activation_hook( __FILE__, 'extrachill_multisite_activate' );

function extrachill_multisite_activate() {
	if ( ! is_multisite() ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'ExtraChill Multisite plugin requires a WordPress multisite installation.' );
	}
}

add_action( 'plugins_loaded', 'extrachill_multisite_init' );

function extrachill_multisite_init() {
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/core/admin-access-control.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/core/extrachill-turnstile.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/extrachill-main/comment-author-links.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/extrachill-main/multisite-search.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/extrachill-main/recent-activity-feed.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/shop/ad-free-license.php';
	require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'inc/community/main-site-comments.php';

	// Load network admin interface
	if ( is_admin() && is_network_admin() ) {
		require_once EXTRACHILL_MULTISITE_PLUGIN_DIR . 'admin/network-security-settings.php';
	}
}