@php
    $scheme = $colorScheme ?? 'auto';
    $activeProvider = $configGroups['AI API']['active_provider'] ?? 'default';
@endphp
<!DOCTYPE html>
<html lang="en" @if($scheme === 'dark') class="dark" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chatbox — Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    @if($scheme === 'auto')
    <script>
        (function () {
            var mq = window.matchMedia('(prefers-color-scheme: dark)');
            function apply() { document.documentElement.classList.toggle('dark', mq.matches); }
            apply();
            mq.addEventListener('change', apply);
        })();
    </script>
    @endif
    <style>
        :root { --theme: {{ $themeColor }}; }

        .btn-primary {
            background-color: var(--theme);
            color: #fff;
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.5rem 1.25rem;
            font-size: 0.875rem; font-weight: 500;
            transition: filter 0.15s; text-decoration: none;
        }
        .btn-primary:hover { filter: brightness(0.88); }

        .btn-secondary {
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.5rem 1.25rem;
            font-size: 0.875rem; font-weight: 500;
            color: var(--theme);
            background-color: color-mix(in srgb, var(--theme) 10%, transparent);
            transition: background-color 0.15s; text-decoration: none;
            border: 1px solid color-mix(in srgb, var(--theme) 25%, transparent);
        }
        .btn-secondary:hover { background-color: color-mix(in srgb, var(--theme) 18%, transparent); }

        .stat-card { border-left: 3px solid var(--theme); }

        .section-heading {
            font-size: 0.7rem; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--theme);
        }

        .config-key {
            font-family: ui-monospace, 'Cascadia Code', monospace;
            font-size: 0.8rem;
        }

        .config-val {
            font-family: ui-monospace, 'Cascadia Code', monospace;
            font-size: 0.8rem;
            word-break: break-all;
        }

        .badge { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-yellow { background: #fef9c3; color: #854d0e; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }
        .badge-gray   { background: #f3f4f6; color: #374151; }
        .dark .badge-green  { background: #14532d; color: #86efac; }
        .dark .badge-red    { background: #7f1d1d; color: #fca5a5; }
        .dark .badge-yellow { background: #713f12; color: #fde68a; }
        .dark .badge-blue   { background: #1e3a5f; color: #93c5fd; }
        .dark .badge-gray   { background: #374151; color: #d1d5db; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased min-h-screen">

<div class="max-w-6xl mx-auto px-4 py-10">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <svg class="w-6 h-6" style="color:var(--theme)" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.716-1.42 2.416L12 17.25l-7.782 1.468c-1.45.3-2.42-1.416-1.42-2.416L4.2 15.3" />
                </svg>
                <h1 class="text-2xl font-bold tracking-tight">AI Chatbox Admin</h1>
            </div>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Laravel {{ $env['laravel'] }} &nbsp;·&nbsp;
                PHP {{ $env['php'] }} &nbsp;·&nbsp;
                <span class="{{ $env['app_debug'] ? 'text-amber-600 dark:text-amber-400 font-medium' : '' }}">
                    {{ $env['app_env'] }}{{ $env['app_debug'] ? ' (debug on)' : '' }}
                </span>
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if($ragEnabled)
            <a href="{{ $ragUrl }}" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                </svg>
                Knowledge Base
            </a>
            @endif
        </div>
    </div>

    {{-- ── Flash ────────────────────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="mb-6 flex items-center gap-2 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-6 flex items-center gap-2 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75h.007v.008H12v-.008z"/></svg>
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Stat cards row ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">

        {{-- RAG stats --}}
        @if($ragEnabled && $ragStats)
        <a href="{{ $ragUrl }}" class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm block hover:shadow-md hover:brightness-[0.97] dark:hover:brightness-110 transition-all" title="Open Knowledge Base">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Documents</p>
            <p class="text-2xl font-bold">{{ $ragStats['documents'] }}</p>
            <p class="text-xs mt-1">
                <span class="text-green-600 dark:text-green-400">{{ $ragStats['documents_ready'] }} ready</span>
                @if($ragStats['documents_failed'] > 0)
                &nbsp;·&nbsp;<span class="text-red-600 dark:text-red-400">{{ $ragStats['documents_failed'] }} failed</span>
                @endif
            </p>
        </a>
        <a href="{{ $ragUrl }}" class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm block hover:shadow-md hover:brightness-[0.97] dark:hover:brightness-110 transition-all" title="Open Knowledge Base">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Chunks</p>
            <p class="text-2xl font-bold">{{ $ragStats['total_chunks'] }}</p>
            <p class="text-xs mt-1">
                @if($ragStats['null_chunks'] > 0)
                    <span class="text-red-600 dark:text-red-400">{{ $ragStats['null_chunks'] }} missing embeddings</span>
                @else
                    <span class="text-green-600 dark:text-green-400">all embedded</span>
                @endif
            </p>
        </a>
        @else
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm col-span-2">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">RAG</p>
            <p class="text-sm text-gray-500 dark:text-gray-400">Disabled — set <code class="config-key">AI_CHATBOX_RAG=true</code> to enable</p>
        </div>
        @endif

        {{-- Memory stats --}}
        @if($memoryStats && !isset($memoryStats['error']))
        <a href="{{ $conversationsUrl }}" class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm block hover:shadow-md hover:brightness-[0.97] dark:hover:brightness-110 transition-all" title="View conversations">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Conversations</p>
            <p class="text-2xl font-bold">{{ $memoryStats['conversations'] }}</p>
            <p class="text-xs text-gray-400 mt-1">database driver</p>
        </a>
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Messages</p>
            <p class="text-2xl font-bold">{{ $memoryStats['messages'] }}</p>
            <p class="text-xs text-gray-400 mt-1">across all threads</p>
        </div>
        @elseif($memoryStats && isset($memoryStats['error']))
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm col-span-2">
            <p class="text-xs text-red-500 mb-1">Memory DB Error</p>
            <p class="text-xs text-gray-500">{{ $memoryStats['error'] }}</p>
        </div>
        @else
        <div class="stat-card bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm col-span-2">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Memory Driver</p>
            <p class="text-sm font-medium">Session</p>
            <p class="text-xs text-gray-400 mt-1">history not persisted to DB</p>
        </div>
        @endif
    </div>

    {{-- ── Diagnostics panel ────────────────────────────────────────────────── --}}
    @php
        $diagErrors   = collect($diagnostics)->where('level', 'error');
        $diagWarnings = collect($diagnostics)->where('level', 'warning');
        $diagInfos    = collect($diagnostics)->where('level', 'info');
    @endphp

    @if($diagErrors->isNotEmpty())
    <div class="mb-4 rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-red-100 dark:bg-red-900/40 border-b border-red-200 dark:border-red-800">
            <svg class="w-4 h-4 text-red-600 dark:text-red-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9.303 3.376c.866 1.5-.217 3.374-1.948 3.374H4.645c-1.73 0-2.813-1.874-1.948-3.374L10.05 3.378c.866-1.5 3.032-1.5 3.898 0l5.355 9.748z"/>
            </svg>
            <span class="text-sm font-semibold text-red-700 dark:text-red-300">{{ $diagErrors->count() }} Configuration Error{{ $diagErrors->count() > 1 ? 's' : '' }}</span>
        </div>
        <ul class="divide-y divide-red-100 dark:divide-red-800/50">
            @foreach($diagErrors->groupBy('group') as $group => $items)
                @foreach($items as $d)
                <li class="flex items-start gap-3 px-4 py-3 text-sm">
                    <span class="badge badge-red mt-0.5 shrink-0">{{ $group }}</span>
                    <span class="text-red-700 dark:text-red-300">{{ $d['message'] }}</span>
                </li>
                @endforeach
            @endforeach
        </ul>
    </div>
    @endif

    @if($diagWarnings->isNotEmpty())
    <div class="mb-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-amber-100 dark:bg-amber-900/40 border-b border-amber-200 dark:border-amber-800">
            <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126z"/>
            </svg>
            <span class="text-sm font-semibold text-amber-700 dark:text-amber-300">{{ $diagWarnings->count() }} Warning{{ $diagWarnings->count() > 1 ? 's' : '' }}</span>
        </div>
        <ul class="divide-y divide-amber-100 dark:divide-amber-800/50">
            @foreach($diagWarnings->groupBy('group') as $group => $items)
                @foreach($items as $d)
                <li class="flex items-start gap-3 px-4 py-3 text-sm">
                    <span class="badge badge-yellow mt-0.5 shrink-0">{{ $group }}</span>
                    <span class="text-amber-700 dark:text-amber-300">{{ $d['message'] }}</span>
                </li>
                @endforeach
            @endforeach
        </ul>
    </div>
    @endif

    @if($diagInfos->isNotEmpty())
    <div class="mb-4 rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 overflow-hidden">
        <div class="flex items-center gap-2 px-4 py-2.5 bg-blue-100 dark:bg-blue-900/40 border-b border-blue-200 dark:border-blue-800">
            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>
            </svg>
            <span class="text-sm font-semibold text-blue-700 dark:text-blue-300">{{ $diagInfos->count() }} Notice{{ $diagInfos->count() > 1 ? 's' : '' }}</span>
        </div>
        <ul class="divide-y divide-blue-100 dark:divide-blue-800/50">
            @foreach($diagInfos->groupBy('group') as $group => $items)
                @foreach($items as $d)
                <li class="flex items-start gap-3 px-4 py-3 text-sm">
                    <span class="badge badge-blue mt-0.5 shrink-0">{{ $group }}</span>
                    <span class="text-blue-700 dark:text-blue-300">{{ $d['message'] }}</span>
                </li>
                @endforeach
            @endforeach
        </ul>
    </div>
    @endif

    @if(empty($diagnostics))
    <div class="mb-6 flex items-center gap-2 rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-300">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
        </svg>
        All configuration checks passed.
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- ── Left column — config groups ─────────────────────────────────── --}}
        <div class="lg:col-span-2 space-y-5">

            @foreach($configGroups as $groupName => $keys)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <span class="section-heading">{{ $groupName }}</span>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @foreach($keys as $key => $val)
                    <div class="flex items-start gap-3 px-5 py-3">
                        <span class="config-key text-gray-500 dark:text-gray-400 shrink-0 w-52">{{ $key }}</span>
                        <span class="config-val text-gray-800 dark:text-gray-200 flex-1">
                            @if(is_null($val))
                                <span class="text-gray-400 dark:text-gray-500 italic">null</span>
                            @elseif(is_bool($val))
                                <span class="badge {{ $val ? 'badge-green' : 'badge-gray' }}">{{ $val ? 'true' : 'false' }}</span>
                            @elseif(is_array($val))
                                {{ implode(', ', $val) }}
                            @elseif($key === 'api_token')
                                {{-- Mask token — show first 6 chars only --}}
                                {{ substr($val, 0, 6) }}{{ strlen($val) > 6 ? str_repeat('•', min(12, strlen($val) - 6)) : '' }}
                            @elseif($key === 'system_prompt' || $key === 'rag_context_prompt')
                                <span class="line-clamp-2 text-gray-600 dark:text-gray-400">{{ $val }}</span>
                            @elseif($key === 'active_provider')
                                <span class="badge badge-green">{{ $val ?: 'default' }}</span>
                            @else
                                {{ $val }}
                            @endif
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach

        </div>

        {{-- ── Right column — providers, env, links ─────────────────────────── --}}
        <div class="space-y-5">

            {{-- Quick links --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <span class="section-heading">Quick Links</span>
                </div>
                <div class="px-5 py-4 space-y-2">
                    @if($ragEnabled)
                    <a href="{{ $ragUrl }}" class="flex items-center gap-2 text-sm hover:underline" style="color:var(--theme)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>
                        Knowledge Base (RAG)
                    </a>
                    @endif
                    <a href="{{ url(config('ai-chatbox.route_prefix') . '/health') }}" target="_blank" class="flex items-center gap-2 text-sm hover:underline" style="color:var(--theme)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z" /></svg>
                        Health Check
                    </a>
                    <a href="/" class="flex items-center gap-2 text-sm hover:underline" style="color:var(--theme)">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                        App Home
                    </a>
                </div>
            </div>

            {{-- Named providers --}}
            @if(!empty($namedProviders))
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                    <span class="section-heading">Named Providers</span>
                </div>
                <div class="px-5 py-3 space-y-3">
                    @foreach($namedProviders as $name => $provider)
                    @php $isActive = ($name === $activeProvider); @endphp
                    <div class="rounded-lg px-3 py-2.5 {{ $isActive ? 'bg-gray-50 dark:bg-gray-700/50' : 'bg-gray-50 dark:bg-gray-700/30' }}"
                         style="{{ $isActive ? 'outline: 2px solid var(--theme); outline-offset: -2px;' : '' }}">
                        <div class="flex items-center gap-2 mb-1.5">
                            <p class="text-xs font-semibold {{ $isActive ? 'text-gray-900 dark:text-gray-100' : 'text-gray-700 dark:text-gray-300' }}">{{ $name }}</p>
                            @if($isActive)
                            <span class="badge badge-green">active</span>
                            @endif
                        </div>
                        @foreach($provider as $k => $v)
                        <div class="flex items-start gap-2 text-xs mb-0.5">
                            <span class="config-key text-gray-400 shrink-0 w-24">{{ $k }}</span>
                            <span class="config-val {{ $isActive ? 'text-gray-700 dark:text-gray-200' : 'text-gray-600 dark:text-gray-300' }} flex-1 break-all">
                                @if($k === 'api_token')
                                    {{ substr($v, 0, 6) }}{{ strlen($v) > 6 ? '••••••' : '' }}
                                @else
                                    {{ $v ?: '—' }}
                                @endif
                            </span>
                        </div>
                        @endforeach
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Frontend Driver --}}
            @php
                $drivers = [
                    'vue'      => ['label' => 'Vue 3',    'desc' => 'Pre-built Vue 3 widget. Zero-config, recommended default.',              'req' => null],
                    'blade'    => ['label' => 'Blade',    'desc' => 'Vanilla JS widget. No framework required.',                              'req' => null],
                    'livewire' => ['label' => 'Livewire', 'desc' => 'Alpine.js widget mounted via Livewire.',                                 'req' => 'livewire/livewire'],
                    'none'     => ['label' => 'None',     'desc' => 'Outputs window.AiChatboxConfig only. Bring your own frontend.',          'req' => null],
                ];
                $livewireInstalled = class_exists(\Livewire\Livewire::class);
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <span class="section-heading">Frontend Driver</span>
                </div>
                <div class="px-5 py-3 space-y-2">
                    @foreach($drivers as $driverKey => $info)
                    @php $isActiveFrontend = ($frontend === $driverKey); @endphp
                    <div class="rounded-lg px-3 py-2.5 bg-gray-50 dark:bg-gray-700/30"
                         style="{{ $isActiveFrontend ? 'outline: 2px solid var(--theme); outline-offset: -2px;' : '' }}">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-xs font-semibold {{ $isActiveFrontend ? 'text-gray-900 dark:text-gray-100' : 'text-gray-600 dark:text-gray-400' }}">{{ $info['label'] }}</span>
                            @if($isActiveFrontend)
                                <span class="badge badge-green">active</span>
                            @endif
                            @if($info['req'])
                                @if($driverKey === 'livewire' && !$livewireInstalled)
                                    <span class="badge badge-red">not installed</span>
                                @elseif($driverKey === 'livewire')
                                    <span class="badge badge-green">installed</span>
                                @endif
                            @endif
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $info['desc'] }}</p>
                        @if($info['req'])
                            <p class="text-xs mt-1 font-mono {{ ($driverKey === 'livewire' && !$livewireInstalled) ? 'text-red-500 dark:text-red-400' : 'text-gray-400' }}">
                                requires: {{ $info['req'] }}
                            </p>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- RAG embedding status --}}
            @if($ragEnabled)
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <span class="section-heading">Embedding Status</span>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-gray-400">URL</span>
                        <span class="config-val text-xs text-right break-all text-gray-700 dark:text-gray-300">
                            {{ config('ai-chatbox.rag_embedding_url') ?: '—' }}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-gray-400">Model</span>
                        <span class="config-val text-xs text-gray-700 dark:text-gray-300">
                            {{ config('ai-chatbox.rag_embedding_model') ?: '—' }}
                        </span>
                    </div>
                    @if($ragStats)
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-gray-500 dark:text-gray-400">Coverage</span>
                        @php
                            $total = $ragStats['total_chunks'];
                            $embedded = $ragStats['embedded_chunks'];
                            $pct = $total > 0 ? round($embedded / $total * 100) : 0;
                        @endphp
                        <span class="badge {{ $pct === 100 ? 'badge-green' : ($pct === 0 ? 'badge-red' : 'badge-yellow') }}">
                            {{ $pct }}% ({{ $embedded }}/{{ $total }})
                        </span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Environment --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
                    <span class="section-heading">Environment</span>
                </div>
                <div class="divide-y divide-gray-50 dark:divide-gray-700/60">
                    @foreach(['laravel' => 'Laravel', 'php' => 'PHP', 'app_env' => 'App Env', 'app_url' => 'App URL'] as $k => $label)
                    <div class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                        <span class="text-gray-500 dark:text-gray-400 shrink-0">{{ $label }}</span>
                        <span class="config-val text-xs text-right text-gray-700 dark:text-gray-300 break-all">{{ $env[$k] }}</span>
                    </div>
                    @endforeach
                    <div class="flex items-center justify-between gap-2 px-5 py-2.5 text-sm">
                        <span class="text-gray-500 dark:text-gray-400 shrink-0">Debug</span>
                        <span class="badge {{ $env['app_debug'] ? 'badge-red' : 'badge-green' }}">{{ $env['app_debug'] ? 'ON' : 'off' }}</span>
                    </div>
                </div>
            </div>

        </div>{{-- end right column --}}
    </div>{{-- end grid --}}

</div>{{-- end container --}}
</body>
</html>
