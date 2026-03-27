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
    $stream      = config('ai-chatbox.stream', true);
    $routeUrl    = route('ai-chatbox.message');
    $streamUrl   = route('ai-chatbox.stream');
    $clearUrl    = route('ai-chatbox.clear');
    $healthUrl   = route('ai-chatbox.health');
    $userSegment = auth()->check() ? auth()->id() : 'guest';
    $storageKey  = 'ai_chatbox_' . $aiChatboxAppHash . '_' . $userSegment;
    $storageType    = config('ai-chatbox.storage', 'local') === 'session' ? 'session' : 'local';
    $offlineMessage = config('ai-chatbox.offline_message', 'AI service is currently unreachable.');
@endphp
<script>
    window.AiChatboxConfig = {
        url:            "{{ $routeUrl }}",
        streamUrl:      "{{ $streamUrl }}",
        clearUrl:       "{{ $clearUrl }}",
        healthUrl:      "{{ $healthUrl }}",
        healthCheck:    {{ $healthCheck ? 'true' : 'false' }},
        stream:         {{ $stream ? 'true' : 'false' }},
        token:          "{{ csrf_token() }}",
        title:          {!! json_encode($title, JSON_HEX_TAG) !!},
        placeholder:    {!! json_encode($placeholder, JSON_HEX_TAG) !!},
        greeting:       {!! json_encode($greeting, JSON_HEX_TAG) !!},
        markdown:       {{ $markdown ? 'true' : 'false' }},
        sound:          {{ $sound ? 'true' : 'false' }},
        soundVolume:    {{ (float) $soundVolume }},
        position:       {!! json_encode($position, JSON_HEX_TAG) !!},
        storageKey:     "{{ $storageKey }}",
        storageType:    "{{ $storageType }}",
        offlineMessage: {!! json_encode($offlineMessage, JSON_HEX_TAG) !!},
        themeColor:     {!! json_encode($themeColor, JSON_HEX_TAG) !!}
    };
</script>
