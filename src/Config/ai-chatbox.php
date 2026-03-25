<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI API Configuration
    |--------------------------------------------------------------------------
    | The endpoint URL and bearer token for your AI provider.
    | These should be set in your application's .env file.
    |
    | Supported formats (auto-detected from the response):
    |
    |   Ollama cloud (native format):
    |     AI_CHATBOX_API_URL=https://ollama.com/api/chat
    |     AI_CHATBOX_API_TOKEN=your_ollama_api_key
    |     AI_CHATBOX_API_MODEL=gpt-oss:120b
    |
    |   Ollama local / OpenAI-compatible:
    |     AI_CHATBOX_API_URL=http://localhost:11434/v1/chat/completions
    |     AI_CHATBOX_API_TOKEN=ollama   (any non-empty string)
    |
    |   If accessing local Ollama from Windows → WSL, use the WSL IP:
    |     run `ip addr show eth0` inside WSL, e.g.
    |     AI_CHATBOX_API_URL=http://172.x.x.x:11434/v1/chat/completions
    */

    'api_url' => env('AI_CHATBOX_API_URL', 'http://localhost:11434/v1/chat/completions'),
    'api_token' => env('AI_CHATBOX_API_TOKEN', 'ollama'),
    'api_model' => env('AI_CHATBOX_API_MODEL', 'phi3:mini'),

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
    'middleware' => ['web', 'throttle:20,1'],

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
    'sound_volume' => env('AI_CHATBOX_SOUND_VOLUME', 0.4),

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
    'history_limit' => env('AI_CHATBOX_HISTORY_LIMIT', 10),

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

    'timeout' => 30,

];
