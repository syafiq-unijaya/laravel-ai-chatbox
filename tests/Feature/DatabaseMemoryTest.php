<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SyafiqUnijaya\AiChatbox\Memory\DatabaseConversationRepository;
use SyafiqUnijaya\AiChatbox\Memory\Models\Conversation;
use SyafiqUnijaya\AiChatbox\Memory\Models\Message;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

/**
 * Tests the database memory driver end-to-end:
 *   - DatabaseConversationRepository directly
 *   - The full HTTP endpoints with AI_CHATBOX_MEMORY_DRIVER=database
 */
class DatabaseMemoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useDatabase();
    }

    // ── DatabaseConversationRepository unit-style tests ───────────────────────

    public function test_get_history_returns_empty_for_unknown_thread(): void
    {
        $repo = new DatabaseConversationRepository();

        $this->assertSame([], $repo->getHistory('550e8400-e29b-4d4f-a716-446655440000'));
    }

    public function test_save_history_creates_conversation_and_messages(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ]);

        $this->assertDatabaseCount('ai_chatbox_conversations', 1);
        $this->assertDatabaseCount('ai_chatbox_messages', 2);

        $this->assertDatabaseHas('ai_chatbox_conversations', ['thread_id' => $threadId]);
        $this->assertDatabaseHas('ai_chatbox_messages', ['role' => 'user',      'content' => 'Hello']);
        $this->assertDatabaseHas('ai_chatbox_messages', ['role' => 'assistant', 'content' => 'Hi there!']);
    }

    public function test_save_history_replaces_existing_messages(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'old message'],
            ['role' => 'assistant', 'content' => 'old reply'],
        ]);

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'new message'],
            ['role' => 'assistant', 'content' => 'new reply'],
        ]);

        $this->assertDatabaseCount('ai_chatbox_conversations', 1); // same conversation
        $this->assertDatabaseCount('ai_chatbox_messages', 2);       // replaced, not appended

        $this->assertDatabaseMissing('ai_chatbox_messages', ['content' => 'old message']);
        $this->assertDatabaseHas('ai_chatbox_messages',    ['content' => 'new message']);
    }

    public function test_get_history_returns_messages_in_order(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'first'],
            ['role' => 'assistant', 'content' => 'second'],
            ['role' => 'user',      'content' => 'third'],
            ['role' => 'assistant', 'content' => 'fourth'],
        ]);

        $history = $repo->getHistory($threadId);

        $this->assertCount(4, $history);
        $this->assertSame('first',  $history[0]['content']);
        $this->assertSame('second', $history[1]['content']);
        $this->assertSame('third',  $history[2]['content']);
        $this->assertSame('fourth', $history[3]['content']);
    }

    public function test_trim_to_limit_drops_oldest_pairs(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'msg1'],
            ['role' => 'assistant', 'content' => 'rep1'],
            ['role' => 'user',      'content' => 'msg2'],
            ['role' => 'assistant', 'content' => 'rep2'],
            ['role' => 'user',      'content' => 'msg3'],
            ['role' => 'assistant', 'content' => 'rep3'],
        ]);

        $repo->trimToLimit($threadId, 2); // keep only newest 2 pairs

        $history = $repo->getHistory($threadId);
        $this->assertCount(4, $history);
        $this->assertSame('msg2', $history[0]['content']); // oldest kept
        $this->assertSame('rep3', $history[3]['content']); // newest
    }

    public function test_trim_to_limit_does_nothing_when_under_limit(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'only'],
            ['role' => 'assistant', 'content' => 'one pair'],
        ]);

        $repo->trimToLimit($threadId, 10);

        $this->assertDatabaseCount('ai_chatbox_messages', 2);
    }

    public function test_clear_removes_all_messages(): void
    {
        $repo     = new DatabaseConversationRepository();
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi!'],
        ]);

        $repo->clear($threadId);

        $this->assertSame([], $repo->getHistory($threadId));
        $this->assertDatabaseCount('ai_chatbox_messages', 0);
        $this->assertDatabaseCount('ai_chatbox_conversations', 1); // conversation row kept
    }

    public function test_clear_is_scoped_to_thread(): void
    {
        $repo    = new DatabaseConversationRepository();
        $threadA = '550e8400-e29b-4d4f-a716-446655440000';
        $threadB = '6ba7b810-9dad-4d4f-80b4-00c04fd430c8';

        $repo->saveHistory($threadA, [['role' => 'user', 'content' => 'A']]);
        $repo->saveHistory($threadB, [['role' => 'user', 'content' => 'B']]);

        $repo->clear($threadA);

        $this->assertSame([], $repo->getHistory($threadA));
        $this->assertCount(1, $repo->getHistory($threadB));
    }

    // ── Full HTTP endpoint tests with DB driver ───────────────────────────────

    public function test_send_message_stores_history_in_database(): void
    {
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $this->mockGuzzle([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'DB reply']]],
            ])),
        ]);

        $this->postJson('/ai-chatbox/message', [
            'message'   => 'DB message',
            'thread_id' => $threadId,
        ])->assertOk()->assertJsonFragment(['reply' => 'DB reply']);

        $this->assertDatabaseHas('ai_chatbox_messages', ['role' => 'user',      'content' => 'DB message']);
        $this->assertDatabaseHas('ai_chatbox_messages', ['role' => 'assistant', 'content' => 'DB reply']);
    }

    public function test_send_message_history_persists_across_requests(): void
    {
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $this->mockGuzzle([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Reply 1']]]])),
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Reply 2']]]])),
        ]);

        // First message
        $this->postJson('/ai-chatbox/message', ['message' => 'Msg 1', 'thread_id' => $threadId]);

        // Second message — history from first request must be present in DB
        $this->postJson('/ai-chatbox/message', ['message' => 'Msg 2', 'thread_id' => $threadId]);

        $history = (new DatabaseConversationRepository())->getHistory($threadId);

        $this->assertCount(4, $history); // 2 pairs
        $this->assertSame('Msg 1',   $history[0]['content']);
        $this->assertSame('Reply 1', $history[1]['content']);
        $this->assertSame('Msg 2',   $history[2]['content']);
        $this->assertSame('Reply 2', $history[3]['content']);
    }

    public function test_different_threads_have_isolated_db_histories(): void
    {
        $threadA = '550e8400-e29b-4d4f-a716-446655440000';
        $threadB = '6ba7b810-9dad-4d4f-80b4-00c04fd430c8';

        $this->mockGuzzle([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Reply A']]]])),
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Reply B']]]])),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Thread A', 'thread_id' => $threadA]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Thread B', 'thread_id' => $threadB]);

        $repo    = new DatabaseConversationRepository();
        $histA   = $repo->getHistory($threadA);
        $histB   = $repo->getHistory($threadB);

        $this->assertSame('Thread A', $histA[0]['content']);
        $this->assertSame('Thread B', $histB[0]['content']);
    }

    public function test_clear_history_endpoint_removes_db_messages(): void
    {
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';
        $repo     = new DatabaseConversationRepository();

        $repo->saveHistory($threadId, [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi'],
        ]);

        $this->postJson('/ai-chatbox/clear', ['thread_id' => $threadId])
             ->assertOk()
             ->assertJsonFragment(['status' => 'ok']);

        $this->assertSame([], $repo->getHistory($threadId));
    }

    public function test_history_is_not_stored_when_disabled(): void
    {
        $this->app['config']->set('ai-chatbox.history_enabled', false);

        $this->mockGuzzle([
            new Response(200, [], json_encode(['choices' => [['message' => ['content' => 'Hi!']]]])),
        ]);

        $this->postJson('/ai-chatbox/message', [
            'message'   => 'Hello',
            'thread_id' => '550e8400-e29b-4d4f-a716-446655440000',
        ])->assertOk();

        $this->assertDatabaseCount('ai_chatbox_messages', 0);
    }
}
