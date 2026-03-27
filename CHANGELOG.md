# Changelog

All notable changes to `syafiq-unijaya/laravel-ai-chatbox` are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Added
- **3-layer architecture** — the package is now organized into three explicit layers:
  - **Layer 1 — AI Engine** (`src/Engine/`): `AiEngineInterface`, `OpenAiCompatibleEngine`, `PromptBuilder`, `HealthChecker`, `AiEngineException`. All HTTP calls, prompt assembly, error classification, and health checks live here.
  - **Layer 2 — Memory** (`src/Memory/`): `ConversationRepositoryInterface`, `SessionConversationRepository`, `DatabaseConversationRepository`, `ContextManager`, `Conversation` model, `Message` model. All history persistence and context trimming live here.
  - **Layer 3 — UI** (`src/Http/Controllers/`, `src/resources/`): `ChatboxController` now only handles HTTP request/response and delegates entirely to Layers 1 and 2.
- **Database memory driver** — new `memory_driver` config key (`AI_CHATBOX_MEMORY_DRIVER`, default `session`). Set to `database` to persist conversation history in the `ai_chatbox_conversations` / `ai_chatbox_messages` tables; history then survives browser sessions and is queryable via Eloquent.
- **New migrations**: `ai_chatbox_conversations` (thread_id, user_id) and `ai_chatbox_messages` (conversation_id, role, content) — auto-loaded when `memory_driver=database`.
- **`AiEngineInterface`** — public contract for the AI engine; implement it to add a custom provider (e.g. Anthropic, Gemini, Cohere) and bind it in the service provider.
- **`ConversationRepositoryInterface`** — public contract for the memory layer; implement it to add a custom storage backend (e.g. Redis, MongoDB).
- **`beginStream()`** on the engine — establishes the AI HTTP connection before `response()->stream()` starts, so network errors still return a proper JSON error response (non-200) rather than a corrupted stream.

### Changed
- `ChatboxController` reduced from ~600 lines to ~120 lines — pure HTTP I/O, no business logic
- Error classification (`E01`–`E19`) moved from `ChatboxController` to `OpenAiCompatibleEngine` (now `public` methods, directly testable)
- `ErrorClassificationTest` updated to target `OpenAiCompatibleEngine` directly (no more reflection into the controller)
- Expanded `composer.json` keywords for better Packagist discoverability (added `rag`, `retrieval-augmented-generation`, `embeddings`, `vector-search`, `streaming`, `sse`, `vue3`, `local-ai`, `ai-assistant`, and more)
- Updated package description in `composer.json` to reflect RAG and streaming capabilities

---

## [0.1.9] — 2026-03-27

### Added
- **RAG admin dark mode** — the `/ai-chatbox/rag` admin UI now fully supports light and dark themes with a new `color_scheme` config key (`auto` / `light` / `dark`)
  - `auto` (default) follows the user's OS/browser preference via `prefers-color-scheme` and updates live without a page reload
  - `light` / `dark` forces a fixed mode server-side with no flash-of-wrong-theme
  - All elements themed: cards, table, inputs, file picker, status badges, buttons, flash messages
- **Configurable RAG context prompt** — new `rag_context_prompt` config key (`AI_CHATBOX_RAG_CONTEXT_PROMPT`) lets you customise the instruction prepended to retrieved chunks; use `{chunks}` as a placeholder for where the retrieved text is inserted
- **Configurable RAG processing timeout** — new `rag_processing_timeout` config key (`AI_CHATBOX_RAG_PROCESSING_TIMEOUT`, default `0` = no limit) controls how long PHP is allowed to run during document upload/reprocess, preventing `Maximum execution time exceeded` errors on slow local embedding models

### Fixed
- **RAG message ordering** — RAG context is now injected immediately before the user's turn (`[system → history → RAG context → user]`) rather than at the front of the message list; models pay most attention to content nearest the end of the context window, so this significantly improves answer accuracy
- **RAG similarity threshold** — lowered default from `0.3` to `0.2` to improve recall for local embedding models (e.g. `nomic-embed-text`) which typically produce lower cosine similarity scores than OpenAI models

---

## [0.1.8] — 2026-03-27

### Added
- **RAG — Retrieval-Augmented Generation** — full implementation allowing the chatbox to answer questions about your own documents:
  - Upload `.md` and `.txt` files (up to 10 MB) via the admin UI at `/ai-chatbox/rag`
  - Documents are chunked (paragraph-aware with configurable size and overlap) and embedded via any OpenAI-compatible embeddings API
  - On every chat message, the user's query is embedded and cosine similarity is computed in PHP against all stored chunks — the top-K most relevant chunks are injected as context into the AI request
  - Admin UI shows indexing status (`Pending` → `Processing` → `Ready` / `Failed`), chunk count, and error details
  - Supports **Reprocess** (re-chunk and re-embed with new settings) and **Delete** actions
  - Admin routes protected by `['web', 'auth']` middleware by default (configurable)
