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

class ChatboxController extends Controller
{
    private const SESSION_KEY = 'ai_chatbox_history';

    // ── Error codes ──────────────────────────────────────────────────────────
    // Configuration
    private const E01 = 'E01'; // api_url is missing or empty
    private const E02 = 'E02'; // api_url cannot be parsed (no host)
    private const E03 = 'E03'; // api_token is missing or empty
    private const E04 = 'E04'; // api_model contains invalid characters

    // Security
    private const E05 = 'E05'; // SSRF protection blocked a private/reserved IP

    // Network / connectivity
    private const E06 = 'E06'; // DNS resolution failed (hostname not found)
    private const E07 = 'E07'; // Connection refused (service not running on that port)
    private const E08 = 'E08'; // Connection timed out
    private const E09 = 'E09'; // SSL/TLS handshake or certificate error
    private const E10 = 'E10'; // Too many redirects
    private const E11 = 'E11'; // Generic connection error

    // API / HTTP
    private const E12 = 'E12'; // HTTP 401 Unauthorized (invalid or expired token)
    private const E13 = 'E13'; // HTTP 403 Forbidden
    private const E14 = 'E14'; // HTTP 404 Not Found (wrong endpoint URL)
    private const E15 = 'E15'; // HTTP 429 Too Many Requests (rate limited)
    private const E16 = 'E16'; // HTTP 5xx server-side error from the AI service
    private const E17 = 'E17'; // Unexpected HTTP status

    // Response
    private const E18 = 'E18'; // API returned an empty or unparseable response
    private const E19 = 'E19'; // Unknown / unclassified error

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $apiUrl   = config('ai-chatbox.api_url');
        $apiToken = config('ai-chatbox.api_token');
        $model    = config('ai-chatbox.api_model');

        if (empty($apiUrl)) {
            return $this->errorResponse(self::E01, 'AI API URL is not configured.', 500);
        }

        if (empty($apiToken)) {
            return $this->errorResponse(self::E03, 'AI API token is not configured.', 500);
        }

        if (!empty($model) && !preg_match('/^[a-zA-Z0-9_:.\-]+$/', $model)) {
            return $this->errorResponse(self::E04, 'Invalid model name configured.', 500);
        }

        $timeout      = config('ai-chatbox.timeout', 30);
        $language     = config('ai-chatbox.language', 'English');
        $system       = str_replace('{language}', $language, config('ai-chatbox.system_prompt', ''));
        $useHistory   = config('ai-chatbox.history_enabled', true);
        $historyLimit = (int) config('ai-chatbox.history_limit', 10);
        $maxTokens    = config('ai-chatbox.max_tokens');
        $temperature  = (float) config('ai-chatbox.temperature', 0.7);

        $messages = [];

        if (!empty($system)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $history = $useHistory ? $request->session()->get(self::SESSION_KEY, []) : [];
        $messages = array_merge($messages, $history);
        $userMessage = $request->input('message');

        if (!empty($system) && !empty($language)) {
            $userMessage .= "\n\n[Important: Reply in {$language} only.]";
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

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
                    'max_tokens'  => $maxTokens,
                    'stream'      => false,
                ], fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // OpenAI-compatible format: data['choices'][0]['message']['content']
            // Ollama native format:     data['message']['content']
            $reply = $data['choices'][0]['message']['content']
                ?? $data['message']['content']
                ?? null;

            if ($reply === null || trim($reply) === '') {
                return $this->errorResponse(self::E18, 'Unable to reach AI service. Please try again later.', 502);
            }

            if ($useHistory) {
                $history[] = ['role' => 'user',      'content' => $request->input('message')];
                $history[] = ['role' => 'assistant', 'content' => trim($reply)];

                $maxEntries = $historyLimit * 2;
                if (count($history) > $maxEntries) {
                    $history = array_slice($history, count($history) - $maxEntries);
                }

                $request->session()->put(self::SESSION_KEY, $history);
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
            $code   = $this->classifyHttpStatus($status);
            return $this->errorResponse($code, 'Unable to reach AI service. Please try again later.', $status, $e);

        } catch (\Throwable $e) {
            return $this->errorResponse(self::E19, 'Unable to reach AI service. Please try again later.', 500, $e);
        }
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['status' => 'ok']);
    }

    public function healthCheck(): JsonResponse
    {
        $apiUrl         = config('ai-chatbox.api_url');
        $offlineMessage = config('ai-chatbox.offline_message', 'AI service is currently unreachable.');
        $timeout        = min((int) config('ai-chatbox.timeout', 30), 5);

        if (empty($apiUrl)) {
            return $this->offlineResponse(self::E01, $offlineMessage);
        }

        $parts = parse_url($apiUrl);
        $host  = $parts['host'] ?? '';

        if (empty($host)) {
            return $this->offlineResponse(self::E02, $offlineMessage);
        }

        if (config('ai-chatbox.ssrf_protection', true)) {
            $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

            // gethostbyname returns the original string unchanged on failure
            if ($ip === $host && !filter_var($ip, FILTER_VALIDATE_IP)) {
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
                'timeout'         => $timeout,
                'connect_timeout' => $timeout,
            ]);

            $client->get($baseUrl, ['http_errors' => false]);

            return response()->json([
                'status'  => 'online',
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
                'status'  => 'online',
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

    private function offlineResponse(string $code, string $message, ?\Throwable $e = null): JsonResponse
    {
        Log::warning('AI Chatbox health check failed', [
            'code'    => $code,
            'message' => $e?->getMessage(),
        ]);

        return response()->json([
            'status'  => 'offline',
            'code'    => $code,
            'message' => $message,
        ], 503);
    }

    private function errorResponse(string $code, string $message, int $status, ?\Throwable $e = null): JsonResponse
    {
        Log::error('AI Chatbox error', [
            'code'    => $code,
            'status'  => $status,
            'message' => $e?->getMessage(),
        ]);

        return response()->json([
            'error' => $message,
            'code'  => $code,
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
            401     => self::E12,
            403     => self::E13,
            404     => self::E14,
            429     => self::E15,
            500, 502, 503, 504 => self::E16,
            default => self::E17,
        };
    }
}
