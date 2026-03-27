# laravel-ai-chatbox

[![Tests](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml/badge.svg)](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/syafiq-unijaya/laravel-ai-chatbox.svg?label=packagist)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Total Downloads](https://img.shields.io/packagist/dt/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![PHP](https://img.shields.io/packagist/php-v/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-42b883?logo=vue.js&logoColor=white)](https://vuejs.org)
[![License](https://img.shields.io/packagist/l/syafiq-unijaya/laravel-ai-chatbox.svg)](LICENSE)

A drop-in AI chatbox widget for Laravel — one Blade directive, zero build tools required in your application. Choose your frontend: **Vue 3** (default), **vanilla JS** (no framework), **Livewire + Alpine.js**, or **API-only** to plug in your own React/Vue/Svelte component.

Messages are proxied through your Laravel backend to any **OpenAI-compatible API**. Defaults to **Ollama** running locally with the `phi3:mini` model. Includes a full **RAG (Retrieval-Augmented Generation)** system with an admin UI for uploading documents the AI can reference.

---

## Features

- **One-line integration** — drop `@aichatbox` anywhere in a Blade layout
- **Four frontend drivers** — Vue 3 (default), vanilla JS (no framework), Livewire + Alpine.js, or API-only (`none`) for React/Svelte/custom builds
- **Universal AI support** — Ollama (local & cloud), OpenAI, Groq, OpenRouter, LM Studio, or any OpenAI-compatible endpoint
- **RAG (Retrieval-Augmented Generation)** — upload `.md`/`.txt` documents; the chatbox retrieves relevant context automatically on every message
- **Knowledge Base UI** — document manager at `/ai-chatbox/rag` with upload/reprocess loading states, status badges, and error details
- **Admin Dashboard** — at `/ai-chatbox/admin`; shows RAG stats, memory stats, all config values, named providers, and a live diagnostic panel with errors, warnings, and notices for misconfigured settings
- **Conversations viewer** — at `/ai-chatbox/admin/conversations`; async-paginated list of all conversation threads with click-to-open message modal showing full chat history in chat-bubble layout
- **Configuration diagnostics** — the admin dashboard validates every config group at load time: missing tables, insecure settings, SSRF conflicts, chunk size issues, empty API tokens, and more
- **Real-time token streaming** — AI replies stream token-by-token via Server-Sent Events (SSE) with a blinking cursor
- **Markdown rendering** — AI replies rendered with `marked.js` + `DOMPurify` (bundled in Vue driver; CDN-loaded in blade/livewire drivers)
- **Conversation threads** — each conversation gets a unique UUID thread; start a fresh thread any time without losing context of others
- **AI Provider Facade** — call `AI::provider('openai')->chat($prompt)` or `AI::chat($prompt)` from anywhere in your app; fluent API to switch model, temperature, and system prompt at runtime
- **Named providers** — configure multiple AI providers (`ollama`, `openai`, `groq`, `lmstudio`) in one config file; each inherits global defaults and only overrides what it needs
- **Database memory driver** — optionally persist conversation threads and messages in `ai_chatbox_conversations` / `ai_chatbox_messages` tables; history survives PHP session expiry and is queryable
- **Session memory** — server-side history per thread with configurable turn limit; context is automatically sent back to the AI on every message
- **Token-based context trimming** — history is trimmed oldest-first by estimated token count to keep requests within your model's context window
- **Message storage** — chat bubbles persist across page refresh via `localStorage` or `sessionStorage`, scoped per user and per app
- **Health check** — pings the AI service before opening; shows an offline toast if unreachable
- **Sound notifications** — Web Audio API ping on AI reply, no audio file needed
- **Dark mode** — `auto` (follows OS), `light`, or `dark`; applies to all admin pages and the Knowledge Base UI
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

> No Node.js or npm required in your application — the Vue bundle is pre-compiled and published as a static asset. The `blade` and `livewire` drivers require no compiled assets at all.

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

If you plan to use **RAG**, run the package migrations:

```bash
php artisan migrate
```

> Alternatively, publish the migration files first if you want to review or modify them:
> ```bash
> php artisan vendor:publish --tag=ai-chatbox-migrations
> php artisan migrate
> ```

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

The chatbox appears as a floating button on every page that includes the layout. Use `@aichatboxConfig` instead if you are bringing your own frontend (React, Svelte, etc.) — it outputs only `window.AiChatboxConfig` with no widget HTML.

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

### Conversation History & Threads

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `history_enabled` | `AI_CHATBOX_HISTORY` | `true` | Send previous messages for context |
| `history_limit` | `AI_CHATBOX_HISTORY_LIMIT` | `50` | Max user+assistant pairs kept per thread in session |
| `context_token_limit` | `AI_CHATBOX_CONTEXT_TOKENS` | `4000` | Max estimated tokens of history to include per request — trims oldest pairs first |

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

### Memory & Storage

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `memory_driver` | `AI_CHATBOX_MEMORY_DRIVER` | `session` | Server-side history driver — `session` (PHP session) or `database` (Eloquent, survives session expiry) |
| `storage` | `AI_CHATBOX_STORAGE` | `local` | Browser storage — `local` (persists across sessions) or `session` (clears on tab close) |

### Widget Appearance

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `frontend` | `AI_CHATBOX_FRONTEND` | `vue` | UI driver — `vue`, `blade`, `livewire`, or `none` (see [Frontend Drivers](#frontend-drivers)) |
| `title` | `AI_CHATBOX_TITLE` | `AI Assistant` | Header title |
| `placeholder` | — | `Type your message...` | Input placeholder text |
| `theme_color` | — | `#4f46e5` | Primary colour (CSS variable) |
| `color_scheme` | `AI_CHATBOX_COLOR_SCHEME` | `auto` | Color scheme for all admin pages — `auto` (follows OS), `light`, or `dark` |
| `position` | `AI_CHATBOX_POSITION` | `bottom-right` | Widget position — `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `greeting` | `AI_CHATBOX_GREETING` | `Hi! How can I help you today?` | Opening message on first open — leave empty to disable |

### Features

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `markdown` | `AI_CHATBOX_MARKDOWN` | `true` | Render AI replies as Markdown |
| `sound` | `AI_CHATBOX_SOUND` | `true` | Play a ping when the AI replies |
| `sound_volume` | `AI_CHATBOX_SOUND_VOLUME` | `0.3` | Volume — `0.0` silent, `1.0` full |
| `stream` | `AI_CHATBOX_STREAM` | `true` | Stream AI replies token-by-token via SSE |

### RAG (Retrieval-Augmented Generation)

| Key | `.env` variable | Default | Description |
|---|---|---|---|
| `rag_enabled` | `AI_CHATBOX_RAG` | `false` | Master switch — enable RAG context injection |
| `rag_embedding_url` | `AI_CHATBOX_EMBEDDING_URL` | `http://localhost:11434/v1/embeddings` | Embedding API endpoint |
| `rag_embedding_model` | `AI_CHATBOX_EMBEDDING_MODEL` | `nomic-embed-text` | Embedding model name |
| `rag_top_k` | `AI_CHATBOX_RAG_TOP_K` | `3` | Number of chunks retrieved per query |
| `rag_chunk_size` | `AI_CHATBOX_RAG_CHUNK_SIZE` | `500` | Target chunk size in tokens (~4 chars/token) |
| `rag_chunk_overlap` | `AI_CHATBOX_RAG_CHUNK_OVERLAP` | `50` | Overlap between consecutive chunks in tokens |
| `rag_similarity_threshold` | `AI_CHATBOX_RAG_THRESHOLD` | `0.2` | Minimum cosine similarity score `0.0`–`1.0` |
| `rag_context_prompt` | `AI_CHATBOX_RAG_CONTEXT_PROMPT` | *(see below)* | Instruction prepended to retrieved chunks — use `{chunks}` as placeholder |
| `rag_processing_timeout` | `AI_CHATBOX_RAG_PROCESSING_TIMEOUT` | `0` | Max seconds for a single upload/reprocess — `0` = no limit (recommended for local models) |
| `rag_admin_middleware` | — | `['web', 'auth']` | Middleware for all admin and Knowledge Base pages (publish config to change — add a role/permission middleware for tighter access control) |

### Named Providers

Named providers are configured under the `providers` key. Each entry only needs the keys that differ from the global defaults above — all other settings (`temperature`, `system_prompt`, `language`, etc.) are inherited automatically.

```php
// config/ai-chatbox.php
'providers' => [
    'ollama'   => ['api_url' => env('OLLAMA_URL', '...'), 'api_token' => env('OLLAMA_TOKEN', 'ollama'),   'api_model' => env('OLLAMA_MODEL', 'phi3:mini')],
    'openai'   => ['api_url' => env('OPENAI_URL', '...'), 'api_token' => env('OPENAI_API_KEY', ''),       'api_model' => env('OPENAI_MODEL', 'gpt-4o')],
    'groq'     => ['api_url' => env('GROQ_URL',   '...'), 'api_token' => env('GROQ_API_KEY', ''),         'api_model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile')],
    'lmstudio' => ['api_url' => env('LMSTUDIO_URL','...'), 'api_token' => env('LMSTUDIO_TOKEN','lmstudio'),'api_model' => env('LMSTUDIO_MODEL','local-model')],
],
```

The corresponding `.env` keys for each built-in named provider:

| Provider | URL variable | Token variable | Model variable |
|---|---|---|---|
| `ollama` | `OLLAMA_URL` | `OLLAMA_TOKEN` | `OLLAMA_MODEL` |
| `openai` | `OPENAI_URL` | `OPENAI_API_KEY` | `OPENAI_MODEL` |
| `groq` | `GROQ_URL` | `GROQ_API_KEY` | `GROQ_MODEL` |
| `lmstudio` | `LMSTUDIO_URL` | `LMSTUDIO_TOKEN` | `LMSTUDIO_MODEL` |

> **Note:** These are distinct from the `AI_CHATBOX_*` variables used by the chatbox widget. `AI_CHATBOX_API_URL` controls the widget; `OLLAMA_URL`/`OPENAI_API_KEY`/etc. control the named facade providers.

---

## Routes

The package registers the following routes under the configured prefix:

```
GET    /ai-chatbox/health                           Ping the AI service (health check)
POST   /ai-chatbox/message                          Send a message, receive a full JSON reply
POST   /ai-chatbox/stream                           Send a message, stream SSE token-by-token reply
POST   /ai-chatbox/clear                            Clear server-side session history for a thread

GET    /ai-chatbox/rag                              Knowledge Base — list indexed documents      [auth]
POST   /ai-chatbox/rag                              Knowledge Base — upload and index a document [auth]
DELETE /ai-chatbox/rag/{id}                         Knowledge Base — delete a document           [auth]
POST   /ai-chatbox/rag/{id}/reprocess               Knowledge Base — re-chunk and re-embed       [auth]

GET    /ai-chatbox/admin                            Admin dashboard                              [auth]
GET    /ai-chatbox/admin/conversations              Conversations list                           [auth]
GET    /ai-chatbox/admin/conversations/data         Conversations JSON (paginated)               [auth]
GET    /ai-chatbox/admin/conversations/{id}/messages  Messages for a conversation (JSON)         [auth]
```

> Admin and Knowledge Base routes require an authenticated user by default (`rag_admin_middleware`). Publish the config to customise — see [Protecting the admin UI](#protecting-the-admin-ui).

---

## Frontend Drivers

The `frontend` config key controls which UI `@aichatbox` renders. All drivers share the same backend API routes and the same `window.AiChatboxConfig` object — only the widget layer changes.

```env
AI_CHATBOX_FRONTEND=vue       # Vue 3 widget (default)
AI_CHATBOX_FRONTEND=blade     # Vanilla JS widget, no framework
AI_CHATBOX_FRONTEND=livewire  # Alpine.js widget via Livewire
AI_CHATBOX_FRONTEND=none      # No widget — API + config only
```

| Driver | Widget | Streaming | JS dependency | Assets required |
|---|---|---|---|---|
| `vue` | Vue 3 SFC | SSE | bundled `chatbox.js` | `php artisan vendor:publish --tag=ai-chatbox-assets` |
| `blade` | Vanilla JS | SSE | none (marked.js from CDN if markdown on) | same |
| `livewire` | Alpine.js | SSE | Alpine.js (bundled with Livewire 3) | same |
| `none` | — | your choice | none | not required |

---

### `vue` — Vue 3 (default)

No extra setup. The pre-compiled Vue bundle mounts to `#ai-chatbox-app` and reads `window.AiChatboxConfig`.

```env
AI_CHATBOX_FRONTEND=vue
```

---

### `blade` — Vanilla JS

A self-contained widget with no framework dependency. Uses the same CSS as the Vue driver (identical IDs and class names), so all appearance config options apply equally.

```env
AI_CHATBOX_FRONTEND=blade
```

If `AI_CHATBOX_MARKDOWN=true`, `marked.js` and `DOMPurify` are loaded from the jsDelivr CDN. Set `AI_CHATBOX_MARKDOWN=false` to remove the CDN dependency entirely.

---

### `livewire` — Livewire + Alpine.js

Renders an Alpine.js chat widget. Livewire 3 bundles Alpine.js automatically, so no additional scripts are needed.

```env
AI_CHATBOX_FRONTEND=livewire
```

The package also registers a Livewire component, so you can mount the widget independently anywhere in your app:

```blade
<livewire:ai-chatbox />
```

> The Livewire component renders the same Alpine.js view and requires `window.AiChatboxConfig` to be present on the page. If you use `<livewire:ai-chatbox />` without `@aichatbox`, add `@aichatboxConfig` to your layout to inject the config.

```blade
{{-- layout --}}
@aichatboxConfig
...
{{-- somewhere else in the page --}}
<livewire:ai-chatbox />
```

---

### `none` — API-only / custom frontend

Outputs only `window.AiChatboxConfig`. No widget HTML or scripts are rendered. Use this when you are building your own frontend in React, Svelte, or any other framework.

```env
AI_CHATBOX_FRONTEND=none
```

Or use the explicit `@aichatboxConfig` directive — same result, regardless of the `frontend` setting:

```blade
@aichatboxConfig
```

**`window.AiChatboxConfig` reference:**

```js
window.AiChatboxConfig = {
    url,            // POST /ai-chatbox/message  — full JSON reply
    streamUrl,      // POST /ai-chatbox/stream   — SSE token stream
    clearUrl,       // POST /ai-chatbox/clear    — clear session history
    healthUrl,      // GET  /ai-chatbox/health   — liveness check
    token,          // CSRF token
    stream,         // boolean — whether streaming is enabled
    healthCheck,    // boolean
    title,          // widget title string
    placeholder,    // input placeholder
    greeting,       // opening message
    markdown,       // boolean
    sound,          // boolean
    soundVolume,    // 0.0–1.0
    position,       // 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
    storageKey,     // localStorage/sessionStorage key (scoped per app + user)
    storageType,    // 'local' | 'session'
    offlineMessage, // toast text when health check fails
    themeColor,     // primary hex colour
};
```

All API endpoints accept `{ message, thread_id }` as JSON and return `{ reply }` (POST) or SSE `data: {"token":"..."}` events ending with `data: [DONE]` (stream).

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

## AI Provider Facade

The `AI` facade lets you call AI providers directly from your PHP code — controllers, jobs, commands, or services — without touching `.env` or the chatbox widget.

### Basic usage

```php
use SyafiqUnijaya\AiChatbox\AI;

// Use the default provider (AI_CHATBOX_* env vars)
$reply = AI::chat('Summarise this document: ...');

// Use a named provider
$reply = AI::provider('openai')->chat('Translate to French: ...');
$reply = AI::provider('groq')->chat('Write a test for this function...');
$reply = AI::provider('ollama')->chat('What is the capital of France?');
```

### Fluent modifiers

Each modifier returns a **new immutable instance** — the original is never changed.

```php
$reply = AI::provider('openai')
    ->withModel('gpt-4o-mini')
    ->withTemperature(0.2)
    ->withSystemPrompt('You are a JSON-only responder. Return only valid JSON.')
    ->withMaxTokens(512)
    ->withTimeout(60)
    ->chat($prompt);
```

| Method | Description |
|---|---|
| `->withModel(string $model)` | Override the model for this call |
| `->withSystemPrompt(string $prompt)` | Override the system prompt |
| `->withLanguage(string $lang)` | Override the reply language |
| `->withTemperature(float $temp)` | Override creativity (`0.0`–`1.0`) |
| `->withMaxTokens(?int $tokens)` | Override max reply length (`null` = model default) |
| `->withTimeout(int $seconds)` | Override the HTTP timeout |
| `->withConfig(array $overrides)` | Merge arbitrary config overrides |

### Streaming via facade

```php
// With a callback (synchronous)
AI::provider('openai')->stream($prompt, [], function (string $token) {
    echo $token;
    ob_flush(); flush();
});

// Without a callback — returns a Closure you invoke later
$reader = AI::provider('default')->stream($prompt);
$reader(fn(string $token) => print($token));
```

### Env vars — widget vs. facade

The chatbox widget and the facade providers use separate env vars:

| Purpose | Env variables |
|---|---|
| **Chatbox widget** (default) | `AI_CHATBOX_API_URL`, `AI_CHATBOX_API_TOKEN`, `AI_CHATBOX_API_MODEL` |
| **Named facade providers** | `OLLAMA_URL`/`OLLAMA_TOKEN`/`OLLAMA_MODEL`, `OPENAI_URL`/`OPENAI_API_KEY`/`OPENAI_MODEL`, etc. |

`AI::chat()` and `AI::provider('default')` both use the `AI_CHATBOX_*` widget config. `AI::provider('openai')` uses the `OPENAI_*` keys.

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

## Conversation Threads

Each time a user first opens the chatbox, a **UUID v4 thread ID** is generated in the browser and stored in `localStorage` (or `sessionStorage`). This ID is sent with every message request and used to scope the server-side session history — so multiple independent conversations never share context.

**New conversation** — the pencil icon (✏️) in the chat header starts a fresh thread. A new UUID is generated, the previous thread's server-side history is left to expire naturally, and the client display is reset. This is distinct from the trash icon which clears the *current* thread's history.

```
Thread A (UUID: 550e8400...)  →  session key: ai_chatbox_history_550e8400...
Thread B (UUID: 6ba7b810...)  →  session key: ai_chatbox_history_6ba7b810...
```

Thread IDs survive page refresh — the same conversation context is restored when the user returns.

---

## Session Memory

When `history_enabled` is `true` (default), every user message and AI reply is stored in the PHP session under the current thread's key and sent back to the AI on every subsequent request, giving the model memory of the full conversation.

```env
AI_CHATBOX_HISTORY=true
AI_CHATBOX_HISTORY_LIMIT=50    # max message pairs kept per thread
```

Set `AI_CHATBOX_HISTORY=false` to make each message completely standalone (no context sent):

```env
AI_CHATBOX_HISTORY=false
```

---

## Real-Time Token Streaming

When `AI_CHATBOX_STREAM=true` (default), AI replies are streamed token-by-token via **Server-Sent Events (SSE)**. The user sees each word appear as it is generated, with a blinking `▋` cursor while the response is in progress.

```env
AI_CHATBOX_STREAM=true    # stream token-by-token (default)
AI_CHATBOX_STREAM=false   # wait for the full reply before displaying
```

**How it works:**

1. The frontend calls `POST /ai-chatbox/stream` using the Fetch API + `ReadableStream`
2. The server proxies the AI API response using Guzzle's `'stream' => true` option and reads 1024-byte chunks
3. Each token is emitted as an SSE event: `data: {"token":"Hello"}`
4. The stream ends with `data: [DONE]`
5. Markdown rendering is applied to the completed reply (not during streaming)

**Server requirements:**

```
Nginx: add  proxy_buffering off;  to your server block
       (the package sets X-Accel-Buffering: no automatically)
PHP:   output_buffering = Off  in php.ini for best results
```

---

## RAG — Retrieval-Augmented Generation

RAG lets the chatbox answer questions about **your own data** — internal documents, company FAQs, product knowledge bases — without fine-tuning any model.

```
User asks a question
     ↓
Query is embedded → cosine similarity search across all indexed chunks
     ↓
Top-K most relevant chunks are injected as a system message
     ↓
AI answers with your knowledge-base context
```

### Quick start

**1. Enable RAG in `.env`:**

```env
AI_CHATBOX_RAG=true
AI_CHATBOX_EMBEDDING_URL=http://localhost:11434/v1/embeddings
AI_CHATBOX_EMBEDDING_MODEL=nomic-embed-text
```

**2. Run the migration:**

```bash
php artisan migrate
```

**3. Upload documents at `/ai-chatbox/rag`** (requires an authenticated user by default).

That's it — every subsequent chat message will automatically retrieve and inject relevant context.

---

### Embedding providers

RAG uses a **separate embedding API** (different from the chat API). Any provider that exposes an `/embeddings` endpoint works.

| Provider | `AI_CHATBOX_EMBEDDING_URL` | `AI_CHATBOX_EMBEDDING_MODEL` |
|---|---|---|
| Ollama (local) | `http://localhost:11434/v1/embeddings` | `nomic-embed-text` |
| Ollama (local) | `http://localhost:11434/v1/embeddings` | `mxbai-embed-large` |
| LM Studio | `http://localhost:1234/v1/embeddings` | your loaded embedding model |
| OpenAI | `https://api.openai.com/v1/embeddings` | `text-embedding-3-small` |
| OpenAI | `https://api.openai.com/v1/embeddings` | `text-embedding-3-large` |

> **Ollama:** Pull an embedding model first:
> ```bash
> ollama pull nomic-embed-text
> ```

---

### Document formats

| Format | Extension | Notes |
|---|---|---|
| Plain text | `.txt` | Chunked directly |
| Markdown | `.md` | Chunked directly — heading structure is preserved |

Maximum upload size: **10 MB** per file.

---

### Admin Dashboard

Visit **`/ai-chatbox/admin`** (authenticated users only) to see:

- **Stat cards** — RAG document/chunk counts, conversation and message counts (database driver)
- **Configuration diagnostics** — a live panel that checks every config group and reports errors (red), warnings (amber), and notices (blue) at page load. All checks pass → a green "All configuration checks passed" banner is shown.
- **All config values** — grouped by section (AI API, Response, Streaming, Widget, Security, RAG, Memory) with their resolved values
- **Named providers** — shows all configured named providers and their settings
- **Environment** — Laravel, PHP, app env, and debug mode

Diagnostic checks include: missing API URL/token/model, placeholder tokens, APP_DEBUG in production, SSRF conflicts with local URLs, open CORS origins, missing database tables, invalid RAG chunk settings, failed/un-embedded documents, weak admin middleware, frontend driver not installed, and more.

---

### Knowledge Base UI

Visit **`/ai-chatbox/rag`** in your browser (authenticated users only).

| Action | Description |
|---|---|
| **Upload** | Select a `.md` or `.txt` file, optionally set a display title, click *Upload & Index* — a full-page overlay with spinner shows while the file is being processed |
| **Reprocess** | Re-chunk and re-embed an existing document (e.g. after changing chunk size or embedding URL) — the row shows a spinning badge while processing |
| **Delete** | Remove the document and all its chunks permanently (confirmation required) |

The page shows each document's indexing status (`Pending` → `Processing` → `Ready` / `Failed`), chunk count, and an expandable error message if embedding failed.

---

### Conversations Viewer

Visit **`/ai-chatbox/admin/conversations`** (or click the Conversations card on the admin dashboard) to browse all recorded conversations (requires `memory_driver=database`).

- **Async-paginated table** — loads conversation rows via JSON without full page reloads; shows thread ID, user name (resolved from the User model), message count, last message preview, and last active time
- **Click a row** to open a modal showing the full message history in a chat-bubble layout (user messages on the right, assistant on the left)
- **Guest sessions** — rows and modal labels show "Guest" when no user is associated

---

### How chunking works

Documents are split into overlapping chunks before embedding:

```
chunk_size   = AI_CHATBOX_RAG_CHUNK_SIZE   (default 500 tokens ≈ 2 000 chars)
chunk_overlap = AI_CHATBOX_RAG_CHUNK_OVERLAP (default 50 tokens ≈ 200 chars)
```

The chunker:
1. Splits on paragraph boundaries (two or more blank lines)
2. Falls back to sentence boundaries (`. ! ?`) for oversized paragraphs
3. Carries over the last `chunk_overlap` characters into the next chunk so context is not lost at boundaries

---

### How retrieval works

On every chat message:

1. The user's message is embedded using the same embedding model
2. Cosine similarity is computed **in PHP** against every chunk stored in the database
3. Chunks below `rag_similarity_threshold` (default `0.2`) are discarded
4. The top `rag_top_k` (default `3`) chunks are injected into the AI's context as a system message using the `rag_context_prompt` template (the `{chunks}` placeholder is replaced with the retrieved text)
5. The AI answers as normal, but now has access to your private data

The default context prompt instructs the model to treat the retrieved chunks as its **primary source** and say "I don't have that information in my knowledge base" if the answer isn't found there. Customise via `.env`:

```env
AI_CHATBOX_RAG_CONTEXT_PROMPT="Use only the following context to answer:\n\n{chunks}\n\nDo not use any other knowledge."
```

Leave `{chunks}` in the prompt — the retrieved text is inserted there. If `{chunks}` is absent, the chunks are appended after the prompt text.

> **Performance note:** Similarity is computed in PHP for simplicity and works well for up to a few thousand chunks. For very large knowledge bases, publish the migrations and switch to a database with native vector support (e.g. `pgvector` for PostgreSQL).

---

### Protecting the admin UI

By default `rag_admin_middleware` is `['web', 'auth']`, which requires an authenticated Laravel user. This applies to all admin pages (`/ai-chatbox/admin`, `/ai-chatbox/admin/conversations`, and `/ai-chatbox/rag`).

The admin dashboard will show a warning if only the default `[web, auth]` middleware is in use, because any authenticated user can then access it. Add a role or permission middleware for production:

```php
// config/ai-chatbox.php
'rag_admin_middleware' => ['web', 'auth', 'role:admin'],           // Spatie roles
'rag_admin_middleware' => ['web', 'auth', 'can:manage-chatbox'],   // Laravel Gates
'rag_admin_middleware' => ['web', 'auth:sanctum'],                 // Sanctum
```

---

## Token Control

The package provides two layers of control over how many tokens are sent to the AI per request.

### Response tokens (`max_tokens`)

Limits how long the AI's reply can be. Leave unset to let the model decide:

```env
AI_CHATBOX_MAX_TOKENS=512    # short replies
AI_CHATBOX_MAX_TOKENS=2048   # longer replies
# unset = model default
```

### Context tokens (`context_token_limit`)

Limits how much conversation history is included in each request. History is trimmed oldest-pair-first until the estimated token count falls below the threshold. Uses a ~4 characters per token heuristic.

```env
AI_CHATBOX_CONTEXT_TOKENS=4000    # phi3:mini, llama3 8B (default)
AI_CHATBOX_CONTEXT_TOKENS=8000    # llama3 70B, Mixtral
AI_CHATBOX_CONTEXT_TOKENS=32000   # GPT-4o, Claude
AI_CHATBOX_CONTEXT_TOKENS=0       # disable — rely on history_limit only
```

Both limits work together: `history_limit` caps by message count, `context_token_limit` caps by estimated tokens. Whichever is reached first takes effect.

### Creativity (`temperature`)

```env
AI_CHATBOX_TEMPERATURE=0.2   # focused, deterministic
AI_CHATBOX_TEMPERATURE=0.7   # balanced (default)
AI_CHATBOX_TEMPERATURE=1.0   # creative, unpredictable
```

---

## Message Storage

Chat messages are stored on two layers:

| Layer | Driver | Lifetime | Scope |
|---|---|---|---|
| **Browser** | `localStorage` *(default)* | Persists across sessions | Per app + per user + per thread |
| **Browser** | `sessionStorage` | Cleared on tab close | Per app + per user + per thread |
| **Server** | PHP session | Until session expires | Per thread ID |

The browser storage key is automatically scoped to prevent history leaking between different applications or different authenticated users on the same browser.

Switch to session storage for more privacy-sensitive applications:

```env
AI_CHATBOX_STORAGE=session
```

---

## Database Memory Driver

By default, server-side conversation history is kept in the PHP session and expires when the session ends. Switch to the `database` driver to persist history in Eloquent models instead:

```env
AI_CHATBOX_MEMORY_DRIVER=database
```

**Run the migration** (if you haven't already):

```bash
php artisan migrate
```

This creates two tables:

| Table | Purpose |
|---|---|
| `ai_chatbox_conversations` | One row per conversation thread (keyed by UUID) |
| `ai_chatbox_messages` | All messages for each thread (role + content) |

**When to use it:**

- History needs to survive PHP session expiry or server restarts
- You want to query, audit, or export conversation logs
- You run multiple PHP workers and need history shared across all of them

**Switching back:**

```env
AI_CHATBOX_MEMORY_DRIVER=session
```

Existing database records are not deleted — they are simply ignored until you switch back.

---

## Markdown Rendering

AI replies are rendered as Markdown by default using [marked.js](https://marked.js.org/) and [DOMPurify](https://github.com/cure53/DOMPurify). Availability depends on the frontend driver:

| Driver | Source |
|---|---|
| `vue` | Bundled into `chatbox.js` — no CDN calls |
| `blade` | Loaded from jsDelivr CDN at runtime |
| `livewire` | Loaded from jsDelivr CDN at runtime |
| `none` | Your responsibility |

Supported elements:

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

### Chat widget

The widget automatically adapts to the user's OS/browser dark mode preference via `prefers-color-scheme: dark`. No configuration required.

### Admin pages

All admin pages — `/ai-chatbox/admin`, `/ai-chatbox/admin/conversations`, and `/ai-chatbox/rag` — share the same `color_scheme` setting:

| Value | Behaviour |
|---|---|
| `auto` *(default)* | Follows the user's OS/browser preference — updates live when the OS setting changes |
| `light` | Always light |
| `dark` | Always dark |

```env
AI_CHATBOX_COLOR_SCHEME=auto    # OS preference (default)
AI_CHATBOX_COLOR_SCHEME=light   # force light
AI_CHATBOX_COLOR_SCHEME=dark    # force dark
```

> Run `php artisan config:clear` after changing `.env` values for them to take effect.

---

## Customising the Widget

**Publish views** to override any Blade template:

```bash
php artisan vendor:publish --tag=ai-chatbox-views
```

Published to `resources/views/vendor/ai-chatbox/`. The relevant files per driver:

| File | Driver | Purpose |
|---|---|---|
| `chatbox.blade.php` | all | Main dispatcher — routes to the active driver |
| `chatbox-config.blade.php` | all | Outputs `window.AiChatboxConfig` (shared by all drivers) |
| `chatbox-vue.blade.php` | `vue` | CSS link + Vue mount point + JS bundle |
| `chatbox-blade.blade.php` | `blade` | Full vanilla JS widget |
| `livewire/chatbox.blade.php` | `livewire` | Alpine.js widget |

---

## Frontend Architecture

The package ships four frontend implementations that share the same backend API and CSS:

```
src/resources/
├── views/
│   ├── chatbox.blade.php             # Dispatcher — routes to active driver
│   ├── chatbox-config.blade.php      # window.AiChatboxConfig (shared)
│   ├── chatbox-vue.blade.php         # Vue 3 driver
│   ├── chatbox-blade.blade.php       # Vanilla JS driver
│   ├── admin.blade.php               # Admin dashboard (/ai-chatbox/admin)
│   ├── admin-conversations.blade.php # Conversations viewer (/ai-chatbox/admin/conversations)
│   ├── rag.blade.php                 # Knowledge Base UI (/ai-chatbox/rag)
│   └── livewire/
│       └── chatbox.blade.php         # Livewire + Alpine.js driver
├── js/
│   ├── app.js                        # Vite entry — mounts Vue to #ai-chatbox-app
│   └── components/
│       └── AiChatbox.vue             # Vue SFC (template + logic + scoped CSS)
└── assets/
    ├── css/chatbox.css               # Compiled — shared by all drivers
    └── js/chatbox.js                 # Compiled Vue bundle (vue driver only)
```

The compiled assets (`chatbox.css` + `chatbox.js`) are pre-built and committed to the repository — your Laravel application requires no Node.js tooling at runtime.

The `blade` and `livewire` drivers use `chatbox.css` for styling (same HTML class names as the Vue widget) and inline JavaScript. No additional compilation is required.

**Rebuilding the Vue bundle** (package contributors only):

```bash
npm install
npm run build   # outputs to src/resources/assets/
```

The Livewire component is auto-registered by the service provider when `livewire/livewire` is installed:

```php
// Registered automatically:
\Livewire\Livewire::component('ai-chatbox', \SyafiqUnijaya\AiChatbox\Livewire\AiChatbox::class);
```

---

## Troubleshooting

If the chatbox shows an offline toast or requests fail, check `storage/logs/laravel.log` for an error code (`E01`–`E19`). Full reference: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

---

## Testing

```bash
composer test
```

The test suite covers all backend behaviour — controller responses, error classification, session history, conversation thread isolation, token-based context trimming, SSE streaming, RAG document upload/delete/reprocess, RAG context injection into chat, CORS middleware, SSRF protection, health check logic, `AiManager` named provider resolution, `AiProvider` fluent modifiers and immutability, and the `AI` facade — using PHPUnit 11 and Orchestra Testbench.

---

## License

MIT — see [LICENSE](LICENSE) for details.
