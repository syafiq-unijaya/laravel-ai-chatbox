@php $scheme = $colorScheme ?? 'auto'; @endphp
<!DOCTYPE html>
<html lang="en" @if($scheme === 'dark') class="dark" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chatbox — Knowledge Base</title>
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
            border-radius: 0.5rem; padding: 0.5rem 1.125rem;
            font-size: 0.875rem; font-weight: 500;
            transition: filter 0.15s, opacity 0.15s;
            border: none; cursor: pointer;
        }
        .btn-primary:hover:not(:disabled)  { filter: brightness(0.88); }
        .btn-primary:focus  { outline: 2px solid var(--theme); outline-offset: 2px; }
        .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }

        .btn-reprocess {
            display: inline-flex; align-items: center; gap: 0.25rem;
            border-radius: 0.375rem; padding: 0.375rem 0.75rem;
            font-size: 0.75rem; font-weight: 500;
            color: var(--theme);
            background-color: color-mix(in srgb, var(--theme) 10%, transparent);
            transition: background-color 0.15s, opacity 0.15s;
            border: none; cursor: pointer;
        }
        .btn-reprocess:hover:not(:disabled) { background-color: color-mix(in srgb, var(--theme) 18%, transparent); }
        .btn-reprocess:disabled { opacity: 0.55; cursor: not-allowed; }

        .focus-theme:focus {
            outline: none;
            box-shadow: 0 0 0 2px var(--theme);
        }

        .file-btn::file-selector-button {
            margin-right: 1rem; padding: 0.375rem 1rem;
            border-radius: 0.5rem; border: none;
            font-size: 0.875rem; font-weight: 500; cursor: pointer;
            background-color: color-mix(in srgb, var(--theme) 10%, transparent);
            color: var(--theme);
            transition: background-color 0.15s;
        }
        .file-btn::file-selector-button:hover {
            background-color: color-mix(in srgb, var(--theme) 18%, transparent);
        }

        /* Status badges */
        .badge { display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.2rem 0.55rem; border-radius: 0.25rem; font-size: 0.72rem; font-weight: 600; white-space: nowrap; }
        .badge-ready      { background: #dcfce7; color: #166534; }
        .badge-pending    { background: #fef9c3; color: #854d0e; }
        .badge-processing { background: #dbeafe; color: #1e40af; }
        .badge-failed     { background: #fee2e2; color: #991b1b; }
        .dark .badge-ready      { background: #14532d; color: #86efac; }
        .dark .badge-pending    { background: #713f12; color: #fde68a; }
        .dark .badge-processing { background: #1e3a5f; color: #93c5fd; }
        .dark .badge-failed     { background: #7f1d1d; color: #fca5a5; }

        /* Spinner */
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner {
            display: inline-block;
            width: 1rem; height: 1rem;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.7s linear infinite;
            flex-shrink: 0;
        }
        .spinner-lg {
            width: 2.5rem; height: 2.5rem;
            border-width: 3px;
        }

        /* Upload overlay */
        #upload-overlay {
            display: none;
            position: fixed; inset: 0; z-index: 50;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(2px);
            align-items: center; justify-content: center;
        }
        #upload-overlay.visible { display: flex; }

        /* Progress bar */
        .progress-bar {
            height: 3px;
            background-color: color-mix(in srgb, var(--theme) 25%, transparent);
            border-radius: 9999px;
            overflow: hidden;
        }
        .progress-bar-inner {
            height: 100%;
            background-color: var(--theme);
            border-radius: 9999px;
            animation: indeterminate 1.4s ease-in-out infinite;
            width: 40%;
        }
        @keyframes indeterminate {
            0%   { transform: translateX(-150%); }
            100% { transform: translateX(350%); }
        }

        /* Row highlight for reprocessing */
        tr.row-processing { background-color: color-mix(in srgb, var(--theme) 4%, transparent) !important; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased min-h-screen">

{{-- ── Upload overlay ─────────────────────────────────────────────────── --}}
<div id="upload-overlay" role="status" aria-live="polite">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl px-10 py-8 flex flex-col items-center gap-4 max-w-xs w-full mx-4 text-center">
        <div class="spinner spinner-lg" style="color:var(--theme)"></div>
        <div>
            <p id="overlay-title" class="font-semibold text-gray-800 dark:text-gray-100 text-base">Uploading &amp; Indexing…</p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Chunking and embedding document.<br>This may take a moment for large files.</p>
        </div>
        <div class="progress-bar w-full">
            <div class="progress-bar-inner"></div>
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">Please don't close this tab.</p>
    </div>
</div>

<div class="w-full px-6 py-10">

    {{-- ── Header ──────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-8">
        <div>
            <div class="mb-1">
                <a href="{{ route('ai-chatbox.admin.index') }}" class="text-xs font-medium hover:underline" style="color:var(--theme)">← Admin</a>
            </div>
            <h1 class="text-2xl font-bold">AI Chatbox — Knowledge Base</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.md</code> or
                <code class="bg-gray-100 dark:bg-gray-700 px-1 rounded">.txt</code> files.
                Relevant chunks are automatically injected into every AI request.
            </p>
        </div>
        <div class="text-right text-xs text-gray-400 dark:text-gray-500 space-y-0.5 shrink-0">
            <div>
                RAG:
                @if($ragEnabled)
                    <span class="text-green-600 dark:text-green-400 font-semibold">Enabled</span>
                @else
                    <span class="text-red-500 dark:text-red-400 font-semibold">Disabled</span>
                @endif
            </div>
            <div>Model: <span class="text-gray-600 dark:text-gray-400">{{ $embeddingModel }}</span></div>
            <div class="max-w-xs break-all">Endpoint: <span class="text-gray-600 dark:text-gray-400">{{ $embeddingUrl }}</span></div>
        </div>
    </div>

    {{-- ── Flash messages ───────────────────────────────────────────────── --}}
    @if(session('success'))
    <div class="mb-5 flex items-start gap-3 rounded-xl bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-green-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        {{ session('success') }}
    </div>
    @endif

    @if(session('error'))
    <div class="mb-5 flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2H9v-2zm0-8h2v6H9V5z" clip-rule="evenodd"/>
        </svg>
        {{ session('error') }}
    </div>
    @endif

    @if(!empty($errors) && $errors->any())
    <div class="mb-5 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    {{-- ── Embedding config warning ──────────────────────────────────────── --}}
    @if(!$embeddingConfigured)
    <div class="mb-6 flex items-start gap-3 rounded-xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 px-5 py-4 text-sm text-red-800 dark:text-red-300">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <div>
            <p class="font-semibold">Embedding not configured — upload and reprocess are disabled</p>
            <p class="mt-1 text-red-700 dark:text-red-400">
                @if(empty($embeddingUrl))
                    <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">rag_embedding_url</code> is not set.
                @endif
                @if(empty($embeddingModel))
                    <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">rag_embedding_model</code> is not set.
                @endif
                Set the provider-specific env var (e.g. <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">LMSTUDIO_EMBEDDING_URL</code> + <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">LMSTUDIO_EMBEDDING_MODEL</code>) or the global <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">AI_CHATBOX_EMBEDDING_URL</code> + <code class="bg-red-100 dark:bg-red-900/50 px-1 rounded">AI_CHATBOX_EMBEDDING_MODEL</code>.
            </p>
        </div>
    </div>
    @endif

    {{-- ── Upload form ──────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mb-6 {{ !$embeddingConfigured ? 'opacity-60' : '' }}">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">Upload Document</h2>
        </div>
        <form id="upload-form" action="{{ route('ai-chatbox.rag.store') }}" method="POST" enctype="multipart/form-data"
              class="px-6 py-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label for="rag-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        File <span class="text-red-500">*</span>
                    </label>
                    <input id="rag-file" name="file" type="file" accept=".md,.txt" required
                           {{ !$embeddingConfigured ? 'disabled' : '' }}
                           class="file-btn block w-full text-sm text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg px-3 py-1.5 {{ !$embeddingConfigured ? 'cursor-not-allowed' : 'cursor-pointer' }} focus-theme">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Accepted: .md, .txt &mdash; Max 10 MB</p>
                </div>
                <div>
                    <label for="rag-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Display Title <span class="text-gray-400 font-normal text-xs">(optional)</span>
                    </label>
                    <input id="rag-title" name="title" type="text" placeholder="Leave blank to use filename"
                           value="{{ old('title') }}"
                           {{ !$embeddingConfigured ? 'disabled' : '' }}
                           class="focus-theme block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button id="upload-btn" type="submit" class="btn-primary" {{ !$embeddingConfigured ? 'disabled' : '' }}>
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Upload &amp; Index
                </button>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    File is chunked and embedded immediately. Large documents may take a minute.
                </p>
            </div>
        </form>
    </div>

    {{-- ── Documents table ──────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-800 dark:text-gray-200 uppercase tracking-wide">
                Indexed Documents
                <span class="ml-2 text-xs font-normal normal-case text-gray-400 dark:text-gray-500">({{ $documents->count() }})</span>
            </h2>
        </div>

        @if($documents->isEmpty())
            <div class="px-6 py-16 text-center text-gray-400 dark:text-gray-500">
                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <p class="text-sm">No documents yet. Upload your first file above.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 text-left">Title</th>
                            <th class="px-6 py-3 text-left">File</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-center">Chunks</th>
                            <th class="px-6 py-3 text-left">Uploaded</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach($documents as $doc)
                        <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/30 transition-colors" id="row-{{ $doc->id }}">

                            {{-- Title + error message --}}
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100 max-w-[220px]">
                                <span class="block truncate" title="{{ $doc->title }}">{{ $doc->title }}</span>
                                @if($doc->error_message)
                                <details class="mt-1">
                                    <summary class="text-xs cursor-pointer select-none
                                        {{ $doc->status === 'failed' ? 'text-red-500 dark:text-red-400' : 'text-amber-600 dark:text-amber-400' }}
                                        hover:underline list-none flex items-center gap-1">
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        {{ $doc->status === 'failed' ? 'View error' : 'Warning' }}
                                    </summary>
                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400 leading-relaxed">{{ $doc->error_message }}</p>
                                </details>
                                @endif
                            </td>

                            {{-- File type + name --}}
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="uppercase text-xs font-mono bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-300">{{ $doc->file_type }}</span>
                                    <span class="truncate max-w-[160px] text-xs" title="{{ $doc->original_filename }}">{{ $doc->original_filename }}</span>
                                </span>
                            </td>

                            {{-- Status badge --}}
                            <td class="px-6 py-4 text-center">
                                <span class="badge badge-{{ $doc->status }}" id="status-{{ $doc->id }}">
                                    @if($doc->status === 'ready')
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                        Ready
                                    @elseif($doc->status === 'processing')
                                        <span class="spinner" style="width:0.65rem;height:0.65rem;border-width:1.5px"></span>
                                        Processing
                                    @elseif($doc->status === 'failed')
                                        <svg class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                                        Failed
                                    @else
                                        Pending
                                    @endif
                                </span>
                            </td>

                            {{-- Chunk count --}}
                            <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400 tabular-nums">
                                {{ $doc->chunk_count ?: '—' }}
                            </td>

                            {{-- Uploaded at --}}
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap text-xs">
                                {{ $doc->created_at->diffForHumans() }}
                            </td>

                            {{-- Actions --}}
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2" id="actions-{{ $doc->id }}">

                                    {{-- Reprocess --}}
                                    <form action="{{ route('ai-chatbox.rag.reprocess', $doc->id) }}" method="POST"
                                          class="reprocess-form" data-doc-id="{{ $doc->id }}" data-doc-title="{{ $doc->title }}">
                                        @csrf
                                        <button type="submit" class="btn-reprocess" {{ !$embeddingConfigured ? 'disabled title="Configure embedding URL and model first"' : '' }}>
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                            </svg>
                                            Reprocess
                                        </button>
                                    </form>

                                    {{-- Delete --}}
                                    <form action="{{ route('ai-chatbox.rag.destroy', $doc->id) }}" method="POST"
                                          class="delete-form" data-doc-title="{{ $doc->title }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-md px-2.5 py-1.5 text-xs font-medium
                                                       text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30
                                                       hover:bg-red-100 dark:hover:bg-red-900/50 transition-colors border-none cursor-pointer">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                            </svg>
                                            Delete
                                        </button>
                                    </form>

                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ── Footer ──────────────────────────────────────────────────────────── --}}
    <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-600">
        AI Chatbox Knowledge Base &mdash; powered by
        <a href="https://github.com/syafiq-unijaya/laravel-ai-chatbox" class="underline hover:text-gray-600 dark:hover:text-gray-400">syafiq-unijaya/laravel-ai-chatbox</a>
    </p>

</div>

<script>
(function () {
    'use strict';

    var overlay     = document.getElementById('upload-overlay');
    var overlayTitle = document.getElementById('overlay-title');
    var uploadForm  = document.getElementById('upload-form');
    var uploadBtn   = document.getElementById('upload-btn');

    // ── Upload form ───────────────────────────────────────────────────────
    uploadForm.addEventListener('submit', function (e) {
        var fileInput = document.getElementById('rag-file');
        if (!fileInput.files.length) return; // let browser validation handle it

        uploadBtn.disabled = true;
        uploadBtn.innerHTML = '<span class="spinner"></span> Uploading…';
        overlayTitle.textContent = 'Uploading & Indexing…';
        overlay.classList.add('visible');
    });

    // ── Reprocess forms ───────────────────────────────────────────────────
    document.querySelectorAll('.reprocess-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var docId    = form.dataset.docId;
            var docTitle = form.dataset.docTitle;

            if (!confirm('Re-chunk and re-embed "' + docTitle + '"?')) {
                e.preventDefault();
                return;
            }

            var row   = document.getElementById('row-' + docId);
            var badge = document.getElementById('status-' + docId);
            var btn   = form.querySelector('button[type="submit"]');

            // Disable all action buttons in this row
            var actionBtns = document.querySelectorAll('#actions-' + docId + ' button');
            actionBtns.forEach(function (b) { b.disabled = true; });

            // Show spinner in row
            btn.innerHTML = '<span class="spinner" style="width:0.65rem;height:0.65rem;border-width:1.5px"></span> Reprocessing…';
            row.classList.add('row-processing');

            // Update badge
            badge.className = 'badge badge-processing';
            badge.innerHTML = '<span class="spinner" style="width:0.65rem;height:0.65rem;border-width:1.5px"></span> Reprocessing';

            // Show overlay with different message
            overlayTitle.textContent = 'Reprocessing "' + docTitle + '"…';
            overlay.classList.add('visible');
        });
    });

    // ── Delete forms ──────────────────────────────────────────────────────
    document.querySelectorAll('.delete-form').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            var docTitle = form.dataset.docTitle;
            if (!confirm('Delete "' + docTitle + '" and all its chunks? This cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
})();
</script>
</body>
</html>
