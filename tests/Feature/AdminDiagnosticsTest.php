<?php
namespace SyafiqUnijaya\AiChatbox\Tests\Feature;

use SyafiqUnijaya\AiChatbox\Tests\TestCase;

/**
 * Tests for the diagnostics panel rendered on the /ai-chatbox/admin dashboard.
 *
 * All tests bypass authentication middleware because the package has no concept
 * of the host app's user model; auth correctness is the host app's responsibility.
 */
class AdminDiagnosticsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(); // Bypass [web, auth] on admin route
    }

    // ── APP_DEBUG rule ────────────────────────────────────────────────────────
    // Only alert when APP_ENV is production / prod / live.

    public function test_app_debug_not_flagged_in_local_environment(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.env', 'local');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('APP_DEBUG is true in production');
    }

    public function test_app_debug_not_flagged_in_staging_environment(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.env', 'staging');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('APP_DEBUG is true in production');
    }

    public function test_app_debug_not_flagged_when_debug_is_false_in_production(): void
    {
        $this->app['config']->set('app.debug', false);
        $this->app['config']->set('app.env', 'production');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('APP_DEBUG is true in production');
    }

    public function test_app_debug_flagged_as_error_in_production(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.env', 'production');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertSee('APP_DEBUG is true in production');
    }

    public function test_app_debug_flagged_as_error_in_prod(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.env', 'prod');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertSee('APP_DEBUG is true in production');
    }

    public function test_app_debug_flagged_as_error_in_live(): void
    {
        $this->app['config']->set('app.debug', true);
        $this->app['config']->set('app.env', 'live');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertSee('APP_DEBUG is true in production');
    }

    // ── Active provider diagnostics ───────────────────────────────────────────

    public function test_active_provider_not_defined_shows_error(): void
    {
        $this->app['config']->set('ai-chatbox.active_provider', 'nonexistent');
        $this->app['config']->set('ai-chatbox.providers', []);

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertSee('nonexistent') // provider name appears in the error message
            ->assertSee('no such provider is defined');
    }

    public function test_active_provider_defined_and_complete_shows_no_error(): void
    {
        $this->app['config']->set('ai-chatbox.active_provider', 'myprovider');
        $this->app['config']->set('ai-chatbox.providers', [
            'myprovider' => [
                'api_url'   => 'http://myprovider.example.com/v1/chat',
                'api_token' => 'myprovider-token',
                'api_model' => 'myprovider-model',
            ],
        ]);

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('no such provider is defined');
    }

    public function test_active_provider_default_does_not_trigger_any_provider_error(): void
    {
        $this->app['config']->set('ai-chatbox.active_provider', 'default');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('no such provider is defined');
    }

    public function test_active_provider_empty_does_not_trigger_provider_error(): void
    {
        $this->app['config']->set('ai-chatbox.active_provider', '');

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertDontSee('no such provider is defined');
    }

    // ── All-pass green banner ─────────────────────────────────────────────────

    public function test_all_pass_banner_shown_when_no_issues(): void
    {
        // Ensure a clean, fully-configured state
        $this->app['config']->set('app.debug', false);
        $this->app['config']->set('app.env', 'local');
        $this->app['config']->set('ai-chatbox.active_provider', 'default');
        $this->app['config']->set('ai-chatbox.ssrf_protection', true);
        // Disable history to suppress the "session driver" info notice
        $this->app['config']->set('ai-chatbox.history_enabled', false);
        $this->app['config']->set('ai-chatbox.history_limit', 50);
        $this->app['config']->set('ai-chatbox.context_token_limit', 4000);
        $this->app['config']->set('ai-chatbox.stream', false); // avoid ob_flush warning in test env
        $this->app['config']->set('ai-chatbox.rate_limit', 20);
        $this->app['config']->set('ai-chatbox.temperature', 0.7);
        $this->app['config']->set('ai-chatbox.timeout', 30);
        $this->app['config']->set('ai-chatbox.rag_enabled', false);
        $this->app['config']->set('ai-chatbox.memory_driver', 'session');
        $this->app['config']->set('ai-chatbox.storage', 'local');
        $this->app['config']->set('ai-chatbox.rag_admin_middleware', ['web', 'auth', 'role:admin']);

        $this->get('/ai-chatbox/admin')
            ->assertOk()
            ->assertSee('All configuration checks passed');
    }
}
