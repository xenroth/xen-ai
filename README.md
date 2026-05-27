# XEN A.I — WordPress AI Chat Assistant Plugin

> **Author:** Xenroth (Richard C. Cupal, LPT)
> **Contact:** [me@xenroth.com](mailto:me@xenroth.com) · [+63 915 038 8448](tel:+639150388448)
> **Version:** 1.2.1· **License:** GPL v2 or later

An AI-powered chat assistant for WordPress that combines your website's own knowledge base with live site content (pages, posts, and WooCommerce products) to answer visitor questions, capture leads, and guide users through your services or store — all from a beautiful floating chat widget.

---

## Features (Free)

| Feature | Details |
|---|---|
| 💬 **Chat Widget** | Floating, toggleable chat bubble injected into every page via `wp_footer`. Notification bubble encourages interaction. |
| 🤖 **Dual AI Provider** | Switch between **OpenAI** (`sk-…`) and **GitHub Models** (free with a GitHub PAT `github_pat_…`). Both use the same OpenAI-compatible API format. |
| 📚 **Knowledge Base** | Upload **PDF**, **DOCX**, **DOC**, **TXT** files or scrape any public URL. AI answers from your content first. |
| 🛒 **Live Site Content** | AI automatically reads published **pages**, **blog posts**, and **WooCommerce products** — including price, stock status, description, and ordering instructions. |
| 👤 **Lead Capture** | AI naturally collects visitor **name & email** through conversation and saves them to the Leads dashboard. |
| 🎨 **Custom Branding** | Set bot name, accent colour, and upload your own **logo/avatar** image. |
| 📊 **Leads & Conversations** | Full conversation history, lead viewer with inline modal, CSV export, per-conversation delete. |
| 🔒 **Security** | Session-based rate limiting (20 msg/hr), nonce verification on every AJAX request, SSRF-safe URL scraper. |

---

## Pro Features *(₱999 one-time payment)*

- 🎯 **Proactive Visitor Questioning** — AI initiates targeted questions before the visitor types.
- 📋 **Knowledge-Base Topic Insights** — Real-time list of KB topics most relevant to what the visitor is browsing.
- 🛍️ **Service & Product Purchase Guide** — Conversational step-by-step guidance to checkout.

Interested? Contact **[me@xenroth.com](mailto:me@xenroth.com)** or **[+63 915 038 8448](tel:+639150388448)**.

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

### 1.0.0
- Initial release with full feature set.
- Dual AI provider support (OpenAI + GitHub Models).
- Live site content awareness (pages, posts, WooCommerce).
- Logo upload via WordPress Media Library.
- Lead capture with name & email extraction.
- Pro features teaser with contact information.
