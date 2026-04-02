<?php

return [

/*
|--------------------------------------------------------------------------
| Active Provider
|--------------------------------------------------------------------------
| The provider the chatbox widget and AI facade use. Must match a key
| under 'providers' below. The provider's api_url, api_token, and
| api_model are the authoritative values — configure them via the
| provider's own env vars (e.g. OLLAMA_URL, OPENAI_URL) rather than
| separate top-level AI_CHATBOX_API_* variables.
|
| Examples:
|   AI_CHATBOX_ACTIVE_PROVIDER=ollama      → uses providers.ollama
|   AI_CHATBOX_ACTIVE_PROVIDER=openai      → uses providers.openai
|   AI_CHATBOX_ACTIVE_PROVIDER=lmstudio    → uses providers.lmstudio
*/

    'active_provider' => env('AI_CHATBOX_ACTIVE_PROVIDER', 'ollama'),

/*
|--------------------------------------------------------------------------
| Response Language
|--------------------------------------------------------------------------
| The language the AI must always reply in, regardless of what language
| the user writes in. Uses the full language name (e.g. 'English',
| 'Bahasa Malaysia', 'French', 'Arabic').
|
| Set to empty string to let the AI reply in whatever language it chooses.
*/

    'language' => env('AI_CHATBOX_LANGUAGE', 'English'),

/*
|--------------------------------------------------------------------------
| System Prompt
|--------------------------------------------------------------------------
| An optional system message sent to the AI on every request.
| Leave empty to use the default. The {language} placeholder is
| automatically replaced with the value of the 'language' config above.
*/

    'system_prompt' => env('AI_CHATBOX_SYSTEM_PROMPT', 'You are a helpful assistant. You must always respond in {language} only, no matter what language the user writes in. Do not switch to any other language under any circumstances.'),

/*
|--------------------------------------------------------------------------
| Route Configuration
|--------------------------------------------------------------------------
| The URL prefix and middleware applied to the chatbox route.
| Change the prefix to avoid collisions with existing routes.
*/

    'route_prefix' => 'ai-chatbox',

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
| When enabled, clicking the chat button first pings the AI service.
| The window only opens if the service is reachable.
| Set to false to skip the check and open immediately.
*/

    'health_check' => env('AI_CHATBOX_HEALTH_CHECK', true),
    'offline_message' => env('AI_CHATBOX_OFFLINE_MESSAGE', 'AI service is currently unreachable.'),

/*
|--------------------------------------------------------------------------
| SSRF Protection
|--------------------------------------------------------------------------
| When enabled, the health check blocks requests to private/reserved IP
| ranges (localhost, 10.x, 172.16.x, 192.168.x, 169.254.x) to prevent
| Server-Side Request Forgery attacks.
|
| Disable this only in local development where your AI service runs on
| localhost or a private network (e.g. local Ollama).
|
| AI_CHATBOX_SSRF_PROTECTION=false
*/

    'ssrf_protection' => env('AI_CHATBOX_SSRF_PROTECTION', true),
    'middleware' => ['web', 'throttle:20,1', 'ai-chatbox.cors'],

/*
|--------------------------------------------------------------------------
| CORS — Allowed Origins
|--------------------------------------------------------------------------
| Origins permitted to call the chatbox endpoints. Defaults to the app's
| own URL so cross-origin requests from other domains are rejected.
| Add additional origins as needed, e.g. ['https://app.example.com'].
*/

    'allowed_origins' => [env('APP_URL', 'http://localhost')],

/*
|--------------------------------------------------------------------------
| Rate Limiting
|--------------------------------------------------------------------------
| Controls the throttle middleware above.
| 'rate_limit'  — max requests allowed per window (default: 20)
| 'rate_window' — window size in minutes (default: 1)
|
| To change, update the middleware entry above or publish the config.
| Example: 'throttle:10,1' = 10 requests per minute per IP.
*/

    'rate_limit' => env('AI_CHATBOX_RATE_LIMIT', 20),
    'rate_window' => env('AI_CHATBOX_RATE_WINDOW', 1),

/*
|--------------------------------------------------------------------------
| Widget Appearance
|--------------------------------------------------------------------------
*/

    'title' => env('AI_CHATBOX_TITLE', 'AI Assistant'),
    'placeholder' => 'Type your message...',
    'theme_color' => '#4f46e5',
    'greeting' => env('AI_CHATBOX_GREETING', 'Hi! How can I help you today?'),

/*
|--------------------------------------------------------------------------
| Color Scheme (Admin RAG UI)
|--------------------------------------------------------------------------
| Controls whether the RAG admin page renders in light or dark mode.
|
| 'auto'  — follows the user's OS / browser preference (default)
| 'light' — always light
| 'dark'  — always dark
*/

    'color_scheme' => env('AI_CHATBOX_COLOR_SCHEME', 'auto'),

/*
|--------------------------------------------------------------------------
| Frontend Driver
|--------------------------------------------------------------------------
| Controls which UI the @aichatbox directive renders.
|
| 'vue'      — Pre-built Vue 3 widget (default, zero-config)
| 'blade'    — Vanilla JS widget, no framework required
| 'livewire' — Alpine.js widget mounted via Livewire (requires livewire/livewire)
| 'none'     — Only outputs window.AiChatboxConfig; bring your own frontend
*/

    'frontend' => env('AI_CHATBOX_FRONTEND', 'vue'),

/*
|--------------------------------------------------------------------------
| Markdown Rendering
|--------------------------------------------------------------------------
| When enabled, AI replies are rendered as Markdown (bold, italics, lists,
| code blocks, etc.) using marked.js + DOMPurify (loaded from CDN).
|
| Set to false to display replies as plain text.
*/

    'markdown' => env('AI_CHATBOX_MARKDOWN', true),

/*
|--------------------------------------------------------------------------
| Sound Notification
|--------------------------------------------------------------------------
| Play a soft ping when the AI replies.
| Uses the Web Audio API — no sound file required.
|
| 'sound'        — true/false to enable/disable
| 'sound_volume' — float between 0.0 (silent) and 1.0 (full)
*/

    'sound' => env('AI_CHATBOX_SOUND', true),
    'sound_volume' => env('AI_CHATBOX_SOUND_VOLUME', 0.3),

/*
|--------------------------------------------------------------------------
| Widget Position
|--------------------------------------------------------------------------
| Where the chatbox appears on screen.
| Supported: 'bottom-right', 'bottom-left', 'top-right', 'top-left'
*/

    'position' => env('AI_CHATBOX_POSITION', 'bottom-right'),

/*
|--------------------------------------------------------------------------
| Conversation History
|--------------------------------------------------------------------------
| When enabled, previous messages are stored in the session and sent to
| the AI on every request, giving it memory of the current conversation.
|
| 'history_enabled' — set to false to disable (each message is standalone)
| 'history_limit'   — max number of user+assistant message PAIRS to keep.
|                     Older messages are dropped to stay within token budgets.
*/

    'history_enabled' => env('AI_CHATBOX_HISTORY', true),
    'history_limit' => env('AI_CHATBOX_HISTORY_LIMIT', 50),

/*
|--------------------------------------------------------------------------
| Context Token Limit
|--------------------------------------------------------------------------
| Approximate maximum number of tokens to include from conversation history
| in each API request. History is trimmed oldest-first (by message pair)
| until the estimated token count falls below this threshold.
|
| Uses a ~4 characters per token estimate. Tune this to stay within your
| model's context window. Common values:
|   phi3:mini   → 4 000   (default)
|   llama3 8B   → 8 000
|   GPT-4o      → 32 000
|
| Set to 0 to disable token-based trimming (rely on history_limit only).
*/

    'context_token_limit' => env('AI_CHATBOX_CONTEXT_TOKENS', 4000),

/*
|--------------------------------------------------------------------------
| Token Streaming (Server-Sent Events)
|--------------------------------------------------------------------------
| When enabled, AI replies are streamed token-by-token to the browser via
| Server-Sent Events (SSE). The user sees the response being written in
| real time rather than waiting for the full reply.
|
| Set to false to fall back to the standard POST request/response cycle.
|
| Requires your AI provider to support stream: true (all OpenAI-compatible
| APIs and Ollama do). Ensure your web server does not buffer responses:
|   Nginx → proxy_buffering off  (set automatically via X-Accel-Buffering)
|   PHP   → disable output_buffering in php.ini for best results
*/

    'stream' => env('AI_CHATBOX_STREAM', true),

/*
|--------------------------------------------------------------------------
| Client-side Storage Driver
|--------------------------------------------------------------------------
| Controls where chat history is persisted in the browser.
|
| 'local'   — localStorage: survives tab/browser close (default)
| 'session' — sessionStorage: cleared when the tab is closed (more private)
|
| Use 'session' for apps where users may discuss sensitive information.
*/

    'storage' => env('AI_CHATBOX_STORAGE', 'local'),

/*
|--------------------------------------------------------------------------
| AI Response Tuning
|--------------------------------------------------------------------------
| 'max_tokens'  — maximum tokens in the AI reply. Lower = shorter/cheaper.
|                 Set to null to let the model decide (API default).
|
| 'temperature' — creativity/randomness of replies.
|                 0.0 = deterministic, 1.0 = creative. Typical: 0.7.
*/

    'max_tokens' => env('AI_CHATBOX_MAX_TOKENS', null),
    'temperature' => env('AI_CHATBOX_TEMPERATURE', 0.7),

/*
|--------------------------------------------------------------------------
| Request Timeout
|--------------------------------------------------------------------------
| Seconds to wait for a response from the AI API before timing out.
*/

    'timeout' => env('AI_CHATBOX_TIMEOUT', 30),

/*
|--------------------------------------------------------------------------
| RAG — Retrieval-Augmented Generation
|--------------------------------------------------------------------------
| When enabled, the chatbox retrieves relevant context from your uploaded
| knowledge-base documents and injects it into every AI request.
|
| 'rag_enabled'              — master switch (default: false)
| 'rag_embedding_url'        — endpoint for generating text embeddings.
|                              Global fallback: AI_CHATBOX_EMBEDDING_URL
|                              Per-provider:    LMSTUDIO_EMBEDDING_URL, OLLAMA_EMBEDDING_URL,
|                                               OPENAI_EMBEDDING_URL, GROQ_EMBEDDING_URL
| 'rag_embedding_model'      — embedding model name.
|                              Global fallback: AI_CHATBOX_EMBEDDING_MODEL
|                              Per-provider:    LMSTUDIO_EMBEDDING_MODEL, OLLAMA_EMBEDDING_MODEL,
|                                               OPENAI_EMBEDDING_MODEL, GROQ_EMBEDDING_MODEL
|                              Defaults: Ollama=nomic-embed-text, OpenAI=text-embedding-3-small
| 'rag_top_k'                — number of chunks to retrieve per query (default: 3)
| 'rag_chunk_size'           — target chunk size in tokens (~4 chars/token, default: 500)
| 'rag_chunk_overlap'        — overlap between chunks in tokens (default: 50)
| 'rag_similarity_threshold' — minimum cosine similarity score 0.0–1.0 (default: 0.3)
| 'rag_admin_middleware'     — middleware for the admin document-management UI.
|                              Default: ['web', 'auth'] — requires an authenticated user.
|                              Change to ['web'] to make it publicly accessible (not recommended).
*/

    'rag_enabled' => env('AI_CHATBOX_RAG', false),
    'rag_embedding_url' => env('AI_CHATBOX_EMBEDDING_URL', 'http://localhost:11434/v1/embeddings'),
    'rag_embedding_model' => env('AI_CHATBOX_EMBEDDING_MODEL', 'nomic-embed-text'),
    'rag_embedding_timeout' => (int) env('AI_CHATBOX_EMBEDDING_TIMEOUT', 10),
    'rag_top_k' => (int) env('AI_CHATBOX_RAG_TOP_K', 3),
    'rag_chunk_size' => (int) env('AI_CHATBOX_RAG_CHUNK_SIZE', 500),
    'rag_chunk_overlap' => (int) env('AI_CHATBOX_RAG_CHUNK_OVERLAP', 50),
    'rag_similarity_threshold' => (float) env('AI_CHATBOX_RAG_THRESHOLD', 0.2),
    'rag_admin_middleware' => ['web', 'auth'],

/*
|--------------------------------------------------------------------------
| RAG Context Prompt
|--------------------------------------------------------------------------
| Instruction prepended to the retrieved context block sent to the AI.
| This tells the model to prioritize the knowledge-base content over its
| general training data. Tune this for your model or use case.
|
| Use {chunks} as the placeholder where the retrieved text will be inserted.
| If {chunks} is absent, the retrieved text is appended after the prompt.
|
| Set to empty string to send the raw chunks with no additional instruction.
*/
    'rag_context_prompt' => env(
        'AI_CHATBOX_RAG_CONTEXT_PROMPT',
        "Use the following knowledge-base excerpts as your PRIMARY source when answering. "
        . "Prioritize this context over your general knowledge. "
        . "If the answer is not found in the context, say \"I don't have that information in my knowledge base.\"\n\n"
        . "Context:\n{chunks}"
    ),

/*
|--------------------------------------------------------------------------
| Memory Driver
|--------------------------------------------------------------------------
| Controls where conversation history (chats and messages) is persisted.
|
| 'session'  — PHP session storage. Zero-config, default.
| 'database' — Eloquent models stored in ai_chatbox_conversations /
|              ai_chatbox_messages tables. History survives browser sessions
|              and is queryable. Run `php artisan migrate` after switching.
*/

    'memory_driver' => env('AI_CHATBOX_MEMORY_DRIVER', 'session'),

/*
|--------------------------------------------------------------------------
| RAG Processing Time Limit
|--------------------------------------------------------------------------
| Maximum seconds PHP is allowed to spend on a single document upload
| (chunking + embedding all chunks). Embedding each chunk makes one HTTP
| call to the embedding API, so large documents on slow local models can
| easily exceed PHP's default 30-second limit.
|
| 0 = no limit (recommended for local models, default)
| 300 = 5 minutes (a safe upper bound for most use cases)
|
| This only affects the RAG admin upload/reprocess request — all other
| requests use the normal PHP max_execution_time.
*/
    'rag_processing_timeout' => (int) env('AI_CHATBOX_RAG_PROCESSING_TIMEOUT', 0),

/*
|--------------------------------------------------------------------------
| Named AI Providers
|--------------------------------------------------------------------------
| Define additional named providers for use with the AI facade:
|
|   AI::provider('ollama')->chat('Hello');
|   AI::provider('openai')->withTemperature(0.2)->chat('Hello');
|   AI::chat('Hello');   // uses the 'default' provider (top-level config above)
|
| Each provider must define api_url, api_token, and api_model via its own
| env vars. All other settings (temperature, system_prompt, language,
| history_limit, etc.) are inherited from the global defaults above.
|
| You can add as many named providers as you like. Custom provider names
| (e.g. 'lmstudio', 'mistral', 'azure') are fully supported.
*/

    'providers' => [

        'lmstudio' => [
            'api_url' => env('LMSTUDIO_URL', 'http://127.0.0.1:1234/v1/chat/completions'),
            'api_token' => env('LMSTUDIO_TOKEN', 'lmstudio'),
            'api_model' => env('LMSTUDIO_MODEL', 'phi-3.5-mini-instruct'),
            'rag_embedding_url' => env('LMSTUDIO_EMBEDDING_URL', 'http://127.0.0.1:1234/v1/embeddings'),
            'rag_embedding_model' => env('LMSTUDIO_EMBEDDING_MODEL', 'text-embedding-nomic-embed-text-v1.5'),
        ],

        'ollama' => [
            'api_url' => env('OLLAMA_URL', 'http://localhost:11434/v1/chat/completions'),
            'api_token' => env('OLLAMA_TOKEN', 'your-ollama-token'),
            'api_model' => env('OLLAMA_MODEL', 'gpt-oss:120b'),
            'rag_embedding_url' => env('OLLAMA_EMBEDDING_URL', 'http://localhost:11434/v1/embeddings'),
            'rag_embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
        ],

        'openai' => [
            'api_url' => env('OPENAI_URL', ''),
            'api_token' => env('OPENAI_API_KEY', ''),
            'api_model' => env('OPENAI_MODEL', ''),
            'rag_embedding_url' => env('OPENAI_EMBEDDING_URL', ''),
            'rag_embedding_model' => env('OPENAI_EMBEDDING_MODEL', ''),
        ],

        'groq' => [
            'api_url' => env('GROQ_URL', ''),
            'api_token' => env('GROQ_API_KEY', ''),
            'api_model' => env('GROQ_MODEL', ''),
            'rag_embedding_url' => env('GROQ_EMBEDDING_URL', ''),
            'rag_embedding_model' => env('GROQ_EMBEDDING_MODEL', ''),
        ],
    ],

];
