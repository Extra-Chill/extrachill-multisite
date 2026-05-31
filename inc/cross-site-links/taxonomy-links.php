<?php
/**
 * Cross-Site Taxonomy Links
 *
 * Functions for linking taxonomy archives across sites in the multisite network.
 * Only returns links to sites where the term exists and has published content.
 * Main, Events, Shop, and Wire sites use REST APIs for accurate counts.
 * Artist site uses slug-based matching to artist_profile CPT.
 *
 * @package ExtraChillMultisite
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Object-cache group for cross-site term link results.
 *
 * Persistent (Redis) on this network, so entries survive across requests and
 * are shared by every consumer. Cache keys embed the current site key, so each
 * site in the network keys independently without needing a per-site group.
 */
const EXTRACHILL_CROSS_SITE_LINKS_CACHE_GROUP = 'extrachill_cross_site_links';

/**
 * Get cross-site links for a taxonomy term where content exists.
 *
 * Object-cache wrapper around extrachill_get_cross_site_term_links_uncached().
 *
 * The uncached computation loops every site mapped to the taxonomy and fires an
 * in-process REST dispatch (or switch_to_blog + WP_Query) per site — up to
 * ~5-6 cross-site dispatches for the `artist` taxonomy. This is a shared hot
 * path: it runs on archive pages (renderers.php) and, since extrachill-blog#7,
 * on single posts via the network bridge. The single-term path of every
 * per-site REST endpoint (events/wire/community/blog/shop taxonomy-counts) is
 * UNCACHED at the endpoint level — only their bulk paths use transients — so
 * without this layer each call pays the full cross-site cost every time.
 *
 * Results are cached in the persistent object cache keyed by
 * taxonomy + term_id + current_site_key (the output is site-relative because
 * the current site is skipped). TTL is filterable; default 1 hour. Invalidated
 * on save/delete of the relevant CPTs across the network (see
 * extrachill_cross_site_links_flush_cache_group()).
 *
 * @param WP_Term|int $term     Term object or term ID.
 * @param string      $taxonomy Taxonomy slug.
 * @return array Array of link data (see _uncached() for shape).
 */
function extrachill_get_cross_site_term_links( $term, $taxonomy ) {
	if ( is_int( $term ) ) {
		$term = get_term( $term, $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return array();
	}

	$current_site_key = extrachill_get_current_site_key();
	$cache_key        = 'links_' . $taxonomy . '_' . (int) $term->term_id . '_' . (string) $current_site_key;

	$cached = wp_cache_get( $cache_key, EXTRACHILL_CROSS_SITE_LINKS_CACHE_GROUP );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : array();
	}

	$links = extrachill_get_cross_site_term_links_uncached( $term, $taxonomy );

	/**
	 * Filters the TTL for cached cross-site term links.
	 *
	 * Keep at or above the consumer-side cache TTLs (e.g. the network bridge's
	 * per-post transient in extrachill-blog) so the inner layer doesn't expire
	 * faster than the outer one.
	 *
	 * @since 1.14.0
	 *
	 * @param int    $ttl      Cache lifetime in seconds. Default 1 hour.
	 * @param string $taxonomy Taxonomy slug.
	 * @param int    $term_id  Term ID.
	 */
	$ttl = (int) apply_filters( 'extrachill_cross_site_links_cache_ttl', HOUR_IN_SECONDS, $taxonomy, (int) $term->term_id );

	wp_cache_set( $cache_key, $links, EXTRACHILL_CROSS_SITE_LINKS_CACHE_GROUP, $ttl );

	return $links;
}

