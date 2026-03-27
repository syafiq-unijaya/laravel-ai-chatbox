# laravel-ai-chatbox

[![Tests](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml/badge.svg)](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/syafiq-unijaya/laravel-ai-chatbox.svg?label=packagist)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Total Downloads](https://img.shields.io/packagist/dt/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![PHP](https://img.shields.io/packagist/php-v/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-42b883?logo=vue.js&logoColor=white)](https://vuejs.org)
[![License](https://img.shields.io/packagist/l/syafiq-unijaya/laravel-ai-chatbox.svg)](LICENSE)

A drop-in AI chatbox widget for Laravel — powered by **Vue 3** on the frontend and your choice of AI provider on the backend. One Blade directive, zero build tools required in your application.

Messages are proxied through your Laravel backend to any **OpenAI-compatible API**. Defaults to **Ollama** running locally with the `phi3:mini` model.

---

## Features

- **One-line integration** — drop `@aichatbox` anywhere in a Blade layout
- **Vue 3 frontend** — reactive widget with no jQuery or external CDN dependencies
- **Universal AI support** — Ollama (local & cloud), OpenAI, Groq, OpenRouter, or any OpenAI-compatible endpoint
- **Markdown rendering** — AI replies rendered with `marked.js` + `DOMPurify`, both bundled (no CDN)
- **Conversation history** — server-side session memory with configurable turn limit
- **Browser persistence** — chat history survives page refresh via `localStorage` or `sessionStorage`
- **Health check** — pings the AI service before opening; shows an offline toast if unreachable
- **Sound notifications** — Web Audio API ping on AI reply, no audio file needed
- **Dark mode** — automatic via `prefers-color-scheme`
- **SSRF protection** — blocks requests to private/reserved IPs on the health endpoint
- **CORS middleware** — restricts chatbox routes to your own origin
- **Rate limiting** — configurable per-IP throttle on all endpoints
- **4 widget positions** — bottom-right, bottom-left, top-right, top-left
- **Fully configurable** — all options controllable via `.env` or published config

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | 8.2+ |
| Laravel | 10, 11, or 12 |

> No Node.js or npm required in your application — the Vue bundle is pre-compiled and published as a static asset.

---

## Installation

### 1. Install via Composer

**From Packagist:**
```bash
composer require syafiq-unijaya/laravel-ai-chatbox
```

**Local development (path repository):**

Add to your project's `composer.json`:
```json
"repositories": [
    {
        "type": "path",
        "url": "../packages/syafiq-unijaya/laravel-ai-chatbox"
    }
],
"require": {
    "syafiq-unijaya/laravel-ai-chatbox": "*"
}
```
Then run `composer update`.

---

### 2. Publish assets

```bash
# Publish CSS + JS to public/vendor/ai-chatbox/
php artisan vendor:publish --tag=ai-chatbox-assets

# Publish config (optional — to customise defaults)
php artisan vendor:publish --tag=ai-chatbox-config
```

---

### 3. Configure `.env`

The package defaults to Ollama on `localhost:11434` with `phi3:mini`. Override any value via `.env`:

```env
AI_CHATBOX_API_URL=http://localhost:11434/v1/chat/completions
AI_CHATBOX_API_TOKEN=ollama
AI_CHATBOX_API_MODEL=phi3:mini
AI_CHATBOX_LANGUAGE=English
```

> **Running Ollama in WSL?**
> `localhost` from a Windows host may not reach WSL. Find your WSL IP and use it instead:
> ```bash
> # run inside WSL
> ip addr show eth0 | grep 'inet '
> ```
> ```env
> AI_CHATBOX_API_URL=http://172.x.x.x:11434/v1/chat/completions
> AI_CHATBOX_SSRF_PROTECTION=false
> ```

> **Local Ollama + SSRF protection?**
> SSRF protection is enabled by default and blocks requests to private IPs including `localhost`.
> Disable for local development:
> ```env
> AI_CHATBOX_SSRF_PROTECTION=false
> ```

---

### 4. Add the widget to a Blade layout

```blade
{{-- anywhere in your layout, e.g. resources/views/layouts/app.blade.php --}}
@aichatbox
```

The chatbox appears as a floating button on every page that includes the layout.

---

## Configuration Reference

Publish and edit `config/ai-chatbox.php` to customise all options.

### AI API

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `api_url` | `AI_CHATBOX_API_URL` | `http://localhost:11434/v1/chat/completions` | AI API endpoint |
| `api_token` | `AI_CHATBOX_API_TOKEN` | `ollama` | Bearer token |
| `api_model` | `AI_CHATBOX_API_MODEL` | `phi3:mini` | Model name |
| `timeout` | `AI_CHATBOX_TIMEOUT` | `30` | Seconds before the request times out |

### Response Language & System Prompt

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `language` | `AI_CHATBOX_LANGUAGE` | `English` | Language the AI must always reply in — leave empty to let the model decide |
| `system_prompt` | `AI_CHATBOX_SYSTEM_PROMPT` | `You are a helpful assistant...` | System message sent on every request — use `{language}` as a placeholder |

### Response Tuning

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `temperature` | `AI_CHATBOX_TEMPERATURE` | `0.7` | Creativity — `0.0` deterministic, `1.0` creative |
| `max_tokens` | `AI_CHATBOX_MAX_TOKENS` | `null` | Max reply length — `null` lets the model decide |

### Conversation History

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `history_enabled` | `AI_CHATBOX_HISTORY` | `true` | Send previous messages for context |
| `history_limit` | `AI_CHATBOX_HISTORY_LIMIT` | `50` | Max user+assistant pairs kept in session |

### Routes & Middleware

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `route_prefix` | — | `ai-chatbox` | URL prefix for all chatbox routes |
| `middleware` | — | `['web', 'throttle:20,1', 'ai-chatbox.cors']` | Middleware stack |
| `rate_limit` | `AI_CHATBOX_RATE_LIMIT` | `20` | Max requests per window per IP |
| `rate_window` | `AI_CHATBOX_RATE_WINDOW` | `1` | Rate limit window in minutes |
| `health_check` | `AI_CHATBOX_HEALTH_CHECK` | `true` | Ping the AI service before opening the chatbox |
| `offline_message` | `AI_CHATBOX_OFFLINE_MESSAGE` | `AI service is currently unreachable.` | Toast message shown when the AI service is offline |

### Security

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `ssrf_protection` | `AI_CHATBOX_SSRF_PROTECTION` | `true` | Block requests to private/reserved IPs |
| `allowed_origins` | — | `[env('APP_URL')]` | Origins permitted to call chatbox endpoints (CORS) |

### Storage

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `storage` | `AI_CHATBOX_STORAGE` | `local` | Browser storage — `local` (persists across sessions) or `session` (clears on tab close) |

### Widget Appearance

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `title` | `AI_CHATBOX_TITLE` | `AI Assistant` | Header title |
| `placeholder` | — | `Type your message...` | Input placeholder text |
| `theme_color` | — | `#4f46e5` | Primary colour (CSS variable) |
| `position` | `AI_CHATBOX_POSITION` | `bottom-right` | Widget position — `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `greeting` | `AI_CHATBOX_GREETING` | `Hi! How can I help you today?` | Opening message on first open — leave empty to disable |

### Features

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `markdown` | `AI_CHATBOX_MARKDOWN` | `true` | Render AI replies as Markdown |
| `sound` | `AI_CHATBOX_SOUND` | `true` | Play a ping when the AI replies |
| `sound_volume` | `AI_CHATBOX_SOUND_VOLUME` | `0.3` | Volume — `0.0` silent, `1.0` full |

---

## Routes

The package registers three routes under the configured prefix:

```
GET  /ai-chatbox/health    Ping the AI service — used by the health check before opening
POST /ai-chatbox/message   Send a user message and receive an AI reply
POST /ai-chatbox/clear     Clear the server-side session conversation history
```

---

## AI Provider Examples

Any OpenAI-compatible API works — just update the `.env` values.

### Ollama (local)

```env
AI_CHATBOX_API_URL=http://localhost:11434/v1/chat/completions
AI_CHATBOX_API_TOKEN=ollama
AI_CHATBOX_API_MODEL=phi3:mini
AI_CHATBOX_SSRF_PROTECTION=false
```

### Ollama Cloud

```env
AI_CHATBOX_API_URL=https://ollama.com/api/chat
AI_CHATBOX_API_TOKEN=your_ollama_api_key
AI_CHATBOX_API_MODEL=gpt-oss:120b
```

### OpenAI

```env
AI_CHATBOX_API_URL=https://api.openai.com/v1/chat/completions
AI_CHATBOX_API_TOKEN=sk-...
AI_CHATBOX_API_MODEL=gpt-4o
```

### Groq

```env
AI_CHATBOX_API_URL=https://api.groq.com/openai/v1/chat/completions
AI_CHATBOX_API_TOKEN=gsk_...
AI_CHATBOX_API_MODEL=llama-3.3-70b-versatile
```

### OpenRouter

```env
AI_CHATBOX_API_URL=https://openrouter.ai/api/v1/chat/completions
AI_CHATBOX_API_TOKEN=sk-or-...
AI_CHATBOX_API_MODEL=mistralai/mistral-7b-instruct
```

### LM Studio (local)

```env
AI_CHATBOX_API_URL=http://localhost:1234/v1/chat/completions
AI_CHATBOX_API_TOKEN=lm-studio
AI_CHATBOX_API_MODEL=your-loaded-model-name
AI_CHATBOX_SSRF_PROTECTION=false
```

> Start LM Studio, load a model, and enable the **Local Server** tab. The model name must match exactly what LM Studio displays (e.g. `lmstudio-community/Meta-Llama-3-8B-Instruct-GGUF`).

---

## Security

### SSRF Protection

The health check endpoint pings the configured `api_url` to verify the AI service is reachable. To prevent Server-Side Request Forgery (SSRF) attacks, requests to private and reserved IP ranges are blocked by default (`localhost`, `10.x`, `172.16.x`, `192.168.x`, `169.254.x`).

```env
# Production — leave enabled (default)
AI_CHATBOX_SSRF_PROTECTION=true

# Local development (e.g. Ollama on localhost) — disable
AI_CHATBOX_SSRF_PROTECTION=false
```

### CORS

The package registers a CORS middleware (`ai-chatbox.cors`) that restricts chatbox endpoints to requests originating from your application's own URL. Cross-origin requests from other domains are rejected with `403`.

To permit additional origins, publish the config and update `allowed_origins`:

```php
'allowed_origins' => [
    env('APP_URL', 'http://localhost'),
    'https://other-allowed-origin.example.com',
],
```

### Authentication

The package does not enforce authentication by default, allowing guest users to interact with the chatbox. To restrict access to authenticated users, publish the config and add your auth middleware:

```php
'middleware' => ['web', 'throttle:20,1', 'ai-chatbox.cors', 'auth'],
// or Sanctum:
'middleware' => ['web', 'throttle:20,1', 'ai-chatbox.cors', 'auth:sanctum'],
```

### Sensitive Data

Conversation history is persisted in the browser's `localStorage` by default. If your users may discuss sensitive information, switch to `sessionStorage`, which is automatically cleared when the tab is closed:

```env
AI_CHATBOX_STORAGE=session
```

> **Note:** Do not discuss passwords, tokens, or other secrets in the chatbox regardless of the storage driver — any JavaScript running on the page can access browser storage.

---

## Response Language

The `language` config forces the AI to always reply in the specified language, regardless of what language the user writes in. It works at two levels:

1. **System prompt** — `{language}` in the system prompt is replaced with the configured value at request time
2. **Per-message reminder** — `[Important: Reply in {language} only.]` is appended to every user message, which improves compliance on smaller models like `phi3:mini`

```env
AI_CHATBOX_LANGUAGE=English
AI_CHATBOX_LANGUAGE="Bahasa Malaysia"
AI_CHATBOX_LANGUAGE=French
AI_CHATBOX_LANGUAGE=Arabic
```

Leave empty to let the model reply in whatever language it chooses:
```env
AI_CHATBOX_LANGUAGE=
```

To customise the system prompt while keeping the language placeholder:
```env
AI_CHATBOX_SYSTEM_PROMPT="You are a customer support agent for Acme Corp. Always reply in {language}."
```

---

## Widget Position

| Value | Location |
|---|---|
| `bottom-right` | Bottom-right corner *(default)* |
| `bottom-left` | Bottom-left corner |
| `top-right` | Top-right corner |
| `top-left` | Top-left corner |

For `top-*` positions the chat window opens downward; for `bottom-*` it opens upward.

```env
AI_CHATBOX_POSITION=bottom-left
```

---

## Health Check

When enabled (default), clicking the chat button first sends a lightweight ping to the AI service. The window only opens if the service is reachable. If unreachable, a toast message is shown near the button for 4 seconds.

Disable for trusted internal environments where the service is always online:

```env
AI_CHATBOX_HEALTH_CHECK=false
```

---

## Markdown Rendering

AI replies are rendered as Markdown by default using [marked.js](https://marked.js.org/) and [DOMPurify](https://github.com/cure53/DOMPurify), both **bundled into the widget's JavaScript asset** — no CDN calls or external scripts required. Supported elements:

- Bold, italic, strikethrough
- Bullet and numbered lists
- Inline code and fenced code blocks (dark theme)
- Blockquotes, headings (H1–H3), tables, horizontal rules
- Links

Disable to display replies as plain text:

```env
AI_CHATBOX_MARKDOWN=false
```

---

## Dark Mode

The widget automatically adapts to the user's OS/browser dark mode preference via `prefers-color-scheme: dark`. No configuration required.

---

## Customising the Widget

**Publish views** to override the Blade template:

```bash
php artisan vendor:publish --tag=ai-chatbox-views
```

Published to `resources/views/vendor/ai-chatbox/chatbox.blade.php`.

---

## Frontend Architecture

The widget frontend is built with **Vue 3** (Composition API) and compiled to a self-contained IIFE bundle using **Vite**. The bundle includes Vue, `axios`, `marked`, and `DOMPurify` — your Laravel application requires no Node.js tooling.

```
src/resources/js/
├── app.js                   # Entry point — mounts Vue to #ai-chatbox-app
└── components/
    └── AiChatbox.vue        # Single-file component (template + logic + styles)
```

To rebuild the frontend assets (package contributors only):

```bash
npm install
npm run build   # outputs to src/resources/assets/
```

---

## Troubleshooting

If the chatbox shows an offline toast or requests fail, check `storage/logs/laravel.log` for an error code (`E01`–`E19`). Full reference: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

---

## Testing

```bash
composer test
```

The test suite covers all backend behaviour — controller responses, error classification, session history, CORS middleware, SSRF protection, and health check logic — using PHPUnit 11 and Orchestra Testbench.

---

## License

MIT — see [LICENSE](LICENSE) for details.
