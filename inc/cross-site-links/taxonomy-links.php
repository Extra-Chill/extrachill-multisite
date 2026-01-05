<?php
/**
 * Cross-Site Taxonomy Links
 *
 * Functions for linking taxonomy archives across sites in the multisite network.
 * Only returns links to sites where the term exists and has published content.
 *
 * @package ExtraChillMultisite
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get cross-site links for a taxonomy term where content exists
 *
 * Checks each mapped site for the term and returns links only where
 * the term exists with at least one published post.
 *
 * @param WP_Term|int $term     Term object or term ID.
 * @param string      $taxonomy Taxonomy slug.
 * @return array Array of link data, each containing:
 *               - blog_id: int
 *               - site_key: string
 *               - url: string
 *               - label: string
 *               - count: int
 */
function ec_get_cross_site_term_links( $term, $taxonomy ) {
	if ( is_int( $term ) ) {
		$term = get_term( $term, $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return array();
	}

	$taxonomy_site_map = ec_get_taxonomy_site_map();
	if ( ! isset( $taxonomy_site_map[ $taxonomy ] ) ) {
		return array();
	}

	$target_sites     = $taxonomy_site_map[ $taxonomy ];
	$current_site_key = ec_get_current_site_key();
	$site_labels      = ec_get_site_labels();
	$links            = array();

	foreach ( $target_sites as $site_key ) {
		// Skip current site.
		if ( $site_key === $current_site_key ) {
			continue;
		}

		$blog_id = ec_get_blog_id( $site_key );
		if ( ! $blog_id ) {
			continue;
		}

		$term_data = ec_check_term_on_site( $term->slug, $taxonomy, $blog_id );
		if ( ! $term_data || $term_data['count'] < 1 ) {
			continue;
		}

		$url = ec_build_term_archive_url( $term->slug, $taxonomy, $blog_id );
		if ( ! $url ) {
			continue;
		}

		$links[] = array(
			'blog_id'  => $blog_id,
			'site_key' => $site_key,
			'url'      => $url,
			'label'    => isset( $site_labels[ $site_key ] ) ? $site_labels[ $site_key ] : ucfirst( $site_key ),
			'count'    => $term_data['count'],
		);
	}

	return $links;
}

/**
 * Check if term exists on target site with published posts
 *
 * @param string $term_slug Term slug to check.
 * @param string $taxonomy  Taxonomy slug.
 * @param int    $blog_id   Target blog ID.
 * @return array|null Array with 'term_id' and 'count', or null if not found.
 */
function ec_check_term_on_site( $term_slug, $taxonomy, $blog_id ) {
	switch_to_blog( $blog_id );
	try {
		// Check if taxonomy exists on this site.
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		// Get actual post count (term->count may include unpublished).
		$post_types = get_taxonomy( $taxonomy )->object_type;
		$query      = new WP_Query(
			array(
				'post_type'      => $post_types,
				'post_status'    => 'publish',
				'tax_query'      => array(
					array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term->term_id,
					),
				),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			)
		);

		return array(
			'term_id' => $term->term_id,
			'count'   => $query->found_posts,
		);
	} finally {
		restore_current_blog();
	}
}

/**
 * Build taxonomy archive URL for a site
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @param int    $blog_id   Target blog ID.
 * @return string|null Archive URL or null on failure.
 */
function ec_build_term_archive_url( $term_slug, $taxonomy, $blog_id ) {
	switch_to_blog( $blog_id );
	try {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = get_term_by( 'slug', $term_slug, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return null;
		}

		$url = get_term_link( $term );
		if ( is_wp_error( $url ) ) {
			return null;
		}

		return $url;
	} finally {
		restore_current_blog();
	}
}