/**
 * Compute cross-site links for a taxonomy term where content exists.
 *
 * Checks each mapped site for the term and returns links only where
 * the term exists with at least one published post. Events and Shop
 * sites use REST APIs for accurate counts.
 *
 * Uncached — call extrachill_get_cross_site_term_links() for the cached path.
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
function extrachill_get_cross_site_term_links_uncached( $term, $taxonomy ) {
	if ( is_int( $term ) ) {
		$term = get_term( $term, $taxonomy );
	}

	if ( ! $term || is_wp_error( $term ) ) {
		return array();
	}

	$taxonomy_site_map = extrachill_get_taxonomy_site_map();
	if ( ! isset( $taxonomy_site_map[ $taxonomy ] ) ) {
		return array();
	}

	$target_sites        = $taxonomy_site_map[ $taxonomy ];
	$current_site_key    = extrachill_get_current_site_key();
	$content_type_labels = extrachill_get_site_content_type_labels();
	$main_blog_id        = ec_get_blog_id( 'main' );
	$events_blog_id      = ec_get_blog_id( 'events' );
	$shop_blog_id        = ec_get_blog_id( 'shop' );
	$wire_blog_id        = ec_get_blog_id( 'wire' );
	$artist_blog_id      = ec_get_blog_id( 'artist' );
	$community_blog_id   = ec_get_blog_id( 'community' );
	$links               = array();

	foreach ( $target_sites as $site_key ) {
		// Skip current site.
		if ( $site_key === $current_site_key ) {
			continue;
		}

		$blog_id = ec_get_blog_id( $site_key );
		if ( ! $blog_id ) {
			continue;
		}

		// Use REST APIs for consistent cross-site data access.
		// Artist site uses slug-based profile matching (CPT, not taxonomy).
		if ( $blog_id === $artist_blog_id && 'artist' === $taxonomy ) {
			$artist_profile = extrachill_get_artist_profile_by_slug( $term->slug );
			$term_data      = $artist_profile ? array(
				'count' => 1,
				'url'   => $artist_profile['permalink'],
			) : null;
		} elseif ( $blog_id === $main_blog_id ) {
			$term_data = extrachill_get_blog_taxonomy_count_via_api( $term->slug, $taxonomy );
		} elseif ( $blog_id === $events_blog_id ) {
			$term_data = extrachill_get_events_upcoming_count_via_api( $term->slug, $taxonomy );
		} elseif ( $blog_id === $shop_blog_id ) {
			$term_data = extrachill_get_shop_taxonomy_count_via_api( $term->slug, $taxonomy );
		} elseif ( $blog_id === $wire_blog_id ) {
			$term_data = extrachill_get_wire_taxonomy_count_via_api( $term->slug, $taxonomy );
		} elseif ( $blog_id === $community_blog_id ) {
			$term_data = extrachill_get_community_taxonomy_count_via_api( $term->slug, $taxonomy );
		} else {
			$term_data = extrachill_check_term_on_site( $term->slug, $taxonomy, $blog_id );
		}

		if ( ! $term_data || $term_data['count'] < 1 ) {
			continue;
		}

		// REST APIs return URL directly, otherwise build it
		if ( isset( $term_data['url'] ) ) {
			$url = $term_data['url'];
		} else {
			$url = extrachill_build_term_archive_url( $term->slug, $taxonomy, $blog_id );
		}

		if ( ! $url ) {
			continue;
		}

		$links[] = array(
			'blog_id'   => $blog_id,
			'site_key'  => $site_key,
			'url'       => $url,
			'label'     => isset( $content_type_labels[ $site_key ] ) ? $content_type_labels[ $site_key ] : ucfirst( $site_key ),
			'term_name' => $term->name,
			'count'     => $term_data['count'],
		);
	}

	return $links;
}

/**
 * Get upcoming event count via internal REST API
 *
 * Uses rest_do_request() for zero HTTP overhead internal call.
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array|null Array with 'count' and 'url', or null if not found.
 */
function extrachill_get_events_upcoming_count_via_api( $term_slug, $taxonomy ) {
	$request = new WP_REST_Request( 'GET', '/extrachill/v1/events/upcoming-counts' );
	$request->set_query_params(
		array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
		)
	);

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return null;
	}

	$data = $response->get_data();
	if ( empty( $data ) || ! isset( $data['count'] ) ) {
		return null;
	}

	return array(
		'term_id' => null,
		'count'   => (int) $data['count'],
		'url'     => isset( $data['url'] ) ? $data['url'] : null,
	);
}

/**
 * Get shop product count via internal REST API
 *
 * Uses rest_do_request() for zero HTTP overhead internal call.
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array|null Array with 'count' and 'url', or null if not found.
 */
function extrachill_get_shop_taxonomy_count_via_api( $term_slug, $taxonomy ) {
	$request = new WP_REST_Request( 'GET', '/extrachill/v1/shop/taxonomy-counts' );
	$request->set_query_params(
		array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
		)
	);

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return null;
	}

	$data = $response->get_data();
	if ( empty( $data ) || ! isset( $data['count'] ) ) {
		return null;
	}

	return array(
		'term_id' => null,
		'count'   => (int) $data['count'],
		'url'     => isset( $data['url'] ) ? $data['url'] : null,
	);
}

/**
 * Get wire post count via internal REST API
 *
 * Uses rest_do_request() for zero HTTP overhead internal call.
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array|null Array with 'count' and 'url', or null if not found.
 */
function extrachill_get_wire_taxonomy_count_via_api( $term_slug, $taxonomy ) {
	$request = new WP_REST_Request( 'GET', '/extrachill/v1/wire/taxonomy-counts' );
	$request->set_query_params(
		array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
		)
	);

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return null;
	}

	$data = $response->get_data();
	if ( empty( $data ) || ! isset( $data['count'] ) ) {
		return null;
	}

	return array(
		'term_id' => null,
		'count'   => (int) $data['count'],
		'url'     => isset( $data['url'] ) ? $data['url'] : null,
	);
}

/**
 * Get community forum data via internal REST API
 *
 * Uses rest_do_request() for zero HTTP overhead internal call.
 * Returns forum URL and topic count (forums are location hubs).
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array|null Array with 'count' and 'url', or null if not found.
 */
