<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SyafiqUnijaya\AiChatbox\Engine\AiProvider;
use SyafiqUnijaya\AiChatbox\Engine\Contracts\AiEngineInterface;
use SyafiqUnijaya\AiChatbox\Engine\PromptBuilder;

class AiProviderTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cfg(array $overrides = []): array
    {
        return array_merge([
            'api_url'       => 'http://ai.test/v1/chat/completions',
            'api_token'     => 'test-token',
            'api_model'     => 'test-model',
            'system_prompt' => 'You are helpful.',
            'language'      => 'English',
            'temperature'   => 0.7,
            'rag_enabled'   => false,
        ], $overrides);
    }

    private function mockEngine(string $reply = 'AI reply'): AiEngineInterface
    {
        $engine = $this->createMock(AiEngineInterface::class);
        $engine->method('complete')->willReturn($reply);
        $engine->method('stream')->willReturnCallback(
            function (array $messages, array $options, callable $onToken) use ($reply): string {
                $onToken($reply);
                return $reply;
            }
        );
        $engine->method('beginStream')->willReturn(
            function (callable $onToken) use ($reply): string {
                $onToken($reply);
                return $reply;
            }
        );
        return $engine;
    }

    private function provider(array $cfg = [], string $reply = 'AI reply'): AiProvider
    {
        return new AiProvider($this->mockEngine($reply), new PromptBuilder(), $this->cfg($cfg));
    }

    // ── chat() ────────────────────────────────────────────────────────────────

    public function test_chat_returns_engine_reply(): void
    {
        $result = $this->provider(reply: 'Hello from AI!')->chat('Hi');

        $this->assertSame('Hello from AI!', $result);
    }

    public function test_chat_passes_history_to_prompt_builder(): void
    {
        $capturedMessages = null;

        $engine = $this->createMock(AiEngineInterface::class);
        $engine->method('complete')
            ->willReturnCallback(function (array $messages) use (&$capturedMessages): string {
                $capturedMessages = $messages;
                return 'OK';
            });

        $provider = new AiProvider($engine, new PromptBuilder(), $this->cfg());
        $history  = [
            ['role' => 'user',      'content' => 'previous message'],
            ['role' => 'assistant', 'content' => 'previous reply'],
        ];

        $provider->chat('new message', $history);

        // Should have: system, user-prev, assistant-prev, user-new
        $this->assertCount(4, $capturedMessages);
        $this->assertSame('previous message', $capturedMessages[1]['content']);
        $this->assertStringContainsString('new message', $capturedMessages[3]['content']);
    }

    // ── stream() ─────────────────────────────────────────────────────────────

    public function test_stream_with_callback_calls_on_token(): void
    {
        $received = '';
        $this->provider(reply: 'streamed')->stream('Hello', [], function (string $t) use (&$received) {
            $received .= $t;
        });

        $this->assertSame('streamed', $received);
    }

    public function test_stream_with_callback_returns_full_reply(): void
    {
        $result = $this->provider(reply: 'full reply')->stream('Hello', [], fn($t) => null);

        $this->assertSame('full reply', $result);
    }

    public function test_stream_without_callback_returns_closure(): void
    {
        $result = $this->provider()->stream('Hello');

        $this->assertInstanceOf(\Closure::class, $result);
    }

    public function test_stream_closure_calls_on_token_when_invoked(): void
    {
        $received = '';
        $reader   = $this->provider(reply: 'token')->stream('Hello');
        $reader(function (string $t) use (&$received) { $received .= $t; });

        $this->assertSame('token', $received);
    }

    // ── Fluent modifiers — immutability ───────────────────────────────────────

    public function test_with_model_returns_new_instance(): void
    {
        $original = $this->provider();
        $modified = $original->withModel('gpt-4o');

        $this->assertNotSame($original, $modified);
    }

    public function test_with_model_does_not_mutate_original(): void
    {
        $original = $this->provider(['api_model' => 'model-a']);
        $original->withModel('model-b');

        $this->assertSame('model-a', $original->getConfig()['api_model']);
    }

    public function test_with_model_sets_new_model(): void
    {
        $provider = $this->provider()->withModel('gpt-4o-mini');

        $this->assertSame('gpt-4o-mini', $provider->getConfig()['api_model']);
    }

    public function test_with_system_prompt_sets_prompt(): void
    {
        $provider = $this->provider()->withSystemPrompt('You are a pirate.');

        $this->assertSame('You are a pirate.', $provider->getConfig()['system_prompt']);
    }

    public function test_with_language_sets_language(): void
    {
        $provider = $this->provider()->withLanguage('French');

        $this->assertSame('French', $provider->getConfig()['language']);
    }

    public function test_with_temperature_sets_temperature(): void
    {
        $provider = $this->provider()->withTemperature(0.1);

        $this->assertSame(0.1, $provider->getConfig()['temperature']);
    }

    public function test_with_max_tokens_sets_max_tokens(): void
    {
        $provider = $this->provider()->withMaxTokens(500);

        $this->assertSame(500, $provider->getConfig()['max_tokens']);
    }

    public function test_with_max_tokens_accepts_null(): void
    {
        $provider = $this->provider()->withMaxTokens(null);

        $this->assertNull($provider->getConfig()['max_tokens']);
    }

    public function test_with_timeout_sets_timeout(): void
    {
        $provider = $this->provider()->withTimeout(60);

        $this->assertSame(60, $provider->getConfig()['timeout']);
    }

    public function test_with_config_merges_overrides(): void
    {
        $provider = $this->provider()->withConfig(['api_model' => 'custom', 'temperature' => 0.0]);

        $this->assertSame('custom', $provider->getConfig()['api_model']);
        $this->assertSame(0.0, $provider->getConfig()['temperature']);
    }

    // ── Modifier chaining ─────────────────────────────────────────────────────

    public function test_modifiers_can_be_chained(): void
    {
        $provider = $this->provider()
            ->withModel('gpt-4o')
            ->withTemperature(0.3)
            ->withSystemPrompt('Be concise.')
            ->withLanguage('Malay')
            ->withMaxTokens(200)
            ->withTimeout(45);

        $cfg = $provider->getConfig();

        $this->assertSame('gpt-4o',       $cfg['api_model']);
        $this->assertSame(0.3,            $cfg['temperature']);
        $this->assertSame('Be concise.',  $cfg['system_prompt']);
        $this->assertSame('Malay',        $cfg['language']);
        $this->assertSame(200,            $cfg['max_tokens']);
        $this->assertSame(45,             $cfg['timeout']);
    }

    // ── getConfig() ───────────────────────────────────────────────────────────

    public function test_get_config_returns_current_config(): void
    {
        $provider = $this->provider(['api_token' => 'my-token']);

        $this->assertSame('my-token', $provider->getConfig()['api_token']);
    }
}
