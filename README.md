# laravel-ai-chatbox

A configurable AI chatbox widget for Laravel. Drop it into any project via Composer — no build tools required.

The chatbox proxies user messages through your Laravel backend to any OpenAI-compatible API, so your API token is never exposed to the browser.

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
AI_CHATBOX_SYSTEM_PROMPT="You are a helpful assistant."
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

The chatbox appears as a floating button in the bottom-right corner of every page that includes the layout.

---

## Configuration

Publish and edit `config/ai-chatbox.php` to customise all options:

| Key | Default | Description |
|---|---|---|
| `api_url` | `http://localhost:11434/v1/chat/completions` | AI API endpoint |
| `api_token` | `ollama` | Bearer token — Ollama ignores the value but any non-empty string is required |
| `api_model` | `phi3:mini` | Model name passed to the API |
| `system_prompt` | `You are a helpful assistant.` | Prepended system message (leave empty to disable) |
| `route_prefix` | `ai-chatbox` | URL prefix for the backend route |
| `middleware` | `['web']` | Middleware applied to the route |
| `title` | `AI Assistant` | Widget header title |
| `placeholder` | `Type your message...` | Input placeholder text |
| `theme_color` | `#4f46e5` | Primary colour applied via CSS variable |
| `timeout` | `30` | Seconds before the API request times out |

---

## Routes

The package registers one route:

```
POST /ai-chatbox/message    (named: ai-chatbox.message)
```

Change the prefix or restrict access via middleware:

```php
// config/ai-chatbox.php
'route_prefix' => 'chatbot',
'middleware'   => ['web', 'auth'],
```

---

## Customising the widget

Publish views to override the Blade template:

```bash
php artisan vendor:publish --tag=ai-chatbox-views
```

The view is published to `resources/views/vendor/ai-chatbox/chatbox.blade.php`.

---

## Using with other AI providers

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
