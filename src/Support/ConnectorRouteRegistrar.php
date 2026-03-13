<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Support;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;

class ConnectorRouteRegistrar
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function register(): void
    {
        if ($this->app->routesAreCached() || ! $this->packageIsEnabled()) {
            return;
        }

        $this->registerApiRoutes();

        if ($this->usesHostedViews()) {
            $this->registerWebRoutes();
        }
    }

    private function registerApiRoutes(): void
    {
        Route::middleware($this->normalizeMiddleware(config('publishlayer.api_middleware', ['api']), ['api']))
            ->prefix($this->normalizePrefix((string) config('publishlayer.api_prefix', 'api/publishlayer')))
            ->name('publishlayer.')
            ->group(__DIR__ . '/../../routes/api.php');
    }

    private function registerWebRoutes(): void
    {
        Route::middleware($this->normalizeMiddleware(config('publishlayer.web_middleware', ['web']), ['web']))
            ->prefix($this->normalizePrefix((string) config('publishlayer.route_prefix', 'knowledge')))
            ->name('publishlayer.')
            ->group(__DIR__ . '/../../routes/web.php');
    }

    /**
     * @param mixed $middleware
     * @return array<int, string>
     */
    private function normalizeMiddleware(mixed $middleware, array $fallback): array
    {
        if (is_string($middleware)) {
            $middleware = array_filter(array_map('trim', explode(',', $middleware)));
        }

        if (! is_array($middleware)) {
            return $fallback;
        }

        $normalized = array_values(array_filter(array_map(
            static fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $middleware
        )));

        return $normalized === [] ? $fallback : $normalized;
    }

    private function normalizePrefix(string $prefix): string
    {
        return trim($prefix, '/');
    }

    private function packageIsEnabled(): bool
    {
        return (bool) config('publishlayer.enabled', true);
    }

    private function usesHostedViews(): bool
    {
        return (string) config('publishlayer.mode', 'hosted_views') === 'hosted_views';
    }
}
