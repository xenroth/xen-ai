# Changelog

All notable changes to XEN AI are documented here.

---

## [1.1.3] — 2026-05-27

### Bug Fixes

- **License activation/deactivation AJAX handlers missing** — `ajax_activate_license()` and `ajax_deactivate_license()` methods were registered as AJAX actions but never defined in `Xen_AI_Admin`, causing a PHP fatal error and "Request failed. Check your connection" on every attempt

---

## [1.1.2] — 2026-05-27

### Bug Fixes

- **License token product mismatch** — `validate_token()` now correctly checks for `'xen-ai-pro'` to match the product value the license server encodes in the signed token (was checking `'xen-ai'`, causing every activation to fail with "invalid token")
- **Deactivate missing action field** — `deactivate()` now sends `'action' => 'deactivate'` in the request body so the server correctly routes the deactivation call instead of rejecting it as an invalid action

---

## [1.1.1] — 2026-05-21

### Bug Fixes

- **Chat head not opening** — removed a duplicate code block that was appended after the closing `})(jQuery);` in `chat.js`, which caused a silent JS syntax error and broke the entire chat widget
- **GitHub/OpenAI token wiped on plugin update** — settings save handler now preserves any stored secret when the posted value is empty (not just when it equals the `••••••••` mask), preventing tokens from being cleared during updates or form saves where the field wasn't touched

---

## [1.1.0] — 2026-05-21

### Security & Abuse Prevention

- **Layered rate limiting** — per-session (20 msg/hr), per-IP hourly (60 msg/hr), and per-IP burst protection (10 msg/min) using WordPress transients; no external dependencies
- **Session-init flood protection** — limits new session creation to 5 per IP per 10 minutes to block automated session-flooding
- **Concurrency lock** — prevents multi-tab and rapid-fire simultaneous requests per session (30s TTL lock)
- **UUID4 session validation** — rejects forged or malformed session IDs before any database work
- **Honeypot field** — hidden `xen_hp` field silently discards bot submissions without alerting the bot
- **User-agent filtering** — rejects headless/empty user agents typical of automated scrapers
- **Input length cap** — hard 2,000 character limit on messages to prevent token-exhaustion attacks
- **Trusted IP resolution** — properly handles `X-Forwarded-For` and `CF-Connecting-IP` headers for accurate rate limiting behind proxies/Cloudflare
- **Cloudflare Turnstile integration** — optional bot challenge token verification (configurable in settings)

### Resilience & Fallback

- **API fallback mode** — when the AI API is quota-exhausted or unavailable, a transient flag activates graceful fallback replies instead of hard errors
- **Configurable fallback message** — admin-defined custom offline message shown to visitors during outages

### Frontend

- **Cooldown timer UI** — when rate-limited, displays a live countdown before the user can send again
- **Rate-limit aware responses** — frontend reads `rate_limited` and `cooldown` flags from the server response

### Admin & Settings

- **Turnstile site key/secret settings** — new fields in the settings panel to configure Cloudflare Turnstile
- **Fallback reply setting** — admin can configure the message shown when the API is unavailable

### Other Improvements

- Hardened knowledge base and site content scrapers against malformed input
- Improved nonce and capability checks across AJAX endpoints
- Minor sanitization and escaping improvements throughout

---

## [1.0.4] — 2026-05-20

- Fix: updater falls back to `/releases` list then `/tags` API — detects updates even without a formal GitHub Release

## [1.0.3] — 2026-05-20

- Fix: session not found — check insert result, fallback without `visitor_ip`, robust `ALTER TABLE` migration

## [1.0.2] — 2026-05-19

- Capture visitor IP; clean leads-only admin view with IP column

## [1.0.1] — 2026-05-18

- License fix, GitHub auto-updater, clean uninstall, pro features wiring
- Dynamic lead capture — AI asks for name first, email only after engagement
- Clean uninstall option and manual data wipe in settings

## [1.0.0] — 2026-05-17

- Initial release
