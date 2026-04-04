# laravel-ai-chatbox

[![Tests](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml/badge.svg)](https://github.com/syafiq-unijaya/laravel-ai-chatbox/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/syafiq-unijaya/laravel-ai-chatbox.svg?label=packagist)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Total Downloads](https://img.shields.io/packagist/dt/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![PHP](https://img.shields.io/packagist/php-v/syafiq-unijaya/laravel-ai-chatbox.svg)](https://packagist.org/packages/syafiq-unijaya/laravel-ai-chatbox)
[![Laravel](https://img.shields.io/badge/Laravel-10%20|%2011%20|%2012-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![Vue.js](https://img.shields.io/badge/Vue.js-3-42b883?logo=vue.js&logoColor=white)](https://vuejs.org)
[![License](https://img.shields.io/packagist/l/syafiq-unijaya/laravel-ai-chatbox.svg)](LICENSE)

A drop-in AI chatbox widget for Laravel. One Blade directive — no build tools required in your application.

Connect to any **OpenAI-compatible API** including Ollama, OpenAI, Groq, LM Studio, and OpenRouter. Includes real-time token streaming, conversation memory, a full **RAG (Retrieval-Augmented Generation)** system, an admin dashboard, and a PHP facade for calling AI from anywhere in your codebase.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration Reference](#configuration-reference)
- [AI Providers](#ai-providers)
- [Frontend Drivers](#frontend-drivers)
- [AI Provider Facade](#ai-provider-facade)
- [Conversation Threads & Memory](#conversation-threads--memory)
- [Pruning Old Conversations](#pruning-old-conversations)
- [Token Control](#token-control)
- [Real-Time Streaming](#real-time-streaming)
- [RAG — Retrieval-Augmented Generation](#rag--retrieval-augmented-generation)
- [Admin Dashboard](#admin-dashboard)
- [Security](#security)
- [Dark Mode](#dark-mode)
- [Customising the Widget](#customising-the-widget)
- [Architecture](#architecture)
- [Extending the Package](#extending-the-package)
- [Troubleshooting](#troubleshooting)
- [Testing](#testing)
- [License](#license)
- [Complete .env Reference](#complete-env-reference)

---

## Features

**Widget & Frontend**
- Drop `@aichatbox` into any Blade layout — nothing else required
- Four frontend drivers: **Vue 3** (default), **vanilla JS** (Blade), **Livewire + Alpine.js**, or **API-only** for React/Svelte/custom builds
- Floating button in any of four corners; dark mode follows OS preference
- Markdown rendering with syntax-highlighted code blocks (bundled in Vue, CDN in Blade/Livewire)
- Sound notification on AI reply (Web Audio API, no audio file needed)
- Messages persist across page refresh via `localStorage` or `sessionStorage`

**AI & Streaming**
- Supports Ollama, OpenAI, Groq, LM Studio, OpenRouter, and any OpenAI-compatible endpoint
- Real-time token streaming via Server-Sent Events (SSE) with a blinking cursor
- Configurable system prompt, language enforcement, temperature, and max tokens
- `AI` facade for calling providers directly from controllers, jobs, or commands

**Conversation Memory**
- Server-side history per thread — context sent back to the AI on every message
- Two memory drivers: **session** (default) or **database** (persists across session expiry)
- Configurable turn limit and token-based context trimming (oldest pairs pruned first)
- Isolated conversation threads with UUID thread IDs; start a new thread without losing others

**RAG — Retrieval-Augmented Generation**
- Upload `.md` and `.txt` documents; the chatbox retrieves relevant context automatically
- Document chunking with configurable size and overlap; per-provider embedding configuration
- Cosine similarity search computed in PHP — no external vector database required
- Knowledge Base UI at `/ai-chatbox/rag` with upload, reprocess, and delete actions

**Admin & Operations**
- Admin dashboard at `/ai-chatbox/admin` with config diagnostics, live error/warning/notice checks, and provider details
- Conversations viewer at `/ai-chatbox/admin/conversations` (requires database memory driver)
- Health check endpoint pings the AI service before the widget opens
- SSRF protection, CORS origin whitelist, configurable rate limiting

---

## Requirements

| | Version |
|---|---|
| PHP | 8.2 or higher |
| Laravel | 10, 11, or 12 |

> No Node.js or npm is required in your application. The Vue bundle is pre-compiled and shipped as a static asset. The `blade` and `livewire` drivers need no compiled assets at all.

---

## Installation

### 1. Require the package

**From Packagist:**

```bash
composer require syafiq-unijaya/laravel-ai-chatbox
```

**Local development (path repository):**

Add to your project's `composer.json`, then run `composer update`:

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

---

### 2. Publish assets

```bash
# Required — publish CSS + JS to public/vendor/ai-chatbox/
php artisan vendor:publish --tag=ai-chatbox-assets

# Optional — publish the config file to customise defaults
php artisan vendor:publish --tag=ai-chatbox-config
```

If you plan to use **RAG** or the **database memory driver**, run the migrations:

```bash
php artisan migrate

# Or publish first to review the migration files
php artisan vendor:publish --tag=ai-chatbox-migrations
php artisan migrate
```

---

### 3. Configure your AI provider

The package defaults to the `ollama` provider on `localhost:11434`. Set your active provider and its credentials in `.env`:

```env
AI_CHATBOX_ACTIVE_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434/v1/chat/completions
OLLAMA_TOKEN=your-ollama-token
OLLAMA_MODEL=gpt-oss:120b
AI_CHATBOX_LANGUAGE=English
```

See [AI Providers](#ai-providers) for examples covering OpenAI, Groq, LM Studio, and more.

> **Running Ollama in WSL?** `localhost` from a Windows host may not reach WSL. Find your WSL IP and use it:
> ```bash
> # run inside WSL
> ip addr show eth0 | grep 'inet '
> ```
> ```env
> OLLAMA_URL=http://172.x.x.x:11434/v1/chat/completions
> AI_CHATBOX_SSRF_PROTECTION=false
> ```

> **Local Ollama on macOS/Linux?** SSRF protection is on by default and blocks `localhost`. Disable it for local development:
> ```env
> AI_CHATBOX_SSRF_PROTECTION=false
> ```

---

### 4. Add the widget to a Blade layout

```blade
{{-- e.g. resources/views/layouts/app.blade.php --}}
@aichatbox
```

The chatbox appears as a floating button on every page that uses the layout. Done.

> Use `@aichatboxConfig` instead if you are building your own frontend (React, Svelte, etc.) — it outputs only `window.AiChatboxConfig` without any widget HTML or scripts.

---

## Configuration Reference

Publish and edit `config/ai-chatbox.php` to change any default.

### Active Provider

| Key | Env var | Default | Description |
|---|---|---|---|
| `active_provider` | `AI_CHATBOX_ACTIVE_PROVIDER` | `ollama` | Provider to use — must match a key under `providers`. The provider's `api_url`, `api_token`, and `api_model` are always the authoritative values. |

> `api_url`, `api_token`, and `api_model` are **not** top-level env vars. They are always sourced from the active named provider. See [AI Providers](#ai-providers).

---

### Response & Language

| Key | Env var | Default | Description |
|---|---|---|---|
| `language` | `AI_CHATBOX_LANGUAGE` | `English` | Language the AI must reply in — leave empty to let the model decide |
| `system_prompt` | `AI_CHATBOX_SYSTEM_PROMPT` | `You are a helpful assistant...` | System message sent on every request — use `{language}` as a placeholder |

The `language` value is enforced at two points per request:

1. The `{language}` placeholder in `system_prompt` is substituted at runtime
2. `[Important: Reply in {language} only.]` is appended to every user message, which improves compliance on smaller models

```env
AI_CHATBOX_LANGUAGE=English
AI_CHATBOX_LANGUAGE="Bahasa Malaysia"
AI_CHATBOX_LANGUAGE=French
AI_CHATBOX_LANGUAGE=            # empty — let the model decide
```

---

### Response Tuning

| Key | Env var | Default | Description |
|---|---|---|---|
| `temperature` | `AI_CHATBOX_TEMPERATURE` | `0.7` | Creativity — `0.0` deterministic, `1.0` creative |
| `max_tokens` | `AI_CHATBOX_MAX_TOKENS` | `null` | Max reply length — `null` lets the model decide |
| `timeout` | `AI_CHATBOX_TIMEOUT` | `30` | Request timeout in seconds |

---

### Widget Appearance

| Key | Env var | Default | Description |
|---|---|---|---|
| `frontend` | `AI_CHATBOX_FRONTEND` | `vue` | UI driver — `vue`, `blade`, `livewire`, or `none` |
| `title` | `AI_CHATBOX_TITLE` | `AI Assistant` | Widget header title |
| `greeting` | `AI_CHATBOX_GREETING` | `Hi! How can I help you today?` | Opening message — leave empty to disable |
| `placeholder` | — | `Type your message...` | Input placeholder text |
| `theme_color` | — | `#4f46e5` | Primary colour (hex) |
| `color_scheme` | `AI_CHATBOX_COLOR_SCHEME` | `auto` | Admin page colour scheme — `auto`, `light`, or `dark` |
| `position` | `AI_CHATBOX_POSITION` | `bottom-right` | Widget position — `bottom-right`, `bottom-left`, `top-right`, `top-left` |
| `markdown` | `AI_CHATBOX_MARKDOWN` | `true` | Render AI replies as Markdown |
| `sound` | `AI_CHATBOX_SOUND` | `true` | Play a ping when the AI replies |
| `sound_volume` | `AI_CHATBOX_SOUND_VOLUME` | `0.3` | Volume — `0.0` silent, `1.0` full |
| `stream` | `AI_CHATBOX_STREAM` | `true` | Stream replies token-by-token via SSE |

---

### Conversation History & Memory

| Key | Env var | Default | Description |
|---|---|---|---|
| `history_enabled` | `AI_CHATBOX_HISTORY` | `true` | Include previous messages for context |
| `history_limit` | `AI_CHATBOX_HISTORY_LIMIT` | `50` | Max user+assistant pairs kept per thread |
| `context_token_limit` | `AI_CHATBOX_CONTEXT_TOKENS` | `4000` | Max estimated tokens of history per request — trims oldest pairs first (`0` = rely on `history_limit` only) |
| `memory_driver` | `AI_CHATBOX_MEMORY_DRIVER` | `session` | Server-side history driver — `session` or `database` |
| `storage` | `AI_CHATBOX_STORAGE` | `local` | Browser storage — `local` (persists across sessions) or `session` (clears on tab close) |

---

### Routes & Security

| Key | Env var | Default | Description |
|---|---|---|---|
| `route_prefix` | — | `ai-chatbox` | URL prefix for all chatbox routes |
| `middleware` | — | `['web', 'throttle:20,1', 'ai-chatbox.cors']` | Middleware stack for chatbox API routes |
| `rate_limit` | `AI_CHATBOX_RATE_LIMIT` | `20` | Max requests per window per IP |
| `rate_window` | `AI_CHATBOX_RATE_WINDOW` | `1` | Rate limit window in minutes |
| `health_check` | `AI_CHATBOX_HEALTH_CHECK` | `true` | Ping the AI service before opening the widget |
| `offline_message` | `AI_CHATBOX_OFFLINE_MESSAGE` | `AI service is currently unreachable.` | Toast shown when the service is unreachable |
| `ssrf_protection` | `AI_CHATBOX_SSRF_PROTECTION` | `true` | Block requests to private/reserved IP ranges |
| `allowed_origins` | — | `[env('APP_URL')]` | Origins allowed to call chatbox endpoints (CORS) |
| `rag_admin_middleware` | — | `['web', 'auth']` | Middleware for all admin and Knowledge Base pages |

---

### RAG

| Key | Env var | Default | Description |
|---|---|---|---|
| `rag_enabled` | `AI_CHATBOX_RAG` | `false` | Master switch — enable RAG context injection |
| `rag_embedding_url` | `AI_CHATBOX_EMBEDDING_URL` | `http://localhost:11434/v1/embeddings` | Embedding API endpoint (global default; overridable per provider) |
| `rag_embedding_model` | `AI_CHATBOX_EMBEDDING_MODEL` | `nomic-embed-text` | Embedding model (global default; overridable per provider) |
| `rag_embedding_timeout` | `AI_CHATBOX_EMBEDDING_TIMEOUT` | `10` | Timeout in seconds for each embedding request |
| `rag_top_k` | `AI_CHATBOX_RAG_TOP_K` | `3` | Number of chunks retrieved per query |
| `rag_chunk_size` | `AI_CHATBOX_RAG_CHUNK_SIZE` | `500` | Target chunk size in tokens (~4 chars/token) |
| `rag_chunk_overlap` | `AI_CHATBOX_RAG_CHUNK_OVERLAP` | `50` | Overlap between consecutive chunks in tokens |
| `rag_similarity_threshold` | `AI_CHATBOX_RAG_THRESHOLD` | `0.2` | Minimum cosine similarity score (`0.0`–`1.0`) |
| `rag_context_prompt` | `AI_CHATBOX_RAG_CONTEXT_PROMPT` | *(see below)* | Instruction prepended to retrieved chunks — use `{chunks}` as placeholder |
| `rag_processing_timeout` | `AI_CHATBOX_RAG_PROCESSING_TIMEOUT` | `0` | Max seconds for a single upload or reprocess — `0` = no limit |

---

## AI Providers

Every API connection is configured through a **named provider**. Set `AI_CHATBOX_ACTIVE_PROVIDER` to the provider name, then configure that provider's own env vars.

### Named providers — configuration

Named providers are defined under the `providers` key in `config/ai-chatbox.php`. Each entry can override any global setting; everything else is inherited.

```php
// config/ai-chatbox.php
'providers' => [
    'ollama'   => [
        'api_url'             => env('OLLAMA_URL',            'http://localhost:11434/v1/chat/completions'),
        'api_token'           => env('OLLAMA_TOKEN',          'your-ollama-token'),
        'api_model'           => env('OLLAMA_MODEL',          'gpt-oss:120b'),
        'rag_embedding_url'   => env('OLLAMA_EMBEDDING_URL',  'http://localhost:11434/v1/embeddings'),
        'rag_embedding_model' => env('OLLAMA_EMBEDDING_MODEL','nomic-embed-text'),
    ],
    'openai'   => [
        'api_url'             => env('OPENAI_URL',            'https://api.openai.com/v1/chat/completions'),
        'api_token'           => env('OPENAI_API_KEY',        ''),
        'api_model'           => env('OPENAI_MODEL',          ''),
        'rag_embedding_url'   => env('OPENAI_EMBEDDING_URL',  ''),
        'rag_embedding_model' => env('OPENAI_EMBEDDING_MODEL',''),
    ],
    'groq'     => [
        'api_url'   => env('GROQ_URL',     'https://api.groq.com/openai/v1/chat/completions'),
        'api_token' => env('GROQ_API_KEY', ''),
        'api_model' => env('GROQ_MODEL',   ''),
    ],
    'lmstudio' => [
        'api_url'             => env('LMSTUDIO_URL',            'http://127.0.0.1:1234/v1/chat/completions'),
        'api_token'           => env('LMSTUDIO_TOKEN',          'lmstudio'),
        'api_model'           => env('LMSTUDIO_MODEL',          'phi-3.5-mini-instruct'),
        'rag_embedding_url'   => env('LMSTUDIO_EMBEDDING_URL',  'http://127.0.0.1:1234/v1/embeddings'),
        'rag_embedding_model' => env('LMSTUDIO_EMBEDDING_MODEL','text-embedding-nomic-embed-text-v1.5'),
    ],
],
```

**Env var reference per provider:**

| Provider | URL | Token | Model | Embedding URL | Embedding model |
|---|---|---|---|---|---|
| `ollama` | `OLLAMA_URL` | `OLLAMA_TOKEN` | `OLLAMA_MODEL` | `OLLAMA_EMBEDDING_URL` | `OLLAMA_EMBEDDING_MODEL` |
| `openai` | `OPENAI_URL` | `OPENAI_API_KEY` | `OPENAI_MODEL` | `OPENAI_EMBEDDING_URL` | `OPENAI_EMBEDDING_MODEL` |
| `groq` | `GROQ_URL` | `GROQ_API_KEY` | `GROQ_MODEL` | `GROQ_EMBEDDING_URL` | `GROQ_EMBEDDING_MODEL` |
| `lmstudio` | `LMSTUDIO_URL` | `LMSTUDIO_TOKEN` | `LMSTUDIO_MODEL` | `LMSTUDIO_EMBEDDING_URL` | `LMSTUDIO_EMBEDDING_MODEL` |

> The chatbox widget and the `AI` facade both resolve through the same named provider. `AI_CHATBOX_ACTIVE_PROVIDER` controls which provider is active for both.

---

### Provider examples

#### Ollama (local)

```env
AI_CHATBOX_ACTIVE_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434/v1/chat/completions
OLLAMA_TOKEN=your-ollama-token
OLLAMA_MODEL=gpt-oss:120b
AI_CHATBOX_SSRF_PROTECTION=false
```

#### Ollama Cloud

```env
AI_CHATBOX_ACTIVE_PROVIDER=ollama
OLLAMA_URL=https://ollama.com/api/chat
OLLAMA_TOKEN=your_ollama_api_key
OLLAMA_MODEL=gpt-oss:120b
```

#### OpenAI

```env
AI_CHATBOX_ACTIVE_PROVIDER=openai
OPENAI_URL=https://api.openai.com/v1/chat/completions
OPENAI_API_KEY=sk-...
OPENAI_MODEL=gpt-4o
```

#### Groq

```env
AI_CHATBOX_ACTIVE_PROVIDER=groq
GROQ_URL=https://api.groq.com/openai/v1/chat/completions
GROQ_API_KEY=gsk_...
GROQ_MODEL=llama-3.3-70b-versatile
```

#### LM Studio (local)

```env
AI_CHATBOX_ACTIVE_PROVIDER=lmstudio
LMSTUDIO_URL=http://localhost:1234/v1/chat/completions
LMSTUDIO_TOKEN=lmstudio
LMSTUDIO_MODEL=your-loaded-model-name
AI_CHATBOX_SSRF_PROTECTION=false
```

> Start LM Studio, load a model, and enable the **Local Server** tab. The model name must match exactly what LM Studio displays.

#### OpenRouter (custom provider)

Add a custom entry to your published `config/ai-chatbox.php`:

```php
'providers' => [
    'openrouter' => [
        'api_url'   => env('OPENROUTER_URL',     'https://openrouter.ai/api/v1/chat/completions'),
        'api_token' => env('OPENROUTER_API_KEY',  ''),
        'api_model' => env('OPENROUTER_MODEL',    'mistralai/mistral-7b-instruct'),
    ],
    // ... other providers
],
```

```env
AI_CHATBOX_ACTIVE_PROVIDER=openrouter
OPENROUTER_URL=https://openrouter.ai/api/v1/chat/completions
OPENROUTER_API_KEY=sk-or-...
OPENROUTER_MODEL=mistralai/mistral-7b-instruct
```

---

## Frontend Drivers

Set `AI_CHATBOX_FRONTEND` to choose how `@aichatbox` renders. All drivers share the same backend API routes and `window.AiChatboxConfig` — only the widget layer differs.

| Driver | Widget | Streaming | JS dependency | Assets required |
|---|---|---|---|---|
| `vue` | Vue 3 SFC | SSE | Bundled `chatbox.js` | `vendor:publish --tag=ai-chatbox-assets` |
| `blade` | Vanilla JS | SSE | None (marked.js from CDN if Markdown on) | Same |
| `livewire` | Alpine.js | SSE | Alpine.js (bundled with Livewire 3) | Same |
| `none` | — | Your choice | None | Not required |

```env
AI_CHATBOX_FRONTEND=vue       # Vue 3 widget (default)
AI_CHATBOX_FRONTEND=blade     # Vanilla JS, no framework
AI_CHATBOX_FRONTEND=livewire  # Alpine.js via Livewire
AI_CHATBOX_FRONTEND=none      # API + config only
```

---

### `vue` — Vue 3 (default)

No extra setup. The pre-compiled bundle mounts to `#ai-chatbox-app` and reads `window.AiChatboxConfig`.

---

### `blade` — Vanilla JS

A self-contained widget with no framework dependency. Uses the same CSS as the Vue driver (identical HTML class names), so all appearance options apply equally.

If `AI_CHATBOX_MARKDOWN=true`, `marked.js` and `DOMPurify` are loaded from jsDelivr at runtime. Set `AI_CHATBOX_MARKDOWN=false` to remove the CDN dependency entirely.

---

### `livewire` — Livewire + Alpine.js

Renders an Alpine.js widget. Livewire 3 bundles Alpine.js automatically — no additional scripts needed.

The package registers a Livewire component, so you can also mount the widget independently:

```blade
<livewire:ai-chatbox />
```

> If you use `<livewire:ai-chatbox />` without `@aichatbox`, add `@aichatboxConfig` to your layout so the widget has access to its configuration.

```blade
{{-- layout --}}
@aichatboxConfig
...
{{-- anywhere on the page --}}
<livewire:ai-chatbox />
```

---

### `none` — API-only / custom frontend

Outputs only `window.AiChatboxConfig`. Use this when building your own React, Svelte, or other framework frontend.

`@aichatboxConfig` produces the same output regardless of the `frontend` setting:

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
    stream,         // boolean
    healthCheck,    // boolean
    title,
    placeholder,
    greeting,
    markdown,       // boolean
    sound,          // boolean
    soundVolume,    // 0.0–1.0
    position,       // 'bottom-right' | 'bottom-left' | 'top-right' | 'top-left'
    storageKey,     // localStorage/sessionStorage key (scoped per app + user)
    storageType,    // 'local' | 'session'
    offlineMessage,
    themeColor,
};
```

All API endpoints accept `{ message, thread_id }` as JSON. Responses:

| Endpoint | Response |
|---|---|
| `POST /ai-chatbox/message` | `{ "reply": "..." }` |
| `POST /ai-chatbox/stream` | SSE events: `data: {"token":"..."}` ending with `data: [DONE]` |
| `POST /ai-chatbox/clear` | `{ "status": "ok" }` |
| `GET /ai-chatbox/health` | `{ "status": "online" }` or `{ "status": "offline", "message": "...", "code": "E##" }` |

---

## AI Provider Facade

The `AI` facade lets you call any configured AI provider directly from controllers, jobs, Artisan commands, or services — without touching the chatbox widget.

### Basic usage

```php
use SyafiqUnijaya\AiChatbox\AI;

// Use the active provider (resolves to AI_CHATBOX_ACTIVE_PROVIDER)
$reply = AI::chat('Summarise this document: ...');

// Use a specific named provider
$reply = AI::provider('openai')->chat('Translate to French: ...');
$reply = AI::provider('groq')->chat('Write a test for this function...');
$reply = AI::provider('ollama')->chat('What is the capital of France?');
```

### Fluent modifiers

Every modifier returns a **new immutable instance** — the original provider is never mutated.

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
// Pass a callback — tokens are emitted synchronously
AI::provider('openai')->stream($prompt, [], function (string $token) {
    echo $token;
    ob_flush(); flush();
});

// Or receive a Closure to invoke later
$reader = AI::provider('default')->stream($prompt);
$reader(fn(string $token) => print($token));
```

### Chat with history

```php
$history = [
    ['role' => 'user',      'content' => 'Previous question'],
    ['role' => 'assistant', 'content' => 'Previous answer'],
];

$reply = AI::provider('default')->chat('Follow-up question', $history);
```

### How provider resolution works

| Usage | Resolves to |
|---|---|
| **Chatbox widget** | Provider named by `AI_CHATBOX_ACTIVE_PROVIDER` |
| `AI::chat()` / `AI::provider('default')` | Provider named by `AI_CHATBOX_ACTIVE_PROVIDER` |
| `AI::provider('openai')` | `openai` entry in `config/ai-chatbox.php` |
| `AI::provider('ollama')` | `ollama` entry in `config/ai-chatbox.php` |

---

## Conversation Threads & Memory

### Thread IDs

Each time a user first opens the chatbox, a **UUID v4 thread ID** is generated in the browser and stored in `localStorage` (or `sessionStorage`). This ID is sent with every message and scopes the server-side history — so multiple independent conversations never share context.

```
Thread A (UUID: 550e8400...)  →  session key: ai_chatbox_history_550e8400...
Thread B (UUID: 6ba7b810...)  →  session key: ai_chatbox_history_6ba7b810...
```

The **pencil icon** in the widget header starts a fresh thread. The **trash icon** clears the current thread's history. Thread IDs survive page refresh — the same conversation context is restored on return.

---

### Session memory driver (default)

History is stored in the PHP session and sent to the AI on every subsequent message.

```env
AI_CHATBOX_MEMORY_DRIVER=session
AI_CHATBOX_HISTORY=true
AI_CHATBOX_HISTORY_LIMIT=50
```

Set `AI_CHATBOX_HISTORY=false` to make every message standalone (no context sent):

```env
AI_CHATBOX_HISTORY=false
```

---

### Database memory driver

Switch to the `database` driver to persist history in Eloquent models. History survives PHP session expiry, is shared across all PHP workers, and can be queried or exported.

```env
AI_CHATBOX_MEMORY_DRIVER=database
```

Run the migration if you haven't already:

```bash
php artisan migrate
```

This creates:

| Table | Purpose |
|---|---|
| `ai_chatbox_conversations` | One row per thread, keyed by UUID |
| `ai_chatbox_messages` | All messages per thread (role + content) |

The [Conversations Viewer](#conversations-viewer) in the admin dashboard requires this driver.

To revert, set `AI_CHATBOX_MEMORY_DRIVER=session`. Existing database records are preserved but ignored until you switch back.

---

### Browser storage

Chat bubbles are persisted in the browser, automatically scoped to prevent history leaking between different apps or different authenticated users on the same device.

| Setting | Behaviour |
|---|---|
| `AI_CHATBOX_STORAGE=local` | Persists across browser sessions (default) |
| `AI_CHATBOX_STORAGE=session` | Cleared when the tab is closed |

---

## Pruning Old Conversations

When using the `database` memory driver, conversation records accumulate over time. The `ai-chatbox:prune-conversations` command permanently deletes conversations (and their messages via cascade) that have had no activity beyond the configured retention period.

### Running the command

```bash
# Use the default from config (AI_CHATBOX_PRUNE_DAYS, default 30 days)
php artisan ai-chatbox:prune-conversations

# Override the retention period at runtime
php artisan ai-chatbox:prune-conversations --days=60

# Preview what would be deleted without making any changes
php artisan ai-chatbox:prune-conversations --dry-run

# Run even when memory_driver is not set to 'database' (e.g. cleanup after switching drivers)
php artisan ai-chatbox:prune-conversations --force
```

### Scheduling automatic pruning

Register the command in your application's `routes/console.php` (Laravel 11+) or `app/Console/Kernel.php` (Laravel 10):

**Laravel 11+ (`routes/console.php`):**

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('ai-chatbox:prune-conversations')->daily();
```

**Laravel 10 (`app/Console/Kernel.php`):**

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('ai-chatbox:prune-conversations')->daily();
}
```

### Configuration

| Key | Env var | Default | Description |
|---|---|---|---|
| `conversation_prune_days` | `AI_CHATBOX_PRUNE_DAYS` | `30` | Conversations inactive for longer than this many days are deleted |

```env
AI_CHATBOX_PRUNE_DAYS=30   # default — 30 days retention
AI_CHATBOX_PRUNE_DAYS=90   # longer retention
AI_CHATBOX_PRUNE_DAYS=7    # aggressive cleanup
```

### Error handling

The command performs the following checks before deleting anything:

| Condition | Behaviour |
|---|---|
| `memory_driver` is not `database` | Exits with an error — use `--force` to override |
| `ai_chatbox_conversations` table missing | Exits with an error and prints migration instructions |
| `ai_chatbox_messages` table missing | Warns but continues (cascade may not apply) |
| `--days` value is less than 1 | Exits with an error |
| No matching conversations found | Exits cleanly with an informational message |

> **How "last activity" is determined:** `updated_at` on the conversation row is updated every time `saveHistory` is called — i.e., every time the user sends a message. Conversations that have genuinely had no activity for `--days` days are safe to remove.

---

## Token Control

Two independent limits control how much is sent to the AI per request.

### Context token limit

Limits the amount of conversation history included per request. History is trimmed oldest-pair-first using a ~4 chars/token heuristic.

```env
AI_CHATBOX_CONTEXT_TOKENS=4000     # phi3:mini, llama3 8B (default)
AI_CHATBOX_CONTEXT_TOKENS=8000     # llama3 70B, Mixtral
AI_CHATBOX_CONTEXT_TOKENS=32000    # GPT-4o, Claude
AI_CHATBOX_CONTEXT_TOKENS=0        # disabled — rely on history_limit only
```

### Max reply tokens

Limits how long the AI's reply can be. Leave unset to let the model decide.

```env
AI_CHATBOX_MAX_TOKENS=512     # short replies
AI_CHATBOX_MAX_TOKENS=2048    # longer replies
AI_CHATBOX_MAX_TOKENS=        # model default (unset)
```

Both limits work together: `history_limit` caps by message count, `context_token_limit` caps by estimated tokens. Whichever is reached first applies.

### Temperature

```env
AI_CHATBOX_TEMPERATURE=0.2    # focused, deterministic
AI_CHATBOX_TEMPERATURE=0.7    # balanced (default)
AI_CHATBOX_TEMPERATURE=1.0    # creative, unpredictable
```

---

## Real-Time Streaming

When `AI_CHATBOX_STREAM=true` (default), AI replies are streamed token-by-token via **Server-Sent Events**. Each word appears as it is generated, with a blinking `▋` cursor while in progress.

```env
AI_CHATBOX_STREAM=true    # stream token-by-token (default)
AI_CHATBOX_STREAM=false   # wait for the full reply before displaying
```

**How it works:**

1. The frontend calls `POST /ai-chatbox/stream` using the Fetch API and `ReadableStream`
2. The server proxies the AI response using Guzzle's `'stream' => true` and reads 1024-byte chunks
3. Each token is emitted as: `data: {"token":"word"}\n\n`
4. The stream ends with: `data: [DONE]\n\n`
5. Markdown rendering is applied to the completed reply, not during streaming

**Server configuration:**

```
Nginx   proxy_buffering off;  (the package sets X-Accel-Buffering: no automatically)
PHP     output_buffering = Off  in php.ini
```

---

## RAG — Retrieval-Augmented Generation

RAG lets the chatbox answer questions about **your own data** — documents, FAQs, knowledge bases — without fine-tuning any model.

```
User sends a message
     ↓
Message is embedded → cosine similarity search across all indexed chunks
     ↓
Top-K most relevant chunks are injected as a system message
     ↓
AI answers using your knowledge-base context
```

### Quick start

**1. Enable RAG:**

```env
AI_CHATBOX_RAG=true
AI_CHATBOX_EMBEDDING_URL=http://localhost:11434/v1/embeddings
AI_CHATBOX_EMBEDDING_MODEL=nomic-embed-text
```

**2. Run the migration:**

```bash
php artisan migrate
```

**3. Upload documents** at `/ai-chatbox/rag` (requires an authenticated user by default).

Every subsequent chat message will automatically retrieve and inject relevant context.

---

### Embedding providers

RAG uses a **separate embedding API** — distinct from the chat API. Any provider with an `/embeddings` endpoint works.

| Provider | `AI_CHATBOX_EMBEDDING_URL` | `AI_CHATBOX_EMBEDDING_MODEL` |
|---|---|---|
| Ollama | `http://localhost:11434/v1/embeddings` | `nomic-embed-text` |
| Ollama | `http://localhost:11434/v1/embeddings` | `mxbai-embed-large` |
| LM Studio | `http://localhost:1234/v1/embeddings` | your loaded embedding model |
| OpenAI | `https://api.openai.com/v1/embeddings` | `text-embedding-3-small` |
| OpenAI | `https://api.openai.com/v1/embeddings` | `text-embedding-3-large` |

> **Ollama:** Pull the embedding model first:
> ```bash
> ollama pull nomic-embed-text
> ```

Named providers support per-provider embedding configuration. Set `rag_embedding_url` and `rag_embedding_model` inside a provider entry to override the global defaults for that provider.

---

### Document formats

| Format | Extension | Notes |
|---|---|---|
| Plain text | `.txt` | Chunked directly |
| Markdown | `.md` | Heading structure is preserved across chunks |

Maximum upload size: **10 MB** per file.

---

### How chunking works

Documents are split into overlapping text chunks before embedding:

```
rag_chunk_size    = 500 tokens ≈ 2 000 chars (default)
rag_chunk_overlap =  50 tokens ≈   200 chars (default)
```

The chunker:

1. Splits on paragraph boundaries (two or more blank lines)
2. Falls back to sentence boundaries (`. ! ?`) for oversized paragraphs
3. Carries `chunk_overlap` characters into the next chunk so context is not lost at boundaries

---

### How retrieval works

On every chat message:

1. The user's message is embedded using the same embedding model
2. Cosine similarity is computed **in PHP** against every stored chunk
3. Chunks below `rag_similarity_threshold` (default `0.2`) are discarded
4. The top `rag_top_k` (default `3`) chunks are injected as a system message using `rag_context_prompt`, replacing the `{chunks}` placeholder

The default prompt instructs the model to treat retrieved chunks as its **primary source** and say "I don't have that information in my knowledge base" if the answer is not found there. Customise via `.env`:

```env
AI_CHATBOX_RAG_CONTEXT_PROMPT="Use only the following context to answer:\n\n{chunks}\n\nDo not use any other knowledge."
```

> **Scale:** Similarity is computed in PHP and works well up to a few thousand chunks. For larger knowledge bases, consider switching to a database with native vector support such as `pgvector` for PostgreSQL.

---

### Knowledge Base UI

Visit **`/ai-chatbox/rag`** (authenticated users only).

| Action | Description |
|---|---|
| **Upload** | Select a `.md` or `.txt` file, optionally set a title, click *Upload & Index* |
| **Reprocess** | Re-chunk and re-embed an existing document after changing chunk or embedding settings |
| **Delete** | Remove the document and all its chunks permanently (confirmation required) |

Each document shows its status (`Pending` → `Processing` → `Ready` / `Failed`), chunk count, and expandable error details on failure.

---

## Admin Dashboard

Visit **`/ai-chatbox/admin`** (authenticated users only).

### Dashboard overview

| Section | Content |
|---|---|
| **Stat cards** | RAG document/chunk counts; conversation and message counts (database driver) |
| **Configuration diagnostics** | Live validation of every config group at page load — errors (red), warnings (amber), notices (blue). All clear → green banner |
| **Config values** | All resolved settings grouped by section |
| **Named providers** | All configured providers and their settings |
| **Environment** | Laravel version, PHP version, app environment, debug mode |

Diagnostic checks include: missing API URL/token/model, placeholder tokens left in place, `APP_DEBUG` on in production, SSRF conflicts with local URLs, open CORS origins, missing database tables, invalid RAG chunk settings, failed or unembedded documents, weak admin middleware, and selected frontend driver not installed.

---

### Conversations viewer

Visit **`/ai-chatbox/admin/conversations`** (requires `memory_driver=database`).

- **Async-paginated table** — loads conversation rows via JSON; shows thread ID, user name, message count, last message preview, and last active time
- **Click a row** to open a modal with full message history in a chat-bubble layout
- **Guest sessions** — rows show "Guest" when no user is associated

---

### Protecting the admin UI

By default, `rag_admin_middleware` is `['web', 'auth']` — any authenticated user can access admin pages. For production, publish the config and add a role or permission middleware:

```php
// config/ai-chatbox.php
'rag_admin_middleware' => ['web', 'auth', 'role:admin'],          // Spatie roles
'rag_admin_middleware' => ['web', 'auth', 'can:manage-chatbox'],  // Laravel Gates
'rag_admin_middleware' => ['web', 'auth:sanctum'],                // Sanctum
```

---

## Routes

All routes are registered under the configured prefix (`ai-chatbox` by default).

```
GET    /ai-chatbox/health                               Health check — ping the AI service
POST   /ai-chatbox/message                              Send a message, receive a full JSON reply
POST   /ai-chatbox/stream                               Send a message, stream SSE tokens
POST   /ai-chatbox/clear                                Clear server-side history for a thread

GET    /ai-chatbox/rag                                  Knowledge Base — list indexed documents  [auth]
POST   /ai-chatbox/rag                                  Knowledge Base — upload a document       [auth]
DELETE /ai-chatbox/rag/{id}                             Knowledge Base — delete a document       [auth]
POST   /ai-chatbox/rag/{id}/reprocess                   Knowledge Base — re-chunk and re-embed   [auth]

GET    /ai-chatbox/admin                                Admin dashboard                          [auth]
GET    /ai-chatbox/admin/conversations                  Conversations list                       [auth]
GET    /ai-chatbox/admin/conversations/data             Conversations JSON (paginated)           [auth]
GET    /ai-chatbox/admin/conversations/{id}/messages    Messages for a conversation (JSON)       [auth]
```

---

## Security

### SSRF protection

The health check pings the configured `api_url` to verify the AI service is reachable. To prevent Server-Side Request Forgery (SSRF), requests to private and reserved IP ranges are blocked by default: `localhost`, `10.x`, `172.16.x`, `192.168.x`, `169.254.x`.

```env
AI_CHATBOX_SSRF_PROTECTION=true   # production — keep enabled (default)
AI_CHATBOX_SSRF_PROTECTION=false  # local Ollama / LM Studio — disable
```

### CORS

The package registers an `ai-chatbox.cors` middleware that restricts chatbox endpoints to requests originating from your application's URL. Cross-origin requests from other domains are rejected with `403`.

To permit additional origins, publish the config:

```php
'allowed_origins' => [
    env('APP_URL', 'http://localhost'),
    'https://other-allowed-origin.example.com',
],
```

### Authentication

By default the chatbox is accessible to guests. To restrict it to authenticated users:

```php
// config/ai-chatbox.php
'middleware' => ['web', 'throttle:20,1', 'ai-chatbox.cors', 'auth'],
// or with Sanctum:
'middleware' => ['web', 'throttle:20,1', 'ai-chatbox.cors', 'auth:sanctum'],
```

### Browser storage & sensitive data

Conversation history is stored in `localStorage` by default. For privacy-sensitive applications, switch to `sessionStorage`:

```env
AI_CHATBOX_STORAGE=session
```

> Do not enter passwords, tokens, or other secrets into the chatbox regardless of the storage driver — any script running on the page can read browser storage.

---

## Dark Mode

### Chat widget

The widget automatically adapts to the OS/browser dark mode preference via `prefers-color-scheme: dark`. No configuration required.

### Admin pages

All admin pages — `/ai-chatbox/admin`, `/ai-chatbox/admin/conversations`, and `/ai-chatbox/rag` — share the `color_scheme` setting:

| Value | Behaviour |
|---|---|
| `auto` *(default)* | Follows OS preference — updates live when changed |
| `light` | Always light |
| `dark` | Always dark |

```env
AI_CHATBOX_COLOR_SCHEME=auto    # OS preference (default)
AI_CHATBOX_COLOR_SCHEME=light
AI_CHATBOX_COLOR_SCHEME=dark
```

> Run `php artisan config:clear` after changing `.env` values.

---

## Customising the Widget

Publish views to override any Blade template:

```bash
php artisan vendor:publish --tag=ai-chatbox-views
```

Published to `resources/views/vendor/ai-chatbox/`:

| File | Driver | Purpose |
|---|---|---|
| `chatbox.blade.php` | all | Main dispatcher — routes to the active driver |
| `chatbox-config.blade.php` | all | Outputs `window.AiChatboxConfig` |
| `chatbox-vue.blade.php` | `vue` | CSS link + Vue mount point + JS bundle |
| `chatbox-blade.blade.php` | `blade` | Full vanilla JS widget |
| `livewire/chatbox.blade.php` | `livewire` | Alpine.js widget |

---

## Architecture

The package is organised into four explicit layers. Each layer communicates only with the layer directly above or below it; controllers contain no business logic.

```
┌──────────────────────────────────────────────────────┐
│  Layer 4 — UI                                        │
│  ChatboxController · RagController · AdminController │
│  Blade views · Vue 3 · Blade · Livewire drivers      │
│  HTTP request / response only                        │
├──────────────────────────────────────────────────────┤
│  Layer 3 — RAG                                       │
│  RagRetriever · EmbeddingService · DocumentChunker   │
│  RagDocument + RagChunk models                       │
│  Document upload, chunking, embedding, retrieval     │
├──────────────────────────────────────────────────────┤
│  Layer 2 — Memory                                    │
│  ContextManager                                      │
│  SessionConversationRepository                       │
│  DatabaseConversationRepository                      │
│  Conversation + Message models                       │
│  History persistence and context trimming            │
├──────────────────────────────────────────────────────┤
│  Layer 1 — AI Engine                                 │
│  OpenAiCompatibleEngine · AiEngineInterface          │
│  PromptBuilder · HealthChecker                       │
│  AiEngineException (error codes E01–E19)             │
│  HTTP calls, prompt assembly, error handling         │
└──────────────────────────────────────────────────────┘
```

**Source layout:**

```
src/
├── Config/
│   └── ai-chatbox.php
├── Database/
│   └── Migrations/
├── Engine/
│   ├── Contracts/AiEngineInterface.php
│   ├── OpenAiCompatibleEngine.php
│   ├── HealthChecker.php
│   └── PromptBuilder.php
├── Http/
│   ├── Controllers/
│   └── Middleware/CorsMiddleware.php
├── Memory/
│   ├── Contracts/ConversationRepositoryInterface.php
│   ├── SessionConversationRepository.php
│   ├── DatabaseConversationRepository.php
│   └── ContextManager.php
├── Models/
│   ├── Conversation.php · Message.php
│   ├── RagDocument.php · RagChunk.php
├── Services/
│   ├── RagRetriever.php
│   ├── EmbeddingService.php
│   └── DocumentChunker.php
├── resources/
│   └── views/
│       ├── chatbox.blade.php
│       ├── chatbox-config.blade.php
│       ├── chatbox-vue.blade.php
│       ├── chatbox-blade.blade.php
│       ├── admin.blade.php
│       ├── admin-conversations.blade.php
│       ├── rag.blade.php
│       └── livewire/chatbox.blade.php
├── AI.php                         # Facade
├── AiManager.php                  # Provider registry + singleton
└── Providers/AiChatboxServiceProvider.php
```

---

## Extending the Package

### Custom AI engine

Implement `AiEngineInterface` to support a provider that is not OpenAI-compatible (Anthropic, Gemini, Cohere, etc.):

```php
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\Exceptions\AiEngineException;

class AnthropicEngine implements AiEngineInterface
{
    public function validateConfig(array $options): void
    {
        if (empty($options['api_token'])) {
            throw new AiEngineException('API token missing', 'E03');
        }
    }

    public function complete(array $messages, array $options = []): string
    {
        // Call Anthropic Messages API, return the reply as a plain string
    }

    public function stream(array $messages, array $options, callable $onToken): string
    {
        // Call $onToken('word') per token, return the full assembled reply
    }

    public function beginStream(array $messages, array $options): \Closure
    {
        // Open the HTTP connection before response()->stream() starts
        // Return a closure: fn(callable $onToken): string
        $this->validateConfig($options);
        return function (callable $onToken): string {
            // read stream, call $onToken per token, return full reply
        };
    }
}
```

Bind in a service provider:

```php
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;

$this->app->bind(AiEngineInterface::class, AnthropicEngine::class);
```

---

### Custom memory driver

Implement `ConversationRepositoryInterface` to store history in Redis, MongoDB, or any other backend:

```php
use SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface;

class RedisConversationRepository implements ConversationRepositoryInterface
{
    public function getHistory(string $threadId): array
    {
        return json_decode(Redis::get("chat:{$threadId}") ?? '[]', true);
    }

    public function saveHistory(string $threadId, array $history): void
    {
        Redis::set("chat:{$threadId}", json_encode($history));
    }

    public function trimToLimit(string $threadId, int $maxPairs): void
    {
        $history = $this->getHistory($threadId);
        $this->saveHistory($threadId, array_slice($history, -($maxPairs * 2)));
    }

    public function clear(string $threadId): void
    {
        Redis::del("chat:{$threadId}");
    }
}
```

Bind in a service provider:

```php
use SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface;

$this->app->bind(ConversationRepositoryInterface::class, RedisConversationRepository::class);
```

> Binding a custom implementation directly overrides the `memory_driver` config key selection.

---

## Troubleshooting

If the widget shows an offline toast or requests fail, check `storage/logs/laravel.log` for an error code (`E01`–`E19`). Full reference: [TROUBLESHOOTING.md](TROUBLESHOOTING.md)

---

## Testing

```bash
composer test
```

The test suite covers: controller responses, error classification, session history, conversation thread isolation, token-based context trimming, SSE streaming, RAG document upload/delete/reprocess, RAG context injection, CORS middleware, SSRF protection, health check logic, `AiManager` named provider resolution, `AiProvider` fluent modifiers and immutability, and the `AI` facade — using PHPUnit 11 and Orchestra Testbench.

---

## License

MIT — see [LICENSE](LICENSE) for details.

---

## Complete `.env` Reference

All available settings with their default values.

```env
# ── Active Provider ────────────────────────────────────────────────────────────
# api_url, api_token, and api_model are always sourced from the active provider.
AI_CHATBOX_ACTIVE_PROVIDER=ollama

# ── Response Language & System Prompt ─────────────────────────────────────────
AI_CHATBOX_LANGUAGE=English
AI_CHATBOX_SYSTEM_PROMPT="You are a helpful assistant. You must always respond in {language} only, no matter what language the user writes in. Do not switch to any other language under any circumstances."

# ── Response Tuning ────────────────────────────────────────────────────────────
AI_CHATBOX_TEMPERATURE=0.7
AI_CHATBOX_MAX_TOKENS=          # leave blank (null) to let the model decide
AI_CHATBOX_TIMEOUT=30

# ── Conversation History ───────────────────────────────────────────────────────
AI_CHATBOX_HISTORY=true
AI_CHATBOX_HISTORY_LIMIT=50
AI_CHATBOX_CONTEXT_TOKENS=4000  # 0 = rely on HISTORY_LIMIT only

# ── Streaming ─────────────────────────────────────────────────────────────────
AI_CHATBOX_STREAM=true

# ── Health Check ──────────────────────────────────────────────────────────────
AI_CHATBOX_HEALTH_CHECK=true
AI_CHATBOX_OFFLINE_MESSAGE="AI service is currently unreachable."

# ── Security ──────────────────────────────────────────────────────────────────
AI_CHATBOX_SSRF_PROTECTION=true  # disable for local Ollama / LM Studio
AI_CHATBOX_RATE_LIMIT=20
AI_CHATBOX_RATE_WINDOW=1

# ── Widget Appearance ─────────────────────────────────────────────────────────
AI_CHATBOX_FRONTEND=vue           # vue | blade | livewire | none
AI_CHATBOX_TITLE="AI Assistant"
AI_CHATBOX_GREETING="Hi! How can I help you today?"
AI_CHATBOX_POSITION=bottom-right  # bottom-right | bottom-left | top-right | top-left
AI_CHATBOX_COLOR_SCHEME=auto      # auto | light | dark  (admin pages)
AI_CHATBOX_MARKDOWN=true
AI_CHATBOX_SOUND=true
AI_CHATBOX_SOUND_VOLUME=0.3

# ── Memory & Storage ──────────────────────────────────────────────────────────
AI_CHATBOX_MEMORY_DRIVER=session  # session | database
AI_CHATBOX_STORAGE=local          # local | session
AI_CHATBOX_PRUNE_DAYS=30          # retention period for ai-chatbox:prune-conversations

# ── RAG ───────────────────────────────────────────────────────────────────────
AI_CHATBOX_RAG=false
AI_CHATBOX_EMBEDDING_URL=http://localhost:11434/v1/embeddings
AI_CHATBOX_EMBEDDING_MODEL=nomic-embed-text
AI_CHATBOX_EMBEDDING_TIMEOUT=10
AI_CHATBOX_RAG_TOP_K=3
AI_CHATBOX_RAG_CHUNK_SIZE=500
AI_CHATBOX_RAG_CHUNK_OVERLAP=50
AI_CHATBOX_RAG_THRESHOLD=0.2
AI_CHATBOX_RAG_CONTEXT_PROMPT="Use the following knowledge-base excerpts as your PRIMARY source when answering. Prioritize this context over your general knowledge. If the answer is not found in the context, say \"I don't have that information in my knowledge base.\"\n\nContext:\n{chunks}"
AI_CHATBOX_RAG_PROCESSING_TIMEOUT=0  # 0 = no limit

# ── Named Provider Credentials ────────────────────────────────────────────────
# The chatbox widget and AI facade both resolve through these env vars.
# AI_CHATBOX_ACTIVE_PROVIDER selects which block is used.

# Ollama
OLLAMA_URL=http://localhost:11434/v1/chat/completions
OLLAMA_TOKEN=your-ollama-token
OLLAMA_MODEL=gpt-oss:120b
OLLAMA_EMBEDDING_URL=http://localhost:11434/v1/embeddings
OLLAMA_EMBEDDING_MODEL=nomic-embed-text

# LM Studio
LMSTUDIO_URL=http://127.0.0.1:1234/v1/chat/completions
LMSTUDIO_TOKEN=lmstudio
LMSTUDIO_MODEL=phi-3.5-mini-instruct
LMSTUDIO_EMBEDDING_URL=http://127.0.0.1:1234/v1/embeddings
LMSTUDIO_EMBEDDING_MODEL=text-embedding-nomic-embed-text-v1.5

# OpenAI
OPENAI_URL=https://api.openai.com/v1/chat/completions
OPENAI_API_KEY=
OPENAI_MODEL=gpt-4o
OPENAI_EMBEDDING_URL=https://api.openai.com/v1/embeddings
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Groq
GROQ_URL=https://api.groq.com/openai/v1/chat/completions
GROQ_API_KEY=
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_EMBEDDING_URL=
GROQ_EMBEDDING_MODEL=
```
