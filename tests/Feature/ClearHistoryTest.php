<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class ClearHistoryTest extends TestCase
{
    public function test_returns_ok_status(): void
    {
        $this->postJson('/ai-chatbox/clear')
            ->assertOk()
            ->assertJsonFragment(['status' => 'ok']);
    }

    public function test_clears_session_history(): void
    {
        session(['ai_chatbox_history' => [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi!'],
        ]]);

        $this->postJson('/ai-chatbox/clear');

        $this->assertNull(session('ai_chatbox_history'));
    }

    public function test_succeeds_when_history_is_already_empty(): void
    {
        $this->postJson('/ai-chatbox/clear')
            ->assertOk();
    }

    // ── Thread-scoped clear ───────────────────────────────────────────────────

    public function test_clears_specific_thread_history(): void
    {
        $threadId   = '550e8400-e29b-4d4f-a716-446655440000';
        $sessionKey = 'ai_chatbox_history_' . str_replace('-', '', $threadId);

        session([$sessionKey => [
            ['role' => 'user',      'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi!'],
        ]]);

        $this->postJson('/ai-chatbox/clear', ['thread_id' => $threadId]);

        $this->assertNull(session($sessionKey));
    }

    public function test_clear_with_thread_id_does_not_affect_other_threads(): void
    {
        $threadA = '550e8400-e29b-4d4f-a716-446655440000';
        $threadB = '6ba7b810-9dad-4d4f-80b4-00c04fd430c8';
        $keyA    = 'ai_chatbox_history_' . str_replace('-', '', $threadA);
        $keyB    = 'ai_chatbox_history_' . str_replace('-', '', $threadB);

        session([
            $keyA => [['role' => 'user', 'content' => 'A']],
            $keyB => [['role' => 'user', 'content' => 'B']],
        ]);

        $this->postJson('/ai-chatbox/clear', ['thread_id' => $threadA]);

        $this->assertNull(session($keyA));
        $this->assertNotNull(session($keyB));
    }
}
