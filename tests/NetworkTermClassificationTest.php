<?php
/** Standalone multisite contract tests for approved network term classification. */

declare( strict_types=1 );

namespace DataMachine\Core {
	class PluginSettings {
		public static bool $enabled = true;
		public static function get( string $key, $default = null ) { return self::$enabled; }
		public static function resolveModelForAgentMode(): array { return array( 'provider' => 'test', 'model' => 'test' ); }
	}
}

namespace DataMachine\Engine\Tasks {
	class TaskScheduler {
		public static array $jobs = array();
		public static array $keys = array();
		public static function schedule( string $type, array $params, array $context = array(), int $parent = 0, string $key = '' ) {
			if ( isset( self::$keys[ $key ] ) ) {
				return self::$keys[ $key ];
			}
			$id                 = count( self::$jobs ) + 1;
			self::$keys[ $key ] = $id;
			self::$jobs[ $id ]  = compact( 'type', 'params', 'context', 'parent', 'key' );
			return $id;
		}
		public static function getLastScheduleError(): ?array { return null; }
	}
}

namespace {
	// phpcs:disable -- WordPress standalone mocks intentionally share one file.
	define( 'ABSPATH', __DIR__ . '/' );

	class WP_Error {
		public function __construct( public string $code = '', public string $message = '', public $data = null ) {}
		public function get_error_message(): string { return $this->message; }
		public function get_error_data( string $code = '' ) { return is_array( $this->data ) ? ( $this->data[ $code ] ?? $this->data ) : $this->data; }
	}

	class WP_Post {
		public function __construct(
			public int $ID,
			public string $post_type,
			public string $post_status,
			public string $post_title,
			public string $post_content
		) {}
	}

	class WP_Term {
		public function __construct(
			public int $term_id,
			public string $slug,
			public string $name,
			public string $description = '',
			public int $parent = 0
		) {}
	}

	$GLOBALS['ntc_blog_id']       = 2;
	$GLOBALS['ntc_blog_stack']    = array();
	$GLOBALS['ntc_terms']         = array();
	$GLOBALS['ntc_posts']         = array();
	$GLOBALS['ntc_meta']          = array();
	$GLOBALS['ntc_relationships'] = array();
	$GLOBALS['ntc_post_types']    = array( 'topic', 'post', 'festival_wire' );
	$GLOBALS['ntc_taxonomies']    = array(
		'topic'         => array( 'artist', 'festival', 'location' ),
		'post'          => array( 'artist', 'festival', 'location' ),
		'festival_wire' => array( 'festival', 'location' ),
	);
	$GLOBALS['ntc_autosaves']     = array();
	$GLOBALS['ntc_revisions']     = array();
	$GLOBALS['ntc_filters']       = array();
	$GLOBALS['ntc_actions']       = array();

