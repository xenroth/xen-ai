# Changelog

All notable changes to XEN AI are documented here.

---

## [1.3.2] — 2026-05-28

### Improvements

- **Removed "Coming Soon" badge from Pro card** — all four Pro features (Proactive Visitor Questioning, KB Topic Insights, Purchase Guide, Topic Quick-Menu) are fully implemented and activate automatically upon license activation. The badge has been removed to accurately reflect availability.

---

## [1.3.1] — 2026-05-29

### Branding

- **Official plugin icon added** — `assets/icon-128x128.png` and `assets/icon-256x256.png` added to the repository for display on WordPress.org plugin listing pages.
- **Plugin list icon** — `class-updater.php` now returns `icons` (1x/2x) in both the update transient and `plugin_information` response, so the XEN AI logo appears in the WordPress admin Plugins list and Updates screen for self-hosted installs.
- **Banner added** — `assets/banner-772x250.png` added for the WordPress.org plugin header banner.

---

## [1.3.0] — 2026-05-28

### WordPress Repository Compliance

- **All `echo` statements properly escaped** — every `echo` in the admin view files (`dashboard.php`, `leads.php`, `license.php`) now wraps output in `esc_html()` or `esc_attr()` as appropriate. Values going into HTML attributes use `esc_attr()`; values going into HTML content use `esc_html()`. This eliminates the most common reason for WP.org plugin review rejection.
- **External services disclosure completed in `readme.txt`** — added the three previously undisclosed external calls: `ip-api.com` (geo-IP in Leads dashboard), `api.xenroth.com` (Pro license validation), and Cloudflare Turnstile (`challenges.cloudflare.com`, optional bot challenge). WP.org requires every external HTTP call to be disclosed.

---

## [1.2.9] — 2026-05-28

### Improvements

- **Announcement bar spacing** — added bottom margin below the version announcement bar so the stats cards no longer stick to its bottom edge.

---

## [1.2.8] — 2026-05-28

### New Features

- **Version announcement bar on dashboard** — a persistent dark-slate banner now appears on the dashboard for both free and pro users, celebrating the current release with key highlights and a direct invite to join the LINE community. Previously the community bar was accidentally commented out and only the free-tier promo banner contained a LINE invite (meaning pro users never saw it after activating).

### WordPress Repository

- **`readme.txt` created** — added the WP.org-required `readme.txt` with proper format: plugin header block, full feature descriptions, installation steps, FAQ, external services disclosure (OpenAI, GitHub Models, QR API), screenshot list, and full changelog.
- **`Author URI` corrected** — changed from `mailto:me@xenroth.com` (invalid for WP.org) to `https://github.com/xenroth`.

---

## [1.2.7] — 2026-05-28

### Improvements

- **Proactive email capture after the 4th reply** — the bot now asks for the visitor's email address on its 4th reply (after 3 exchanges), making the ask feel like a privilege rather than a data grab. The AI frames it wittily — something like *"I don't do this for everyone, but you've been such a pleasure to chat with — would you mind sharing your email? I'll make sure you're first to hear about any exclusive offers. Zero spam!"* — then never asks again. Before the 4th reply the bot stays focused on helping; after the ask it only captures email if the visitor volunteers it naturally.

  Technically: `reply_count` is now tracked per session (counted from the in-memory message history) and passed to the system prompt builder. Three reply-count branches drive distinct instructions: too-early (don't ask), on-the-dot (ask wittily), already-asked (don't push).

---

## [1.2.6] — 2026-05-28

### Improvements

- **System Status: Test Connection button** — a new "🔌 Test Connection" button in the System Status card makes a live API call and immediately tells you whether your key is valid, has no credits, or is failing for another reason. This makes it easy to diagnose why the chatbot shows the fallback message without needing to check error logs.

- **System Status: Fallback Mode indicator with Clear button** — when the `xen_ai_api_unavailable` transient is active (meaning the chatbot is in offline/fallback mode after a quota or rate-limit error), a clear warning is now shown in the System Status card with a "Clear Now" button to exit fallback mode immediately without waiting the full 5 minutes.

- **System Status card spacing** — added bottom margin below the System Status card so subsequent sections on the dashboard are visually separated.

### Bug Fixes

- **`$is_auth` pattern too broad** — the previous `'authentication'` substring check in `map_error_to_friendly()` could match incidentally in non-auth error messages (e.g. phrases like "authentication required for additional usage"). Removed the broad match and kept only the specific patterns (`incorrect api key`, `invalid api key`, `invalid authentication`, `unauthorized`, `401`).

---

## [1.2.5] — 2026-05-28

### Bug Fixes

- **"I'm a little busy" shown on first message after setting a new API key** — when an API error (quota, billing, 429) occurs, the plugin sets a `xen_ai_api_unavailable` transient for 5 minutes that blocks all subsequent requests. Previously, this transient was never cleared when settings were saved, so saving a new valid key would still return the fallback message until the transient expired. Fixed by calling `delete_transient('xen_ai_api_unavailable')` at the end of every settings save.

- **Invalid API key not distinguished from quota exhaustion** — a 401 "Incorrect API key" response from OpenAI hit the catch-all error path ("I'm having trouble responding"), which was confusing and could be mistaken for a temporary issue. Auth/unauthorized responses are now detected separately and routed to the "not fully set up" message, making it clear the key itself is the problem rather than the API being unavailable.

---

## [1.2.4] — 2026-05-28

### Improvements

- **System Status moved to top of dashboard** — the System Status card now appears as the first section on the dashboard, above the community bar, stats grid, and all other content, so the API connection state and plugin version are immediately visible.

---

## [1.2.3] — 2026-05-28

### Bug Fixes

