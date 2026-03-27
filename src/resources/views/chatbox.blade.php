@once
    @include('ai-chatbox::chatbox-config')
@endonce

@php $aiChatboxFrontend = config('ai-chatbox.frontend', 'vue'); @endphp

@if($aiChatboxFrontend === 'vue')
    @include('ai-chatbox::chatbox-vue')
@elseif($aiChatboxFrontend === 'blade')
    @include('ai-chatbox::chatbox-blade')
@elseif($aiChatboxFrontend === 'livewire')
    @include('ai-chatbox::livewire.chatbox')
@endif
{{-- 'none': only the config block above is rendered --}}
