<?php
/**
 * Extra Chill mail helpers.
 *
 * - `extrachill_mail_site_id()` / `ec_mail_site_id()` resolve the closest
 *   SMTP-configured site so outgoing mail does not silently fail from
 *   subsites that lack credentials.
 * - `ec_send_email()` / `ec_send_email_queued()` are thin one-line migration
 *   targets that delegate to the Data Machine `datamachine/send-email` and
 *   `datamachine/send-email-queued` abilities.
 *
 * EC-branded templates (`extrachill/branded`, `extrachill/minimal`) are
 * registered against the DM `datamachine_email_templates` filter — see
 * {@see extrachill_register_email_templates()} below.
 *
 * @package ExtraChillMultisite\Core\Mail
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the canonical list of SMTP-configured site IDs on the network.
 *
 * Easy WP SMTP is configured per-site, not network-wide. Only the sites in
 * this list have valid SMTP credentials; mail originating on any other
 * subsite must `switch_to_blog()` to one of these before sending or it will
 * silently fail.
 *
 * Filter `extrachill_smtp_configured_sites` to opt new sites in without a
 * code change. Default: main + community (confirmed configured in
 * production).
 *
 * @return int[] Sorted list of blog IDs that have Easy WP SMTP configured.
 */
function extrachill_smtp_configured_sites() {
	$defaults = array(
		(int) EC_BLOG_ID_MAIN,
		(int) EC_BLOG_ID_COMMUNITY,
	);

	$sites = apply_filters( 'extrachill_smtp_configured_sites', $defaults );

	// Defensive normalization — accept any iterable, coerce to ints, drop
	// zero/negative IDs, de-dupe.
	$out = array();
	foreach ( (array) $sites as $id ) {
		$id = (int) $id;
		if ( $id > 0 ) {
			$out[ $id ] = $id;
		}
	}

	return array_values( $out );
}

/**
 * Resolve the blog ID to use for outgoing mail in the current context.
 *
 * Resolution order:
 *   1. If the current site is SMTP-configured, return its ID (no switch
 *      needed — `wp_mail()` works on this site).
 *   2. Otherwise fall back to `ec_get_blog_id('main')`.
 *
 * Safe to call from any subsite context, including before `init`.
 *
 * @return int Blog ID of the SMTP-configured site to send mail through.
 */
function extrachill_mail_site_id() {
	$configured = extrachill_smtp_configured_sites();

	$current = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;

	if ( $current > 0 && in_array( $current, $configured, true ) ) {
		return $current;
	}

	$main = function_exists( 'ec_get_blog_id' ) ? (int) ec_get_blog_id( 'main' ) : (int) EC_BLOG_ID_MAIN;

	return $main > 0 ? $main : (int) EC_BLOG_ID_MAIN;
}

/**
 * Alias matching the shorter `ec_*` naming convention.
 *
 * @return int Blog ID of the SMTP-configured site to send mail through.
 */
function ec_mail_site_id() {
	return extrachill_mail_site_id();
}

/**
 * Send an EC-branded email via the `datamachine/send-email` ability.
 *
 * One-line migration target for plugins moving off raw `wp_mail()`. Wraps
 * the DM ability with sensible Extra Chill defaults:
 *   - `template`     => `extrachill/branded` (full link grid + footer)
 *   - `mail_site_id` => `extrachill_mail_site_id()` (auto-resolves SMTP site)
 *
 * Caller can override either default by passing them in `$args`.
 *
 * The underlying ability handles `switch_to_blog()` plumbing internally
 * when `mail_site_id` is provided — callers must NOT wrap this in their
 * own `switch_to_blog()`.
 *
 * @see datamachine/send-email
 *
 * @param array $args Arguments forwarded to the ability. Required keys
 *                    documented by the ability: `to`, `subject`. When using
 *                    a template, pass `context` (array) instead of `body`.
 * @return array Result array as returned by the ability:
 *               `[ 'success' => bool, 'message' => string, ... ]`.
 *               On bootstrap failure: `[ 'success' => false, 'error' => string ]`.
 */
