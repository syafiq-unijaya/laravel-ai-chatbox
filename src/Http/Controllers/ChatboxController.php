<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use SyafiqUnijaya\AiChatbox\AiManager;
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\Exceptions\AiEngineException;
use SyafiqUnijaya\AiChatbox\Engine\HealthChecker;
use SyafiqUnijaya\AiChatbox\Engine\PromptBuilder;
use SyafiqUnijaya\AiChatbox\Memory\ContextManager;
use SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Layer 3 — UI
 *
 * Handles HTTP request/response only. All AI calls are delegated to the
 * Engine layer; all history persistence is delegated to the Memory layer.
 */
class ChatboxController extends Controller
{
    public function __construct(
        private readonly AiEngineInterface $engine,
        private readonly ConversationRepositoryInterface $repository,
        private readonly PromptBuilder $promptBuilder,
        private readonly ContextManager $contextManager,
        private readonly HealthChecker $healthChecker,
    ) {}

    // ── Endpoints ─────────────────────────────────────────────────────────────

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'thread_id' => ['nullable', 'string', 'max:36'],
        ]);

        $cfg = $this->effectiveConfig();
        $threadId = $request->input('thread_id', '');
        $userMsg = $request->input('message');

        // Memory layer: retrieve + context-trim history
        $history = $this->repository->getHistory($threadId);
        $system = $this->promptBuilder->systemMessages($cfg);
        $history = $this->contextManager->trim($history, $system, $userMsg, $cfg);

        // Engine layer: build prompt and call AI
        $messages = $this->promptBuilder->build($userMsg, $history, $cfg);

        try {
            $reply = $this->engine->complete($messages, $cfg);
        } catch (AiEngineException $e) {
            return $this->engineError($e);
        }

        // Memory layer: persist trimmed history + new pair
        if ($cfg['history_enabled'] ?? true) {
            $history[] = ['role' => 'user', 'content' => $userMsg];
            $history[] = ['role' => 'assistant', 'content' => $reply];
            $this->repository->saveHistory($threadId, $history);
            $this->repository->trimToLimit($threadId, (int) ($cfg['history_limit'] ?? 50));
        }

        return response()->json(['reply' => $reply]);
    }

    public function streamMessage(Request $request): StreamedResponse | JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'thread_id' => ['nullable', 'string', 'max:36'],
        ]);

        $cfg = $this->effectiveConfig();
        $threadId = $request->input('thread_id', '');
        $userMsg = $request->input('message');

        // Memory layer: retrieve + context-trim history before streaming starts
        $history = $this->repository->getHistory($threadId);
        $system = $this->promptBuilder->systemMessages($cfg);
        $history = $this->contextManager->trim($history, $system, $userMsg, $cfg);

        $messages = $this->promptBuilder->build($userMsg, $history, $cfg);
        $useHistory = $cfg['history_enabled'] ?? true;
        $historyLimit = (int) ($cfg['history_limit'] ?? 50);

        // Establish the AI connection before starting the HTTP stream response.
        // This ensures connection / config errors can still return proper JSON (non-200).
        try {
            $streamReader = $this->engine->beginStream($messages, $cfg);
        } catch (AiEngineException $e) {
            return $this->engineError($e);
        }

        return response()->stream(
            function () use ($streamReader, $threadId, $userMsg, $history, $useHistory, $historyLimit) {
                try {
                    $fullReply = $streamReader(function (string $token) {
                        echo 'data: ' . json_encode(['token' => $token]) . "\n\n";
                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    });

                    if ($useHistory && $fullReply !== '') {
                        $history[] = ['role' => 'user', 'content' => $userMsg];
                        $history[] = ['role' => 'assistant', 'content' => $fullReply];
                        $this->repository->saveHistory($threadId, $history);
                        $this->repository->trimToLimit($threadId, $historyLimit);
                        session()->save(); // required because the response has already started
                    }

                } catch (AiEngineException $e) {
                    Log::error('AI Chatbox stream error', [
                        'code' => $e->errorCode,
                        'message' => $e->getMessage(),
                    ]);
                }

                echo "data: [DONE]\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
                'Connection' => 'keep-alive',
            ]
        );
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $this->repository->clear($request->input('thread_id', ''));

        return response()->json(['status' => 'ok']);
    }

    public function healthCheck(Request $request): JsonResponse
    {
        $providerName = $request->query('provider');
        $cfg = $providerName
        ? app(AiManager::class)->resolveConfig((string) $providerName)
        : $this->effectiveConfig();

        $result = $this->healthChecker->check($cfg);
        $status = $result['status'] === 'online' ? 200 : 503;

        return response()->json($result, $status);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Return the effective config for the chatbox widget, resolved from the
     * active provider. api_url, api_token, and api_model always come from
     * the provider entry — never from top-level env vars.
     */
    private function effectiveConfig(): array
    {
        return app(AiManager::class)->resolveConfig(
            config('ai-chatbox.active_provider', 'default')
        );
    }

    private function engineError(AiEngineException $e): JsonResponse
    {
        Log::error('AI Chatbox error', [
            'code' => $e->errorCode,
            'status' => $e->getHttpStatus(),
            'message' => $e->getMessage(),
        ]);

        return response()->json([
            'error' => $e->getMessage(),
            'code' => $e->errorCode,
        ], $e->getHttpStatus());
    }
}
