<?php
namespace SyafiqUnijaya\AiChatbox;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use SyafiqUnijaya\AiChatbox\Http\Middleware\CorsMiddleware;

class AiChatboxServiceProvider extends ServiceProvider
{
    public const VERSION = '1.5.0';

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/ai-chatbox.php',
            'ai-chatbox'
        );

        // Bind a Guzzle client factory so tests can swap it with a mock handler
        $this->app->bind('ai-chatbox.guzzle', function () {
            return fn(array $config = []) => new Client($config);
        });
    }

    public function boot(): void
    {
        if (config('app.debug') && config('ai-chatbox.api_token') !== 'ollama') {
            Log::warning('AI Chatbox: APP_DEBUG is enabled while a real API token is configured. Disable debug mode in production.');
        }

        $this->loadViewsFrom(__DIR__ . '/resources/views', 'ai-chatbox');
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');

        // Compute once at boot — app.url never changes at runtime
        View::share('aiChatboxAppHash', substr(md5(config('app.url', 'default')), 0, 8));
        View::share('aiChatboxVersion', self::VERSION);

        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerBladeDirective();
        $this->registerLivewireComponent();
    }

    protected function registerRoutes(): void
    {
        Route::aliasMiddleware('ai-chatbox.cors', CorsMiddleware::class);

        // Chatbox API routes (CORS + throttle protected)
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });

        // RAG admin routes (auth protected, no CORS/throttle needed)
        Route::group($this->ragRouteConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/rag.php');
        });
    }

    protected function routeConfiguration(): array
    {
        $limit = config('ai-chatbox.rate_limit', 20);
        $window = config('ai-chatbox.rate_window', 1);
        $middleware = config('ai-chatbox.middleware', ['web']);

        // Replace the static 'throttle:20,1' placeholder with the config values
        $middleware = array_map(function ($m) use ($limit, $window) {
            return $m === 'throttle:20,1' ? "throttle:{$limit},{$window}" : $m;
        }, $middleware);

        return [
            'prefix' => config('ai-chatbox.route_prefix'),
            'middleware' => $middleware,
        ];
    }

    protected function ragRouteConfiguration(): array
    {
        return [
            'prefix' => config('ai-chatbox.route_prefix') . '/rag',
            'middleware' => config('ai-chatbox.rag_admin_middleware', ['web', 'auth']),
        ];
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            // Config
            $this->publishes([
                __DIR__ . '/Config/ai-chatbox.php' => config_path('ai-chatbox.php'),
            ], 'ai-chatbox-config');

            // Views
            $this->publishes([
                __DIR__ . '/resources/views' => resource_path('views/vendor/ai-chatbox'),
            ], 'ai-chatbox-views');

            // Assets (CSS + JS)
            $this->publishes([
                __DIR__ . '/resources/assets' => public_path('vendor/ai-chatbox'),
            ], 'ai-chatbox-assets');

            // Migrations
            $this->publishes([
                __DIR__ . '/Database/Migrations' => database_path('migrations'),
            ], 'ai-chatbox-migrations');
        }
    }

    protected function registerBladeDirective(): void
    {
        // @aichatbox — renders the full widget (driver determined by 'frontend' config)
        Blade::directive('aichatbox', function () {
            return "<?php echo view('ai-chatbox::chatbox')->render(); ?>";
        });

        // @aichatboxConfig — outputs only window.AiChatboxConfig; for custom frontends
        Blade::directive('aichatboxConfig', function () {
            return "<?php echo view('ai-chatbox::chatbox-config')->render(); ?>";
        });
    }

    protected function registerLivewireComponent(): void
    {
        if (class_exists(\Livewire\Livewire::class)) {
            \Livewire\Livewire::component('ai-chatbox', \SyafiqUnijaya\AiChatbox\Livewire\AiChatbox::class);
        }
    }
}