function ec_send_email( array $args ) {
	$defaults = array(
		'template'     => 'extrachill/branded',
		'mail_site_id' => extrachill_mail_site_id(),
	);

	$args = wp_parse_args( $args, $defaults );

	if ( ! function_exists( 'wp_get_ability' ) ) {
		return array(
			'success' => false,
			'error'   => 'WordPress Abilities API not available — datamachine/send-email cannot be resolved.',
		);
	}

	$ability = wp_get_ability( 'datamachine/send-email' );
	if ( ! $ability ) {
		return array(
			'success' => false,
			'error'   => 'Ability datamachine/send-email is not registered. Is the Data Machine plugin active?',
		);
	}

	return $ability->execute( $args );
}

/**
 * Queue an EC-branded email via the `datamachine/send-email-queued` ability.
 *
 * Identical shape to {@see ec_send_email()} but routes through the
 * Action Scheduler-backed queued variant for non-blocking sends.
 * Supports an optional `send_at` (ISO8601 string) for delayed delivery.
 *
 * @see datamachine/send-email-queued
 *
 * @param array $args Arguments forwarded to the ability.
 * @return array Result array as returned by the ability.
 */
function ec_send_email_queued( array $args ) {
	$defaults = array(
		'template'     => 'extrachill/branded',
		'mail_site_id' => extrachill_mail_site_id(),
	);

	$args = wp_parse_args( $args, $defaults );

	if ( ! function_exists( 'wp_get_ability' ) ) {
		return array(
			'success' => false,
			'error'   => 'WordPress Abilities API not available — datamachine/send-email-queued cannot be resolved.',
		);
	}

	$ability = wp_get_ability( 'datamachine/send-email-queued' );
	if ( ! $ability ) {
		return array(
			'success' => false,
			'error'   => 'Ability datamachine/send-email-queued is not registered. Is the Data Machine plugin active?',
		);
	}

	return $ability->execute( $args );
}

/**
 * Register EC-branded email templates against the DM template filter.
 *
 * Templates are PHP partials under `templates/email/`. Each callable
 * receives `array $context` and returns the rendered HTML string.
 *
 * The template file path itself is filterable via
 * `extrachill_email_template_path` so consumers can override markup
 * without forking this plugin (e.g. a child plugin can return a
 * different absolute path for `extrachill/branded`).
 *
 * Documented context keys (all optional, partials must provide defaults):
 *   - `subject_html`    Pre-escaped subject for the `<title>` tag.
 *   - `body_html`       Main message HTML, already sanitized.
 *   - `recipient_name`  Greeting personalization.
 *   - `cta_url`         Optional call-to-action URL.
 *   - `cta_label`       Optional call-to-action label.
 *   - `preheader`       Preview text shown by mail clients.
 *
 * @param array $templates Existing template map keyed by template ID.
 * @return array Modified template map.
 */
function extrachill_register_email_templates( $templates ) {
	// Defensive: other filter hooks may have returned a non-array.
	// PHPStan narrows `$templates` from the docblock, but at runtime
	// `apply_filters` makes no such guarantee.
	if ( ! is_array( $templates ) ) { // @phpstan-ignore-line
		$templates = array();
	}

	$templates['extrachill/branded'] = function ( array $context ) {
		return extrachill_render_email_template( 'branded', $context );
	};

	$templates['extrachill/minimal'] = function ( array $context ) {
		return extrachill_render_email_template( 'minimal', $context );
	};

	return $templates;
}
add_filter( 'datamachine_email_templates', 'extrachill_register_email_templates' );

/**
 * Render a template partial with output buffering.
 *
 * Resolves the template path via the `extrachill_email_template_path`
 * filter so a child plugin can swap markup without forking.
 *
 * @param string $template_id Template ID (e.g. `branded`, `minimal`).
 * @param array  $context     Template variables.
 * @return string Rendered HTML. Empty string if the partial is missing.
 */
function extrachill_render_email_template( $template_id, array $context ) {
	$default_path = EXTRACHILL_MULTISITE_PLUGIN_DIR . 'templates/email/' . $template_id . '.php';

	/**
	 * Filter the resolved absolute path to an EC email template partial.
	 *
	 * @param string $default_path Absolute path to the bundled partial.
	 * @param string $template_id  Template ID being rendered.
	 * @param array  $context      Context array passed to the template.
	 */
	$path = apply_filters( 'extrachill_email_template_path', $default_path, $template_id, $context );

	// Defensive: filter may have returned a non-string.
	if ( ! is_string( $path ) || ! file_exists( $path ) ) { // @phpstan-ignore-line
		return '';
	}

	ob_start();
	include $path;
	return (string) ob_get_clean();
}
