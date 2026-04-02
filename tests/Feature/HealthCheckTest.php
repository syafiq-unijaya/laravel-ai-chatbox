<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use SyafiqUnijaya\AiChatbox\Tests\TestCase;

class HealthCheckTest extends TestCase
{
    // ── Configuration errors ─────────────────────────────────────────────────

    public function test_returns_e01_when_api_url_is_empty(): void
    {
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', '');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E01', 'status' => 'offline']);
    }

    public function test_returns_e02_when_api_url_has_no_host(): void
    {
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', 'not-a-valid-url');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E02', 'status' => 'offline']);
    }

    // ── SSRF protection ───────────────────────────────────────────────────────

    public function test_returns_e05_when_ssrf_blocks_private_ip(): void
    {
        $this->app['config']->set('ai-chatbox.ssrf_protection', true);
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', 'http://192.168.1.100:11434/v1/chat/completions');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E05', 'status' => 'offline']);
    }

    public function test_returns_e05_when_ssrf_blocks_localhost(): void
    {
        $this->app['config']->set('ai-chatbox.ssrf_protection', true);
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', 'http://127.0.0.1:11434/v1/chat/completions');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E05', 'status' => 'offline']);
    }

    public function test_returns_e06_when_dns_resolution_fails(): void
    {
        $this->app['config']->set('ai-chatbox.ssrf_protection', true);
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', 'http://nonexistent-host.invalid/api');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E06', 'status' => 'offline']);
    }

    // ── Network errors (via Guzzle mock) ─────────────────────────────────────

    public function test_returns_e07_when_connection_is_refused(): void
    {
        $this->mockGuzzle([
            new ConnectException(
                'cURL error 7: Failed to connect: Connection refused',
                new Request('GET', '/')
            ),
        ]);

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E07', 'status' => 'offline']);
    }

    public function test_returns_e08_when_connection_times_out(): void
    {
        $this->mockGuzzle([
            new ConnectException(
                'cURL error 28: Operation timed out after 5000 milliseconds',
                new Request('GET', '/')
            ),
        ]);

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E08', 'status' => 'offline']);
    }

    public function test_returns_e09_when_ssl_handshake_fails(): void
    {
        $this->mockGuzzle([
            new ConnectException(
                'cURL error 60: SSL certificate problem: unable to get local issuer certificate',
                new Request('GET', '/')
            ),
        ]);

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E09', 'status' => 'offline']);
    }

    public function test_returns_e10_when_too_many_redirects(): void
    {
        $this->mockGuzzle([
            new TooManyRedirectsException(
                'Will not follow more than 5 redirects',
                new Request('GET', '/')
            ),
        ]);

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['code' => 'E10', 'status' => 'offline']);
    }

    // ── Online responses ──────────────────────────────────────────────────────

    public function test_returns_online_when_service_responds_with_200(): void
    {
        $this->mockGuzzle([new Response(200)]);

        $this->getJson('/ai-chatbox/health')
            ->assertOk()
            ->assertJsonFragment(['status' => 'online']);
    }

    public function test_returns_online_when_service_responds_with_4xx(): void
    {
        // A 4xx means the server is up, just rejecting the request
        $this->mockGuzzle([new Response(401)]);

        $this->getJson('/ai-chatbox/health')
            ->assertOk()
            ->assertJsonFragment(['status' => 'online']);
    }

    public function test_offline_message_is_configurable(): void
    {
        $this->app['config']->set('ai-chatbox.providers.testprovider.api_url', '');
        $this->app['config']->set('ai-chatbox.offline_message', 'Custom offline message.');

        $this->getJson('/ai-chatbox/health')
            ->assertStatus(503)
            ->assertJsonFragment(['message' => 'Custom offline message.']);
    }

    // ── Active provider routing ───────────────────────────────────────────────

    public function test_health_check_uses_active_provider_url(): void
    {
        // testprovider URL is valid but overriding to a different provider to confirm routing
        $this->app['config']->set('ai-chatbox.providers', [
            'myprovider' => [
                'api_url'   => 'http://active.example.com/v1/chat',
                'api_token' => 'active-token',
                'api_model' => 'active-model',
            ],
        ]);
        $this->app['config']->set('ai-chatbox.active_provider', 'myprovider');

        $this->mockGuzzle([new Response(200)]);

        $this->getJson('/ai-chatbox/health')
            ->assertOk()
            ->assertJsonFragment(['status' => 'online']);
    }

    public function test_health_check_with_active_provider_default_uses_first_provider(): void
    {
        // 'default' resolves to the first configured provider (testprovider from TestCase)
        $this->app['config']->set('ai-chatbox.active_provider', 'default');

        $this->mockGuzzle([new Response(200)]);

        $this->getJson('/ai-chatbox/health')
            ->assertOk()
            ->assertJsonFragment(['status' => 'online']);
    }
}
