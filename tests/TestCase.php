<?php
namespace SyafiqUnijaya\AiChatbox\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Orchestra\Testbench\TestCase as Orchestra;
use SyafiqUnijaya\AiChatbox\AiChatboxServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [AiChatboxServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=');
        $app['config']->set('app.url', 'http://localhost');
        $app['config']->set('app.debug', false);
        $app['config']->set('session.driver', 'array');

        // SQLite in-memory for RAG model tests
        $app['config']->set('database.default', 'testingdb');
        $app['config']->set('database.connections.testingdb', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('ai-chatbox.ssrf_protection', false);
        $app['config']->set('ai-chatbox.allowed_origins', ['http://localhost']);
        $app['config']->set('ai-chatbox.offline_message', 'AI service is currently unreachable.');
        $app['config']->set('ai-chatbox.memory_driver', 'session');
        $app['config']->set('ai-chatbox.rag_enabled', false);
        $app['config']->set('ai-chatbox.rag_embedding_url', 'http://embed.example.com/v1/embeddings');
        $app['config']->set('ai-chatbox.rag_embedding_model', 'test-embed');

        // Use a named provider so effectiveConfig() / resolveConfig() always has a valid target.
        // Tests that need to override api settings do so via 'ai-chatbox.providers.testprovider.*'.
        $app['config']->set('ai-chatbox.active_provider', 'testprovider');
        $app['config']->set('ai-chatbox.providers', [
            'testprovider' => [
                'api_url'   => 'http://ai.example.com/v1/chat/completions',
                'api_token' => 'test-token',
                'api_model' => 'test-model',
            ],
        ]);
    }

    /**
     * Switch to the database memory driver and run migrations.
     * Call at the top of any test that needs DB-backed conversation history.
     */
    protected function useDatabase(): void
    {
        $this->app['config']->set('ai-chatbox.memory_driver', 'database');

        // Re-bind the repository now that the config key has changed
        $this->app->bind(
            \SyafiqUnijaya\AiChatbox\Memory\Contracts\ConversationRepositoryInterface::class,
            \SyafiqUnijaya\AiChatbox\Memory\DatabaseConversationRepository::class
        );

        $this->artisan('migrate');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * Bind a Guzzle MockHandler so the controller makes no real HTTP requests.
     */
    protected function mockGuzzle(array $responses): void
    {
        $mock    = new MockHandler($responses);
        $handler = HandlerStack::create($mock);

        $this->app->bind('ai-chatbox.guzzle', function () use ($handler) {
            return fn(array $config = []) => new Client(['handler' => $handler] + $config);
        });
    }
}
