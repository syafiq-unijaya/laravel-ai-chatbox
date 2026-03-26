<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
}
