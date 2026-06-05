#!/usr/bin/env bash
set -euo pipefail

# Cross-widget Turnstile browser smoke.
#
# Boots a disposable WordPress (WP Codebox / Playground), activates
# extrachill-multisite, seeds a page that renders TWO .cf-turnstile widgets via
# the plugin's own ec_render_turnstile_widget() plus a faithful stub of
# Cloudflare's implicit auto-render loop, then drives a real headless browser
# over the page and captures console + page-error + screenshot artifacts.
#
# What this catches today: a hard render/navigation failure of the seeded page
# (the page must load and both widgets must reach the DOM). The screenshot and
# seed output are the durable evidence.
#
# What it is staged for: asserting the full cross-widget auto-render contract
# (both widgets render; zero uncaught page errors) the moment the WP Codebox
# browser runtime on the host captures page console/errors (or exposes the
# richer wordpress.browser-actions expect/assert steps). The seed already emits
# an `EC_TURNSTILE_SMOKE rendered=<n> total=<n>` console marker and lets the
# dangling-callback throw surface uncaught, so those assertions light up without
# touching this harness once capture is available. Tracked upstream.
#
# Usage:
#   tests/browser/run-cross-widget-smoke.sh
#
# Requires the `wp-codebox` CLI on PATH (the WP Codebox plugin's runtime).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
RECIPE="${SCRIPT_DIR}/cross-widget-turnstile-recipe.json"
ARTIFACTS_DIR="${SCRIPT_DIR}/artifacts"

if ! command -v wp-codebox >/dev/null 2>&1; then
    echo "SKIP: wp-codebox CLI not found on PATH — cannot run the browser smoke." >&2
    exit 0
fi

rm -rf "$ARTIFACTS_DIR"

run_json="$(mktemp)"
trap 'rm -f "$run_json"' EXIT

echo "Running cross-widget Turnstile browser recipe..."
if ! ( cd "$SCRIPT_DIR" && wp-codebox recipe-run --recipe "$RECIPE" --json ) > "$run_json" 2>&1; then
    echo "FAIL: recipe-run exited non-zero" >&2
    tail -n 40 "$run_json" >&2
    exit 1
fi

# A top-level recipe error (e.g. navigation TimeoutError, PHP fatal in the seed,
# failed plugin activation) means the page did not load/render as expected.
top_error="$(python3 - "$run_json" <<'PY'
import json, sys
raw = open(sys.argv[1]).read()
start = raw.find("{")
end = raw.rfind("}")
try:
    data = json.loads(raw[start:end + 1])
except Exception:
    print("PARSE_FAIL")
    sys.exit(0)
err = data.get("error")
print(json.dumps(err) if err else "")
PY
)"

if [ "$top_error" = "PARSE_FAIL" ]; then
    echo "FAIL: could not parse recipe-run output" >&2
    tail -n 40 "$run_json" >&2
    exit 1
fi

if [ -n "$top_error" ]; then
    echo "FAIL: recipe reported an error (page did not load/render cleanly):" >&2
    echo "  $top_error" >&2
    exit 1
fi

# Confirm the seed reported both widgets, and the probe produced a screenshot.
seed_ok="$(python3 - "$run_json" <<'PY'
import json, sys
raw = open(sys.argv[1]).read()
start = raw.find("{")
end = raw.rfind("}")
data = json.loads(raw[start:end + 1])
seeded = False
for cmd in data.get("executions", []):
    if cmd.get("command") == "wordpress.run-php":
        out = cmd.get("stdout", "") or ""
        try:
            payload = json.loads(out.strip().splitlines()[-1])
        except Exception:
            payload = {}
        if payload.get("seeded") and int(payload.get("widgets", 0)) >= 2:
            seeded = True
print("ok" if seeded else "no")
PY
)"

if [ "$seed_ok" != "ok" ]; then
    echo "FAIL: seed did not report two rendered widgets" >&2
    tail -n 40 "$run_json" >&2
    exit 1
fi

# Locate the browser-probe screenshot as durable evidence the page rendered.
screenshot="$(find "$ARTIFACTS_DIR" -name screenshot.png 2>/dev/null | head -n 1)"
if [ -z "$screenshot" ] || [ ! -s "$screenshot" ]; then
    echo "FAIL: browser-probe produced no screenshot — page likely did not render" >&2
    exit 1
fi

echo "PASS: page loaded, two Turnstile widgets seeded, browser captured a render."
echo "  Screenshot: ${screenshot}"

# Best-effort: if this WP Codebox build captures page console/errors, enforce the
# full cross-widget contract. When the channels are inert (older CLI builds),
# these assertions are skipped rather than producing a false green.
summary="$(find "$ARTIFACTS_DIR" -path '*browser/summary.json' 2>/dev/null | head -n 1)"
if [ -n "$summary" ]; then
    python3 - "$summary" "$ARTIFACTS_DIR" <<'PY'
import json, sys, glob, os

summary = json.load(open(sys.argv[1]))
counts = summary.get("summary", {})
errors = int(counts.get("errors", 0))
console_n = int(counts.get("consoleMessages", 0))

if errors > 0:
    print(f"FAIL: browser captured {errors} uncaught page error(s) — cross-widget auto-render aborted")
    sys.exit(1)

if console_n == 0:
    print("NOTE: this WP Codebox build did not capture page console/errors;")
    print("      skipping the full both-widgets-rendered assertion (load+screenshot smoke only).")
    sys.exit(0)

# Console capture is live — enforce the rendered marker.
artifacts_dir = sys.argv[2]
console_files = glob.glob(os.path.join(artifacts_dir, "**", "browser", "console.jsonl"), recursive=True)
rendered = total = None
for path in console_files:
    for line in open(path):
        if "EC_TURNSTILE_SMOKE" not in line:
            continue
        try:
            text = json.loads(line).get("text", "")
        except Exception:
            text = line
        for tok in text.split():
            if tok.startswith("rendered="):
                rendered = int(tok.split("=", 1)[1])
            if tok.startswith("total="):
                total = int(tok.split("=", 1)[1])

if rendered is None or total is None:
    print("FAIL: console captured but the EC_TURNSTILE_SMOKE marker was missing")
    sys.exit(1)

if rendered != total or total < 2:
    print(f"FAIL: only {rendered}/{total} widgets rendered — a sibling widget was aborted")
    sys.exit(1)

print(f"PASS: full cross-widget contract held — {rendered}/{total} widgets rendered, zero page errors.")
PY
fi

echo "Cross-widget Turnstile browser smoke passed"
