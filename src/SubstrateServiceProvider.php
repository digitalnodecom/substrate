<?php

declare(strict_types=1);

namespace Roots\Substrate;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\Compilers\BladeCompiler;
use Laravel\Mcp\Facades\Mcp;
use Roots\Substrate\Mcp\Substrate as McpServer;
use Roots\Substrate\Middleware\InjectSubstrate;

class SubstrateServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/substrate.php',
            'substrate'
        );

        if (! $this->shouldRun()) {
            return;
        }

        $this->app->singleton(SubstrateManager::class, fn (): SubstrateManager => new SubstrateManager);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Router $router): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        // Register the MCP server
        Mcp::local('substrate', McpServer::class);

        $this->registerPublishing();
        $this->registerCommands();
        $this->registerRoutes();

        if (config('substrate.browser_logs_watcher', true)) {
            $this->registerBrowserLogger();
            $this->callAfterResolving('blade.compiler', $this->registerBladeDirectives(...));
            $this->hookIntoResponses($router);
        }
    }

    /**
     * Register the package's publishable resources.
     */
    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/substrate.php' => $this->app->configPath('substrate.php'),
            ], 'substrate-config');
        }
    }

    /**
     * Register the package's commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\StartCommand::class,
                Console\ExecuteToolCommand::class,
                Console\InstallCommand::class,
                Console\UpdateCommand::class,
            ]);
        }
    }

    /**
     * Register the browser logging routes.
     */
    protected function registerRoutes(): void
    {
        Route::post('/_substrate/browser-logs', function (\Illuminate\Http\Request $request) {
            $logs = $request->input('logs', []);

            $logPath = $this->app->storagePath('logs/browser.log');

            foreach ($logs as $log) {
                $level = match ($log['type'] ?? 'log') {
                    'warn' => 'WARNING',
                    'error', 'window_error', 'uncaught_error', 'unhandled_rejection' => 'ERROR',
                    'info' => 'INFO',
                    default => 'DEBUG',
                };

                $message = $this->buildLogMessageFromData($log['data'] ?? []);
                $timestamp = $log['timestamp'] ?? date('Y-m-d H:i:s');
                $url = $log['url'] ?? '';

                $logLine = sprintf(
                    "[%s] %s: %s | URL: %s\n",
                    $timestamp,
                    $level,
                    $message,
                    $url
                );

                file_put_contents($logPath, $logLine, FILE_APPEND | LOCK_EX);
            }

            return response()->json(['status' => 'logged']);
        })->name('substrate.browser-logs');
    }

    /**
     * Build a string message from log data.
     */
    private function buildLogMessageFromData(array $data): string
    {
        $messages = [];

        foreach ($data as $value) {
            $messages[] = match (true) {
                is_array($value) => $this->buildLogMessageFromData($value),
                is_string($value), is_numeric($value) => (string) $value,
                is_bool($value) => $value ? 'true' : 'false',
                is_null($value) => 'null',
                is_object($value) => json_encode($value),
                default => (string) $value,
            };
        }

        return implode(' ', $messages);
    }

    /**
     * Register the browser logging channel.
     */
    protected function registerBrowserLogger(): void
    {
        config([
            'logging.channels.browser' => [
                'driver' => 'single',
                'path' => $this->app->storagePath('logs/browser.log'),
                'level' => env('LOG_LEVEL', 'debug'),
            ],
        ]);
    }

    /**
     * Register Blade directives.
     */
    protected function registerBladeDirectives(BladeCompiler $bladeCompiler): void
    {
        $bladeCompiler->directive(
            'substrateJs',
            fn (): string => '<?php echo '.Services\BrowserLogger::class.'::getScript(); ?>'
        );
    }

    /**
     * Hook into HTTP responses to inject browser logger.
     */
    protected function hookIntoResponses(Router $router): void
    {
        $this->app->booted(function () use ($router): void {
            $router->pushMiddlewareToGroup('web', InjectSubstrate::class);
        });
    }

    /**
     * Determine if Substrate should run.
     */
    protected function shouldRun(): bool
    {
        if (! config('substrate.enabled', true)) {
            return false;
        }

        if ($this->app->runningUnitTests()) {
            return false;
        }

        // Check WordPress environment type if available
        if (function_exists('wp_get_environment_type')) {
            $wpEnv = wp_get_environment_type();
            if (! in_array($wpEnv, ['local', 'development'], true)) {
                // Also allow if WP_DEBUG is true
                if (! defined('WP_DEBUG') || ! WP_DEBUG) {
                    return false;
                }
            }

            return true;
        }

        // Fallback: Only enable on local environments or when debug is true
        if (! $this->app->environment('local') && config('app.debug', false) !== true) {
            return false;
        }

        return true;
    }
}
