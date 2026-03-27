<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI Chatbox — Knowledge Base</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        [x-cloak] { display: none !important; }
        .badge-ready    { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800; }
        .badge-pending  { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800; }
        .badge-processing { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800; }
        .badge-failed   { @apply inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 antialiased min-h-screen">

<div class="max-w-5xl mx-auto px-4 py-10">

    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">AI Chatbox — Knowledge Base</h1>
            <p class="mt-1 text-sm text-gray-500">
                Upload <code class="bg-gray-100 px-1 rounded">.md</code> or
                <code class="bg-gray-100 px-1 rounded">.txt</code> files.
                The chatbox will retrieve relevant context from them automatically.
            </p>
        </div>
        <div class="text-right text-xs text-gray-400 space-y-0.5">
            <div>
                RAG:
                @if($ragEnabled)
                    <span class="text-green-600 font-semibold">Enabled</span>
                @else
                    <span class="text-red-500 font-semibold">Disabled</span>
                    <span class="text-gray-400">(set <code>AI_CHATBOX_RAG=true</code>)</span>
                @endif
            </div>
            <div>Model: <span class="text-gray-600">{{ $embeddingModel }}</span></div>
            <div>Endpoint: <span class="text-gray-600 break-all">{{ $embeddingUrl }}</span></div>
        </div>
    </div>

    {{-- ── Flash messages ───────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="mb-6 flex items-start gap-3 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 flex items-start gap-3 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-1-5h2v2H9v-2zm0-8h2v6H9V5z" clip-rule="evenodd"/>
            </svg>
            {{ session('error') }}
        </div>
    @endif

    @if(!empty($errors) && $errors->any())
        <div class="mb-6 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Upload form ──────────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-8">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-800">Upload Document</h2>
        </div>
        <form action="{{ route('ai-chatbox.rag.store') }}" method="POST" enctype="multipart/form-data"
              class="px-6 py-5 space-y-4">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="rag-file" class="block text-sm font-medium text-gray-700 mb-1">
                        File <span class="text-red-500">*</span>
                    </label>
                    <input id="rag-file" name="file" type="file" accept=".md,.txt" required
                           class="block w-full text-sm text-gray-600 file:mr-4 file:py-2 file:px-4
                                  file:rounded-lg file:border-0 file:text-sm file:font-medium
                                  file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100
                                  border border-gray-300 rounded-lg px-3 py-1.5 cursor-pointer">
                    <p class="mt-1 text-xs text-gray-400">Accepted: .md, .txt — Max 10 MB</p>
                </div>

                <div>
                    <label for="rag-title" class="block text-sm font-medium text-gray-700 mb-1">
                        Display Title <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input id="rag-title" name="title" type="text" placeholder="Leave blank to use filename"
                           value="{{ old('title') }}"
                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2
                               text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none
                               focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zM6.293 6.707a1 1 0 010-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L11 5.414V13a1 1 0 11-2 0V5.414L7.707 6.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    Upload &amp; Index
                </button>
                <p class="text-xs text-gray-400">
                    The file will be chunked and embedded immediately.
                    Large files may take a moment.
                </p>
            </div>
        </form>
    </div>

    {{-- ── Documents table ──────────────────────────────────────────────── --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-800">
                Indexed Documents
                <span class="ml-2 text-xs font-normal text-gray-400">({{ $documents->count() }})</span>
            </h2>
        </div>

        @if($documents->isEmpty())
            <div class="px-6 py-16 text-center text-gray-400">
                <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                </svg>
                <p class="text-sm">No documents yet. Upload your first file above.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-6 py-3 text-left">Title</th>
                            <th class="px-6 py-3 text-left">File</th>
                            <th class="px-6 py-3 text-center">Status</th>
                            <th class="px-6 py-3 text-center">Chunks</th>
                            <th class="px-6 py-3 text-left">Uploaded</th>
                            <th class="px-6 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($documents as $doc)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 font-medium text-gray-900">
                                    {{ $doc->title }}
                                    @if($doc->status === 'failed' && $doc->error_message)
                                        <p class="mt-0.5 text-xs text-red-500 font-normal truncate max-w-xs" title="{{ $doc->error_message }}">
                                            {{ $doc->error_message }}
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-gray-500">
                                    <span class="inline-flex items-center gap-1">
                                        <span class="uppercase text-xs font-mono bg-gray-100 px-1.5 py-0.5 rounded text-gray-600">{{ $doc->file_type }}</span>
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
                                <td class="px-6 py-4 text-center text-gray-600">
                                    {{ $doc->chunk_count ?: '—' }}
                                </td>
                                <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                    {{ $doc->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Reprocess --}}
                                        <form action="{{ route('ai-chatbox.rag.reprocess', $doc->id) }}" method="POST"
                                              onsubmit="return confirm('Reprocess and re-embed this document?')">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded px-2.5 py-1.5 text-xs font-medium
                                                           text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition"
                                                    title="Re-chunk and re-embed">
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
                                                           text-red-700 bg-red-50 hover:bg-red-100 transition">
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
    <p class="mt-6 text-center text-xs text-gray-400">
        AI Chatbox Knowledge Base &mdash; powered by
        <a href="https://github.com/syafiq-unijaya/laravel-ai-chatbox"
           class="underline hover:text-gray-600">syafiq-unijaya/laravel-ai-chatbox</a>
    </p>

</div>
</body>
</html>
