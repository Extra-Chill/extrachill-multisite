<?php
/**
 * Taxonomy Count Abilities
 *
 * Generic primitive for counting published posts per taxonomy term on any site.
 * Used by cross-site linking, homepage badges, and mobile app.
 *
 * This is a network-level concern: "how many published posts does term X have
 * on site Y?" The ability handles switch_to_blog internally when a site key
 * is provided, so callers don't need to manage blog context.
 *
 * @package ExtraChillMultisite\Abilities
 */

namespace ExtraChillMultisite\Abilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxonomyCountAbilities {

	private static bool $registered = false;

	public function __construct() {
		if ( ! self::$registered ) {
			$this->registerAbilities();
			self::$registered = true;
		}
	}

	private function registerAbilities(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'registerCategory' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register' ) );
	}

	public function registerCategory(): void {
		wp_register_ability_category(
			'extrachill-multisite',
			array(
				'label'       => __( 'Extra Chill Multisite', 'extrachill-multisite' ),
				'description' => __( 'Network-wide cross-site operations', 'extrachill-multisite' ),
			)
		);
	}

	public function register(): void {
		wp_register_ability(
			'extrachill/taxonomy-post-counts',
			array(
				'label'               => __( 'Taxonomy Post Counts', 'extrachill-multisite' ),
				'description'         => __( 'Count published posts per taxonomy term on a given site. Returns terms sorted by post count descending.', 'extrachill-multisite' ),
				'category'            => 'extrachill-multisite',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'taxonomy', 'site' ),
					'properties' => array(
						'taxonomy'  => array(
							'type'        => 'string',
							'description' => __( 'Taxonomy slug to count.', 'extrachill-multisite' ),
						),
						'site'      => array(
							'type'        => 'string',
							'description' => __( 'Site key (e.g. "wire", "main", "shop"). Uses ec_get_blog_id().', 'extrachill-multisite' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Post type to count. If omitted, uses all post types registered for the taxonomy.', 'extrachill-multisite' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'site'     => array( 'type' => 'string' ),
						'taxonomy' => array( 'type' => 'string' ),
						'terms'    => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'term_id' => array( 'type' => 'integer' ),
									'name'    => array( 'type' => 'string' ),
									'slug'    => array( 'type' => 'string' ),
									'count'   => array( 'type' => 'integer' ),
									'url'     => array( 'type' => 'string' ),
								),
							),
						),
						'total'    => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => array( $this, 'executeGetTaxonomyPostCounts' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
	}

	/**
	 * Execute taxonomy-post-counts ability.
	 *
	 * Switches to the target site, runs a single SQL query counting published
	 * posts per term, and returns structured results.
	 *
	 * @param array $input Input parameters.
	 * @return array Term counts sorted by post count descending.
	 */
	public function executeGetTaxonomyPostCounts( array $input ): array {
		$taxonomy  = $input['taxonomy'];
		$site_key  = $input['site'];
		$post_type = $input['post_type'] ?? null;

		$blog_id = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( $site_key ) : null;
		if ( ! $blog_id ) {
			return array(
				'success'  => false,
				'site'     => $site_key,
				'taxonomy' => $taxonomy,
				'terms'    => array(),
				'total'    => 0,
			);
		}

		switch_to_blog( $blog_id );
		try {
			return $this->computeCounts( $taxonomy, $site_key, $post_type );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Compute post counts per term using a single SQL query.
	 *
	 * Must be called in the correct blog context (after switch_to_blog).
	 *
	 * @param string      $taxonomy  Taxonomy slug.
	 * @param string      $site_key  Site key for the response.
	 * @param string|null $post_type Optional explicit post type.
	 * @return array Structured result.
	 */
	private function computeCounts( string $taxonomy, string $site_key, ?string $post_type ): array {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array(
				'success'  => false,
				'site'     => $site_key,
				'taxonomy' => $taxonomy,
				'terms'    => array(),
				'total'    => 0,
			);
		}

		// Resolve post types.
		if ( $post_type ) {
			$post_types = array( $post_type );
		} else {
			$tax_obj    = get_taxonomy( $taxonomy );
			$post_types = $tax_obj->object_type;
		}

		global $wpdb;

		$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT p.ID) AS post_count
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				INNER JOIN {$wpdb->posts} p ON tr.object_id = p.ID
				WHERE tt.taxonomy = %s
				AND p.post_type IN ({$type_placeholders})
				AND p.post_status = 'publish'
				GROUP BY t.term_id
				ORDER BY post_count DESC",
				array_merge( array( $taxonomy ), $post_types )
			)
		);

		if ( empty( $rows ) ) {
			return array(
				'success'  => true,
				'site'     => $site_key,
				'taxonomy' => $taxonomy,
				'terms'    => array(),
				'total'    => 0,
			);
		}

		$terms = array();
		foreach ( $rows as $row ) {
			if ( (int) $row->post_count < 1 ) {
				continue;
			}

			$url = get_term_link( (int) $row->term_id, $taxonomy );
			if ( is_wp_error( $url ) ) {
				continue;
			}

			$terms[] = array(
				'term_id' => (int) $row->term_id,
				'name'    => $row->name,
				'slug'    => $row->slug,
				'count'   => (int) $row->post_count,
				'url'     => $url,
			);
		}

		return array(
			'success'  => true,
			'site'     => $site_key,
			'taxonomy' => $taxonomy,
			'terms'    => $terms,
			'total'    => count( $terms ),
		);
	}
}
