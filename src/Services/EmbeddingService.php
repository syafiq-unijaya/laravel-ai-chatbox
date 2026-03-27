<?php
namespace SyafiqUnijaya\AiChatbox\Services;

use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    /**
     * Generate an embedding vector for the given text.
     *
     * Tries the OpenAI-compatible format first (used by OpenAI, LM Studio,
     * Ollama /v1/embeddings). Falls back to Ollama native response shapes
     * (/api/embed → embeddings[0], /api/embeddings → embedding).
     *
     * @return float[]|null  Null when the API call fails or returns no vector.
     */
    public function embed(string $text): ?array
    {
        $url = config('ai-chatbox.rag_embedding_url', '');
        $model = config('ai-chatbox.rag_embedding_model', 'nomic-embed-text');
        $token = config('ai-chatbox.api_token', '');

        if (empty($url)) {
            Log::warning('AI Chatbox RAG: rag_embedding_url is not configured.');
            return null;
        }

        try {
            $client = app('ai-chatbox.guzzle')(['timeout' => 60]);

            $response = $client->post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'model' => $model,
                    'input' => $text, // OpenAI + Ollama /v1/embeddings
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            // OpenAI / LM Studio / Ollama v1 format: data[0].embedding
            if (isset($data['data'][0]['embedding']) && is_array($data['data'][0]['embedding'])) {
                return array_map('floatval', $data['data'][0]['embedding']);
            }

            // Ollama /api/embed format: embeddings[0]
            if (isset($data['embeddings'][0]) && is_array($data['embeddings'][0])) {
                return array_map('floatval', $data['embeddings'][0]);
            }

            // Ollama /api/embeddings format: embedding
            if (isset($data['embedding']) && is_array($data['embedding'])) {
                return array_map('floatval', $data['embedding']);
            }

            Log::warning('AI Chatbox RAG: Embedding API returned an unrecognised format.', [
                'keys' => array_keys($data ?? []),
            ]);

            return null;

        } catch (\Throwable $e) {
            Log::error('AI Chatbox RAG: Embedding API call failed.', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
