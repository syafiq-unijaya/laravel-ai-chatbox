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

        $app['config']->set('ai-chatbox.api_url', 'http://ai.example.com/v1/chat/completions');
        $app['config']->set('ai-chatbox.api_token', 'test-token');
        $app['config']->set('ai-chatbox.api_model', 'test-model');
        $app['config']->set('ai-chatbox.ssrf_protection', false);
        $app['config']->set('ai-chatbox.allowed_origins', ['http://localhost']);
        $app['config']->set('ai-chatbox.offline_message', 'AI service is currently unreachable.');
        $app['config']->set('ai-chatbox.rag_enabled', false);
        $app['config']->set('ai-chatbox.rag_embedding_url', 'http://embed.example.com/v1/embeddings');
        $app['config']->set('ai-chatbox.rag_embedding_model', 'test-embed');
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