- **New config keys**: `rag_enabled`, `rag_embedding_url`, `rag_embedding_model`, `rag_top_k`, `rag_chunk_size`, `rag_chunk_overlap`, `rag_similarity_threshold`, `rag_admin_middleware`
- **New database tables**: `ai_chatbox_rag_documents`, `ai_chatbox_rag_chunks` (auto-loaded migrations, no manual registration required)
- **New service classes**: `DocumentChunker`, `EmbeddingService`, `RagRetriever`
- **New Eloquent models**: `RagDocument`, `RagChunk`
- **New controller**: `RagController` with `index`, `store`, `destroy`, `reprocess` actions
- **New publishable tag**: `ai-chatbox-migrations` — publish migrations before running if you want to review or customise them
- Embedding response format auto-detection — works with Ollama `/v1/embeddings`, Ollama `/api/embed`, and OpenAI `/v1/embeddings` without any extra configuration
- RAG admin UI uses Tailwind CDN with a CSS custom property (`--theme`) so it inherits the configured `theme_color` without a build step

### Changed
- `ChatboxController` now injects RAG context into every chat and stream request when `rag_enabled` is `true`

---

## [0.1.7] — 2026-03-27

### Added
- **Conversation threads** — each browser session gets a UUID v4 thread ID stored in `localStorage`/`sessionStorage`, scoped to both the app URL and the authenticated user; multiple independent conversations never share context
- **New thread** button (pencil icon) in the chat header — generates a new UUID and resets the client display while leaving old server-side history to expire naturally
- **Real-time token streaming** via Server-Sent Events (SSE) — AI replies stream token-by-token with a blinking `▋` cursor; uses `POST /ai-chatbox/stream` (Fetch API + `ReadableStream` on the client, Guzzle `stream: true` on the server)
- **Context token limit** — new `context_token_limit` config key (`AI_CHATBOX_CONTEXT_TOKENS`, default `4000`) trims conversation history oldest-pair-first by estimated token count (~4 chars/token) to stay within the model's context window
- **Stream config key** — `stream` / `AI_CHATBOX_STREAM` (default `true`) to toggle between SSE streaming and full-response mode
- `POST /ai-chatbox/clear` route to clear the server-side session history for a specific thread
- New feature tests: `StreamMessageTest`, expanded `SendMessageTest`, `ClearHistoryTest`
- `X-Accel-Buffering: no` response header set automatically on SSE responses to disable Nginx proxy buffering

### Changed
- History is now stored and retrieved per thread ID rather than in a single global session key

---

## [0.1.6] — 2026-03-27

### Fixed
- Resolved Vue.js template string escaping issues that caused JavaScript errors in certain Blade rendering contexts
- Improved `TROUBLESHOOTING.md` with additional error scenarios

---

## [0.1.5] — 2026-03-27

### Changed
- **Frontend rewritten in Vue 3** — replaced the vanilla JavaScript + jQuery implementation with a Vue 3 single-file component (`AiChatbox.vue`) using the Composition API, compiled to a self-contained IIFE bundle via Vite
- Bundle now includes Vue 3, `axios`, `marked`, and `DOMPurify` — no external CDN calls required at runtime
- Blade view significantly simplified; all reactive UI logic moved into the Vue component
- Added `package.json` and `vite.config.js` for contributors to rebuild the frontend assets (`npm run build`)

---

## [0.1.4] — 2026-03-26

### Changed
- Improved asset loading — assets are served more reliably across different server configurations
- Config values are now cached-compatible (safe to use with `php artisan config:cache`)
- Removed redundant route registrations

---

## [0.1.3] — 2026-03-26

### Changed
- Expanded README with configuration reference, provider examples, and usage notes

---

## [0.1.2] — 2026-03-26

### Added
- **Full test suite** with PHPUnit 11 and Orchestra Testbench:
  - `SendMessageTest` — message proxying, error handling, history, language enforcement
  - `ClearHistoryTest` — session history clearing per thread
  - `CorsMiddlewareTest` — origin validation, preflight requests
  - `HealthCheckTest` — AI service ping, SSRF blocking
  - `ErrorClassificationTest` — all E01–E19 error codes
- **GitHub Actions CI** workflow (`.github/workflows/tests.yml`) running tests on PHP 8.2/8.3 × Laravel 10/11/12
- `phpunit.xml` configuration with SQLite in-memory database for fast test runs

---

## [0.1.1] — 2026-03-26

### Added
- **Structured error codes** (`E01`–`E19`) — every failure path in the controller now returns a machine-readable error code alongside the human-readable message, making it easy to diagnose issues from `storage/logs/laravel.log`
- **`TROUBLESHOOTING.md`** — full reference guide mapping each error code to its cause and resolution steps
- Error codes cover: authentication failures, connection errors, timeouts, model not found, context length exceeded, content policy violations, rate limiting, invalid responses, and more

---

## [0.1.0] — 2026-03-26

