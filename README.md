# XEN A.I — WordPress AI Chat Assistant Plugin

> **Author:** Xenroth (Richard C. Cupal, LPT)
> **Contact:** [me@xenroth.com](mailto:me@xenroth.com) · [+63 915 038 8448](tel:+639150388448)
> **Version:** 1.3.0 · **License:** GPL v2 or later

An AI-powered chat assistant for WordPress that combines your website's own knowledge base with live site content (pages, posts, and WooCommerce products) to answer visitor questions, capture leads, and guide users through your services or store — all from a beautiful floating chat widget.

---

## Features (Free)

| Feature | Details |
|---|---|
| 💬 **Chat Widget** | Floating, toggleable chat bubble injected into every page via `wp_footer`. Animated notification bubble encourages interaction. Fully customisable open/close behaviour. |
| 🤖 **Dual AI Provider** | Switch between **OpenAI** (paid, `sk-…`) and **GitHub Models** (completely free with a GitHub PAT). Both providers use the same OpenAI-compatible Chat Completions format — no code changes needed to switch. |
| 📚 **Knowledge Base** | Upload **PDF**, **DOCX**, **DOC**, **TXT** files or scrape any public URL. AI prioritises your content when answering questions. Manage entries from the admin panel with per-item delete. |
| 🛒 **Live Site Content Awareness** | AI automatically reads your published **pages**, **blog posts**, and **WooCommerce products** — including price, stock status, description, and buy links. No manual syncing needed. |
| 👤 **Proactive Lead Capture** | AI asks for the visitor's **name** on the first reply, then — on the 4th reply — wittily invites them to share their **email** with exclusive-offer framing. Both are stored automatically to the Leads dashboard. Captured data is never shown to the visitor. |
| 🎨 **Custom Branding** | Set a **bot name**, choose an **accent colour**, and upload your own **logo/avatar** (stored in WordPress Media Library). Changes reflect instantly in the widget. |
| ✍️ **Custom AI Instructions** | Write a **system prompt** to define the bot's personality, focus topics, tone, and any business-specific rules (e.g. pricing policies, escalation steps). |
| 📊 **Leads & Conversations Dashboard** | View all captured leads with **name**, **email**, **IP**, **country flag**, and **message count**. Full conversation history per lead, inline modal, deduplication, search/filter, sortable columns, and **CSV export**. |
| 🔧 **System Status & Diagnostics** | Admin dashboard shows live API connection state, active model, and plugin version. Includes a **Test Connection** button (live API call with specific pass/fail/quota diagnosis) and a **Clear Fallback Mode** button to exit offline mode instantly. |
| 🔁 **API Fallback Mode** | When the AI API hits a quota or rate-limit error, the plugin enters graceful offline mode and shows a configurable fallback message to visitors. Fallback clears automatically after 5 minutes or manually via the dashboard. |
| 🔒 **Layered Security** | Per-session rate limiting (20 msg/hr), per-IP hourly cap (60 msg/hr), burst protection (10 msg/min), session-init flood protection (5/10 min), concurrency lock, UUID4 session validation, honeypot field, user-agent filtering, 2,000-character input cap, nonce verification on every request, and optional **Cloudflare Turnstile** bot challenge. |
| 🔄 **Automatic Updates** | GitHub-based auto-updater hooks into WordPress's native update system — updates appear in **Dashboard → Updates** and install with one click, just like a WordPress.org plugin. |

---

## Pro Features *(₱999 one-time · all future Pro features included)*

| Feature | Details |
|---|---|
| 🎯 **Proactive Visitor Questioning** | The AI opens with targeted questions tailored to the page the visitor is on — before they even type — to surface their needs and drive deeper engagement from the very first second. |
| 📋 **Knowledge-Base Topic Insights** | After every AI reply, the backend searches the KB for entries related to the visitor's message and surfaces up to 5 relevant topics in real time. The visitor sees a live-updating **Related Topics** panel in the chat. |
| 🛍️ **Service & Product Purchase Guide** | Step-by-step conversational guidance that walks prospects through your offerings and directs them to checkout or a sales contact — turning browsers into buyers. |
| 💬 **Topic Quick-Menu (KB Chips)** | Clickable topic chips appear above the chat input listing all active KB topics. One tap pre-fills and sends the message instantly — no typing required. The panel updates dynamically to show only topics related to the current conversation. |
| 🔮 **All Future Pro Features** | Every new capability released under the Pro tier is automatically unlocked for existing license holders — forever. |

