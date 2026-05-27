# Changelog

All notable changes to XEN AI are documented here.

---

## [1.1.7] — 2026-05-27

### Bug Fixes

- **License always detected as Free despite server activation** — root cause was three compounding bugs in `class-license.php`:
  1. **`site_domain()` did not strip `www.` prefix** — the license server normalises domains by removing `www.` before embedding the domain in the signed token (e.g. `example.com`), but the plugin was comparing against the raw WordPress host (`www.example.com`). The check always failed → `validate_token()` returned `false` → plugin treated every install as Free
  2. **`encrypt()` used `||` as a binary separator inside a raw 16-byte IV** — the separator bytes `\x7c\x7c` could appear within the random binary IV, causing `decrypt()` to split at the wrong position and corrupt the stored record. Fixed by switching to `OPENSSL_RAW_DATA` and a colon `:` separator between two b64url-encoded values; legacy records are still readable via a fallback path
  3. **`activate()` POST body sent `'product' => 'xen-ai'`** — corrected to `'xen-ai-pro'` to match the server and token payload
- **License page forces fresh validation** — the Pro License admin page now clears the 24-hour transient cache on every visit so stale "Free" results don't persist after activation

### Action required for existing installs
After deploying this update, go to **XEN A.I → Pro License** and re-enter your license key. The previous activation attempt was rejected silently by the plugin (token domain mismatch), so no record was stored in WordPress — the server activation slot is still held and will be reused automatically on the same domain.

---

## [1.1.6] — 2026-05-27

### New

- **Pro topic quick-menu chips** — when Pro is active and KB entries exist, clickable topic chips appear above the chat input; clicking one pre-fills and sends the message instantly, hiding the chip bar. Powered by new `pro_topics` field in the session-init response
- **Getting Started guide on dashboard** — collapsible step-by-step onboarding card shows how to connect an AI provider, set bot identity, build the knowledge base, write a system prompt, test the widget, and optionally activate Pro. Steps auto-mark as done based on live status (e.g. API configured, KB has entries, Pro active)

### Changes

- **Pro sell card hidden when active** — the "XEN A.I Pro" upsell section is no longer shown once a license is active (the Pro hero banner already covers this); the fourth Pro feature (Topic Quick-Menu) is now listed in the upsell card for free users
- **Q1 confirmed (internal)** — all 3 previously listed Pro features (Proactive Visitor Questioning, KB Topic Insights, Purchase Guide) were already fully implemented and activate automatically via WordPress filters when a valid license is detected

---

## [1.1.5] — 2026-05-27

### New

- **Pro Version hero banner** — when a license is active the dashboard now shows a full-width "PRO VERSION ACTIVE" banner listing all unlocked perks, license key (masked), bound domain, activation date, and a note that all future Pro features are included automatically
- **Pro title badge** — the Dashboard `h1` title gains a gold "✦ PRO" pill when a license is active
- **Pro card unlocked state** — the Pro features card transforms when active: features show green checkmarks, an extra "All Future Pro Features — Included" tile appears, and the card border turns green
- **LINE Community & Announcements bar** — a permanent green strip always visible below the hero/promo area linking to the developer's LINE group for plugin updates and announcements (previously hidden behind the promo condition)

---

## [1.1.4] — 2026-05-27

### New

- **Free Pro License promo banner** — dashboard now shows a highlighted promotional banner for non-activated installs offering free Pro license keys to the first 10 users, with a LINE community QR code and join link (https://line.me/R/ti/g/DBGUQQdSg2). Banner auto-hides once a license is active.

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