### Added
- **CORS middleware** (`ai-chatbox.cors`) — restricts chatbox endpoints to requests originating from your app's own URL (`APP_URL`); additional origins can be added via `allowed_origins` config
- **SSRF protection** — the health check endpoint now blocks requests to private and reserved IP ranges (`localhost`, `10.x`, `172.16.x`, `192.168.x`, `169.254.x`) to prevent Server-Side Request Forgery attacks; disable with `AI_CHATBOX_SSRF_PROTECTION=false` for local development
- New config keys: `ssrf_protection`, `allowed_origins`

---

## [0.0.9] — 2026-03-25

### Fixed
- `localStorage` key is now scoped to both the application URL and the authenticated user — previously all users on the same browser shared the same chat history key, causing messages from one user to appear for another

---

## [0.0.8] — 2026-03-25

### Fixed
- Resolved session persistence bugs — chat history now reliably survives page refresh
- `localStorage` is written and read correctly; state is no longer lost on navigation

---

## [0.0.7] — 2026-03-25

### Added
- **Chat history persistence** — conversation messages are saved to `localStorage` and restored when the user returns to the page; chat no longer resets on every page load

---

## [0.0.6] — 2026-03-25

### Added
- **Ollama cloud (`ollama.com`) compatibility** — auto-detects the Ollama native chat response format (different from the OpenAI-compatible format) and parses it correctly; both Ollama local (OpenAI-compatible) and Ollama cloud (native) APIs now work without any extra configuration
- Updated config comments to document Ollama cloud `.env` example

---

## [0.0.5] — 2026-03-25

### Changed
- README cleanup and corrections

---

## [0.0.4] — 2026-03-25

### Added
- **Language preference** — new `language` / `AI_CHATBOX_LANGUAGE` config forces the AI to always reply in a specified language regardless of the language the user writes in; uses both a system prompt instruction and a per-message reminder for better compliance on small models
- `system_prompt` config key for a fully customisable system message with a `{language}` placeholder

### Fixed
- Fixed a broken icon rendering bug in the chat button

### Changed
- Removed unused CSS and simplified the stylesheet significantly

---

## [0.0.3] — 2026-03-25

### Added
- **Health check** — clicking the chat button now pings the AI service first; if unreachable, a toast is shown near the button for 4 seconds (`health_check`, `offline_message` config keys)
- **Widget position** — configurable corner placement: `bottom-right`, `bottom-left`, `top-right`, `top-left` (`position` / `AI_CHATBOX_POSITION`)
- **Sound notification** — soft Web Audio API ping when the AI replies (`sound`, `sound_volume` config keys)
- **Markdown rendering** — AI replies rendered as formatted Markdown (bold, lists, code blocks, tables) using `marked.js` + `DOMPurify`, both bundled (`markdown` / `AI_CHATBOX_MARKDOWN`)
- **Conversation history** — previous messages sent back to the AI on every request for context (`history_enabled`, `history_limit` config keys)
- **Response tuning** — `temperature` and `max_tokens` config keys
- **Client-side storage driver** — switch between `localStorage` and `sessionStorage` (`storage` / `AI_CHATBOX_STORAGE`)
- **Dark mode** — chat widget automatically adapts to `prefers-color-scheme: dark`
- **Rate limiting** — `throttle:20,1` middleware applied to all chatbox routes (`rate_limit`, `rate_window` config keys)
- **Route prefix** — configurable URL prefix (`route_prefix` config key, default `ai-chatbox`)

---

## [0.0.2] — 2026-03-25

### Added
- Chatbox title now configurable via `AI_CHATBOX_TITLE`

---

## [0.0.1] — 2026-03-25

### Added
- Initial release
- Floating chat widget injected via `@aichatbox` Blade directive — no build tools required in the host application
- Messages proxied through Laravel to any OpenAI-compatible API
- Default configuration targets **Ollama** running locally on `localhost:11434` with `phi3:mini`
- Supports Ollama (local), OpenAI, Groq, OpenRouter, and LM Studio out of the box
- Configurable API URL, token, and model via `.env`
- Service provider with auto-discovery, asset publishing, and view publishing

[Unreleased]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.9...HEAD
[0.1.9]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.8...0.1.9
[0.1.8]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.7...0.1.8
[0.1.7]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.6...0.1.7
[0.1.6]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.5...0.1.6
[0.1.5]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.4...0.1.5
[0.1.4]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.3...0.1.4
[0.1.3]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.2...0.1.3
[0.1.2]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.1...0.1.2
[0.1.1]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.9...0.1.0
[0.0.9]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.8...0.0.9
[0.0.8]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.7...0.0.8
[0.0.7]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.6...0.0.7
[0.0.6]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.5...0.0.6
[0.0.5]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.4...0.0.5
[0.0.4]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.3...0.0.4
[0.0.3]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.2...0.0.3
[0.0.2]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/syafiq-unijaya/laravel-ai-chatbox/releases/tag/0.0.1
