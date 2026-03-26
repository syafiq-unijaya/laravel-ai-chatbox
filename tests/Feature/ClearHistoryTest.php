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
}
