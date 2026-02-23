<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Commands\DoctorCommand;
use PublishLayer\LaravelConnector\Commands\InstallCommand;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;

class PublishLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer.php', 'publishlayer');

        $this->app->singleton(PublishLayerClientContract::class, function ($app): PublishLayerClient {
            $defaultConnection = (array) config('publishlayer.connections.default', []);
            $connection = [
                'api_key' => $defaultConnection['api_key'] ?? config('publishlayer.api_key'),
                'workspace_id' => $defaultConnection['workspace_id'] ?? config('publishlayer.workspace_id'),
                'base_url' => $defaultConnection['base_url'] ?? config('publishlayer.base_url'),
            ];
            $http = (array) config('publishlayer.http', []);
            $http['timeout_seconds'] = config('publishlayer.timeout', $http['timeout_seconds'] ?? 10);

            return new PublishLayerClient($app->make(HttpFactory::class), $connection, $http);
        });

        $this->app->alias(PublishLayerClientContract::class, PublishLayerClient::class);
        $this->app->singleton('publishlayer', fn ($app): PublishLayerClientContract => $app->make(PublishLayerClientContract::class));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/publishlayer.php' => config_path('publishlayer.php'),
        ], 'publishlayer-config');

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
            ]);
        }
    }
}