function extrachill_get_community_taxonomy_count_via_api( $term_slug, $taxonomy ) {
	$request = new WP_REST_Request( 'GET', '/extrachill/v1/community/taxonomy-counts' );
	$request->set_query_params(
		array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
		)
	);

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return null;
	}

	$data = $response->get_data();
	if ( empty( $data ) || ! isset( $data['count'] ) ) {
		return null;
	}

	return array(
		'term_id' => null,
		'count'   => (int) $data['count'],
		'url'     => isset( $data['url'] ) ? $data['url'] : null,
	);
}

/**
 * Get blog post count via internal REST API
 *
 * Uses rest_do_request() for zero HTTP overhead internal call.
 *
 * @param string $term_slug Term slug.
 * @param string $taxonomy  Taxonomy slug.
 * @return array|null Array with 'count' and 'url', or null if not found.
 */
function extrachill_get_blog_taxonomy_count_via_api( $term_slug, $taxonomy ) {
	$request = new WP_REST_Request( 'GET', '/extrachill/v1/blog/taxonomy-counts' );
	$request->set_query_params(
		array(
			'taxonomy' => $taxonomy,
			'slug'     => $term_slug,
		)
	);

	$response = rest_do_request( $request );

	if ( $response->is_error() ) {
		return null;
	}

	$data = $response->get_data();
	if ( empty( $data ) || ! isset( $data['count'] ) ) {
		return null;
	}

	return array(
		'term_id' => null,
		'count'   => (int) $data['count'],
		'url'     => isset( $data['url'] ) ? $data['url'] : null,
	);
}

/**
 * Check if term exists on target site with published posts
 *
 * @param string $term_slug Term slug to check.
 * @param string $taxonomy  Taxonomy slug.
 * @param int    $blog_id   Target blog ID.
 * @return array|null Array with 'term_id' and 'count', or null if not found.
 */
function extrachill_check_term_on_site( $term_slug, $taxonomy, $blog_id ) {
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
function extrachill_build_term_archive_url( $term_slug, $taxonomy, $blog_id ) {
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

/**
 * Flush the cross-site term links object-cache group.
 *
 * The cached result of extrachill_get_cross_site_term_links() answers "does
 * this term have published content on the other network sites?" — an answer
 * that changes whenever relevant content is published, unpublished, or deleted
 * on ANY site (a new event, a new wire story, a new blog post). Per-term
 * invalidation isn't practical because the cache is keyed by term across
 * sites, so a single content change can affect many cached entries.
 *
 * The pragmatic, correct strategy is a group-level flush on content change of
 * the relevant CPTs, backstopped by the short default TTL. The flush is cheap:
 * the persistent object cache (Redis drop-in) supports native group flushing
 * via wp_cache_flush_group(), so this clears only this group — not the whole
 * cache.
 *
 * @return void
 */
function extrachill_cross_site_links_flush_cache_group() {
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( EXTRACHILL_CROSS_SITE_LINKS_CACHE_GROUP );
	}
}

/**
 * Post types whose publish/unpublish/delete can change cross-site link answers.
 *
 * Spans every site mapped in extrachill_get_taxonomy_site_map(): blog posts
 * (main), events (events), festival wire (wire), products (shop), and artist
 * profiles (artist). Filterable so new content surfaces can opt in.
 *
 * @return string[] Post type slugs.
 */
function extrachill_cross_site_links_invalidating_post_types() {
	return apply_filters(
		'extrachill_cross_site_links_invalidating_post_types',
		array(
			'post',
			'data_machine_events',
			'festival_wire',
			'product',
			'artist_profile',
			'forum',
			'topic',
		)
	);
}

/**
 * Maybe flush the cross-site links cache when relevant content changes.
 *
 * Fires on save_post. Skips autosaves/revisions and post types that can't
 * affect cross-site link answers, so routine edits elsewhere don't thrash the
 * cache.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @return void
 */
function extrachill_cross_site_links_maybe_flush_on_save( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	if ( ! in_array( $post->post_type, extrachill_cross_site_links_invalidating_post_types(), true ) ) {
		return;
	}

	extrachill_cross_site_links_flush_cache_group();
}
add_action( 'save_post', 'extrachill_cross_site_links_maybe_flush_on_save', 10, 2 );

/**
 * Flush on hard delete of a relevant post.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object (WP 5.5+ passes this).
 * @return void
 */
function extrachill_cross_site_links_maybe_flush_on_delete( $post_id, $post = null ) {
	if ( $post instanceof WP_Post
		&& ! in_array( $post->post_type, extrachill_cross_site_links_invalidating_post_types(), true ) ) {
		return;
	}

	extrachill_cross_site_links_flush_cache_group();
}
add_action( 'deleted_post', 'extrachill_cross_site_links_maybe_flush_on_delete', 10, 2 );
