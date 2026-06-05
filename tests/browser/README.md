# Cross-widget Turnstile browser smoke

A real-browser smoke for the Cloudflare Turnstile integration, focused on the
**cross-widget** failure class that PHPUnit cannot reach.

## Why this exists

`ec_render_turnstile_widget()` emits `<div class="cf-turnstile">` elements that
Cloudflare's `api.js` renders via an **implicit auto-render pass** — a single
loop over every `.cf-turnstile` element on the page. If one widget carries a
`data-callback` attribute naming a JS function that is never defined, that loop
**throws while processing that widget and aborts for every widget on the page**.
An unrelated sibling widget (e.g. the event-submission captcha on a page that
also carries the footer newsletter form) then silently never renders. That is
exactly what shipped as newsletter issue #17.

The PHPUnit suite (`tests/TurnstileTest.php`) covers the PHP renderer in
isolation — including a guard that the renderer never injects an unsolicited
`data-callback`. But the *cross-widget auto-render contract* is a DOM + JS
behaviour that only manifests with multiple real widgets co-rendering in a
browser. This smoke covers that layer.

## What it does

`run-cross-widget-smoke.sh` drives the WP Codebox (Playground) runtime:

1. Boots a disposable WordPress and activates `extrachill-multisite`.
2. Runs `seed-two-widgets.php`, which renders **two** `.cf-turnstile` widgets via
   the plugin's own `ec_render_turnstile_widget()` and injects a faithful stub of
   Cloudflare's implicit auto-render loop. The stub iterates widgets in document
   order, invokes each `data-callback` (if present) before marking the widget
   rendered, and — like the real `api.js` — does **not** swallow a throw, so a
   dangling callback aborts the remaining widgets.
3. Opens the seeded page in a headless browser (`wordpress.browser-probe`) and
   captures console, page errors, and a screenshot.

## What it asserts

- The page **loads and renders** without a navigation/PHP failure.
- The seed reports **two** widgets.
- The browser produced a **screenshot** (durable render evidence).
- **When the WP Codebox build captures page console/errors:** the full contract —
  zero uncaught page errors and `rendered == total` widgets via the
  `EC_TURNSTILE_SMOKE rendered=<n> total=<n>` console marker the seed emits. On
  builds whose browser runtime does not yet capture console/errors, this final
  assertion is **skipped with a NOTE rather than producing a false green**; the
  seed and runner are already wired so it lights up automatically once capture
  (or the richer `wordpress.browser-actions` expect/assert steps) is available.

## Running

```bash
tests/browser/run-cross-widget-smoke.sh
```

Requires the `wp-codebox` CLI on `PATH`. Artifacts (including the screenshot)
are written to `tests/browser/artifacts/` and are git-ignored.
