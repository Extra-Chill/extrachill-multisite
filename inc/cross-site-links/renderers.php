<?php
/**
 * Cross-Site Link Renderers
 *
 * Display components for rendering cross-site navigation buttons.
 * Uses button-3 button-small classes from theme root.css for consistent styling.
 *
 * @package ExtraChillMultisite
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render cross-site taxonomy links on archive pages
 *
 * Hooked to: extrachill_archive_below_description
 */
function ec_render_cross_site_taxonomy_links() {
	if ( ! is_tax() ) {
		return;
	}

	$term = get_queried_object();
	if ( ! $term || ! isset( $term->taxonomy ) ) {
		return;
	}

	$links = ec_get_cross_site_term_links( $term, $term->taxonomy );
	if ( empty( $links ) ) {
		return;
	}

	echo '<div class="ec-cross-site-links ec-cross-site-taxonomy-links">';
	foreach ( $links as $link ) {
		ec_cross_site_link_button( $link );
	}
	echo '</div>';
}

/**
 * Render cross-site user links on author archives
 *
 * Hooked to: extrachill_after_author_bio
 *
 * @param int $user_id Author user ID.
 */
function ec_render_cross_site_user_links( $user_id ) {
	if ( ! $user_id || ! is_int( $user_id ) ) {
		return;
	}

	$links = ec_get_cross_site_user_links( $user_id );
	if ( empty( $links ) ) {
		return;
	}

	echo '<div class="ec-cross-site-links ec-cross-site-user-links">';
	foreach ( $links as $link ) {
		ec_cross_site_link_button( $link );
	}
	echo '</div>';
}

/**
 * Render artist profile button on artist taxonomy archives
 *
 * Hooked to: extrachill_archive_header_actions
 */
function ec_render_cross_site_artist_archive_links() {
	if ( ! is_tax( 'artist' ) ) {
		return;
	}

	$term = get_queried_object();
	if ( ! ( $term instanceof WP_Term ) || empty( $term->slug ) ) {
		return;
	}

	$links = ec_get_cross_site_artist_links( $term->slug, 'archive' );
	if ( empty( $links ) ) {
		return;
	}

	echo '<div class="ec-cross-site-links ec-cross-site-artist-links">';
	foreach ( $links as $link ) {
		ec_cross_site_link_button( $link );
	}
	echo '</div>';
}

/**
 * Render blog coverage button on artist profiles
 *
 * Called directly by artist platform plugin template.
 *
 * @param string $artist_slug Artist profile slug.
 */
function ec_render_cross_site_artist_profile_links( $artist_slug ) {
	if ( empty( $artist_slug ) ) {
		return;
	}

	$links = ec_get_cross_site_artist_links( $artist_slug, 'profile' );
	if ( empty( $links ) ) {
		return;
	}

	echo '<div class="ec-cross-site-links ec-cross-site-artist-profile-links">';
	foreach ( $links as $link ) {
		ec_cross_site_link_button( $link );
	}
	echo '</div>';
}

/**
 * Render a single cross-site link button
 *
 * @param array  $link  Link data with 'url', 'label', and optional 'count'.
 * @param string $class Additional CSS class.
 */
function ec_cross_site_link_button( $link, $class = '' ) {
	if ( empty( $link['url'] ) || empty( $link['label'] ) ) {
		return;
	}

	$button_class = 'button-3 button-small ec-cross-site-link';
	if ( ! empty( $class ) ) {
		$button_class .= ' ' . esc_attr( $class );
	}

	$label = esc_html( $link['label'] );
	if ( isset( $link['count'] ) && $link['count'] > 0 ) {
		$label .= ' (' . (int) $link['count'] . ')';
	}

	printf(
		'<a href="%s" class="%s">%s</a>',
		esc_url( $link['url'] ),
		esc_attr( $button_class ),
		$label
	);
}