- **API key save regression (proper fix)** — the 1.2.2 focus/blur mask-clearing approach was fragile: it depended on character-exact comparison between the JavaScript constant and the PHP-rendered value, and silently failed if the user never clicked the field before saving. The architecture has been corrected: all three secret fields (`api_key`, `github_token`, `turnstile_secret_key`) now render with `value=""` and display the `••••••••` indicator only in the `placeholder`. An empty submission means "keep existing key"; any non-empty submission is treated as a new key. The PHP preserve logic is simplified to a single empty-string check. A one-time DB cleanup also strips any previously corrupted `••••••••`-prefixed values that may have been saved before this fix.

---

## [1.2.2] — 2026-05-28

### Bug Fixes

- **OpenAI API key not saving** — the API key (and GitHub token / Turnstile secret) fields displayed a `••••••••` mask as their literal value. Typing a new key on top of the mask caused `••••••••new_key` to be saved instead of just the new key, making the API call fail silently. Fixed by adding focus/blur handlers in `admin.js`: the mask is cleared when the user focuses the field so they can type a fresh value; if the field is left empty on blur, the mask is restored so the existing key is preserved on save.

- **Chatbot widget shows double greeting on empty knowledge base** — when Pro is active, `startSession()` appended a page-contextual greeting to the chat messages while the widget was still closed. When the user then opened the chat, `open()` saw `greeted = false` and appended the static greeting again, resulting in two opening messages. Fixed by setting `XenChat.greeted = true` in `chat.js` when the Pro greeting is received, so `open()` skips the redundant static greeting.

### Improvements

- **Announcement bar update reminder** — the Community & Announcements strip on the Dashboard now includes a reminder to check for updates under WordPress → Plugins to ensure the latest version is always installed.

---

## [1.2.1] — 2026-05-27

### Improved

- **Leads deduplication** — the Leads page now groups conversations by visitor name + IP and surfaces one representative row per unique person (prefers the row that captured an email), eliminating repeated entries for multi-session visitors.
- **Leads search / filter** — a search bar at the top of the Leads table filters results by name, email, or IP address in real time (form-based GET request, no JS dependency).
- **Sortable columns** — Name, Email, IP, Message count, and Date columns are all clickable to sort ascending or descending; active column is highlighted.
- **Geo-IP country detection** — the IP Address column now resolves each unique IP to a country flag + name via `ip-api.com` (client-side, batched, staggered to stay within the free 45 req/min rate limit).
- **CSV export improvements** — Export CSV now applies the same deduplication logic and search filter as the on-screen table, and includes a new **Country** column resolved server-side via `ip-api.com`. Private/reserved IP ranges are labelled `Local/Private` without an external call.

---

## [1.2.0] — 2026-05-27

### Changes

- **GitHub repository owner updated** — plugin URI, auto-updater (`GH_USER`), and README all updated from `sepiroth-x` to `xenroth`. Existing installs are unaffected (GitHub redirects old URLs), but this version makes the new username canonical and verifies the auto-update pipeline works end-to-end.

---

## [1.1.9] — 2026-05-27

### Added (Pro)

- **Toggleable KB topic panel in chat widget** — a 📚 book icon button appears in the chat header when Pro is active. Clicking it slides open a compact panel listing all active knowledge-base topics. Clicking any topic pre-fills the input and sends the message instantly, then closes the panel.
- **Query-aware related topics** — after every AI reply, the backend searches the knowledge base for entries whose `title` or `content` matches words in the user's message and returns up to 5 `related_topics`. The KB panel updates in real time to show only those relevant topics (subtitle changes to "Related to your message"). If no KB entries match the query the panel resets to the full topic list. When the panel is closed, the KB toggle button briefly pulses to signal new related topics are available.

### Technical changes

- `includes/class-chat-ajax.php` — final AJAX reply now passes through a new `xen_ai_chat_reply_data` filter so Pro (or third-party) hooks can append extra fields without touching the core handler
- `includes/class-pro-features.php` — new `filter_chat_reply_data()` method hooked on `xen_ai_chat_reply_data`: splits user message into 3+ char words, runs safe `LIKE` queries against `xen_ai_knowledge.title` and `.content`, returns top 5 matched titles as `related_topics`
- `admin/views/chat-widget.php` — KB toggle `<button>` added to header actions; `#xen-ai-kb-panel` region added below header
- `assets/js/chat.js` — new `kbTopicsAll`, `kbPanelOpen` state; `populateKbPanel()`, `toggleKbPanel()`, `openKbPanel()`, `closeKbPanel()` methods; `startSession()` and `sendMessage()` updated; KB toggle shown via `display:flex` when Pro topics present
- `assets/css/chat.css` — `.xen-ai-win-header-actions` wrapper; `.xen-ai-kb-toggle` button; `.xen-ai-kb-panel`, `.xen-ai-kb-panel-header`, `.xen-ai-kb-list`, `.xen-ai-kb-item`, `.xen-ai-kb-empty` panel styles; `xen-kb-pulse` animation

---

## [1.1.8] — 2026-05-27

### Added

- **Email field on license activation form** — users can now enter an optional contact email address alongside their license key when activating Pro. The email is:
  - Collected via a new `your@email.com` input field next to the license key field in **XEN A.I → Pro License**
  - Sent to the license server in the activation POST body (`email` field)
  - Stored in the local encrypted license record in `wp_options`
  - Shown in the license status strip on the Pro License page and in the Pro hero banner on the Dashboard

### Server update required
- **`license_activations` table** — run this once on the server DB:
  ```sql
  ALTER TABLE license_activations ADD COLUMN email varchar(150) DEFAULT NULL AFTER domain;
  ```
- **`license-api.php`** — update `handle_verify()` to read `$body['email']` and include it in the `INSERT INTO license_activations` call.

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
