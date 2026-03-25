<?php
namespace SyafiqUnijaya\AiChatbox\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ChatboxController extends Controller
{
    public function sendMessage(Request $request): JsonResponse
    {
        $request->validate([
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $apiUrl = config('ai-chatbox.api_url');
        $apiToken = config('ai-chatbox.api_token');
        $model = config('ai-chatbox.api_model');
        $timeout = config('ai-chatbox.timeout', 30);
        $system = config('ai-chatbox.system_prompt', '');

        if (empty($apiToken)) {
            return response()->json(['error' => 'AI API token is not configured.'], 500);
        }

        $messages = [];

        if (!empty($system)) {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        try {
            $client = new Client(['timeout' => $timeout]);

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $reply = $data['choices'][0]['message']['content'] ?? 'No response from AI.';

            return response()->json(['reply' => trim($reply)]);

        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 500;
            $message = $e->hasResponse()
            ? json_decode($e->getResponse()->getBody()->getContents(), true)['error']['message'] ?? $e->getMessage()
            : $e->getMessage();

            return response()->json(['error' => $message], $status);
        }
    }
}
