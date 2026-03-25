@php
    $title      = config('ai-chatbox.title', 'AI Assistant');
    $placeholder = config('ai-chatbox.placeholder', 'Type your message...');
    $themeColor = config('ai-chatbox.theme_color', '#4f46e5');
    $routeUrl   = route('ai-chatbox.message');
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
<div id="ai-chatbox-wrapper">

    {{-- Floating toggle button --}}
    <button id="ai-chatbox-toggle" aria-label="Toggle chat" title="{{ $title }}">
        <svg id="ai-chatbox-icon-open"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M20 2H4a2 2 0 0 0-2 2v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/></svg>
        <svg id="ai-chatbox-icon-close" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="display:none"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
    </button>

    {{-- Chat window --}}
    <div id="ai-chatbox-window" aria-live="polite">

        <div id="ai-chatbox-header">
            <span>{{ $title }}</span>
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

{{-- ── Script ── --}}
<script>
    window.AiChatboxConfig = {
        url:   "{{ $routeUrl }}",
        token: "{{ csrf_token() }}"
    };
</script>
<script src="{{ asset('vendor/ai-chatbox/js/chatbox.js') }}"></script>
