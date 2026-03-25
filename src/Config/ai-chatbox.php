<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI API Configuration
    |--------------------------------------------------------------------------
    | The endpoint URL and bearer token for your AI provider.
    | These should be set in your application's .env file.
    |
    | Defaults to Ollama running locally on WSL (OpenAI-compatible API).
    | Ollama does not require a real token — any non-empty string works.
    |
    | If accessing Ollama from Windows → WSL, use the WSL IP instead of
    | localhost: run `ip addr show eth0` inside WSL to find it, e.g.
    |   AI_CHATBOX_API_URL=http://172.x.x.x:11434/v1/chat/completions
    */

    'api_url' => env('AI_CHATBOX_API_URL', 'http://localhost:11434/v1/chat/completions'),

    'api_token' => env('AI_CHATBOX_API_TOKEN', 'ollama'),

    'api_model' => env('AI_CHATBOX_API_MODEL', 'phi3:mini'),

    /*
    |--------------------------------------------------------------------------
    | System Prompt
    |--------------------------------------------------------------------------
    | An optional system message sent to the AI on every request.
    | Leave empty to disable.
    */

    'system_prompt' => env('AI_CHATBOX_SYSTEM_PROMPT', 'You are a helpful assistant.'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    | The URL prefix and middleware applied to the chatbox route.
    | Change the prefix to avoid collisions with existing routes.
    */

    'route_prefix' => 'ai-chatbox',

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Widget Appearance
    |--------------------------------------------------------------------------
    */

    'title' => 'AI Assistant',
    'placeholder' => 'Type your message...',
    'theme_color' => '#4f46e5',

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    | Seconds to wait for a response from the AI API before timing out.
    */

    'timeout' => 30,

];
