# laravel-ai-chatbox

A configurable AI chatbox widget for Laravel. Drop it into any project via Composer — no build tools required.

Messages are proxied through your Laravel backend to any OpenAI-compatible API, so your API token is never exposed to the browser.

Defaults to **Ollama** running locally (e.g. on WSL) with the `phi3:mini` model.

---

## Requirements

- PHP 8.1+
- Laravel 10 / 11 / 12
- jQuery (optional — falls back to native `fetch`)

---

## Installation

### 1. Install via Composer

**From Packagist (once published):**
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

# Publish config (optional — to override defaults)
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
| `timeout` | — | `30` | Seconds before the API request times out |

### Response Language & System Prompt

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `language` | `AI_CHATBOX_LANGUAGE` | `English` | Language the AI must always reply in — leave empty to let the model decide |
| `system_prompt` | `AI_CHATBOX_SYSTEM_PROMPT` | `You are a helpful assistant...` | System message sent on every request — use `{language}` as a placeholder for the configured language |

### Response Tuning

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `temperature` | `AI_CHATBOX_TEMPERATURE` | `0.7` | Creativity — `0.0` deterministic, `1.0` creative |
| `max_tokens` | `AI_CHATBOX_MAX_TOKENS` | `null` | Max reply length — omit to let the model decide |

### Conversation History

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `history_enabled` | `AI_CHATBOX_HISTORY` | `true` | Send previous messages for context |
| `history_limit` | `AI_CHATBOX_HISTORY_LIMIT` | `10` | Max user+assistant pairs to keep in session |

### Routes & Security

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `route_prefix` | — | `ai-chatbox` | URL prefix for all chatbox routes |
| `middleware` | — | `['web', 'throttle:20,1']` | Middleware applied to all routes |
| `rate_limit` | `AI_CHATBOX_RATE_LIMIT` | `20` | Max requests per window per IP |
| `rate_window` | `AI_CHATBOX_RATE_WINDOW` | `1` | Rate limit window in minutes |
| `health_check` | `AI_CHATBOX_HEALTH_CHECK` | `true` | Ping the AI service before opening the chatbox |

### Widget Appearance

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `title` | `AI_CHATBOX_TITLE` | `AI Assistant` | Header title |
| `placeholder` | — | `Type your message...` | Input placeholder text |
| `theme_color` | — | `#4f46e5` | Primary colour (applied via CSS variable) |
| `position` | `AI_CHATBOX_POSITION` | `bottom-right` | Widget position — see below |
| `greeting` | `AI_CHATBOX_GREETING` | `Hi! How can I help you today?` | Opening message shown on first open — leave empty to disable |

### Features

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `markdown` | `AI_CHATBOX_MARKDOWN` | `true` | Render AI replies as Markdown |
| `sound` | `AI_CHATBOX_SOUND` | `true` | Play a ping when the AI replies |
| `sound_volume` | `AI_CHATBOX_SOUND_VOLUME` | `0.4` | Volume — `0.0` silent, `1.0` full |

---

## Routes

The package registers three routes under the configured prefix:

```
GET  /ai-chatbox/health    Check if the AI service is reachable
POST /ai-chatbox/message   Send a message to the AI
POST /ai-chatbox/clear     Clear the session conversation history
```

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

Supported values for `position`:

| Value | Location |
|---|---|
| `bottom-right` | Bottom-right corner (default) |
| `bottom-left` | Bottom-left corner |
| `top-right` | Top-right corner |
| `top-left` | Top-left corner |

For `top-*` positions the chat window opens downward; for `bottom-*` it opens upward.

```env
AI_CHATBOX_POSITION=bottom-left
```

---

## Health Check

When enabled (default), clicking the chat button first sends a lightweight ping to the AI service base URL. The window only opens if the service is reachable. If unreachable, a toast message is shown near the button for 4 seconds.

Disable for local development or trusted internal environments:

```env
AI_CHATBOX_HEALTH_CHECK=false
```

---

## Markdown Rendering

AI replies are rendered as Markdown by default using [marked.js](https://marked.js.org/) and [DOMPurify](https://github.com/cure53/DOMPurify), both loaded from CDN. Supported elements:

- Bold, italic, strikethrough
- Bullet and numbered lists
- Inline code and fenced code blocks (dark theme)
- Blockquotes, headings, tables, horizontal rules
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

## Using with Other AI Providers

Any OpenAI-compatible API works — just swap the `.env` values.

**Ollama — different model:**
```env
AI_CHATBOX_API_URL=http://localhost:11434/v1/chat/completions
AI_CHATBOX_API_TOKEN=ollama
AI_CHATBOX_API_MODEL=llama3
```

**OpenAI:**
```env
AI_CHATBOX_API_URL=https://api.openai.com/v1/chat/completions
AI_CHATBOX_API_TOKEN=sk-...
AI_CHATBOX_API_MODEL=gpt-4o
```

**Azure OpenAI:**
```env
AI_CHATBOX_API_URL=https://YOUR_RESOURCE.openai.azure.com/openai/deployments/YOUR_DEPLOYMENT/chat/completions?api-version=2024-02-01
AI_CHATBOX_API_TOKEN=YOUR_AZURE_KEY
AI_CHATBOX_API_MODEL=gpt-4o
```

---

## License

MIT