Activate via **XEN A.I → Pro License** in your WordPress admin, or contact the developer to get a key:
**[me@xenroth.com](mailto:me@xenroth.com)** · **[+63 915 038 8448](tel:+639150388448)** · [LINE Community](https://line.me/R/ti/g/DBGUQQdSg2)

---

## Requirements

- WordPress **5.8+**
- PHP **7.4+**
- An **OpenAI API key** (`sk-…`) **or** a **GitHub Personal Access Token** (`github_pat_…`) with access to GitHub Models
- *(Optional)* WooCommerce for product-aware AI responses

---

## Installation

1. Download or clone this repository into your `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/xenroth/xen-ai.git ai_assistant
   ```
2. Activate the plugin in **WordPress Admin → Plugins**.
3. Go to **XEN A.I → Settings**:
   - Choose your **AI Provider** (OpenAI or GitHub Models).
   - Enter your **API key / GitHub Token**.
   - Customise the bot name, accent colour, and logo.
4. Go to **XEN A.I → Knowledge Base** to upload documents or add URLs.
5. The chat widget appears automatically on your site's front end.

---

## AI Provider Setup

### OpenAI
1. Get an API key at [platform.openai.com/api-keys](https://platform.openai.com/api-keys).
2. In Settings, select **OpenAI**, paste your `sk-…` key, and choose a model.

### GitHub Models (Free)
1. Create a Personal Access Token at [github.com/settings/tokens](https://github.com/settings/tokens) (classic or fine-grained; no special scopes required).
2. In Settings, select **GitHub Models**, paste your `github_pat_…` token, and choose a model (e.g. GPT-4o, Llama 3.1 405B, Mistral Large).

> GitHub Models is free during the public preview and uses the same OpenAI-compatible API format.

---

## Plugin Structure

```
ai_assistance/
├── ai_assistance.php               # Plugin entry point & constants
├── includes/
│   ├── class-xen-ai-core.php       # Singleton boot, DB activation, asset enqueue
│   ├── class-knowledge-base.php    # KB CRUD + keyword-based context search
│   ├── class-ai-handler.php        # OpenAI / GitHub Models API wrapper
│   ├── class-site-content.php      # Live pages, posts & WooCommerce context
│   ├── class-file-processor.php    # PDF / DOCX / DOC / TXT / URL text extractor
│   └── class-chat-ajax.php         # Front-end AJAX endpoints (session + chat)
├── admin/
│   ├── class-admin.php             # Admin menus, assets, all admin AJAX
│   └── views/
│       ├── dashboard.php
│       ├── knowledge-base.php
│       ├── settings.php
│       ├── leads.php
│       └── chat-widget.php         # Front-end widget HTML (injected into wp_footer)
└── assets/
    ├── css/
    │   ├── admin.css
    │   └── chat.css
    └── js/
        ├── admin.js
        └── chat.js
```

---

## Database Tables

Created automatically on plugin activation:

| Table | Purpose |
|---|---|
| `{prefix}xen_ai_knowledge` | Knowledge base entries (title, content, source type) |
| `{prefix}xen_ai_conversations` | Chat sessions with captured lead data |
| `{prefix}xen_ai_messages` | Individual messages per conversation |

---

## Security Notes

- All AJAX endpoints verify nonces and check `manage_options` capability for admin actions.
- API keys and GitHub tokens are stored in `wp_options` and masked (`••••••••`) in the Settings UI — the real values are never overwritten by the placeholder.
- The URL scraper blocks requests to localhost, `127.0.0.1`, `::1`, and RFC-1918 private ranges to prevent SSRF attacks.
- Uploaded files for the knowledge base are stored in a private directory (`wp-content/uploads/xen-ai/`) protected by `.htaccess deny from all`.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for the full version history.
