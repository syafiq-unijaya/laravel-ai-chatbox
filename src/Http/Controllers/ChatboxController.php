<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatboxController extends Controller
{
    private const SESSION_KEY = 'ai_chatbox_history';

    private const E01 = 'E01'; // api_url is missing or empty
    private const E02 = 'E02'; // api_url cannot be parsed (no host)
    private const E03 = 'E03'; // api_token is missing or empty
    private const E04 = 'E04'; // api_model contains invalid characters

    private const E05 = 'E05'; // SSRF protection blocked a private/reserved IP

    private const E06 = 'E06'; // DNS resolution failed (hostname not found)
    private const E07 = 'E07'; // Connection refused (service not running on that port)
    private const E08 = 'E08'; // Connection timed out
    private const E09 = 'E09'; // SSL/TLS handshake or certificate error
    private const E10 = 'E10'; // Too many redirects
    private const E11 = 'E11'; // Generic connection error

    private const E12 = 'E12'; // HTTP 401 Unauthorized (invalid or expired token)
    private const E13 = 'E13'; // HTTP 403 Forbidden
    private const E14 = 'E14'; // HTTP 404 Not Found (wrong endpoint URL)
    private const E15 = 'E15'; // HTTP 429 Too Many Requests (rate limited)
    private const E16 = 'E16'; // HTTP 5xx server-side error from the AI service
    private const E17 = 'E17'; // Unexpected HTTP status

    private const E18 = 'E18'; // API returned an empty or unparseable response
    private const E19 = 'E19'; // Unknown / unclassified error

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message'   => ['required', 'string', 'max:2000'],
            'thread_id' => ['nullable', 'string', 'max:36'],
        ]);

        $cfg = config('ai-chatbox');

        $apiUrl   = $cfg['api_url']   ?? '';
        $apiToken = $cfg['api_token'] ?? '';
        $model    = $cfg['api_model'] ?? '';

        if (empty($apiUrl)) {
            return $this->errorResponse(self::E01, 'AI API URL is not configured.', 500);
        }

        if (empty($apiToken)) {
            return $this->errorResponse(self::E03, 'AI API token is not configured.', 500);
        }

        if (!empty($model) && !preg_match('/^[a-zA-Z0-9_:.\-]+$/', $model)) {
            return $this->errorResponse(self::E04, 'Invalid model name configured.', 500);
        }

        $timeout           = $cfg['timeout']            ?? 30;
        $language          = $cfg['language']           ?? 'English';
        $system            = str_replace('{language}', $language, $cfg['system_prompt'] ?? '');
        $useHistory        = $cfg['history_enabled']    ?? true;
        $historyLimit      = (int) ($cfg['history_limit']       ?? 50);
        $contextTokenLimit = (int) ($cfg['context_token_limit'] ?? 4000);
        $maxTokens         = $cfg['max_tokens']         ?? null;
        $temperature       = (float) ($cfg['temperature'] ?? 0.7);

        $sessionKey  = $this->sessionKey($request->input('thread_id', ''));
        $userMessage = $request->input('message');

        $systemMessages = [];
        if (!empty($system)) {
            $systemMessages[] = ['role' => 'system', 'content' => $system];
        }

        $history = $useHistory ? $request->session()->get($sessionKey, []) : [];

        // Trim history by message-pair count
        $maxEntries = $historyLimit * 2;
        if (count($history) > $maxEntries) {
            $history = array_slice($history, count($history) - $maxEntries);
        }

        // Trim history by approximate token count (4 chars ≈ 1 token)
        if ($contextTokenLimit > 0) {
            $apiMessage = (!empty($system) && !empty($language))
                ? $userMessage . "\n\n[Important: Reply in {$language} only.]"
                : $userMessage;

            while (count($history) >= 2) {
                $all = array_merge($systemMessages, $history, [['role' => 'user', 'content' => $apiMessage]]);
                if ($this->estimateTokens($all) <= $contextTokenLimit) {
                    break;
                }
                array_splice($history, 0, 2); // drop oldest user+assistant pair
            }
        }

        // Append language reminder only to the outgoing API payload, not to the
        // stored version, so history doesn't accumulate the suffix on every entry.
        $apiMessage = (!empty($system) && !empty($language))
            ? $userMessage . "\n\n[Important: Reply in {$language} only.]"
            : $userMessage;

        $messages   = array_merge($systemMessages, $history);
        $messages[] = ['role' => 'user', 'content' => $apiMessage];

        try {
            $client = $this->makeClient(['timeout' => $timeout]);

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => array_filter([
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                    'stream' => false,
                ], fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // OpenAI-compatible format: data['choices'][0]['message']['content']
            // Ollama native format:     data['message']['content']
            $reply = $data['choices'][0]['message']['content'] ?? $data['message']['content'] ?? null;

            if ($reply === null || trim($reply) === '') {
                return $this->errorResponse(self::E18, 'Unable to reach AI service. Please try again later.', 502);
            }

            if ($useHistory) {
                $history[] = ['role' => 'user',      'content' => $userMessage];
                $history[] = ['role' => 'assistant', 'content' => trim($reply)];

                $maxEntries = $historyLimit * 2;
                if (count($history) > $maxEntries) {
                    $history = array_slice($history, count($history) - $maxEntries);
                }

                $request->session()->put($sessionKey, $history);
            }

            return response()->json(['reply' => trim($reply)]);

        } catch (TooManyRedirectsException $e) {
            return $this->errorResponse(self::E10, 'Unable to reach AI service. Please try again later.', 502, $e);

        } catch (ConnectException $e) {
            return $this->errorResponse(
                $this->classifyConnectException($e),
                'Unable to reach AI service. Please try again later.',
                503,
                $e
            );

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            $code = $this->classifyHttpStatus($status);
            return $this->errorResponse($code, 'Unable to reach AI service. Please try again later.', $status, $e);

        } catch (\Throwable $e) {
            return $this->errorResponse(self::E19, 'Unable to reach AI service. Please try again later.', 500, $e);
        }
    }

    public function streamMessage(Request $request): StreamedResponse|JsonResponse
    {
        $request->validate([
            'message'   => ['required', 'string', 'max:2000'],
            'thread_id' => ['nullable', 'string', 'max:36'],
        ]);

        $cfg = config('ai-chatbox');

        $apiUrl   = $cfg['api_url']   ?? '';
        $apiToken = $cfg['api_token'] ?? '';
        $model    = $cfg['api_model'] ?? '';

        if (empty($apiUrl)) {
            return $this->errorResponse(self::E01, 'AI API URL is not configured.', 500);
        }
        if (empty($apiToken)) {
            return $this->errorResponse(self::E03, 'AI API token is not configured.', 500);
        }
        if (!empty($model) && !preg_match('/^[a-zA-Z0-9_:.\-]+$/', $model)) {
            return $this->errorResponse(self::E04, 'Invalid model name configured.', 500);
        }

        $timeout           = $cfg['timeout']            ?? 30;
        $language          = $cfg['language']           ?? 'English';
        $system            = str_replace('{language}', $language, $cfg['system_prompt'] ?? '');
        $useHistory        = $cfg['history_enabled']    ?? true;
        $historyLimit      = (int) ($cfg['history_limit']       ?? 50);
        $contextTokenLimit = (int) ($cfg['context_token_limit'] ?? 4000);
        $temperature       = (float) ($cfg['temperature'] ?? 0.7);

        $sessionKey  = $this->sessionKey($request->input('thread_id', ''));
        $userMessage = $request->input('message');

        $systemMessages = [];
        if (!empty($system)) {
            $systemMessages[] = ['role' => 'system', 'content' => $system];
        }

        $history = $useHistory ? $request->session()->get($sessionKey, []) : [];

        $maxEntries = $historyLimit * 2;
        if (count($history) > $maxEntries) {
            $history = array_slice($history, count($history) - $maxEntries);
        }

        $apiMessage = (!empty($system) && !empty($language))
            ? $userMessage . "\n\n[Important: Reply in {$language} only.]"
            : $userMessage;

        if ($contextTokenLimit > 0) {
            while (count($history) >= 2) {
                $all = array_merge($systemMessages, $history, [['role' => 'user', 'content' => $apiMessage]]);
                if ($this->estimateTokens($all) <= $contextTokenLimit) {
                    break;
                }
                array_splice($history, 0, 2);
            }
        }

        $messages   = array_merge($systemMessages, $history);
        $messages[] = ['role' => 'user', 'content' => $apiMessage];

        try {
            $client = $this->makeClient(['timeout' => $timeout]);

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => array_filter([
                    'model'       => $model,
                    'messages'    => $messages,
                    'temperature' => $temperature,
                    'stream'      => true,
                ], fn($v) => $v !== null),
                'stream' => true,
            ]);

            $body = $response->getBody();

            return response()->stream(
                function () use ($body, $request, $sessionKey, $useHistory, $historyLimit, $userMessage, $history) {
                    $fullReply = '';
                    $buffer    = '';

                    while (!$body->eof()) {
                        $buffer .= $body->read(1024);

                        // Process every complete line in the buffer
                        while (($nl = strpos($buffer, "\n")) !== false) {
                            $line   = rtrim(substr($buffer, 0, $nl), "\r");
                            $buffer = substr($buffer, $nl + 1);

                            if ($line === '' || str_starts_with($line, ':')) {
                                continue; // SSE comment or blank keep-alive line
                            }

                            if ($line === 'data: [DONE]') {
                                break 2;
                            }

                            // Strip SSE prefix (OpenAI-compatible) or parse raw JSON (Ollama native)
                            $jsonStr = str_starts_with($line, 'data: ')
                                ? substr($line, 6)
                                : $line;

                            $data = json_decode($jsonStr, true);
                            if (!is_array($data)) {
                                continue;
                            }

                            // OpenAI-compatible: choices[0].delta.content
                            // Ollama native:     message.content
                            $token = $data['choices'][0]['delta']['content']
                                  ?? $data['message']['content']
                                  ?? null;

                            if ($token !== null && $token !== '') {
                                $fullReply .= $token;

                                echo 'data: ' . json_encode(['token' => $token]) . "\n\n";
                                if (ob_get_level() > 0) ob_flush();
                                flush();
                            }

                            // Ollama native signals end with done: true (emit token above first)
                            if (($data['done'] ?? false) === true) {
                                break 2;
                            }
                        }
                    }

                    // Persist completed reply to server-side session
                    if ($useHistory && $fullReply !== '') {
                        $history[] = ['role' => 'user',      'content' => $userMessage];
                        $history[] = ['role' => 'assistant', 'content' => trim($fullReply)];

                        $maxEntries = $historyLimit * 2;
                        if (count($history) > $maxEntries) {
                            $history = array_slice($history, count($history) - $maxEntries);
                        }

                        $request->session()->put($sessionKey, $history);
                        $request->session()->save();
                    }

                    echo "data: [DONE]\n\n";
                    if (ob_get_level() > 0) ob_flush();
                    flush();
                },
                200,
                [
                    'Content-Type'      => 'text/event-stream',
                    'Cache-Control'     => 'no-cache',
                    'X-Accel-Buffering' => 'no',   // disable Nginx buffering
                    'Connection'        => 'keep-alive',
                ]
            );

        } catch (TooManyRedirectsException $e) {
            return $this->errorResponse(self::E10, 'Unable to reach AI service. Please try again later.', 502, $e);

        } catch (ConnectException $e) {
            return $this->errorResponse(
                $this->classifyConnectException($e),
                'Unable to reach AI service. Please try again later.',
                503,
                $e
            );

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            return $this->errorResponse($this->classifyHttpStatus($status), 'Unable to reach AI service. Please try again later.', $status, $e);

        } catch (\Throwable $e) {
            return $this->errorResponse(self::E19, 'Unable to reach AI service. Please try again later.', 500, $e);
        }
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $sessionKey = $this->sessionKey($request->input('thread_id', ''));
        $request->session()->forget($sessionKey);

        return response()->json(['status' => 'ok']);
    }

    public function healthCheck(): JsonResponse
    {
        $cfg = config('ai-chatbox');
        $apiUrl = $cfg['api_url'] ?? '';
        $offlineMessage = $cfg['offline_message'] ?? 'AI service is currently unreachable.';
        $timeout = min((int) ($cfg['timeout'] ?? 30), 5);

        if (empty($apiUrl)) {
            return $this->offlineResponse(self::E01, $offlineMessage);
        }

        $parts = parse_url($apiUrl);
        $host = $parts['host'] ?? '';

        if (empty($host)) {
            return $this->offlineResponse(self::E02, $offlineMessage);
        }

        if ($cfg['ssrf_protection'] ?? true) {
            $ip = $this->resolveHost($host);

            if ($ip === null) {
                return $this->offlineResponse(self::E06, $offlineMessage);
            }

            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return $this->offlineResponse(self::E05, $offlineMessage);
            }
        }

        $baseUrl = ($parts['scheme'] ?? 'http') . '://' . $host;
        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        try {
            $client = $this->makeClient([
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
            ]);

            $client->get($baseUrl, ['http_errors' => false]);

            return response()->json([
                'status' => 'online',
                'message' => 'AI service is reachable.',
            ]);

        } catch (TooManyRedirectsException $e) {
            return $this->offlineResponse(self::E10, $offlineMessage, $e);

        } catch (ConnectException $e) {
            return $this->offlineResponse(
                $this->classifyConnectException($e),
                $offlineMessage,
                $e
            );

        } catch (RequestException $e) {
            // Any HTTP response (even 4xx/5xx) means the server is up
            return response()->json([
                'status' => 'online',
                'message' => 'AI service is reachable.',
            ]);

        } catch (\Throwable $e) {
            return $this->offlineResponse(self::E19, $offlineMessage, $e);
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function makeClient(array $config): Client
    {
        $factory = app('ai-chatbox.guzzle');
        return $factory($config);
    }

    /**
     * Resolve a hostname to an IP, with a static in-process cache to avoid
     * repeated DNS lookups for the same host across health check calls.
     * Returns null if resolution fails.
     */
    private function resolveHost(string $host): ?string
    {
        static $cache = [];

        if (isset($cache[$host])) {
            return $cache[$host];
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $cache[$host] = $host;
        }

        $ip = gethostbyname($host);

        // gethostbyname returns the original string unchanged on failure
        if ($ip === $host) {
            return null;
        }

        return $cache[$host] = $ip;
    }

    private function offlineResponse(string $code, string $message,  ? \Throwable $e = null) : JsonResponse
    {
        Log::warning('AI Chatbox health check failed', [
            'code' => $code,
            'message' => $e?->getMessage(),
        ]);

        return response()->json([
            'status' => 'offline',
            'code' => $code,
            'message' => $message,
        ], 503);
    }

    private function errorResponse(string $code, string $message, int $status,  ? \Throwable $e = null) : JsonResponse
    {
        Log::error('AI Chatbox error', [
            'code' => $code,
            'status' => $status,
            'message' => $e?->getMessage(),
        ]);

        return response()->json([
            'error' => $message,
            'code' => $code,
        ], $status);
    }

    private function classifyConnectException(ConnectException $e): string
    {
        $msg = strtolower($e->getMessage());

        if (str_contains($msg, 'could not resolve host') || str_contains($msg, 'name or service not known')) {
            return self::E06;
        }
        if (str_contains($msg, 'connection refused')) {
            return self::E07;
        }
        if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
            return self::E08;
        }
        if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate') || str_contains($msg, 'tls')) {
            return self::E09;
        }

        return self::E11;
    }

    private function classifyHttpStatus(int $status): string
    {
        return match ($status) {
            401 => self::E12,
            403 => self::E13,
            404 => self::E14,
            429 => self::E15,
            500, 502, 503, 504 => self::E16,
            default => self::E17,
        };
    }

    /**
     * Return the session key scoped to the given thread ID.
     * Falls back to the default key when no valid UUID v4 is provided,
     * preserving backward compatibility with clients that don't send thread_id.
     */
    private function sessionKey(string $threadId): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $threadId)) {
            return self::SESSION_KEY . '_' . str_replace('-', '', strtolower($threadId));
        }

        return self::SESSION_KEY;
    }

    /**
     * Estimate token count using the ~4 characters per token heuristic.
     * Applies to the combined content of all messages.
     */
    private function estimateTokens(array $messages): int
    {
        $chars = array_sum(array_map(fn($m) => strlen($m['content'] ?? ''), $messages));

        return (int) ceil($chars / 4);
    }
}
