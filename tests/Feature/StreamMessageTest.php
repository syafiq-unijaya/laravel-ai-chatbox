<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class StreamMessageTest extends TestCase
{
    /**
     * Build a mock Guzzle response whose body is a streaming SSE payload.
     * Matches the OpenAI-compatible format (data: {...} lines).
     */
    private function streamResponse(array $tokens, int $status = 200): Response
    {
        $body = '';
        foreach ($tokens as $token) {
            $body .= 'data: ' . json_encode(['choices' => [['delta' => ['content' => $token]]]]) . "\n\n";
        }
        $body .= "data: [DONE]\n\n";

        return new Response($status, ['Content-Type' => 'text/event-stream'], Utils::streamFor($body));
    }

    /**
     * Build a mock response in Ollama native streaming format.
     */
    private function ollamaStreamResponse(array $tokens): Response
    {
        $body = '';
        foreach ($tokens as $i => $token) {
            $done  = ($i === count($tokens) - 1);
            $body .= json_encode(['message' => ['content' => $token], 'done' => $done]) . "\n";
        }

        return new Response(200, ['Content-Type' => 'application/x-ndjson'], Utils::streamFor($body));
    }

    // ── Configuration errors ──────────────────────────────────────────────────

    public function test_returns_e01_when_api_url_is_empty(): void
    {
        $this->app['config']->set('ai-chatbox.api_url', '');

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E01']);
    }

    public function test_returns_e03_when_api_token_is_empty(): void
    {
        $this->app['config']->set('ai-chatbox.api_token', '');

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E03']);
    }

    public function test_returns_e04_when_model_name_is_invalid(): void
    {
        $this->app['config']->set('ai-chatbox.api_model', 'bad model!');

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hello'])
            ->assertStatus(500)
            ->assertJsonFragment(['code' => 'E04']);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function test_validates_message_is_required(): void
    {
        $this->postJson('/ai-chatbox/stream', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    // ── Successful streaming ──────────────────────────────────────────────────

    public function test_streams_openai_compatible_tokens(): void
    {
        $this->mockGuzzle([$this->streamResponse(['Hello', ' world', '!'])]);

        $response = $this->postJson('/ai-chatbox/stream', ['message' => 'Hi']);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));

        $body = $response->streamedContent();
        $this->assertStringContainsString('data: {"token":"Hello"}', $body);
        $this->assertStringContainsString('data: {"token":" world"}', $body);
        $this->assertStringContainsString('data: {"token":"!"}', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
    }

    public function test_streams_ollama_native_tokens(): void
    {
        $this->mockGuzzle([$this->ollamaStreamResponse(['Hi', ' there'])]);

        $body = $this->postJson('/ai-chatbox/stream', ['message' => 'Hello'])
            ->streamedContent();

        $this->assertStringContainsString('data: {"token":"Hi"}', $body);
        $this->assertStringContainsString('data: {"token":" there"}', $body);
        $this->assertStringContainsString('data: [DONE]', $body);
    }

    // ── Session history ───────────────────────────────────────────────────────

    public function test_saves_streamed_reply_to_session(): void
    {
        $this->mockGuzzle([$this->streamResponse(['Hello', ' world'])]);

        // streamedContent() triggers the streaming callback, which saves the session
        $this->postJson('/ai-chatbox/stream', ['message' => 'Hi'])->streamedContent();

        $history = session('ai_chatbox_history');
        $this->assertNotEmpty($history);
        $this->assertSame('Hi', $history[0]['content']);
        $this->assertSame('Hello world', $history[1]['content']);
    }

    public function test_does_not_save_history_when_disabled(): void
    {
        $this->app['config']->set('ai-chatbox.history_enabled', false);
        $this->mockGuzzle([$this->streamResponse(['Hi'])]);

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hello'])->streamedContent();

        $this->assertEmpty(session('ai_chatbox_history'));
    }

    public function test_scopes_streamed_history_to_thread_id(): void
    {
        $threadId   = '550e8400-e29b-4d4f-a716-446655440000';
        $sessionKey = 'ai_chatbox_history_' . str_replace('-', '', $threadId);

        $this->mockGuzzle([$this->streamResponse(['Streamed reply'])]);

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hi', 'thread_id' => $threadId])
            ->streamedContent();

        $this->assertNotEmpty(session($sessionKey));
        $this->assertNull(session('ai_chatbox_history'));
    }

    // ── Network errors ────────────────────────────────────────────────────────

    public function test_returns_e07_on_connection_refused(): void
    {
        $this->mockGuzzle([
            new ConnectException('cURL error 7: Connection refused', new Request('POST', '/')),
        ]);

        $this->postJson('/ai-chatbox/stream', ['message' => 'Hi'])
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E07']);
    }

    // ── Nginx buffering header ────────────────────────────────────────────────

    public function test_response_sets_x_accel_buffering_off(): void
    {
        $this->mockGuzzle([$this->streamResponse(['Hi'])]);

        $response = $this->postJson('/ai-chatbox/stream', ['message' => 'Hello']);

        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }
}
