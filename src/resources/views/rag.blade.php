@php $scheme = $colorScheme ?? 'auto'; @endphp
<!DOCTYPE html>
<html lang="en" @if($scheme === 'dark') class="dark" @endif>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chatbox — Knowledge Base</title>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script src="https://cdn.tailwindcss.com"></script>
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

        /* Theme-colored elements via CSS custom property */
        .btn-primary {
            background-color: var(--theme);
            color: #fff;
            display: inline-flex; align-items: center; gap: 0.5rem;
            border-radius: 0.5rem; padding: 0.5rem 1rem;
            font-size: 0.875rem; font-weight: 500;
            transition: filter 0.15s;
            border: none; cursor: pointer;
        }
        .btn-primary:hover  { filter: brightness(0.88); }
        .btn-primary:focus  { outline: 2px solid var(--theme); outline-offset: 2px; }

        .btn-reprocess {
            display: inline-flex; align-items: center; gap: 0.25rem;
            border-radius: 0.25rem; padding: 0.375rem 0.625rem;
            font-size: 0.75rem; font-weight: 500;
            color: var(--theme);
            background-color: color-mix(in srgb, var(--theme) 10%, transparent);
            transition: background-color 0.15s;
            border: none; cursor: pointer;
        }
        .btn-reprocess:hover { background-color: color-mix(in srgb, var(--theme) 18%, transparent); }

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

        /* Status badges — light */
        .badge-ready      { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; background: #dcfce7; color: #166534; }
        .badge-pending    { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; background: #fef9c3; color: #854d0e; }
        .badge-processing { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; background: #dbeafe; color: #1e40af; }
        .badge-failed     { display: inline-flex; align-items: center; padding: 0.125rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 500; background: #fee2e2; color: #991b1b; }

        /* Status badges — dark */
        .dark .badge-ready      { background: #14532d; color: #86efac; }
        .dark .badge-pending    { background: #713f12; color: #fde68a; }
        .dark .badge-processing { background: #1e3a5f; color: #93c5fd; }
        .dark .badge-failed     { background: #7f1d1d; color: #fca5a5; }
    </style>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 antialiased min-h-screen">

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">AI Chatbox — Knowledge Base</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Upload <code class="bg-gray-100 dark:bg-gray-700 dark:text-gray-300 px-1 rounded">.md</code> or
                <code class="bg-gray-100 dark:bg-gray-700 dark:text-gray-300 px-1 rounded">.txt</code> files.
                The chatbox will retrieve relevant context from them automatically.
            </p>
        </div>
        <div class="text-right text-xs text-gray-400 dark:text-gray-500 space-y-0.5">
            <div>
                RAG:
                @if($ragEnabled)
                    <span class="text-green-600 dark:text-green-400 font-semibold">Enabled</span>
                @else
                    <span class="text-red-500 dark:text-red-400 font-semibold">Disabled</span>
                    <span class="text-gray-400 dark:text-gray-500">(set <code>AI_CHATBOX_RAG=true</code>)</span>
                @endif
            </div>
            <div>Model: <span class="text-gray-600 dark:text-gray-400">{{ $embeddingModel }}</span></div>
            <div>Endpoint: <span class="text-gray-600 dark:text-gray-400 break-all">{{ $embeddingUrl }}</span></div>
        </div>
    </div>

    {{-- ── Flash messages ───────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-6 flex items-start gap-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 px-4 py-3 text-green-800 dark:text-green-300 text-sm">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500 dark:text-green-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-start gap-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-4 py-3 text-red-800 dark:text-red-300 text-sm">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500 dark:text-red-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2H9v-2zm0-8h2v6H9V5z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    @if(!empty($errors) && $errors->any())
        <div class="mb-6 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 px-4 py-3 text-red-800 dark:text-red-300 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Upload form ──────────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm mb-8">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">Upload Document</h2>
        </div>
        <form action="{{ route('ai-chatbox.rag.store') }}" method="POST" enctype="multipart/form-data"
              class="px-6 py-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="rag-file" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        File <span class="text-red-500">*</span>
                    </label>
                    <input id="rag-file" name="file" type="file" accept=".md,.txt" required
                           class="file-btn block w-full text-sm text-gray-600 dark:text-gray-300 border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded-lg px-3 py-1.5 cursor-pointer focus-theme">
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Accepted: .md, .txt — Max 10 MB</p>
                </div>

                <div>
                    <label for="rag-title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Display Title <span class="text-gray-400 dark:text-gray-500 font-normal">(optional)</span>
                    </label>
                    <input id="rag-title" name="title" type="text" placeholder="Leave blank to use filename"
                           value="{{ old('title') }}"
                           class="focus-theme block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 px-3 py-2 text-sm">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit" class="btn-primary">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Upload &amp; Index
                </button>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    The file will be chunked and embedded immediately.
                    Large files may take a moment.
                </p>
            </div>
        </form>
    </div>

    {{-- ── Documents table ──────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800 dark:text-gray-200">
                Indexed Documents
                <span class="ml-2 text-xs font-normal text-gray-400 dark:text-gray-500">({{ $documents->count() }})</span>
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
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-gray-100">
                                    {{ $doc->title }}
                                    @if($doc->status === 'failed' && $doc->error_message)
                                        <p class="mt-0.5 text-xs text-red-500 dark:text-red-400 font-normal truncate max-w-xs" title="{{ $doc->error_message }}">
                                            {{ $doc->error_message }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="uppercase text-xs font-mono bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded text-gray-600 dark:text-gray-300">{{ $doc->file_type }}</span>
                                        <span class="truncate max-w-[180px]" title="{{ $doc->original_filename }}">{{ $doc->original_filename }}</span>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="badge-{{ $doc->status }}">
                                        @if($doc->status === 'ready') ✓ Ready
                                        @elseif($doc->status === 'processing') ⏳ Processing
                                        @elseif($doc->status === 'failed') ✗ Failed
                                        @else Pending
                                        @endif
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center text-gray-600 dark:text-gray-400">
                                    {{ $doc->chunk_count ?: '—' }}
                                </td>
                                <td class="px-6 py-4 text-gray-500 dark:text-gray-400 whitespace-nowrap">
                                    {{ $doc->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Reprocess --}}
                                        <form action="{{ route('ai-chatbox.rag.reprocess', $doc->id) }}" method="POST"
                                              onsubmit="return confirm('Reprocess and re-embed this document?')">
                                            @csrf
                                            <button type="submit" class="btn-reprocess" title="Re-chunk and re-embed">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                                </svg>
                                                Reprocess
                                            </button>
                                        </form>

                                        {{-- Delete --}}
                                        <form action="{{ route('ai-chatbox.rag.destroy', $doc->id) }}" method="POST"
                                              onsubmit="return confirm('Delete this document and all its chunks? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium
                                                           text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30 hover:bg-red-100 dark:hover:bg-red-900/50 transition">
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

    {{-- ── Info footer ──────────────────────────────────────────────────── --}}
    <p class="mt-6 text-center text-xs text-gray-400 dark:text-gray-600">
        AI Chatbox Knowledge Base &mdash; powered by
        <a href="https://github.com/syafiq-unijaya/laravel-ai-chatbox"
           class="underline hover:text-gray-600 dark:hover:text-gray-400">syafiq-unijaya/laravel-ai-chatbox</a>
    </p>

</div>
</body>
</html>
