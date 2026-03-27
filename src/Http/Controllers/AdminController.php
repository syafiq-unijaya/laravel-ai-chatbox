<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use SyafiqUnijaya\AiChatbox\Memory\Models\Conversation;
use SyafiqUnijaya\AiChatbox\Memory\Models\Message;
use SyafiqUnijaya\AiChatbox\Models\RagChunk;
use SyafiqUnijaya\AiChatbox\Models\RagDocument;

class AdminController extends Controller
{
    public function index(): View
    {
        $ragEnabled = (bool) config('ai-chatbox.rag_enabled');

        // ── RAG stats ─────────────────────────────────────────────────────────
        $ragStats = null;
        if ($ragEnabled) {
            $ragStats = [
                'documents' => RagDocument::count(),
                'documents_ready' => RagDocument::where('status', 'ready')->count(),
                'documents_failed' => RagDocument::where('status', 'failed')->count(),
                'total_chunks' => RagChunk::count(),
                'embedded_chunks' => RagChunk::whereNotNull('embedding')->count(),
                'null_chunks' => RagChunk::whereNull('embedding')->count(),
            ];
        }

        // ── Memory / conversation stats (database driver only) ────────────────
        $memoryStats = null;
        if (config('ai-chatbox.memory_driver') === 'database') {
            try {
                $memoryStats = [
                    'conversations' => \SyafiqUnijaya\AiChatbox\Memory\Models\Conversation::count(),
                    'messages' => \SyafiqUnijaya\AiChatbox\Memory\Models\Message::count(),
                ];
            } catch (\Throwable) {
                // Table may not exist yet (migration not run)
                $memoryStats = ['error' => 'Run php artisan migrate to create the conversations/messages tables.'];
            }
        }

        // ── Config groups ─────────────────────────────────────────────────────
        $cfg = config('ai-chatbox', []);

        $configGroups = [
            'AI API' => [
                'active_provider' => $cfg['active_provider'] ?? 'default',
                'api_url' => $cfg['api_url'] ?? null,
                'api_token' => $cfg['api_token'] ?? null,
                'api_model' => $cfg['api_model'] ?? null,
                'timeout' => $cfg['timeout'] ?? null,
            ],
            'Response' => [
                'language' => $cfg['language'] ?? null,
                'system_prompt' => $cfg['system_prompt'] ?? null,
                'temperature' => $cfg['temperature'] ?? null,
                'max_tokens' => $cfg['max_tokens'] ?? null,
            ],
            'Streaming & History' => [
                'stream' => $cfg['stream'] ?? null,
                'history_enabled' => $cfg['history_enabled'] ?? null,
                'history_limit' => $cfg['history_limit'] ?? null,
                'context_token_limit' => $cfg['context_token_limit'] ?? null,
                'memory_driver' => $cfg['memory_driver'] ?? null,
                'storage' => $cfg['storage'] ?? null,
            ],
            'Widget' => [
                'frontend' => $cfg['frontend'] ?? null,
                'title' => $cfg['title'] ?? null,
                'greeting' => $cfg['greeting'] ?? null,
                'placeholder' => $cfg['placeholder'] ?? null,
                'theme_color' => $cfg['theme_color'] ?? null,
                'color_scheme' => $cfg['color_scheme'] ?? null,
                'position' => $cfg['position'] ?? null,
                'markdown' => $cfg['markdown'] ?? null,
                'sound' => $cfg['sound'] ?? null,
                'sound_volume' => $cfg['sound_volume'] ?? null,
            ],
            'Routes & Security' => [
                'route_prefix' => $cfg['route_prefix'] ?? null,
                'middleware' => $cfg['middleware'] ?? null,
                'rate_limit' => $cfg['rate_limit'] ?? null,
                'rate_window' => $cfg['rate_window'] ?? null,
                'health_check' => $cfg['health_check'] ?? null,
                'ssrf_protection' => $cfg['ssrf_protection'] ?? null,
                'allowed_origins' => $cfg['allowed_origins'] ?? null,
            ],
            'RAG' => [
                'rag_enabled' => $cfg['rag_enabled'] ?? null,
                'rag_embedding_url' => $cfg['rag_embedding_url'] ?? null,
                'rag_embedding_model' => $cfg['rag_embedding_model'] ?? null,
                'rag_top_k' => $cfg['rag_top_k'] ?? null,
                'rag_chunk_size' => $cfg['rag_chunk_size'] ?? null,
                'rag_chunk_overlap' => $cfg['rag_chunk_overlap'] ?? null,
                'rag_similarity_threshold' => $cfg['rag_similarity_threshold'] ?? null,
                'rag_processing_timeout' => $cfg['rag_processing_timeout'] ?? null,
                'rag_admin_middleware' => $cfg['rag_admin_middleware'] ?? null,
                'rag_context_prompt' => $cfg['rag_context_prompt'] ?? null,
            ],
        ];

        // ── Named providers ───────────────────────────────────────────────────
        $namedProviders = $cfg['providers'] ?? [];

        // ── Diagnostics ───────────────────────────────────────────────────────
        // Each item: ['level' => 'error'|'warning'|'info', 'group' => string, 'message' => string]
        $diagnostics = [];

        // — AI API —
        $apiUrl = $cfg['api_url'] ?? '';
        if (empty($apiUrl)) {
            $diagnostics[] = ['level' => 'error', 'group' => 'AI API', 'message' => 'api_url (AI_CHATBOX_API_URL) is not set. The chatbox cannot connect to any AI provider.'];
        } else {
            $parsedHost = parse_url($apiUrl, PHP_URL_HOST);
            $isLocalUrl = in_array($parsedHost, ['localhost', '127.0.0.1', '::1'])
            || str_starts_with($parsedHost ?? '', '192.168.')
            || str_starts_with($parsedHost ?? '', '10.')
            || str_starts_with($parsedHost ?? '', '172.');
            if ($isLocalUrl && config('app.env') === 'production') {
                $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => "api_url points to a local/private address ({$parsedHost}) in a production environment. Ensure your AI service is reachable from the production server."];
            }
            if ($isLocalUrl && ($cfg['ssrf_protection'] ?? true)) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => "api_url points to a local address ({$parsedHost}) but ssrf_protection is enabled. Health-check pings to this URL will be blocked. Set AI_CHATBOX_SSRF_PROTECTION=false for local AI services."];
            }
        }
        $apiToken = $cfg['api_token'] ?? '';
        if (empty($apiToken)) {
            $diagnostics[] = ['level' => 'error', 'group' => 'AI API', 'message' => 'api_token (AI_CHATBOX_API_TOKEN) is not set. All API requests will be rejected.'];
        } elseif (in_array(strtolower($apiToken), ['your-api-key', 'your-api-token', 'sk-xxx', 'changeme', 'secret'])) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => 'api_token looks like a placeholder value. Make sure you have set a real API token.'];
        }
        if (empty($cfg['api_model'])) {
            $diagnostics[] = ['level' => 'error', 'group' => 'AI API', 'message' => 'api_model (AI_CHATBOX_MODEL) is not set. The AI provider won\'t know which model to use.'];
        }

        // — Active provider —
        $activeProvider = $cfg['active_provider'] ?? 'default';
        if (!empty($activeProvider) && $activeProvider !== 'default') {
            $providers = $cfg['providers'] ?? [];
            if (!array_key_exists($activeProvider, $providers)) {
                $diagnostics[] = ['level' => 'error', 'group' => 'AI API', 'message' => "active_provider is set to \"{$activeProvider}\" but no such provider is defined under 'providers' in the config. The chatbox will throw an exception on every request."];
            } else {
                $ap = $providers[$activeProvider];
                if (empty($ap['api_url'])) {
                    $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => "Active provider \"{$activeProvider}\" has no api_url set — it will inherit the top-level AI_CHATBOX_API_URL."];
                }
                if (empty($ap['api_token'])) {
                    $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => "Active provider \"{$activeProvider}\" has no api_token set — it will inherit the top-level AI_CHATBOX_API_TOKEN."];
                }
                if (empty($ap['api_model'])) {
                    $diagnostics[] = ['level' => 'warning', 'group' => 'AI API', 'message' => "Active provider \"{$activeProvider}\" has no api_model set — it will inherit the top-level AI_CHATBOX_API_MODEL."];
                }
            }
        }

                                                     // — Named providers —
                                                     // Named providers are optional — only warn if a provider looks intentionally
                                                     // configured (non-empty token or non-default URL) but is incomplete.
                                                     // Providers left at package defaults (empty token, stock URL) are silently skipped.
        $defaultTokens = ['ollama', 'lmstudio', '']; // package defaults that signal "not set up"
        foreach (($cfg['providers'] ?? []) as $providerName => $providerCfg) {
            $pToken = $providerCfg['api_token'] ?? '';
            $pUrl = $providerCfg['api_url'] ?? '';
            $pModel = $providerCfg['api_model'] ?? '';

            $hasCustomToken = !empty($pToken) && !in_array(strtolower($pToken), $defaultTokens);
            $hasCustomUrl = !empty($pUrl);

            // Only validate providers the user has partially or fully configured
            $looksConfigured = $hasCustomToken || $hasCustomUrl;
            if (!$looksConfigured) {
                continue;
            }

            // URL must be valid if provided
            if (!empty($pUrl) && !filter_var($pUrl, FILTER_VALIDATE_URL)) {
                $diagnostics[] = ['level' => 'error', 'group' => 'Providers', 'message' => "Named provider \"{$providerName}\" has an invalid api_url: \"{$pUrl}\"."];
            }
            // Token missing on a provider the user appears to be using
            if (empty($pToken)) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'Providers', 'message' => "Named provider \"{$providerName}\" has a URL configured but no api_token. Calls to this provider will fail."];
            }
            // Model missing on a provider the user appears to be using
            if (empty($pModel)) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'Providers', 'message' => "Named provider \"{$providerName}\" has no api_model set. Specify a model or it will inherit the global default."];
            }
        }

        // — Security —
        $prodEnvs = ['production', 'prod', 'live'];
        if (config('app.debug') && in_array(strtolower(config('app.env', '')), $prodEnvs)) {
            $diagnostics[] = ['level' => 'error', 'group' => 'Security', 'message' => 'APP_DEBUG is true in production. This can expose stack traces, API tokens, and environment variables to end users.'];
        }
        if (empty($cfg['ssrf_protection']) || $cfg['ssrf_protection'] === false) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Security', 'message' => 'ssrf_protection is disabled. Enable it to block requests to internal/private network addresses.'];
        }
        $origins = $cfg['allowed_origins'] ?? [];
        if (in_array('*', (array) $origins)) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Security', 'message' => 'allowed_origins includes "*" — CORS is open to all origins. Restrict this in production.'];
        }
        $rateLimit = (int) ($cfg['rate_limit'] ?? 20);
        if ($rateLimit > 100) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Security', 'message' => "rate_limit is set to {$rateLimit} requests/window. This is unusually high — consider lowering it to protect your API quota."];
        }
        if ($rateLimit === 0) {
            $diagnostics[] = ['level' => 'error', 'group' => 'Security', 'message' => 'rate_limit is 0. Rate limiting is effectively disabled, leaving your API key unprotected.'];
        }

        // — Response tuning —
        $temperature = (float) ($cfg['temperature'] ?? 0.7);
        if ($temperature > 1.5) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Response', 'message' => "temperature is {$temperature} — very high values produce incoherent or random responses. Typical range: 0.3–1.0."];
        }
        $maxTokens = $cfg['max_tokens'] ?? null;
        if ($maxTokens !== null && (int) $maxTokens < 64) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Response', 'message' => "max_tokens is set to {$maxTokens}. This is very low and will cut off most AI responses. Set to null to let the model decide, or at least 256."];
        }
        $timeout = (int) ($cfg['timeout'] ?? 30);
        if ($timeout < 10) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Response', 'message' => "timeout is {$timeout}s — too low for most AI providers, especially with streaming enabled. Recommended: 30–120s."];
        } elseif ($timeout > 300) {
            $diagnostics[] = ['level' => 'info', 'group' => 'Response', 'message' => "timeout is {$timeout}s — very high. PHP workers will be held open for up to {$timeout}s on slow requests."];
        }
        $systemPrompt = $cfg['system_prompt'] ?? '';
        $language = $cfg['language'] ?? '';
        if (!empty($systemPrompt) && str_contains($systemPrompt, '{language}') && empty($language)) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Response', 'message' => 'system_prompt contains the {language} placeholder but language is empty — the placeholder will not be substituted.'];
        }

        // — History —
        $historyEnabled = (bool) ($cfg['history_enabled'] ?? true);
        $historyLimit = (int) ($cfg['history_limit'] ?? 50);
        $contextTokens = (int) ($cfg['context_token_limit'] ?? 4000);
        if ($historyEnabled && $historyLimit === 0) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'History', 'message' => 'history_enabled is true but history_limit is 0 — no history will be sent to the AI. Set a positive history_limit or disable history.'];
        }
        if ($historyEnabled && $contextTokens > 0 && $contextTokens < 500) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'History', 'message' => "context_token_limit is very low ({$contextTokens} tokens). Most history will be trimmed before it reaches the AI. Typical values: 4000–32000."];
        }
        if ($historyEnabled && $contextTokens === 0 && $historyLimit > 20) {
            $diagnostics[] = ['level' => 'info', 'group' => 'History', 'message' => "context_token_limit is 0 (disabled) and history_limit is {$historyLimit}. Large histories may exceed your model's context window. Consider enabling token trimming."];
        }

        // — Frontend driver —
        $frontend = $cfg['frontend'] ?? 'vue';
        if ($frontend === 'livewire' && !class_exists(\Livewire\Livewire::class)) {
            $diagnostics[] = ['level' => 'error', 'group' => 'Frontend', 'message' => 'frontend is set to "livewire" but the livewire/livewire package is not installed. Run: composer require livewire/livewire'];
        }

        // — RAG —
        if ($ragEnabled) {
            $embeddingUrl = $cfg['rag_embedding_url'] ?? '';
            if (empty($embeddingUrl)) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => 'rag_embedding_url (AI_CHATBOX_EMBEDDING_URL) is not set. Document embeddings cannot be generated and RAG retrieval will not work.'];
            } else {
                $embHost = parse_url($embeddingUrl, PHP_URL_HOST);
                $isLocal = in_array($embHost, ['localhost', '127.0.0.1', '::1'])
                || str_starts_with($embHost ?? '', '192.168.')
                || str_starts_with($embHost ?? '', '10.')
                || str_starts_with($embHost ?? '', '172.');
                if ($isLocal && ($cfg['ssrf_protection'] ?? true)) {
                    $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "rag_embedding_url points to a local address ({$embHost}) but ssrf_protection is enabled — embedding requests may be blocked. Set AI_CHATBOX_SSRF_PROTECTION=false for local embedding services."];
                }
            }
            if (empty($cfg['rag_embedding_model'])) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => 'rag_embedding_model (AI_CHATBOX_EMBEDDING_MODEL) is not set. No embedding model will be used.'];
            }
            if (!Schema::hasTable('ai_chatbox_rag_documents')) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => 'Table ai_chatbox_rag_documents is missing. Run: php artisan migrate'];
            }
            if (!Schema::hasTable('ai_chatbox_rag_chunks')) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => 'Table ai_chatbox_rag_chunks is missing. Run: php artisan migrate'];
            }
            $threshold = (float) ($cfg['rag_similarity_threshold'] ?? 0.3);
            $chunkSize = (int) ($cfg['rag_chunk_size'] ?? 500);
            $chunkOverlap = (int) ($cfg['rag_chunk_overlap'] ?? 50);
            $topK = (int) ($cfg['rag_top_k'] ?? 3);
            if ($threshold <= 0) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => 'rag_similarity_threshold is 0 — all chunks are returned regardless of relevance. Recommended: 0.3–0.85.'];
            } elseif ($threshold > 0.95) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "rag_similarity_threshold is very high ({$threshold}). Most chunks will be filtered out, even relevant ones. Recommended: 0.3–0.85."];
            }
            if ($topK === 0) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => 'rag_top_k is 0 — no chunks will be retrieved. RAG context will never be injected into AI requests.'];
            } elseif ($topK > 20) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "rag_top_k is {$topK} — injecting this many chunks may exceed your model's context window. Recommended: 3–10."];
            }
            if ($chunkSize < 100) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "rag_chunk_size is {$chunkSize} tokens — very small chunks may not contain enough context for meaningful retrieval. Recommended: 300–800."];
            }
            if ($chunkOverlap >= $chunkSize) {
                $diagnostics[] = ['level' => 'error', 'group' => 'RAG', 'message' => "rag_chunk_overlap ({$chunkOverlap}) must be less than rag_chunk_size ({$chunkSize}). This will cause infinite chunking loops."];
            }
            if (isset($ragStats['documents']) && $ragStats['documents'] === 0) {
                $diagnostics[] = ['level' => 'info', 'group' => 'RAG', 'message' => 'RAG is enabled but no documents have been uploaded yet. Upload documents in the Knowledge Base to give the AI context.'];
            }
            if (isset($ragStats['null_chunks']) && $ragStats['null_chunks'] > 0) {
                $pct = $ragStats['total_chunks'] > 0 ? round($ragStats['null_chunks'] / $ragStats['total_chunks'] * 100) : 0;
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "{$ragStats['null_chunks']} chunk(s) ({$pct}%) have no stored embedding. The AI cannot see that content. Check your embedding URL and reprocess affected documents."];
            }
            if (isset($ragStats['documents_failed']) && $ragStats['documents_failed'] > 0) {
                $diagnostics[] = ['level' => 'warning', 'group' => 'RAG', 'message' => "{$ragStats['documents_failed']} document(s) are in a failed state. Open the Knowledge Base and reprocess them."];
            }
        }

        // — Memory / Database driver —
        if (config('ai-chatbox.memory_driver') === 'database') {
            if (!Schema::hasTable('ai_chatbox_conversations')) {
                $diagnostics[] = ['level' => 'error', 'group' => 'Memory', 'message' => 'Table ai_chatbox_conversations is missing. Run: php artisan migrate'];
            }
            if (!Schema::hasTable('ai_chatbox_messages')) {
                $diagnostics[] = ['level' => 'error', 'group' => 'Memory', 'message' => 'Table ai_chatbox_messages is missing. Run: php artisan migrate'];
            }
        } else {
            if ($historyEnabled && $historyLimit > 0) {
                $diagnostics[] = ['level' => 'info', 'group' => 'Memory', 'message' => 'memory_driver is "session" — conversation history is stored in the PHP session and lost when it expires. Switch to "database" for persistent, queryable history.'];
            }
        }
        $clientStorage = $cfg['storage'] ?? 'local';
        if ($clientStorage === 'local' && config('app.env') === 'production') {
            $diagnostics[] = ['level' => 'info', 'group' => 'Memory', 'message' => 'Client-side storage is set to "local" (localStorage). Chat history survives browser restarts and is visible to anyone with access to that browser. Use "session" for sensitive conversations.'];
        }

        // — Admin / RAG page protection —
        $adminMiddleware = (array) ($cfg['rag_admin_middleware'] ?? ['web', 'auth']);
        $defaultMiddleware = ['web', 'auth'];
        sort($adminMiddleware);
        sort($defaultMiddleware);
        if ($adminMiddleware === $defaultMiddleware) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Security', 'message' =>
                'The admin and Knowledge Base pages are only protected by the default middleware [web, auth] — any authenticated user can access them. ' .
                'Add a role or permission middleware to rag_admin_middleware in your config, e.g. "role:admin" (Spatie), "can:manage-chatbox" (Laravel Gates), or a custom middleware.',
            ];
        } else {
            // Check if any role/permission-style middleware is present
            $hasRoleCheck = collect($adminMiddleware)->contains(function ($m) {
                return preg_match('/^(role|permission|can|ability|authorize)[.:\-]/i', $m)
                || in_array(strtolower($m), ['admin', 'superadmin', 'is-admin', 'isadmin']);
            });
            if (!$hasRoleCheck) {
                $diagnostics[] = ['level' => 'info', 'group' => 'Security', 'message' =>
                    'Admin middleware has been customised but no role/permission middleware was detected. ' .
                    'Ensure access is restricted to trusted users only.',
                ];
            }
        }

        // — Streaming —
        if (($cfg['stream'] ?? false) && !function_exists('ob_flush')) {
            $diagnostics[] = ['level' => 'warning', 'group' => 'Streaming', 'message' => 'stream is enabled but output buffering functions are unavailable. Streaming responses may not work correctly.'];
        }

        // ── Environment ───────────────────────────────────────────────────────
        $env = [
            'laravel' => app()->version(),
            'php' => phpversion(),
            'app_env' => config('app.env'),
            'app_debug' => config('app.debug'),
            'app_url' => config('app.url'),
        ];

        return view('ai-chatbox::admin', [
            'ragStats' => $ragStats,
            'memoryStats' => $memoryStats,
            'configGroups' => $configGroups,
            'namedProviders' => $namedProviders,
            'env' => $env,
            'diagnostics' => $diagnostics,
            'themeColor' => $cfg['theme_color'] ?? '#4f46e5',
            'colorScheme' => $cfg['color_scheme'] ?? 'auto',
            'ragEnabled' => $ragEnabled,
            'frontend' => $frontend,
            'ragUrl' => route('ai-chatbox.rag.index'),
            'conversationsUrl' => config('ai-chatbox.memory_driver') === 'database'
            ? route('ai-chatbox.admin.conversations')
            : null,
        ]);
    }

    // ── Conversations list page ───────────────────────────────────────────────

    public function conversations(): View
    {
        return view('ai-chatbox::admin-conversations', [
            'themeColor' => config('ai-chatbox.theme_color', '#4f46e5'),
            'colorScheme' => config('ai-chatbox.color_scheme', 'auto'),
            'dataUrl' => route('ai-chatbox.admin.conversations.data'),
            'messagesUrl' => rtrim(route('ai-chatbox.admin.conversations'), '/') . '/__id__/messages',
        ]);
    }

    // ── Conversations JSON (paginated) ────────────────────────────────────────

    public function conversationsData(Request $request): JsonResponse
    {
        $perPage = 20;
        $page = max(1, (int) $request->query('page', 1));

        $paginator = Conversation::withCount('messages')
            ->orderByDesc('updated_at')
            ->paginate($perPage, ['*'], 'page', $page);

        // Resolve user names in one query to avoid N+1
        $userNames = [];
        try {
            $userModel = config('auth.providers.users.model', 'App\\Models\\User');
            $userIds = $paginator->getCollection()->pluck('user_id')->filter()->unique()->values();
            if ($userIds->isNotEmpty()) {
                $userModel::whereIn('id', $userIds)
                    ->get()
                    ->each(function ($u) use (&$userNames) {
                        $userNames[$u->id] = $u->name ?? $u->username ?? $u->email ?? null;
                    });
            }
        } catch (\Throwable) {
            // User model unavailable — fall back gracefully
        }

        $items = $paginator->getCollection()->map(function (Conversation $c) use ($userNames) {
            $last = $c->messages()->orderByDesc('id')->first(['role', 'content']);
            return [
                'id' => $c->id,
                'thread_id' => $c->thread_id,
                'user_id' => $c->user_id,
                'user_name' => $userNames[$c->user_id] ?? null,
                'messages_count' => $c->messages_count,
                'last_role' => $last?->role,
                'last_preview' => $last ? mb_strimwidth($last->content, 0, 100, '…') : null,
                'created_at' => $c->created_at?->diffForHumans(),
                'updated_at' => $c->updated_at?->diffForHumans(),
            ];
        });

        return response()->json([
            'data' => $items,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
        ]);
    }

    // ── Messages for one conversation (JSON) ──────────────────────────────────

    public function conversationMessages(int $id): JsonResponse
    {
        $conversation = Conversation::findOrFail($id);

        $messages = Message::where('conversation_id', $id)
            ->orderBy('id')
            ->get(['id', 'role', 'content', 'created_at'])
            ->map(fn(Message $m) => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at?->format('H:i · d M Y'),
            ]);

        // Try to resolve a display name from the host app's User model
        $userName = null;
        if ($conversation->user_id) {
            try {
                $userModel = config('auth.providers.users.model', 'App\\Models\\User');
                $user = $userModel::find($conversation->user_id);
                $userName = $user ? ($user->name ?? $user->username ?? $user->email ?? null) : null;
            } catch (\Throwable) {
                // User model unavailable — fall back gracefully
            }
        }

        return response()->json([
            'thread_id' => $conversation->thread_id,
            'user_id' => $conversation->user_id,
            'user_name' => $userName,
            'messages' => $messages,
        ]);
    }
}
