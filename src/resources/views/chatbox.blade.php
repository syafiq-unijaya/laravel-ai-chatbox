@php
    $title       = config('ai-chatbox.title', 'AI Assistant');
    $placeholder = config('ai-chatbox.placeholder', 'Type your message...');
    $themeColor  = config('ai-chatbox.theme_color', '#4f46e5');
    $greeting    = config('ai-chatbox.greeting', '');
    $position    = config('ai-chatbox.position', 'bottom-right');
    $markdown    = config('ai-chatbox.markdown', true);
    $sound       = config('ai-chatbox.sound', true);
    $soundVolume = config('ai-chatbox.sound_volume', 0.4);
    $healthCheck = config('ai-chatbox.health_check', true);
    $routeUrl    = route('ai-chatbox.message');
    $clearUrl    = route('ai-chatbox.clear');
    $healthUrl   = route('ai-chatbox.health');
@endphp

{{-- ── Inline CSS variables so theme_color works without extra build steps ── --}}
<style>
    :root {
        --chatbox-color: {{ $themeColor }};
    }
</style>

{{-- ── Stylesheet ── --}}
<link rel="stylesheet" href="{{ asset('vendor/ai-chatbox/css/chatbox.css') }}">

{{-- ── Widget markup ── --}}
<div id="ai-chatbox-wrapper" class="ai-chatbox--{{ $position }}">

    {{-- Offline toast --}}
    <div id="ai-chatbox-offline-toast" role="alert" aria-live="assertive"></div>

    {{-- Floating toggle button --}}
    <button id="ai-chatbox-toggle" aria-label="Toggle chat" title="{{ $title }}">
        <svg id="ai-chatbox-icon-open"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
        <svg id="ai-chatbox-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="display:none"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        <svg id="ai-chatbox-icon-loading" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="display:none"><circle cx="12" cy="12" r="10" stroke-opacity=".25"/><path d="M12 2a10 10 0 0 1 10 10" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/></path></svg>
    </button>

    {{-- Chat window --}}
    <div id="ai-chatbox-window" aria-live="polite">

        <div id="ai-chatbox-header">
            <span>{{ $title }}</span>
            <button id="ai-chatbox-clear" title="Clear conversation" aria-label="Clear conversation">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
            </button>
        </div>

        <div id="ai-chatbox-messages"></div>

        <form id="ai-chatbox-form" autocomplete="off">
            @csrf
            <input
                type="text"
                id="ai-chatbox-input"
                placeholder="{{ $placeholder }}"
                maxlength="2000"
                required
            >
            <button type="submit" id="ai-chatbox-send" aria-label="Send">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
            </button>
        </form>

    </div>
</div>

{{-- ── Markdown libraries (CDN) — only loaded when markdown is enabled ── --}}
@if($markdown)
<script src="https://cdn.jsdelivr.net/npm/marked@9/marked.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
@endif

{{-- ── Script ── --}}
<script>
    window.AiChatboxConfig = {
        url:         "{{ $routeUrl }}",
        clearUrl:    "{{ $clearUrl }}",
        healthUrl:   "{{ $healthUrl }}",
        healthCheck: {{ $healthCheck ? 'true' : 'false' }},
        token:       "{{ csrf_token() }}",
        greeting:    "{{ addslashes($greeting) }}",
        markdown:    {{ $markdown ? 'true' : 'false' }},
        sound:       {{ $sound ? 'true' : 'false' }},
        soundVolume: {{ (float) $soundVolume }}
    };
</script>
<script src="{{ asset('vendor/ai-chatbox/js/chatbox.js') }}"></script>
