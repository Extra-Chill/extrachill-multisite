<?php
/**
 * Asset Management for ExtraChill Multisite
 *
 * Loads CSS assets with filemtime() versioning.
 * Conditionally enqueues based on page context.
 *
 * @package ExtraChill\Multisite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue 404 error page styles.
 */
function extrachill_multisite_enqueue_404_styles() {
	if ( ! is_404() ) {
		return;
	}

	$css_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'assets/css/404.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'extrachill-multisite-404',
			EXTRACHILL_MULTISITE_PLUGIN_URL . 'assets/css/404.css',
			array( 'extrachill-root' ),
			filemtime( $css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'extrachill_multisite_enqueue_404_styles', 10 );

/**
 * Enqueue EC-specific taxonomy badge colors.
 *
 * Loads after theme's base taxonomy-badges.css to override generic styling
 * with music-specific colors for festivals, locations, venues, and artists.
 */
function extrachill_multisite_enqueue_taxonomy_badge_styles() {
	$css_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'assets/css/taxonomy-badges.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'extrachill-multisite-taxonomy-badges',
			EXTRACHILL_MULTISITE_PLUGIN_URL . 'assets/css/taxonomy-badges.css',
			array( 'extrachill-taxonomy-badges' ),
			filemtime( $css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'extrachill_multisite_enqueue_taxonomy_badge_styles', 15 );

/**
 * Enqueue community activity styles.
 *
 * Loads when sidebar is active or community activity is displayed.
 */
function extrachill_multisite_enqueue_community_activity_styles() {
	// Only load on singular posts where the sidebar renders.
	if ( ! is_singular() ) {
		return;
	}

	$css_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'assets/css/community-activity.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'extrachill-multisite-community-activity',
			EXTRACHILL_MULTISITE_PLUGIN_URL . 'assets/css/community-activity.css',
			array( 'extrachill-root' ),
			filemtime( $css_path )
		);
	}
}
add_action( 'wp_enqueue_scripts', 'extrachill_multisite_enqueue_community_activity_styles', 15 );
