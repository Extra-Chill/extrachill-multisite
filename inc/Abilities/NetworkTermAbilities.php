<?php
/**
 * Abilities for approved network term search, projection, and classification.
 *
 * @package ExtraChillNetwork\Abilities
 */

namespace ExtraChillNetwork\Abilities;

defined( 'ABSPATH' ) || exit;

/** Register network-owned term abilities. */
class NetworkTermAbilities {
	/** Hook ability registration. */
	public function __construct() {
		add_action( 'wp_abilities_api_init', array( $this, 'register' ) );
	}

	/** Register search, projection, and classification contracts. */
	public function register(): void {
		wp_register_ability(
			'extrachill/search-network-terms',
			array(
				'label'               => __( 'Search Approved Network Terms', 'extrachill-network' ),
				'description'         => __( 'Search trusted network taxonomy identities without creating local terms.', 'extrachill-network' ),
				'category'            => 'extrachill-network',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'site', 'post_type', 'taxonomy', 'query' ),
					'properties' => array(
						'site'      => array( 'type' => 'string' ),
						'post_type' => array( 'type' => 'string' ),
						'taxonomy'  => array( 'type' => 'string' ),
						'query'     => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'limit'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 50,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site'      => array( 'type' => 'string' ),
						'post_type' => array( 'type' => 'string' ),
						'taxonomy'  => array( 'type' => 'string' ),
						'terms'     => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( $this, 'search' ),
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

		wp_register_ability(
			'extrachill/project-network-term',
			array(
				'label'               => __( 'Project Approved Network Term', 'extrachill-network' ),
				'description'         => __( 'Resolve or create a local term projection from one approved taxonomy and slug identity.', 'extrachill-network' ),
				'category'            => 'extrachill-network',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'site', 'post_id', 'taxonomy', 'slug' ),
					'properties' => array(
						'site'     => array( 'type' => 'string' ),
						'post_id'  => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'taxonomy' => array( 'type' => 'string' ),
						'slug'     => array( 'type' => 'string' ),
						'dry_run'  => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'taxonomy' => array( 'type' => 'string' ),
						'slug'     => array( 'type' => 'string' ),
						'term_id'  => array( 'type' => 'integer' ),
						'created'  => array( 'type' => 'boolean' ),
						'dry_run'  => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => array( $this, 'project' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'show_in_rest' => true,
					'annotations'  => array( 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'extrachill/classify-post-terms',
			array(
				'label'               => __( 'Classify Post Network Terms', 'extrachill-network' ),
				'description'         => __( 'Schedule classification or an explicit rerun for one eligible network post.', 'extrachill-network' ),
				'category'            => 'extrachill-network',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'site', 'post_id' ),
					'properties' => array(
						'site'       => array( 'type' => 'string' ),
						'post_id'    => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'taxonomies' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'uniqueItems' => true,
						),
						'force'      => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'dry_run'    => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'scheduled'   => array( 'type' => 'boolean' ),
						'job_id'      => array( 'type' => 'integer' ),
						'fingerprint' => array( 'type' => 'string' ),
						'taxonomies'  => array( 'type' => 'array' ),
						'reason'      => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( $this, 'classify' ),
				'permission_callback' => '__return_true',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Search approved identities for an eligible target.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function search( array $input ) {
		$site_key  = sanitize_key( $input['site'] );
		$post_type = sanitize_key( $input['post_type'] );
		$taxonomy  = sanitize_key( $input['taxonomy'] );
		$blog_id   = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( $site_key ) : 0;
		if ( ! $blog_id ) {
			return new \WP_Error( 'unknown_site', __( 'Unknown target site.', 'extrachill-network' ) );
		}

		switch_to_blog( $blog_id );
		try {
			if ( ! in_array( $taxonomy, \extrachill_network_get_eligible_term_taxonomies( $site_key, $post_type, array( $taxonomy ) ), true ) ) {
				return new \WP_Error( 'unsupported_term_search', __( 'That taxonomy is not eligible for the target.', 'extrachill-network' ) );
			}
			$terms = \extrachill_network_search_terms( $taxonomy, $input['query'], $input['limit'] ?? 20 );
			return is_wp_error( $terms ) ? $terms : array(
				'site'      => $site_key,
				'post_type' => $post_type,
				'taxonomy'  => $taxonomy,
				'terms'     => $terms,
			);
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Materialize one approved local projection.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function project( array $input ) {
		$site_key = sanitize_key( $input['site'] );
		$blog_id  = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( $site_key ) : 0;
		if ( ! $blog_id ) {
			return new \WP_Error( 'unknown_site', __( 'Unknown target site.', 'extrachill-network' ) );
		}
		switch_to_blog( $blog_id );
		try {
			$post = get_post( absint( $input['post_id'] ) );
			if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				return new \WP_Error( 'term_projection_forbidden', __( 'You cannot edit that post.', 'extrachill-network' ), array( 'status' => 403 ) );
			}
			$taxonomy = sanitize_key( $input['taxonomy'] );
			$tax_obj  = get_taxonomy( $taxonomy );
			if ( ! $tax_obj || ! current_user_can( $tax_obj->cap->assign_terms ) ) {
				return new \WP_Error( 'term_projection_forbidden', __( 'You cannot assign terms from that taxonomy.', 'extrachill-network' ), array( 'status' => 403 ) );
			}
			return \extrachill_network_project_term( $site_key, $post->post_type, $taxonomy, $input['slug'], ! empty( $input['dry_run'] ) );
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Schedule one manual classification or rerun.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public function classify( array $input ) {
		$site_key = sanitize_key( $input['site'] );
		$blog_id  = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( $site_key ) : 0;
		if ( ! $blog_id ) {
			return new \WP_Error( 'unknown_site', __( 'Unknown target site.', 'extrachill-network' ) );
		}
		switch_to_blog( $blog_id );
		try {
			$post = get_post( absint( $input['post_id'] ) );
			if ( ! $post instanceof \WP_Post || ! current_user_can( 'edit_post', $post->ID ) ) {
				return new \WP_Error( 'classification_forbidden', __( 'You cannot classify that post.', 'extrachill-network' ), array( 'status' => 403 ) );
			}
		} finally {
			restore_current_blog();
		}
		return \extrachill_network_schedule_term_classification( $input );
	}
}
