<?php
/**
 * Seed a page that renders TWO Cloudflare Turnstile widgets via the plugin's
 * own ec_render_turnstile_widget(), plus a faithful stub of Cloudflare's
 * implicit auto-render pass.
 *
 * This reproduces the real-world failure class behind newsletter #17: when one
 * .cf-turnstile widget on a page carries a data-callback attribute pointing at a
 * JS function that is never defined, Cloudflare's api.js implicit auto-render
 * throws while processing that widget and aborts the loop for EVERY widget on
 * the page — so an unrelated sibling widget (e.g. the event-submission captcha)
 * silently never renders.
 *
 * The stub api.js below mirrors that contract: it iterates every .cf-turnstile
 * element in document order and, for each, invokes the function named by its
 * data-callback (if any) before marking it rendered. A data-callback naming an
 * undefined function throws — and, exactly like the real api.js auto-render
 * loop, that throw aborts the remaining widgets. The browser-probe smoke then
 * asserts (a) zero uncaught page errors and (b) BOTH widgets rendered.
 *
 * Run inside the wp-codebox sandbox via wordpress.run-php.
 *
 * @package ExtraChill\Multisite
 */

if ( ! function_exists( 'ec_render_turnstile_widget' ) ) {
	echo wp_json_encode(
		array(
			'seeded' => false,
			'error'  => 'ec_render_turnstile_widget() not available — plugin not loaded',
		)
	);
	return;
}

// Configure Turnstile so the renderer emits real widgets (it returns '' when
// unconfigured). Test keys only; never hits the network in this smoke.
update_site_option( 'ec_turnstile_site_key', '1x00000000000000000000AA' );
update_site_option( 'ec_turnstile_secret_key', '1x0000000000000000000000000000000AA' );

// Render two widgets the way real consumers do: the footer newsletter form
// (invisible) and a second form's widget. Neither passes a data-callback — the
// fixed, correct behaviour. The stub api.js + this markup together let the smoke
// prove the cross-widget contract holds.
$widget_one = ec_render_turnstile_widget(
	array(
		'data-size' => 'invisible',
		'id'        => 'ec-smoke-widget-newsletter',
	)
);

$widget_two = ec_render_turnstile_widget(
	array(
		'id' => 'ec-smoke-widget-events',
	)
);

// Faithful stub of Cloudflare's implicit auto-render loop. Iterates widgets in
// document order; a data-callback naming an undefined function throws and
// aborts the loop (matching real api.js behaviour). Each successfully processed
// widget gets data-rendered="1".
$stub_api_js = <<<'JS'
(function () {
	function autoRender() {
		var widgets = document.querySelectorAll('.cf-turnstile');
		var total = widgets.length;
		var rendered = 0;
		// Mirror real api.js: a throw inside the loop (e.g. an undefined
		// data-callback) aborts the remaining widgets. We do NOT wrap the body
		// in try/catch so the failure surfaces exactly as it would in
		// production — as an uncaught page error that browser-probe captures.
		for (var i = 0; i < total; i++) {
			var el = widgets[i];
			var cbName = el.getAttribute('data-callback');
			if (cbName) {
				var cb = window[cbName];
				cb('stub-token');
			}
			el.setAttribute('data-rendered', '1');
			rendered++;
		}
		// Machine-readable marker for the smoke runner to assert against. Emit it
		// repeatedly on a short interval so the browser-probe console listener
		// (which attaches after navigation) reliably captures at least one copy
		// within its post-load capture window. Recompute rendered count from the
		// DOM each tick so the marker reflects the true end state.
		function emitMarker() {
			var done = document.querySelectorAll('.cf-turnstile[data-rendered="1"]').length;
			var all = document.querySelectorAll('.cf-turnstile').length;
			console.log('EC_TURNSTILE_SMOKE rendered=' + done + ' total=' + all);
		}
		emitMarker();
		setInterval(emitMarker, 250);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', autoRender);
	} else {
		autoRender();
	}
})();
JS;

$body = $widget_one . "\n" . $widget_two
	. "\n<script>\n" . $stub_api_js . "\n</script>";

// Create (or update) a published page the browser-probe can visit at a stable
// slug.
$existing = get_page_by_path( 'ec-turnstile-cross-widget-smoke' );
$postarr  = array(
	'post_title'   => 'EC Turnstile Cross-Widget Smoke',
	'post_name'    => 'ec-turnstile-cross-widget-smoke',
	'post_status'  => 'publish',
	'post_type'    => 'page',
	'post_content' => $body,
);
if ( $existing ) {
	$postarr['ID'] = $existing->ID;
}
$page_id = wp_insert_post( $postarr, true );

if ( is_wp_error( $page_id ) ) {
	echo wp_json_encode(
		array(
			'seeded' => false,
			'error'  => 'Failed to seed smoke page: ' . $page_id->get_error_message(),
		)
	);
	return;
}

echo wp_json_encode(
	array(
		'seeded'   => true,
		'page_id'  => (int) $page_id,
		'url'      => get_permalink( $page_id ),
		'widgets'  => 2,
		'has_callback' => ( false !== strpos( $body, 'data-callback' ) ),
	)
);
