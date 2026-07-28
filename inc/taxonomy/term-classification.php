<?php
/**
 * Data Machine registration and post lifecycle scheduling for term classification.
 *
 * @package ExtraChillNetwork
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Data Machine task type. */
const EXTRACHILL_NETWORK_TERM_CLASSIFICATION_TASK = 'extrachill_network_classify_post_terms';

add_filter(
	'datamachine_tasks',
	static function ( array $tasks ): array {
		if ( ! class_exists( '\DataMachine\Engine\AI\System\Tasks\SystemTask' ) ) {
			return $tasks;
		}
		require_once __DIR__ . '/term-classification-task.php';
		$tasks[ EXTRACHILL_NETWORK_TERM_CLASSIFICATION_TASK ] = \ExtraChillNetwork\Taxonomy\TermClassificationTask::class;
		return $tasks;
	}
);

/**
 * Schedule one bounded classification using Data Machine idempotency.
 *
 * @param array $args {site, post_id, taxonomies?, force?, dry_run?}.
 * @return array<string,mixed>|WP_Error
 */
function extrachill_network_schedule_term_classification( $args ) {
	if ( ! class_exists( '\DataMachine\Engine\Tasks\TaskScheduler' ) || ! class_exists( '\DataMachine\Core\PluginSettings' ) ) {
		return new WP_Error( 'classification_runtime_unavailable', __( 'Data Machine task execution is unavailable.', 'extrachill-network' ) );
	}
	if ( ! \DataMachine\Core\PluginSettings::get( 'extrachill_network_term_classification_enabled', true ) ) {
		return new WP_Error( 'classification_task_disabled', __( 'Network term classification is disabled.', 'extrachill-network' ) );
	}

	$site_key = sanitize_key( $args['site'] ?? '' );
	$post_id  = absint( $args['post_id'] ?? 0 );
	$blog_id  = function_exists( 'ec_get_blog_id' ) ? ec_get_blog_id( $site_key ) : 0;
	if ( ! $blog_id || ! $post_id ) {
		return new WP_Error( 'invalid_classification_target', __( 'A valid site and post are required.', 'extrachill-network' ) );
	}

	switch_to_blog( $blog_id );
	try {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return new WP_Error( 'classification_post_not_found', __( 'The classification target was not found.', 'extrachill-network' ) );
		}
		$taxonomies = extrachill_network_get_eligible_term_taxonomies( $site_key, $post->post_type, $args['taxonomies'] ?? array() );
		if ( empty( $taxonomies ) ) {
			return new WP_Error( 'unsupported_classification_target', __( 'No approved taxonomy is registered for this post type.', 'extrachill-network' ) );
		}

		$fingerprint = extrachill_network_term_classification_fingerprint( $post );
		if ( empty( $args['force'] ) && extrachill_network_term_classification_is_current( $post_id, $taxonomies, $fingerprint ) ) {
			return array(
				'scheduled'   => false,
				'reason'      => 'identical_fingerprint',
				'fingerprint' => $fingerprint,
			);
		}

		$model = \DataMachine\Core\PluginSettings::resolveModelForAgentMode( 0, 'system' );
		if ( empty( $model['provider'] ) || empty( $model['model'] ) ) {
			return new WP_Error( 'classification_provider_unavailable', __( 'No system AI provider and model are configured.', 'extrachill-network' ) );
		}

		$params = array(
			'site'        => $site_key,
			'post_id'     => $post_id,
			'taxonomies'  => $taxonomies,
			'fingerprint' => $fingerprint,
			'force'       => ! empty( $args['force'] ),
			'dry_run'     => ! empty( $args['dry_run'] ),
		);
		$key    = sprintf( 'extrachill-network-terms:%s:%d:%s:%s', $site_key, $post_id, implode( ',', $taxonomies ), $fingerprint );
		if ( ! empty( $args['force'] ) ) {
			$key .= ':' . wp_generate_uuid4();
		}

		$job_id = \DataMachine\Engine\Tasks\TaskScheduler::schedule(
			EXTRACHILL_NETWORK_TERM_CLASSIFICATION_TASK,
			$params,
			array( 'origin' => 'extrachill-network' ),
			0,
			$key
		);
		if ( ! $job_id ) {
			$error = \DataMachine\Engine\Tasks\TaskScheduler::getLastScheduleError();
			return new WP_Error( 'classification_schedule_failed', $error['message'] ?? __( 'The classification job could not be scheduled.', 'extrachill-network' ) );
		}

		return array(
			'scheduled'   => true,
			'job_id'      => (int) $job_id,
			'fingerprint' => $fingerprint,
			'taxonomies'  => $taxonomies,
		);
	} finally {
		restore_current_blog();
	}
}

/**
 * Schedule configured pending, direct-publish, and meaningful publish updates.
 *
 * @param string  $new_status New status.
 * @param string  $old_status Previous status.
 * @param WP_Post $post       Post object.
 * @return void
 */
function extrachill_network_maybe_schedule_term_classification( $new_status, $old_status, $post ) {
	if ( ! $post instanceof WP_Post || ! empty( $GLOBALS['extrachill_network_term_classifier_writing'] ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) || in_array( $new_status, array( 'auto-draft', 'draft', 'trash', 'inherit' ), true ) ) {
		return;
	}

	$is_pending_transition = 'pending' === $new_status && 'pending' !== $old_status;
	$is_publish_transition = 'publish' === $new_status;
	if ( ! $is_pending_transition && ! $is_publish_transition ) {
		return;
	}

	$site_key = function_exists( 'extrachill_get_current_site_key' ) ? extrachill_get_current_site_key() : null;
	if ( ! $site_key ) {
		return;
	}
	$taxonomies = extrachill_network_get_eligible_term_taxonomies( $site_key, $post->post_type );
	if ( empty( $taxonomies ) ) {
		return;
	}

	$content_text = trim( wp_strip_all_tags( (string) $post->post_title . ' ' . strip_shortcodes( (string) $post->post_content ) ) );
	if ( mb_strlen( $content_text ) < (int) apply_filters( 'extrachill_network_term_classification_min_length', 40, $post ) ) {
		return;
	}

	extrachill_network_schedule_term_classification(
		array(
			'site'       => $site_key,
			'post_id'    => $post->ID,
			'taxonomies' => $taxonomies,
		)
	);
}
add_action( 'transition_post_status', 'extrachill_network_maybe_schedule_term_classification', 30, 3 );
