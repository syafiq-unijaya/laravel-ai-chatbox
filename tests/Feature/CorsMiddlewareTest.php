<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class CorsMiddlewareTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ai-chatbox.allowed_origins', ['https://myapp.example.com']);
    }

    public function test_blocks_request_from_unknown_origin(): void
    {
        $this->withHeader('Origin', 'https://attacker.example.com')
            ->getJson('/ai-chatbox/health')
            ->assertForbidden();
    }

    public function test_allows_request_from_configured_origin(): void
    {
        $this->mockGuzzle([new Response(200)]);

        $this->withHeader('Origin', 'https://myapp.example.com')
            ->getJson('/ai-chatbox/health')
            ->assertOk();
    }

    public function test_sets_acao_header_for_allowed_origin(): void
    {
        $this->mockGuzzle([new Response(200)]);

        $response = $this->withHeader('Origin', 'https://myapp.example.com')
            ->getJson('/ai-chatbox/health');

        $response->assertHeader(
            'Access-Control-Allow-Origin',
            'https://myapp.example.com'
        );
    }

    public function test_allows_request_with_no_origin_header(): void
    {
        // Same-origin requests (e.g. browser same-site, Blade form) send no Origin header
        $this->mockGuzzle([new Response(200)]);

        $this->getJson('/ai-chatbox/health')
            ->assertOk();
    }

    public function test_does_not_set_acao_header_when_no_origin_sent(): void
    {
        $this->mockGuzzle([new Response(200)]);

        $response = $this->getJson('/ai-chatbox/health');

        $this->assertFalse(
            $response->headers->has('Access-Control-Allow-Origin'),
            'ACAO header should not be present for same-origin requests'
        );
    }
}
