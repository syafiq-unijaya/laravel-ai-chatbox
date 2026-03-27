<?php
namespace SyafiqUnijaya\AiChatbox\Engine;

use Illuminate\Support\Facades\Log;
use SyafiqUnijaya\AiChatbox\Services\EmbeddingService;
use SyafiqUnijaya\AiChatbox\Services\RagRetriever;

class PromptBuilder
{
    /**
     * Assemble the full messages array for an AI chat completion request.
     *
     * Message order:
     *   [system prompt] → [conversation history] → [RAG context] → [user message]
     *
     * RAG context is placed immediately before the user turn so the model
     * pays maximum attention to the retrieved knowledge-base content.
     *
     * @param  array<int, array{role: string, content: string}>  $history  Pre-trimmed history
     * @param  array<string, mixed>  $cfg  Package config array (or subset)
     * @return array<int, array{role: string, content: string}>
     */
    public function build(string $userMessage, array $history, array $cfg): array
    {
        $language = $cfg['language'] ?? 'English';
        $systemPrompt = $cfg['system_prompt'] ?? '';
        $messages = [];

        // 1. System prompt
        if (!empty($systemPrompt)) {
            $messages[] = ['role' => 'system', 'content' => str_replace('{language}', $language, $systemPrompt)];
        }

        // 2. Conversation history
        foreach ($history as $entry) {
            $messages[] = $entry;
        }

        // 3. RAG context (placed nearest to the user turn for best model attention)
        foreach ($this->ragContext($userMessage, $cfg) as $ragMsg) {
            $messages[] = $ragMsg;
        }

        // 4. User message — language reminder is appended to the outgoing payload
        //    but intentionally NOT stored in history, so it doesn't accumulate.
        $apiMessage = (!empty($systemPrompt) && !empty($language))
        ? $userMessage . "\n\n[Important: Reply in {$language} only.]"
        : $userMessage;

        $messages[] = ['role' => 'user', 'content' => $apiMessage];

        return $messages;
    }

    /**
     * Build a system-role messages array from the configured system prompt.
     * Returns an empty array when the system prompt is blank.
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function systemMessages(array $cfg): array
    {
        $language = $cfg['language'] ?? 'English';
        $system = str_replace('{language}', $language, $cfg['system_prompt'] ?? '');

        return empty($system) ? [] : [['role' => 'system', 'content' => $system]];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{role: string, content: string}>
     */
    private function ragContext(string $query, array $cfg): array
    {
        if (!($cfg['rag_enabled'] ?? false)) {
            return [];
        }

        try {
            $retriever = new RagRetriever(new EmbeddingService(
                $cfg['rag_embedding_url'] ?? null,
                $cfg['rag_embedding_model'] ?? null,
                $cfg['api_token'] ?? null,
            ));
            $chunks = $retriever->retrieve($query);

            if (empty($chunks)) {
                return [];
            }

            $joined = implode("\n\n---\n\n", $chunks);
            $prompt = $cfg['rag_context_prompt'] ?? '';

            $content = str_contains($prompt, '{chunks}')
            ? str_replace('{chunks}', $joined, $prompt)
            : ($prompt !== '' ? $prompt . "\n\n" . $joined : $joined);

            return [['role' => 'system', 'content' => $content]];

        } catch (\Throwable $e) {
            Log::warning('AI Chatbox RAG retrieval failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}
