=== XEN A.I — AI Chat Assistant & Lead Capture ===
Contributors: xenroth
Tags: ai, chatbot, chat, lead-capture, openai, knowledge-base, woocommerce, gpt
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.2.7
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered chat assistant with a knowledge base, lead capture, and WooCommerce awareness. Works with OpenAI or GitHub Models (free).

== Description ==

XEN A.I adds a smart, floating chat assistant to your WordPress site. It answers visitor questions using your custom knowledge base, captures leads naturally through conversation, and is aware of your pages, posts, and WooCommerce products out of the box.

**Free Features**

* 💬 **Floating Chat Widget** — auto-injected on all pages, animated notification bubble, fully customisable
* 🤖 **Dual AI Provider** — OpenAI (paid) or GitHub Models (completely free with a GitHub account)
* 📚 **Knowledge Base** — upload PDF, DOCX, DOC, TXT files or scrape any public URL
* 🛒 **Live Site Content Awareness** — AI reads your pages, posts, and WooCommerce products (price, stock, links) automatically
* 👤 **Proactive Lead Capture** — AI asks for name on the first reply, then wittily invites an email on the 4th reply
* 🎨 **Custom Branding** — bot name, accent colour, logo/avatar via WordPress Media Library
* ✍️ **Custom AI Instructions** — system prompt to define personality, tone, and business rules
* 📊 **Leads & Conversations Dashboard** — search, filter, sortable columns, geo-IP country flag, CSV export, inline conversation modal
* 🔧 **System Status & Diagnostics** — Test Connection button, Fallback Mode indicator, update checker
* 🔁 **API Fallback Mode** — graceful offline message when API quota/rate-limit is hit; auto-clears in 5 min or manually via dashboard
* 🔒 **Layered Security** — per-session rate limiting (20 msg/hr), per-IP cap (60 msg/hr), burst protection (10 msg/min), session-init flood guard, concurrency lock, UUID4 session validation, honeypot, user-agent filtering, 2,000-char input cap, nonce verification, optional Cloudflare Turnstile
* 🔄 **Auto-Updates** — GitHub-based updater; updates appear in Dashboard → Updates just like a WordPress.org plugin

**Pro Features (₱999 one-time)**

* 🎯 **Proactive Visitor Questioning** — AI initiates targeted questions tailored to the page before the visitor even types
* 📋 **Knowledge-Base Topic Insights** — real-time related-topics panel surfaces up to 5 relevant KB entries per reply
* 🛍️ **Service & Product Purchase Guide** — step-by-step conversational guidance to checkout or a sales contact
* 💬 **Topic Quick-Menu Chips** — clickable KB topic chips above the chat input; one tap pre-fills and sends
* 🔮 **All Future Pro Features** — every new Pro capability is automatically included for existing license holders

**External Services**

This plugin sends data to external services when processing chat messages:

* **OpenAI API** (`https://api.openai.com`) — used when the OpenAI provider is selected. Messages and knowledge-base context are sent to OpenAI to generate AI replies. See [OpenAI Privacy Policy](https://openai.com/privacy) and [Terms of Use](https://openai.com/terms).
* **GitHub Models API** (`https://models.inference.ai.azure.com`) — used when the GitHub Models provider is selected. Same data is sent to GitHub/Azure-hosted models. See [GitHub Privacy Statement](https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement) and [GitHub Terms of Service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service).
* **QR Code API** (`https://api.qrserver.com`) — used in the admin dashboard only to generate a QR code image for the LINE community group link. No user data is sent.

No data is sent to any external service by this plugin outside of the above.

== Installation ==

1. Upload the `xen-ai` folder to the `/wp-content/plugins/` directory (or install via **Plugins → Add New → Upload Plugin**).
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **XEN A.I → Settings** and add your AI provider key:
   * **OpenAI**: paste your `sk-...` API key.
   * **GitHub Models (free)**: paste a GitHub Personal Access Token (`github_pat_...`) with no special scopes required.
4. Optionally, go to **XEN A.I → Knowledge Base** and upload files or scrape a URL.
5. Visit your site — the chat bubble will appear in the bottom-right corner.

== Frequently Asked Questions ==

= Is this plugin free to use? =

Yes. The plugin itself is free and open-source (GPL v2). Using OpenAI requires a paid OpenAI account; GitHub Models is completely free with a free GitHub account.

= Do I need WooCommerce? =

No. WooCommerce support is an automatic enhancement — if WooCommerce is active, the AI reads your products. The plugin works fine without it.

= How is my API key stored? =

API keys are stored encrypted in `wp_options`. The settings page never shows the real value — it only shows a masked placeholder. Empty submissions preserve the existing key.

= Is the chat widget GDPR-friendly? =

The plugin collects visitor names and emails only when the visitor provides them voluntarily through conversation. No tracking scripts or cookies are set by this plugin. You are responsible for including this data collection in your own privacy policy.

= Does it work with page builders (Elementor, Divi, etc.)? =

Yes. The widget is injected via `wp_footer`, which all properly-coded themes and page builders support.

= Where can I get a Pro license? =

Contact the developer: [me@xenroth.com](mailto:me@xenroth.com) or +63 915 038 8448. You can also activate your key under **XEN A.I → Pro License** once you have it.

== Screenshots ==

1. Dashboard overview with System Status card and stats grid.
2. Floating chat widget on the front end.
3. Knowledge Base management panel.
4. Leads & Conversations dashboard with inline message modal.
5. Settings page — AI provider, branding, and custom instructions.

== Changelog ==

= 1.2.7 =
* Added proactive email-capture directive: AI wittily invites email on the 4th reply using reply-count-gated system prompt logic.
* Fixed `$is_auth` detection pattern to exclude false positives from non-authentication errors.
* Prevented double greeting when Pro greeting is already received.

= 1.2.6 =
* Added Test Connection button to admin dashboard with live API call, quota/auth/success diagnosis.
* Added Fallback Mode indicator with "Clear Now" button to manually exit offline mode.
* Fixed spacing on System Status card (24px bottom margin).
* Tightened `$is_auth` error pattern list.

= 1.2.5 =
* Transient `xen_ai_api_unavailable` is now cleared automatically when settings are saved.
* Added auth-error vs quota-error distinction so valid key updates exit fallback mode immediately.

= 1.2.4 =
* Moved System Status card to the top of the dashboard page.

= 1.2.3 =
* Fixed API key save architecture: settings fields use `value=""` with mask in placeholder only.
* Added PHP cleanup for mask-corrupted values already stored in the database.
* Empty submission now preserves the existing key instead of overwriting it.

= 1.2.2 =
* Fixed double-greeting bug for Pro users.
* Added update-reminder note to the community bar.

= 1.0.0 =
* Initial release with full feature set including dual AI provider, knowledge base, lead capture, WooCommerce awareness, and Pro license system.

== Upgrade Notice ==

= 1.2.7 =
Recommended update. Improves AI email-capture behaviour and dashboard diagnostics. No database changes.
