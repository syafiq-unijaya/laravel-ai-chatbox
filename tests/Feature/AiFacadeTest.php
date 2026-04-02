<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use SyafiqUnijaya\AiChatbox\AI;
use SyafiqUnijaya\AiChatbox\AiManager;
use SyafiqUnijaya\AiChatbox\Engine\AiProvider;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class AiFacadeTest extends TestCase
{
    // ── Container / facade resolution ─────────────────────────────────────────

    public function test_ai_manager_is_resolvable_from_container(): void
    {
        $manager = $this->app->make(AiManager::class);

        $this->assertInstanceOf(AiManager::class, $manager);
    }

    public function test_ai_manager_is_singleton(): void
    {
        $a = $this->app->make(AiManager::class);
        $b = $this->app->make(AiManager::class);

        $this->assertSame($a, $b);
    }

    public function test_ai_facade_resolves_manager(): void
    {
        $this->assertInstanceOf(AiManager::class, AI::getFacadeRoot());
    }

    public function test_ai_provider_default_returns_ai_provider(): void
    {
        $provider = AI::provider('default');

        $this->assertInstanceOf(AiProvider::class, $provider);
    }

    public function test_ai_provider_default_resolves_to_active_named_provider(): void
    {
        $this->app['config']->set('ai-chatbox.providers.testprovider', [
            'api_url'   => 'http://test.provider.com',
            'api_token' => 'test-token',
            'api_model' => 'test-model-x',
        ]);

        $provider = AI::provider('default');

        $this->assertSame('http://test.provider.com', $provider->getConfig()['api_url']);
        $this->assertSame('test-model-x',             $provider->getConfig()['api_model']);
    }

    // ── Named providers ───────────────────────────────────────────────────────

    public function test_ai_provider_named_resolves_correct_url(): void
    {
        $this->app['config']->set('ai-chatbox.providers.ollama', [
            'api_url'   => 'http://localhost:11434/v1/chat/completions',
            'api_token' => 'ollama',
            'api_model' => 'phi3:mini',
        ]);

        $provider = AI::provider('ollama');

        $this->assertSame('http://localhost:11434/v1/chat/completions', $provider->getConfig()['api_url']);
        $this->assertSame('ollama',    $provider->getConfig()['api_token']);
        $this->assertSame('phi3:mini', $provider->getConfig()['api_model']);
    }

    public function test_ai_provider_named_inherits_global_temperature(): void
    {
        $this->app['config']->set('ai-chatbox.temperature', 0.3);
        $this->app['config']->set('ai-chatbox.providers.openai', [
            'api_url'   => 'https://api.openai.com/v1/chat/completions',
            'api_token' => 'sk-test',
            'api_model' => 'gpt-4o',
        ]);

        $provider = AI::provider('openai');

        $this->assertSame(0.3, $provider->getConfig()['temperature']);
    }

    public function test_ai_provider_throws_for_unknown_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AI::provider('doesnotexist');
    }

    // ── Fluent modifiers through the facade ───────────────────────────────────

    public function test_with_model_modifier_via_facade(): void
    {
        $provider = AI::provider('default')->withModel('new-model');

        $this->assertSame('new-model', $provider->getConfig()['api_model']);
    }

    public function test_modifiers_do_not_affect_subsequent_provider_calls(): void
    {
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_model', 'original-model');

        AI::provider('default')->withModel('modified-model');

        // A fresh call should still return the original model
        $this->assertSame('original-model', AI::provider('default')->getConfig()['api_model']);
    }

    // ── chat() end-to-end via the facade ─────────────────────────────────────

    public function test_ai_provider_chat_returns_reply(): void
    {
        $this->mockGuzzle([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Hello from provider!']]],
            ])),
        ]);

        $reply = AI::provider('default')->chat('Hi');

        $this->assertSame('Hello from provider!', $reply);
    }

    public function test_ai_direct_chat_uses_default_provider(): void
    {
        $this->mockGuzzle([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Direct reply']]],
            ])),
        ]);

        // AI::chat() should delegate to AI::provider('default')->chat()
        $reply = AI::chat('Hello');

        $this->assertSame('Direct reply', $reply);
    }

    public function test_ai_provider_chat_with_history(): void
    {
        $this->mockGuzzle([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Context-aware reply']]],
            ])),
        ]);

        $history = [
            ['role' => 'user',      'content' => 'Previous question'],
            ['role' => 'assistant', 'content' => 'Previous answer'],
        ];

        $reply = AI::provider('default')->chat('Follow-up question', $history);

        $this->assertSame('Context-aware reply', $reply);
    }

    public function test_chained_modifiers_are_used_in_request(): void
    {
        $this->mockGuzzle([
            new Response(200, [], json_encode([
                'choices' => [['message' => ['content' => 'Modified reply']]],
            ])),
        ]);

        $reply = AI::provider('default')
            ->withSystemPrompt('You are a brief assistant.')
            ->withTemperature(0.1)
            ->chat('Hello');

        $this->assertSame('Modified reply', $reply);
    }
}
