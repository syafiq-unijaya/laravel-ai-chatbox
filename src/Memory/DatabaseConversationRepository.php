<?php
namespace SyafiqUnijaya\AiChatbox\Memory;

use SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface;
use SyafiqUnijaya\AiChatbox\Memory\Models\Conversation;
use SyafiqUnijaya\AiChatbox\Memory\Models\Message;

/**
 * Stores conversation history in the database.
 * Requires the ai_chatbox_conversations and ai_chatbox_messages tables.
 *
 * Enable with: AI_CHATBOX_MEMORY_DRIVER=database
 * Then run:    php artisan migrate
 */
class DatabaseConversationRepository implements ConversationRepositoryInterface
{
    public function getHistory(string $threadId): array
    {
        $conversation = Conversation::where('thread_id', $threadId)->first();

        if (!$conversation) {
            return [];
        }

        return $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();
    }

    public function saveHistory(string $threadId, array $history): void
    {
        $conversation = Conversation::firstOrCreate(
            ['thread_id' => $threadId],
            ['user_id' => auth()->id()]
        );

        $conversation->messages()->delete();

        if (!empty($history)) {
            $conversation->messages()->createMany($history);
        }

        // Keep updated_at current so the prune command can use it as last-activity time
        $conversation->touch();
    }

    public function trimToLimit(string $threadId, int $maxPairs): void
    {
        $conversation = Conversation::where('thread_id', $threadId)->first();

        if (!$conversation) {
            return;
        }

        $total = $conversation->messages()->count();
        $max = $maxPairs * 2;

        if ($total <= $max) {
            return;
        }

        $excess = $total - $max;
        $ids = $conversation->messages()
            ->orderBy('id')
            ->limit($excess)
            ->pluck('id');

        Message::whereIn('id', $ids)->delete();
    }

    public function clear(string $threadId): void
    {
        $conversation = Conversation::where('thread_id', $threadId)->first();
        $conversation?->messages()->delete();
    }
}