	function __( string $text ): string { return $text; }
	function absint( $value ): int { return abs( (int) $value ); }
	function sanitize_key( $value ): string { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) ); }
	function sanitize_title( $value ): string { return strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '-', (string) $value ), '-' ) ); }
	function sanitize_text_field( $value ): string { return trim( strip_tags( (string) $value ) ); }
	function wp_strip_all_tags( $value ): string { return strip_tags( (string) $value ); }
	function strip_shortcodes( $value ): string { return (string) $value; }
	function wp_json_encode( $value ): string { return (string) json_encode( $value ); }
	function wp_generate_uuid4(): string { return 'force-' . ( count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ) + 1 ); }
	function current_time(): string { return '2026-07-28 12:00:00'; }
	function is_wp_error( $value ): bool { return $value instanceof WP_Error; }
	function get_current_blog_id(): int { return $GLOBALS['ntc_blog_id']; }
	function switch_to_blog( $blog_id ): bool {
		$GLOBALS['ntc_blog_stack'][] = $GLOBALS['ntc_blog_id'];
		$GLOBALS['ntc_blog_id']      = (int) $blog_id;
		return true;
	}
	function restore_current_blog(): bool {
		$GLOBALS['ntc_blog_id'] = (int) array_pop( $GLOBALS['ntc_blog_stack'] );
		return true;
	}
	function ec_get_blog_id( string $key ): int { return array( 'main' => 1, 'community' => 2, 'events' => 7, 'wire' => 11 )[ $key ] ?? 0; }
	function extrachill_get_current_site_key(): ?string { return array( 1 => 'main', 2 => 'community', 7 => 'events', 11 => 'wire' )[ get_current_blog_id() ] ?? null; }
	function taxonomy_exists( string $taxonomy ): bool { return in_array( $taxonomy, array( 'artist', 'festival', 'location' ), true ); }
	function post_type_exists( string $post_type ): bool { return in_array( $post_type, $GLOBALS['ntc_post_types'], true ); }
	function is_object_in_taxonomy( string $post_type, string $taxonomy ): bool { return in_array( $taxonomy, $GLOBALS['ntc_taxonomies'][ $post_type ] ?? array(), true ); }
	function apply_filters( string $hook, $value, ...$args ) {
		foreach ( $GLOBALS['ntc_filters'][ $hook ] ?? array() as $callback ) {
			$value = $callback( $value, ...$args );
		}
		return $value;
	}
	function add_filter( string $hook, $callback ): void { $GLOBALS['ntc_filters'][ $hook ][] = $callback; }
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): void { $GLOBALS['ntc_actions'][ $hook ][] = $callback; }
	function wp_is_post_autosave( int $post_id ) { return in_array( $post_id, $GLOBALS['ntc_autosaves'], true ) ? $post_id : false; }
	function wp_is_post_revision( int $post_id ) { return in_array( $post_id, $GLOBALS['ntc_revisions'], true ) ? $post_id : false; }
	function get_post( int $post_id ) { return $GLOBALS['ntc_posts'][ get_current_blog_id() ][ $post_id ] ?? null; }
	function get_post_meta( int $post_id, string $key, bool $single = false ) { return $GLOBALS['ntc_meta'][ get_current_blog_id() ][ $post_id ][ $key ] ?? ''; }
	function update_post_meta( int $post_id, string $key, $value ): bool { $GLOBALS['ntc_meta'][ get_current_blog_id() ][ $post_id ][ $key ] = $value; return true; }
	function delete_post_meta( int $post_id, string $key ): bool { unset( $GLOBALS['ntc_meta'][ get_current_blog_id() ][ $post_id ][ $key ] ); return true; }
	function get_term( int $term_id, string $taxonomy ) {
		foreach ( $GLOBALS['ntc_terms'][ get_current_blog_id() ][ $taxonomy ] ?? array() as $term ) {
			if ( $term->term_id === $term_id ) {
				return $term;
			}
		}
		return false;
	}
	function get_term_by( string $field, $value, string $taxonomy ) {
		foreach ( $GLOBALS['ntc_terms'][ get_current_blog_id() ][ $taxonomy ] ?? array() as $term ) {
			if ( ( 'slug' === $field && $term->slug === $value ) || ( 'id' === $field && $term->term_id === (int) $value ) ) {
				return $term;
			}
		}
		return false;
	}
	function get_terms( array $args ) {
		$terms = array_values( $GLOBALS['ntc_terms'][ get_current_blog_id() ][ $args['taxonomy'] ] ?? array() );
		if ( ! empty( $args['search'] ) ) {
			$query = strtolower( (string) $args['search'] );
			$terms = array_values( array_filter( $terms, static fn( WP_Term $term ): bool => str_contains( strtolower( $term->name ), $query ) || str_contains( $term->slug, sanitize_title( $query ) ) ) );
		}
		return array_slice( $terms, 0, (int) ( $args['number'] ?? count( $terms ) ) );
	}
	function wp_insert_term( string $name, string $taxonomy, array $args ) {
		$existing = get_term_by( 'slug', $args['slug'], $taxonomy );
		if ( $existing ) {
			return new WP_Error( 'term_exists', 'Exists', array( 'term_exists' => $existing->term_id ) );
		}
		$all = $GLOBALS['ntc_terms'][ get_current_blog_id() ][ $taxonomy ] ?? array();
		$id  = 1000 + count( $all );
		$GLOBALS['ntc_terms'][ get_current_blog_id() ][ $taxonomy ][ $args['slug'] ] = new WP_Term( $id, $args['slug'], $name, $args['description'] ?? '', (int) ( $args['parent'] ?? 0 ) );
		return array( 'term_id' => $id );
	}
	function wp_get_object_terms( int $post_id, string $taxonomy ) {
		$terms = array();
		foreach ( $GLOBALS['ntc_relationships'][ get_current_blog_id() ][ $post_id ][ $taxonomy ] ?? array() as $term_id ) {
			$term = get_term( (int) $term_id, $taxonomy );
			if ( $term ) {
				$terms[] = $term;
			}
		}
		return $terms;
	}
	function wp_set_object_terms( int $post_id, array $term_ids, string $taxonomy ) {
		$GLOBALS['ntc_relationships'][ get_current_blog_id() ][ $post_id ][ $taxonomy ] = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
		return $term_ids;
	}

	require_once dirname( __DIR__ ) . '/inc/taxonomy/network-terms.php';
	require_once dirname( __DIR__ ) . '/inc/taxonomy/term-classification.php';

	function ntc_assert( bool $condition, string $message ): void {
		if ( ! $condition ) {
			throw new RuntimeException( $message );
		}
	}
	function ntc_assert_same( $expected, $actual, string $message ): void {
		if ( $expected !== $actual ) {
			throw new RuntimeException( $message . '\nExpected: ' . var_export( $expected, true ) . '\nActual: ' . var_export( $actual, true ) );
		}
	}
	function ntc_slugs( int $blog_id, int $post_id, string $taxonomy ): array {
		$previous = get_current_blog_id();
		switch_to_blog( $blog_id );
		$slugs = array_map( static fn( WP_Term $term ): string => $term->slug, wp_get_object_terms( $post_id, $taxonomy ) );
		restore_current_blog();
		ntc_assert_same( $previous, get_current_blog_id(), 'Term lookup restores blog context.' );
		sort( $slugs );
		return $slugs;
	}
	function ntc_reset_scheduler(): void {
		\DataMachine\Engine\Tasks\TaskScheduler::$jobs = array();
		\DataMachine\Engine\Tasks\TaskScheduler::$keys = array();
	}

	// Approved source policy excludes Events' broad scraper union.
	$policy = extrachill_network_get_term_policy();
	ntc_assert_same( array( 'main' ), $policy['sources']['artist'], 'Artist identity comes only from the curated Main taxonomy.' );
	ntc_assert( ! in_array( 'events', array_merge( ...array_values( $policy['sources'] ) ), true ), 'Events is not an approved source.' );

	$GLOBALS['ntc_terms'][1]['artist']['phish']       = new WP_Term( 10, 'phish', 'Phish', 'Jam band' );
	$GLOBALS['ntc_terms'][1]['festival']['bonnaroo']  = new WP_Term( 11, 'bonnaroo', 'Bonnaroo' );
	$GLOBALS['ntc_terms'][1]['location']['charleston'] = new WP_Term( 12, 'charleston', 'Charleston' );
	$GLOBALS['ntc_terms'][11]['festival']['bonnaroo'] = new WP_Term( 210, 'bonnaroo', 'Bonnaroo', 'Editorial festival' );
	$GLOBALS['ntc_terms'][7]['artist']['noise-act']   = new WP_Term( 70001, 'noise-act', 'Noise Act' );
	$GLOBALS['ntc_terms'][2]['location']['charleston'] = new WP_Term( 201, 'charleston', 'Charleston' );
	$GLOBALS['ntc_terms'][2]['festival']['oldfest']   = new WP_Term( 202, 'oldfest', 'Oldfest' );
	$GLOBALS['ntc_posts'][2][50] = new WP_Post( 50, 'topic', 'publish', 'Phish at Bonnaroo', 'Charleston fans discuss the Phish set at Bonnaroo.' );
	$GLOBALS['ntc_relationships'][2][50] = array( 'location' => array( 201 ), 'festival' => array( 202 ), 'artist' => array() );
	$GLOBALS['ntc_meta'][2][50][EXTRACHILL_NETWORK_TERM_PROVENANCE_META] = array(
		'schema_version' => 1,
		'fingerprints'   => array( 'festival' => 'old' ),
		'terms'          => array( 'festival' => array( 'oldfest' ) ),
	);

	$search = extrachill_network_search_terms( 'artist', 'Noise', 20 );
	ntc_assert_same( array(), $search, 'Unapproved Events terms never appear in registry search.' );
	$search = extrachill_network_search_terms( 'festival', 'Bonnaroo', 20 );
	ntc_assert_same( 'wire', $search[0]['source'], 'Festival search honors trusted source priority and de-duplicates identity.' );

	$start_blog = get_current_blog_id();
	$projected  = extrachill_network_project_term( 'community', 'topic', 'artist', 'phish' );
	ntc_assert( ! is_wp_error( $projected ) && $projected['term_id'] !== 10, 'Projection creates a target-local term ID from taxonomy and slug.' );
	ntc_assert_same( $start_blog, get_current_blog_id(), 'Projection restores nested multisite context.' );
	$rejected = extrachill_network_project_term( 'community', 'topic', 'artist', 'noise-act' );
	ntc_assert( is_wp_error( $rejected ), 'Projection cannot mint an arbitrary or unapproved identity.' );

	$selector_calls = 0;
	$result = extrachill_network_classify_post_terms(
		array( 'site' => 'community', 'post_id' => 50 ),
		static function ( WP_Post $post, array $candidates ) use ( &$selector_calls ): array {
			++$selector_calls;
			return array(
				array( 'taxonomy' => 'artist', 'slug' => 'phish', 'confidence' => 0.99 ),
				array( 'taxonomy' => 'festival', 'slug' => 'bonnaroo', 'confidence' => 0.96 ),
				array( 'taxonomy' => 'location', 'slug' => 'charleston', 'confidence' => 0.50 ),
			);
		}
	);
	ntc_assert( ! is_wp_error( $result ), 'Eligible topic classification succeeds.' );
	ntc_assert_same( array( 'charleston' ), ntc_slugs( 2, 50, 'location' ), 'Existing human location survives low-confidence AI output.' );
	ntc_assert_same( array( 'bonnaroo' ), ntc_slugs( 2, 50, 'festival' ), 'Prior AI festival is replaced by a high-confidence approved identity.' );
	ntc_assert_same( array( 'phish' ), ntc_slugs( 2, 50, 'artist' ), 'Missing local artist projection is assigned by local ID.' );

	$idempotent = extrachill_network_classify_post_terms(
		array( 'site' => 'community', 'post_id' => 50 ),
		static function () use ( &$selector_calls ): array { ++$selector_calls; return array(); }
	);
	ntc_assert_same( 'identical_fingerprint', $idempotent['reason'], 'Successful identical fingerprints are no-ops.' );
	ntc_assert_same( 1, $selector_calls, 'Idempotent no-op does not invoke AI selection.' );

	$before_terms = $GLOBALS['ntc_relationships'][2][50];
	$before_meta  = $GLOBALS['ntc_meta'][2][50][EXTRACHILL_NETWORK_TERM_PROVENANCE_META];
	$dry_run = extrachill_network_classify_post_terms(
		array( 'site' => 'community', 'post_id' => 50, 'force' => true, 'dry_run' => true ),
		static fn(): array => array()
	);
	ntc_assert_same( true, $dry_run['dry_run'], 'Dry-run reports its execution mode.' );
	ntc_assert_same( $before_terms, $GLOBALS['ntc_relationships'][2][50], 'Dry-run does not mutate relationships.' );
	ntc_assert_same( $before_meta, $GLOBALS['ntc_meta'][2][50][EXTRACHILL_NETWORK_TERM_PROVENANCE_META], 'Dry-run does not persist provenance.' );

	$GLOBALS['ntc_posts'][2][50]->post_title   = 'General music discussion';
	$GLOBALS['ntc_posts'][2][50]->post_content = 'A sufficiently detailed conversation with no approved named entities in this particular post.';
	$no_match = extrachill_network_classify_post_terms(
		array( 'site' => 'community', 'post_id' => 50, 'force' => true ),
		static fn(): array => array()
	);
	ntc_assert_same( 0, $no_match['candidate_count'], 'No-match is a successful classification result.' );
	ntc_assert_same( array(), ntc_slugs( 2, 50, 'artist' ), 'No-match removes only prior AI-owned artist assignments.' );
	ntc_assert_same( array(), ntc_slugs( 2, 50, 'festival' ), 'No-match removes only prior AI-owned festival assignments.' );
	ntc_assert_same( array( 'charleston' ), ntc_slugs( 2, 50, 'location' ), 'No-match never removes human-owned terms.' );
	$current_fingerprint = extrachill_network_term_classification_fingerprint( $GLOBALS['ntc_posts'][2][50] );
	ntc_assert( extrachill_network_term_classification_is_current( 50, array( 'artist', 'festival', 'location' ), $current_fingerprint ), 'No-match stores successful per-taxonomy fingerprints.' );

	$effect = $result['effects'][0];
	$undo   = extrachill_network_restore_term_classification_effect( $effect );
	ntc_assert_same( 'reverted', $undo['status'], 'Recorded prior relationships are sufficient for undo.' );
	ntc_assert_same( array( 'oldfest' ), ntc_slugs( 2, 50, 'festival' ), 'Undo restores prior AI relationship state.' );
	ntc_assert_same( 2, get_current_blog_id(), 'Undo restores the caller blog context.' );

	// Runtime registration is checked after switching; policy alone is insufficient.
	$original_topic_taxonomies = $GLOBALS['ntc_taxonomies']['topic'];
	$GLOBALS['ntc_taxonomies']['topic'] = array();
	ntc_assert_same( array(), extrachill_network_get_eligible_term_taxonomies( 'community', 'topic' ), 'Missing runtime registrations fail closed after switch_to_blog.' );
	$GLOBALS['ntc_taxonomies']['topic'] = $original_topic_taxonomies;

	// Scheduling uses Data Machine's operation key for atomic duplicate suppression.
	$GLOBALS['ntc_posts'][2][50]->post_title   = 'Phish returns to Bonnaroo';
	$GLOBALS['ntc_posts'][2][50]->post_content = 'A meaningful published update about Phish playing Bonnaroo for Charleston fans.';
	delete_post_meta( 50, EXTRACHILL_NETWORK_TERM_PROVENANCE_META );
	ntc_reset_scheduler();
	$first  = extrachill_network_schedule_term_classification( array( 'site' => 'community', 'post_id' => 50 ) );
	$second = extrachill_network_schedule_term_classification( array( 'site' => 'community', 'post_id' => 50 ) );
	ntc_assert_same( $first['job_id'], $second['job_id'], 'Identical queued/processing jobs resolve through one operation key.' );
	ntc_assert_same( 1, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Duplicate scheduling does not enqueue duplicate work.' );

	// Lifecycle matrix: pending, direct publish, and changed publish schedule; exclusions do not.
	ntc_reset_scheduler();
	extrachill_network_maybe_schedule_term_classification( 'pending', 'draft', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 1, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Transition to pending schedules classification.' );
	ntc_reset_scheduler();
	extrachill_network_maybe_schedule_term_classification( 'publish', 'draft', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 1, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Direct publish schedules classification.' );
	ntc_reset_scheduler();
	extrachill_network_maybe_schedule_term_classification( 'publish', 'publish', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 1, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Meaningful published updates schedule classification.' );

	foreach ( array( array( 'draft', 'draft' ), array( 'trash', 'publish' ), array( 'auto-draft', 'draft' ) ) as $transition ) {
		ntc_reset_scheduler();
		extrachill_network_maybe_schedule_term_classification( $transition[0], $transition[1], $GLOBALS['ntc_posts'][2][50] );
		ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), "Excluded {$transition[1]}->{$transition[0]} transition does not schedule." );
	}

	ntc_reset_scheduler();
	$GLOBALS['ntc_autosaves'][] = 50;
	extrachill_network_maybe_schedule_term_classification( 'publish', 'publish', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Autosaves do not schedule.' );
	$GLOBALS['ntc_autosaves'] = array();
	$GLOBALS['ntc_revisions'][] = 50;
	extrachill_network_maybe_schedule_term_classification( 'publish', 'publish', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Revisions do not schedule.' );
	$GLOBALS['ntc_revisions'] = array();

	$GLOBALS['extrachill_network_term_classifier_writing'] = true;
	extrachill_network_maybe_schedule_term_classification( 'publish', 'publish', $GLOBALS['ntc_posts'][2][50] );
	ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Classifier-originated writes cannot loop.' );
	$GLOBALS['extrachill_network_term_classifier_writing'] = false;

	$unsupported = new WP_Post( 99, 'page', 'publish', 'Long enough unsupported title', 'Long enough unsupported content for policy exclusion.' );
	extrachill_network_maybe_schedule_term_classification( 'publish', 'draft', $unsupported );
	ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Unsupported post types do not schedule.' );
	$short = new WP_Post( 98, 'topic', 'publish', 'Tiny', 'Short' );
	extrachill_network_maybe_schedule_term_classification( 'publish', 'draft', $short );
	ntc_assert_same( 0, count( \DataMachine\Engine\Tasks\TaskScheduler::$jobs ), 'Insufficient content does not schedule.' );

	\DataMachine\Core\PluginSettings::$enabled = false;
	$disabled = extrachill_network_schedule_term_classification( array( 'site' => 'community', 'post_id' => 50 ) );
	ntc_assert( is_wp_error( $disabled ), 'Disabled task state prevents scheduling.' );
	\DataMachine\Core\PluginSettings::$enabled = true;

	ntc_assert_same( 2, get_current_blog_id(), 'All tested multisite operations restore the original context.' );
	fwrite( STDOUT, "Network term classification tests passed.\n" );
}
