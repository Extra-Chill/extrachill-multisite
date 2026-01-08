<?php
/**
 * Canonical Authority Resolution
 *
 * Centralized system for determining canonical URLs for taxonomy archives
 * across the Extra Chill multisite network. When a taxonomy archive exists
 * on multiple sites, this determines which site is the authoritative source.
 *
 * @package ExtraChillMultisite
 * @since 1.4.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get canonical authority configuration for taxonomies
 *
 * Defines which site is canonical for each shared taxonomy.
 * - Single string: That site is always canonical
 * - Array: Priority cascade, first site with content wins
 *
 * Conditions:
 * - 'profile_with_image': Artist profile must exist with profile image
 * - 'has_posts': Site must have at least 1 published post for the term
 * - null: Just check if term exists on the canonical site
 *
 * @return array Taxonomy slug => canonical configuration
 */
function ec_get_taxonomy_canonical_config() {
	return apply_filters(
		'ec_taxonomy_canonical_config',
		array(
			'artist'   => array(
				'canonical' => 'artist',
				'condition' => 'profile_with_image',
			),
			'venue'    => array(
				'canonical' => 'events',
				'condition' => null,
			),
			'location' => array(
				'canonical' => 'events',
				'condition' => null,
			),
			'festival' => array(
				'canonical' => array( 'main', 'wire', 'events' ),
				'condition' => 'has_posts',
			),
		)
	);
}

/**
 * Get canonical authority URL for a taxonomy term
 *
 * Resolves the canonical URL for a taxonomy archive. Returns null if:
 * - Current site IS the canonical authority
 * - No canonical authority is configured for this taxonomy
 * - The canonical site doesn't have the term/content
 *
 * @param WP_Term|int $term     Term object or term ID.
 * @param string      $taxonomy Taxonomy slug.
 * @return string|null Canonical URL or null if self-canonical.
 */
function ec_get_canonical_authority_url( $term, $taxonomy ) {
	if ( is_int( $term ) ) {
		$term = get_term( $term, $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return null;
	}

	$config = ec_get_taxonomy_canonical_config();
	if ( ! isset( $config[ $taxonomy ] ) ) {
		return null;
	}

	$taxonomy_config  = $config[ $taxonomy ];
	$canonical        = $taxonomy_config['canonical'];
	$condition        = $taxonomy_config['condition'] ?? null;
	$current_site_key = ec_get_current_site_key();

	// Handle priority cascade (array of sites).
	if ( is_array( $canonical ) ) {
		return ec_resolve_cascade_canonical( $term->slug, $taxonomy, $canonical, $condition, $current_site_key );
	}

	// Handle single canonical site.
	// If we're already on the canonical site, return null (self-canonical).
	if ( $current_site_key === $canonical ) {
		return null;
	}

	// Artist taxonomy has special handling (profile CPT, not taxonomy term).
	if ( 'artist' === $taxonomy && 'artist' === $canonical ) {
		return ec_resolve_artist_canonical( $term->slug );
	}

	// Standard taxonomy term canonical.
	return ec_resolve_term_canonical( $term->slug, $taxonomy, $canonical );
}

/**
 * Resolve canonical URL for artist taxonomy
 *
 * Artist is special because canonical points to artist_profile CPT,
 * not a taxonomy archive. Also requires profile image to exist.
 *
 * @param string $slug Artist slug.
 * @return string|null Canonical URL or null if profile doesn't qualify.
 */
function ec_resolve_artist_canonical( $slug ) {
	if ( ! ec_artist_profile_has_image( $slug ) ) {
		return null;
	}

	$profile = ec_get_artist_profile_by_slug( $slug );
	if ( ! $profile ) {
		return null;
	}

	return $profile['permalink'];
}

/**
 * Check if artist profile exists and has a profile image
 *
 * @param string $slug Artist slug.
 * @return bool True if profile exists with image.
 */
function ec_artist_profile_has_image( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( empty( $slug ) ) {
		return false;
	}

	$artist_blog_id = ec_get_blog_id( 'artist' );
	if ( ! $artist_blog_id ) {
		return false;
	}

	switch_to_blog( $artist_blog_id );
	try {
		$posts = get_posts(
			array(
				'post_type'      => 'artist_profile',
				'post_status'    => 'publish',
				'name'           => $slug,
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		if ( empty( $posts ) ) {
			return false;
		}

		$artist_id = (int) $posts[0];
		$thumbnail_id = get_post_thumbnail_id( $artist_id );

		return ! empty( $thumbnail_id );
	} finally {
		restore_current_blog();
	}
}

/**
 * Resolve canonical URL for standard taxonomy term
 *
 * Used for venue, location - single canonical site.
 *
 * @param string $slug     Term slug.
 * @param string $taxonomy Taxonomy slug.
 * @param string $site_key Canonical site key.
 * @return string|null Canonical URL or null if term doesn't exist.
 */
function ec_resolve_term_canonical( $slug, $taxonomy, $site_key ) {
	$blog_id = ec_get_blog_id( $site_key );
	if ( ! $blog_id ) {
		return null;
	}

	switch_to_blog( $blog_id );
	try {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return null;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );
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

/**
 * Resolve canonical URL using priority cascade
 *
 * Used for festival taxonomy - checks sites in order, first with content wins.
 *
 * @param string   $slug             Term slug.
 * @param string   $taxonomy         Taxonomy slug.
 * @param array    $site_keys        Priority-ordered array of site keys.
 * @param string   $condition        Condition type ('has_posts' or null).
 * @param string   $current_site_key Current site's key.
 * @return string|null Canonical URL or null if no site qualifies or current site is canonical.
 */
function ec_resolve_cascade_canonical( $slug, $taxonomy, $site_keys, $condition, $current_site_key ) {
	foreach ( $site_keys as $site_key ) {
		$blog_id = ec_get_blog_id( $site_key );
		if ( ! $blog_id ) {
			continue;
		}

		$has_content = ec_site_has_taxonomy_content( $slug, $taxonomy, $blog_id, $condition );

		if ( $has_content ) {
			// This site is the canonical authority.
			// If it's the current site, return null (self-canonical).
			if ( $site_key === $current_site_key ) {
				return null;
			}

			// Build and return the canonical URL.
			return ec_resolve_term_canonical( $slug, $taxonomy, $site_key );
		}
	}

	// No site in cascade has content, remain self-canonical.
	return null;
}

/**
 * Check if site has content for a taxonomy term
 *
 * @param string      $slug      Term slug.
 * @param string      $taxonomy  Taxonomy slug.
 * @param int         $blog_id   Blog ID to check.
 * @param string|null $condition Condition type.
 * @return bool True if site has qualifying content.
 */
function ec_site_has_taxonomy_content( $slug, $taxonomy, $blog_id, $condition ) {
	switch_to_blog( $blog_id );
	try {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return false;
		}

		$term = get_term_by( 'slug', $slug, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return false;
		}

		// If no condition, just check term exists.
		if ( null === $condition ) {
			return true;
		}

		// Check for published posts.
		if ( 'has_posts' === $condition ) {
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
					'no_found_rows'  => true,
				)
			);

			return $query->post_count > 0;
		}

		return false;
	} finally {
		restore_current_blog();
	}
}
