<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use SyafiqUnijaya\AiChatbox\AiManager;
use SyafiqUnijaya\AiChatbox\Engine\AiProvider;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class AiManagerTest extends TestCase
{
    private function setProviders(array $providers): void
    {
        $this->app['config']->set('ai-chatbox.providers', $providers);
    }

    // ── provider() — default ──────────────────────────────────────────────────

    public function test_provider_default_returns_ai_provider(): void
    {
        $provider = $this->app->make(AiManager::class)->provider('default');

        $this->assertInstanceOf(AiProvider::class, $provider);
    }

    public function test_provider_default_config_matches_global(): void
    {
        $this->app['config']->set('ai-chatbox.api_url',   'http://test-default.example.com');
        $this->app['config']->set('ai-chatbox.api_model', 'default-model');

        $provider = $this->app->make(AiManager::class)->provider('default');

        $this->assertSame('http://test-default.example.com', $provider->getConfig()['api_url']);
        $this->assertSame('default-model',                   $provider->getConfig()['api_model']);
    }

    // ── provider() — named ────────────────────────────────────────────────────

    public function test_named_provider_overrides_api_keys(): void
    {
        $this->setProviders([
            'openai' => [
                'api_url'   => 'https://api.openai.com/v1/chat/completions',
                'api_token' => 'sk-test',
                'api_model' => 'gpt-4o',
            ],
        ]);

        $provider = $this->app->make(AiManager::class)->provider('openai');

        $this->assertSame('https://api.openai.com/v1/chat/completions', $provider->getConfig()['api_url']);
        $this->assertSame('sk-test', $provider->getConfig()['api_token']);
        $this->assertSame('gpt-4o',  $provider->getConfig()['api_model']);
    }

    public function test_named_provider_inherits_global_settings(): void
    {
        $this->app['config']->set('ai-chatbox.temperature', 0.42);
        $this->setProviders([
            'groq' => [
                'api_url'   => 'https://api.groq.com/openai/v1/chat/completions',
                'api_token' => 'groq-key',
                'api_model' => 'llama3-70b',
            ],
        ]);

        $provider = $this->app->make(AiManager::class)->provider('groq');

        $this->assertSame(0.42, $provider->getConfig()['temperature']);
    }

    public function test_named_provider_can_override_temperature(): void
    {
        $this->app['config']->set('ai-chatbox.temperature', 0.7);
        $this->setProviders([
            'cold' => [
                'api_url'     => 'http://cold.example.com',
                'api_token'   => 'token',
                'api_model'   => 'model',
                'temperature' => 0.0,
            ],
        ]);

        $provider = $this->app->make(AiManager::class)->provider('cold');

        $this->assertSame(0.0, $provider->getConfig()['temperature']);
    }

    // ── error case ────────────────────────────────────────────────────────────

    public function test_unknown_provider_throws_invalid_argument_exception(): void
    {
        $this->setProviders([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/doesnotexist/');

        $this->app->make(AiManager::class)->provider('doesnotexist');
    }

    // ── __call delegation ─────────────────────────────────────────────────────

    public function test_manager_is_singleton(): void
    {
        $a = $this->app->make(AiManager::class);
        $b = $this->app->make(AiManager::class);

        $this->assertSame($a, $b);
    }
}
