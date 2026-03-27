<?php
namespace SyafiqUnijaya\AiChatbox\Memory;

/**
 * Trims conversation history to fit within both message-count and token-count
 * limits before it is sent to the AI engine.
 *
 * Works on in-memory arrays; does not read from or write to any storage.
 * The caller is responsible for persisting the trimmed result.
 */
class ContextManager
{
    /**
     * Return a trimmed copy of $history that fits within the configured limits.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @param  array<int, array{role: string, content: string}>  $systemMessages  Already-built system message(s)
     * @param  array<string, mixed>  $cfg
     * @return array<int, array{role: string, content: string}>
     */
    public function trim(array $history, array $systemMessages, string $userMessage, array $cfg): array
    {
        $historyLimit = (int) ($cfg['history_limit'] ?? 50);
        $contextTokenLimit = (int) ($cfg['context_token_limit'] ?? 4000);
        $language = $cfg['language'] ?? 'English';
        $systemPrompt = $cfg['system_prompt'] ?? '';

        // 1. Trim by pair count (fast, no token estimation needed)
        $max = $historyLimit * 2;
        if (count($history) > $max) {
            $history = array_slice($history, count($history) - $max);
        }

        if ($contextTokenLimit <= 0) {
            return $history;
        }

        // 2. Trim by estimated token count (~4 chars per token)
        //    The language reminder is part of the API payload but not stored in history.
        $apiMessage = (!empty($systemPrompt) && !empty($language))
        ? $userMessage . "\n\n[Important: Reply in {$language} only.]"
        : $userMessage;

        while (count($history) >= 2) {
            $all = array_merge($systemMessages, $history, [['role' => 'user', 'content' => $apiMessage]]);

            if ($this->estimateTokens($all) <= $contextTokenLimit) {
                break;
            }

            array_splice($history, 0, 2); // drop oldest user + assistant pair
        }

        return $history;
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function estimateTokens(array $messages): int
    {
        $chars = array_sum(array_map(fn($m) => strlen($m['content'] ?? ''), $messages));

        return (int) ceil($chars / 4);
    }
}
