<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Commands\DoctorCommand;
use PublishLayer\LaravelConnector\Commands\InstallCommand;
use PublishLayer\LaravelConnector\Commands\RegisterWebhooksCommand;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;

class PublishLayerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer_connector.php', 'publishlayer_connector');

        $this->app->singleton(PublishLayerClientContract::class, function ($app): PublishLayerClient {
            $defaultConnection = (array) config('publishlayer_connector.connections.default', []);
            $connection = [
                'api_key' => $defaultConnection['api_key'] ?? config('publishlayer_connector.api_key'),
                'workspace_id' => $defaultConnection['workspace_id'] ?? config('publishlayer_connector.workspace_id'),
                'base_url' => $defaultConnection['base_url'] ?? config('publishlayer_connector.base_url'),
            ];
            $http = (array) config('publishlayer_connector.http', []);
            $http['timeout_seconds'] = config('publishlayer_connector.timeout', $http['timeout_seconds'] ?? 10);

            return new PublishLayerClient($app->make(HttpFactory::class), $connection, $http);
        });

        $this->app->alias(PublishLayerClientContract::class, PublishLayerClient::class);
        $this->app->singleton('publishlayer', fn ($app): PublishLayerClientContract => $app->make(PublishLayerClientContract::class));

        // Register ImageDownloadService as singleton
        $this->app->singleton(ImageDownloadService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/publishlayer_connector.php' => config_path('publishlayer_connector.php'),
        ], 'publishlayer-connector-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/create_publishlayer_webhook_events_table.php.stub' => $this->getMigrationPath('create_publishlayer_webhook_events_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_drafts_table.php.stub' => $this->getMigrationPath('create_publishlayer_drafts_table'),
        ], 'publishlayer-connector-migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                RegisterWebhooksCommand::class,
            ]);
        }
    }

    /**
     * Get migration path with timestamp.
     */
    private function getMigrationPath(string $name): string
    {
        $timestamp = date('Y_m_d_His');

        return database_path("migrations/{$timestamp}_{$name}.php");
    }
}
