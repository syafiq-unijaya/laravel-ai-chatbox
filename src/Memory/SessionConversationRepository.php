<?php
namespace SyafiqUnijaya\AiChatbox\Memory;

use SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface;

/**
 * Stores conversation history in the PHP session.
 * Zero-config, default driver.
 */
class SessionConversationRepository implements ConversationRepositoryInterface
{
    private const BASE_KEY = 'ai_chatbox_history';

    public function getHistory(string $threadId): array
    {
        return session($this->key($threadId), []);
    }

    public function saveHistory(string $threadId, array $history): void
    {
        session([$this->key($threadId) => $history]);
    }

    public function trimToLimit(string $threadId, int $maxPairs): void
    {
        $key = $this->key($threadId);
        $history = session($key, []);
        $max = $maxPairs * 2;

        if (count($history) > $max) {
            session([$key => array_slice($history, count($history) - $max)]);
        }
    }

    public function clear(string $threadId): void
    {
        session()->forget($this->key($threadId));
    }

    // ── Session key ───────────────────────────────────────────────────────────

    /**
     * Return the session key scoped to the given thread ID.
     * Falls back to the base key when no valid UUID v4 is provided,
     * preserving backward compatibility with clients that omit thread_id.
     */
    private function key(string $threadId): string
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $threadId)) {
            return self::BASE_KEY . '_' . str_replace('-', '', strtolower($threadId));
        }

        return self::BASE_KEY;
    }
}
