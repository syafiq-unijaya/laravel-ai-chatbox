<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatboxController extends Controller
{
    private const SESSION_KEY = 'ai_chatbox_history';

    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $apiUrl = config('ai-chatbox.api_url');
        $apiToken = config('ai-chatbox.api_token');
        $model = config('ai-chatbox.api_model');
        $timeout = config('ai-chatbox.timeout', 30);
        $language = config('ai-chatbox.language', 'English');
        $system = str_replace('{language}', $language, config('ai-chatbox.system_prompt', ''));
        $useHistory = config('ai-chatbox.history_enabled', true);
        $historyLimit = (int) config('ai-chatbox.history_limit', 10);
        $maxTokens = config('ai-chatbox.max_tokens');
        $temperature = (float) config('ai-chatbox.temperature', 0.7);

        if (empty($apiToken)) {
            return response()->json(['error' => 'AI API token is not configured.'], 500);
        }

        // Build message array starting with optional system prompt
        $messages = [];

        if (!empty($system)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        // Append stored history, then the new user message
        $history = $useHistory ? $request->session()->get(self::SESSION_KEY, []) : [];
        $messages = array_merge($messages, $history);
        $userMessage = $request->input('message');

        // Append a language reminder to the user turn so small models stay on track
        if (!empty($system) && !empty($language)) {
            $userMessage .= "\n\n[Important: Reply in {$language} only.]";
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        try {
            $client = new Client(['timeout' => $timeout]);

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
                ], fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $reply = $data['choices'][0]['message']['content'] ?? 'No response from AI.';

            // Persist history (user + assistant pair), capped at history_limit pairs
            if ($useHistory) {
                $history[] = ['role' => 'user', 'content' => $request->input('message')];
                $history[] = ['role' => 'assistant', 'content' => trim($reply)];

                // Each pair = 2 entries; drop oldest pair when over the limit
                $maxEntries = $historyLimit * 2;
                if (count($history) > $maxEntries) {
                    $history = array_slice($history, count($history) - $maxEntries);
                }

                $request->session()->put(self::SESSION_KEY, $history);
            }

            return response()->json(['reply' => trim($reply)]);

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            $message = $e->hasResponse()
            ? json_decode($e->getResponse()->getBody()->getContents(), true)['error']['message'] ?? $e->getMessage()
            : $e->getMessage();

            return response()->json(['error' => $message], $status);
        }
    }

    public function clearHistory(Request $request): JsonResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return response()->json(['status' => 'ok']);
    }

    public function healthCheck(): JsonResponse
    {
        $apiUrl = config('ai-chatbox.api_url');
        $timeout = min((int) config('ai-chatbox.timeout', 30), 5); // cap health check at 5s

        // Derive the base URL (scheme + host + port) to avoid sending a real request
        $parts = parse_url($apiUrl);
        $baseUrl = ($parts['scheme'] ?? 'http') . '://' . ($parts['host'] ?? 'localhost');

        if (!empty($parts['port'])) {
            $baseUrl .= ':' . $parts['port'];
        }

        try {
            $client = new Client([
                'timeout' => $timeout,
                'connect_timeout' => $timeout,
            ]);

            $client->get($baseUrl, ['http_errors' => false]);

            return response()->json([
                'status' => 'online',
                'message' => 'AI service is reachable.',
            ]);

        } catch (ConnectException $e) {
            return response()->json([
                'status' => 'offline',
                'message' => 'AI service is unreachable. Please try again later.',
            ], 503);

        } catch (RequestException $e) {
            // A non-connection HTTP error still means the server is up
            return response()->json([
                'status' => 'online',
                'message' => 'AI service is reachable.',
            ]);
        }
    }
}
