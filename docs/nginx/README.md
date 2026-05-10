# nginx — bot blocking, abuse mitigation, rate limiting

Canonical reference copies of the nginx config that protects extrachill.com from credential scanners, aggressive scrapers, and brute-force login probes. These files are the source of truth — the live `/etc/nginx/` config on the production VPS should match them.

## Why these exist

On **2026-05-10** Pinterestbot crawled the events calendar with `past=1` queries that bypassed cache and saturated PHP-FPM, taking the network down. While debugging we also discovered ongoing credential scanning (5,675 hits to `/config.env` from a single IP) and steady brute-force probing of `/wp-login.php` from rotating IPs.

The fix at the time was an nginx-layer config that:

- **429s known bad bots** by user-agent (Pinterest specifically — they back off on 429 better than 403)
- **drops connections (444)** from credential-scanner IPs and probe URIs (`.env`, `.git/`, `wp-config.php.bak`, etc.)
- **rate-limits `/wp-json/`** per-IP so one client can't monopolize FPM workers

That config lived only on the VPS. A server rebuild would have lost it. This directory codifies it.

## Files

| File | Scope | Purpose |
|------|-------|---------|
| `bot-blocking.conf` | `http { }` | UA / IP / URI block maps + `wpjson` rate-limit zone. Install in `/etc/nginx/conf.d/`. |
| `server-snippet.conf` | `server { }` | The `if`-guards that turn the maps into actual returns + the `/wp-json/` rate-limited location. Paste into the site server block. |
| `wp-login-ratelimit.conf` | mixed | NEW — adds `/wp-login.php` rate limiting (3 req/min per IP, burst 5). The zone is `http {}`-scoped, the location goes in the server block. |

## Wiring it into a fresh nginx setup

Assuming a Debian/Ubuntu nginx package (the auto-include `/etc/nginx/conf.d/*.conf` is wired up by default):

1. Copy the http-scope files into `/etc/nginx/conf.d/`:
   ```bash
   sudo cp docs/nginx/bot-blocking.conf       /etc/nginx/conf.d/bot-blocking.conf
   sudo cp docs/nginx/wp-login-ratelimit.conf /etc/nginx/conf.d/wp-login-ratelimit.conf
   ```

2. Open the site server block (e.g. `/etc/nginx/sites-available/extrachill`) and paste:
   - The contents of `server-snippet.conf` early in the HTTPS server block (before any `location` directives so the `if`-guards run first).
   - The `location = /wp-login.php { ... }` block from the bottom of `wp-login-ratelimit.conf` ABOVE the generic `location ~ \.php$` block — exact-match (`=`) takes precedence over regex, so order matters less, but readability matters.

3. Validate and reload:
   ```bash
   sudo nginx -t && sudo systemctl reload nginx
   ```

## Status codes used

| Code | Meaning | Used for |
|------|---------|----------|
| `429` | Too Many Requests | Bad bots, rate-limit hits — signals "back off" |
| `444` | nginx-only: close connection, no response | Credential scanners, probe URIs — don't even leak that the path exists |

## Maintenance — these blocklists drift

The IP and bot lists in `bot-blocking.conf` are **hand-maintained snapshots**, not authoritative. They were derived from log analysis on **2026-05-10**. The right way to extend them is the same way they were built originally:

- `tail -F /var/log/nginx/access.log | grep -E '(\.env|config\.env|\.git/|wp-config\.php\.)'` to see live probe traffic
- `awk '{print $1}' /var/log/nginx/access.log | sort | uniq -c | sort -rn | head` to spot single-IP volume spikes
- check 4xx/5xx ratio per IP for credential-stuffing patterns

When you find a new offender, add it to `bot-blocking.conf` here, commit, then sync to the VPS — don't hand-edit the live config and let it drift back out of sync.

The `/wp-login.php` rate limit is the one piece of this config that's **mostly self-maintaining** — it doesn't need a list. New brute-force IPs hit the same per-IP cap automatically.

## Future work

- A pipeline / scheduled job that mines nginx access logs and proposes blocklist additions as a PR against this repo (instead of needing a human to grep). Out of scope for this issue.
- Cloudflare-only allowlist for `xmlrpc.php` if any legitimate consumer surfaces (currently `deny all`).
- Optional WordPress mu-plugin backstop for `/wp-login.php` rate limiting on environments without nginx in front (shared hosting, dev containers). Tracked separately.

## See also

- Extra-Chill/extrachill-multisite#12 — the issue this directory was created for.
- Extra-Chill/data-machine-events#246 — calendar caching, the proper upstream fix for the original Pinterestbot DOS vector.
