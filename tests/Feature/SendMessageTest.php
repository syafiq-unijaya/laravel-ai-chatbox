<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class SendMessageTest extends TestCase
{
    private function openAiResponse(string $content): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'choices' => [['message' => ['content' => $content]]],
        ]));
    }

    private function ollamaResponse(string $content): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'message' => ['content' => $content],
        ]));
    }

    private function errorResponse(int $status, string $message): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode([
            'error' => ['message' => $message],
        ]));
    }

    // ── Configuration errors ─────────────────────────────────────────────────

    public function test_returns_e01_when_api_url_is_empty(): void
    {
        $this->app['config']->set('ai-chatbox.api_url', '');

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E01']);
    }

    public function test_returns_e03_when_api_token_is_empty(): void
    {
        $this->app['config']->set('ai-chatbox.api_token', '');

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E03']);
    }

    public function test_returns_e04_when_model_name_is_invalid(): void
    {
        $this->app['config']->set('ai-chatbox.api_model', 'invalid model name!');

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E04']);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_validates_message_is_required(): void
    {
        $this->postJson('/ai-chatbox/message', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_validates_message_max_length(): void
    {
        $this->postJson('/ai-chatbox/message', ['message' => str_repeat('a', 2001)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ── Successful responses ──────────────────────────────────────────────────

    public function test_returns_reply_from_openai_compatible_response(): void
    {
        $this->mockGuzzle([$this->openAiResponse('Hello from AI!')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertOk()
            ->assertJsonFragment(['reply' => 'Hello from AI!']);
    }

    public function test_returns_reply_from_ollama_native_response(): void
    {
        $this->mockGuzzle([$this->ollamaResponse('Hello from Ollama!')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertOk()
            ->assertJsonFragment(['reply' => 'Hello from Ollama!']);
    }

    // ── Empty / malformed response ────────────────────────────────────────────

    public function test_returns_e18_when_reply_is_null(): void
    {
        $this->mockGuzzle([
            new Response(200, [], json_encode(['unexpected' => 'format'])),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonFragment(['code' => 'E18']);
    }

    public function test_returns_e18_when_reply_is_empty_string(): void
    {
        $this->mockGuzzle([$this->openAiResponse('')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(502)
            ->assertJsonFragment(['code' => 'E18']);
    }

    // ── HTTP error codes ──────────────────────────────────────────────────────

    public function test_returns_e12_on_401_unauthorized(): void
    {
        $this->mockGuzzle([$this->errorResponse(401, 'Unauthorized')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(401)
            ->assertJsonFragment(['code' => 'E12']);
    }

    public function test_returns_e14_on_404_not_found(): void
    {
        $this->mockGuzzle([$this->errorResponse(404, 'Not found')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(404)
            ->assertJsonFragment(['code' => 'E14']);
    }

    public function test_returns_e15_on_429_rate_limited(): void
    {
        $this->mockGuzzle([$this->errorResponse(429, 'Rate limit exceeded')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(429)
            ->assertJsonFragment(['code' => 'E15']);
    }

    public function test_returns_e16_on_500_server_error(): void
    {
        $this->mockGuzzle([$this->errorResponse(500, 'Internal server error')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E16']);
    }

    // ── Network errors ────────────────────────────────────────────────────────

    public function test_returns_e07_when_connection_is_refused(): void
    {
        $this->mockGuzzle([
            new ConnectException('cURL error 7: Connection refused', new Request('POST', '/')),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E07']);
    }

    public function test_returns_e08_when_request_times_out(): void
    {
        $this->mockGuzzle([
            new ConnectException('cURL error 28: Operation timed out', new Request('POST', '/')),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E08']);
    }

    // ── Error message is not leaked to client ─────────────────────────────────

    public function test_raw_api_error_is_not_returned_to_client(): void
    {
        $this->mockGuzzle([$this->errorResponse(500, 'Internal DB error at 10.0.0.1:5432')]);

        $response = $this->postJson('/ai-chatbox/message', ['message' => 'Hello']);

        $this->assertStringNotContainsString('10.0.0.1', $response->getContent());
        $this->assertStringNotContainsString('DB error', $response->getContent());
    }

    // ── Session history ───────────────────────────────────────────────────────

    public function test_stores_conversation_in_session(): void
    {
        $this->mockGuzzle([$this->openAiResponse('Hi there!')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello']);

        $history = session('ai_chatbox_history');
        $this->assertNotEmpty($history);
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Hello', $history[0]['content']);
        $this->assertSame('assistant', $history[1]['role']);
        $this->assertSame('Hi there!', $history[1]['content']);
    }

    public function test_does_not_store_history_when_disabled(): void
    {
        $this->app['config']->set('ai-chatbox.history_enabled', false);
        $this->mockGuzzle([$this->openAiResponse('Hi!')]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello']);

        $this->assertEmpty(session('ai_chatbox_history'));
    }

    public function test_history_is_capped_at_configured_limit(): void
    {
        $this->app['config']->set('ai-chatbox.history_limit', 2);

        // Seed session with 2 existing pairs (4 entries = at the limit)
        session(['ai_chatbox_history' => [
            ['role' => 'user',      'content' => 'msg1'],
            ['role' => 'assistant', 'content' => 'reply1'],
            ['role' => 'user',      'content' => 'msg2'],
            ['role' => 'assistant', 'content' => 'reply2'],
        ]]);

        $this->mockGuzzle([$this->openAiResponse('reply3')]);
        $this->postJson('/ai-chatbox/message', ['message' => 'msg3']);

        $history = session('ai_chatbox_history');
        // Limit is 2 pairs = 4 entries max; oldest pair dropped, newest 2 pairs remain
        $this->assertCount(4, $history);
        $this->assertSame('msg2', $history[0]['content']);
    }

    // ── Conversation threads ──────────────────────────────────────────────────

    public function test_thread_id_scopes_history_to_its_own_session_key(): void
    {
        $threadId = '550e8400-e29b-4d4f-a716-446655440000';
        $expectedKey = 'ai_chatbox_history_' . str_replace('-', '', $threadId);

        $this->mockGuzzle([$this->openAiResponse('Reply A')]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Hello', 'thread_id' => $threadId]);

        $this->assertNotEmpty(session($expectedKey));
        $this->assertNull(session('ai_chatbox_history')); // default key must be untouched
    }

    public function test_different_thread_ids_have_isolated_histories(): void
    {
        $threadA = '550e8400-e29b-4d4f-a716-446655440000';
        $threadB = '6ba7b810-9dad-4d4f-80b4-00c04fd430c8';
        $keyA    = 'ai_chatbox_history_' . str_replace('-', '', $threadA);
        $keyB    = 'ai_chatbox_history_' . str_replace('-', '', $threadB);

        $this->mockGuzzle([
            $this->openAiResponse('Reply A'),
            $this->openAiResponse('Reply B'),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Thread A msg', 'thread_id' => $threadA]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Thread B msg', 'thread_id' => $threadB]);

        $this->assertSame('Thread A msg', session($keyA)[0]['content']);
        $this->assertSame('Thread B msg', session($keyB)[0]['content']);
    }

    public function test_invalid_thread_id_falls_back_to_default_session_key(): void
    {
        $this->mockGuzzle([$this->openAiResponse('Hi')]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Hello', 'thread_id' => 'not-a-valid-uuid']);

        $this->assertNotEmpty(session('ai_chatbox_history'));
    }

    // ── Token-based context trimming ──────────────────────────────────────────

    public function test_trims_history_when_context_token_limit_is_exceeded(): void
    {
        // Set a very tight token limit so even a small history gets trimmed
        $this->app['config']->set('ai-chatbox.context_token_limit', 20);
        $this->app['config']->set('ai-chatbox.history_limit', 100);

        // Seed with a large history that would exceed 20 tokens (80+ chars)
        session(['ai_chatbox_history' => [
            ['role' => 'user',      'content' => 'this is a long first message that pushes over the limit'],
            ['role' => 'assistant', 'content' => 'this is a long first assistant reply that also adds tokens'],
            ['role' => 'user',      'content' => 'short'],
            ['role' => 'assistant', 'content' => 'short'],
        ]]);

        $this->mockGuzzle([$this->openAiResponse('OK')]);
        $this->postJson('/ai-chatbox/message', ['message' => 'hi']);

        // The full history + current message exceeds 20 tokens, so old pairs are dropped
        // The stored history after the response should not have all 4 original entries + new pair
        $history = session('ai_chatbox_history');
        $this->assertNotEmpty($history);
        // Verify the oldest long pair was pruned (first two entries should NOT be the very long ones)
        $this->assertNotSame('this is a long first message that pushes over the limit', $history[0]['content']);
    }

    public function test_token_trimming_disabled_when_limit_is_zero(): void
    {
        $this->app['config']->set('ai-chatbox.context_token_limit', 0);
        $this->app['config']->set('ai-chatbox.history_limit', 10);

        session(['ai_chatbox_history' => [
            ['role' => 'user',      'content' => str_repeat('a', 1000)],
            ['role' => 'assistant', 'content' => str_repeat('b', 1000)],
        ]]);

        $this->mockGuzzle([$this->openAiResponse('Fine')]);
        // No exception should be thrown; history passed as-is (only message-count limit applies)
        $this->postJson('/ai-chatbox/message', ['message' => 'test'])->assertOk();
    }

    // ── Database memory driver ────────────────────────────────────────────────

    public function test_database_driver_persists_history_across_requests(): void
    {
        $this->useDatabase();

        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $this->mockGuzzle([
            $this->openAiResponse('Reply 1'),
            $this->openAiResponse('Reply 2'),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'First',  'thread_id' => $threadId]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Second', 'thread_id' => $threadId]);

        // History lives in DB, not session
        $this->assertEmpty(session('ai_chatbox_history'));
        $this->assertDatabaseCount('ai_chatbox_messages', 4); // 2 pairs
    }

    // ── Active provider routing ───────────────────────────────────────────────

    public function test_send_message_routes_through_active_provider(): void
    {
        // Top-level api_url/token are empty — would give E01/E03 if effectiveConfig() is ignored
        $this->app['config']->set('ai-chatbox.api_url', '');
        $this->app['config']->set('ai-chatbox.api_token', '');
        $this->app['config']->set('ai-chatbox.providers', [
            'myprovider' => [
                'api_url'   => 'http://active.example.com/v1/chat/completions',
                'api_token' => 'active-token',
                'api_model' => 'active-model',
            ],
        ]);
        $this->app['config']->set('ai-chatbox.active_provider', 'myprovider');

        $this->mockGuzzle([
            new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'choices' => [['message' => ['content' => 'Reply from active provider']]],
            ])),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'Hello'])
            ->assertOk()
            ->assertJsonFragment(['reply' => 'Reply from active provider']);
    }

    // ── Database memory driver ────────────────────────────────────────────────

    public function test_database_driver_history_limit_caps_stored_messages(): void
    {
        $this->useDatabase();
        $this->app['config']->set('ai-chatbox.history_limit', 1); // keep 1 pair max

        $threadId = '550e8400-e29b-4d4f-a716-446655440000';

        $this->mockGuzzle([
            $this->openAiResponse('Reply 1'),
            $this->openAiResponse('Reply 2'),
        ]);

        $this->postJson('/ai-chatbox/message', ['message' => 'First',  'thread_id' => $threadId]);
        $this->postJson('/ai-chatbox/message', ['message' => 'Second', 'thread_id' => $threadId]);

        // Only the newest 1 pair = 2 messages should remain
        $this->assertDatabaseCount('ai_chatbox_messages', 2);
        $this->assertDatabaseHas('ai_chatbox_messages', ['content' => 'Second']);
        $this->assertDatabaseMissing('ai_chatbox_messages', ['content' => 'First']);
    }
}
