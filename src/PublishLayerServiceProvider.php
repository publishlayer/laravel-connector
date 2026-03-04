<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Commands\DoctorCommand;
use PublishLayer\LaravelConnector\Commands\HeartbeatCommand;
use PublishLayer\LaravelConnector\Commands\InstallCommand;
use PublishLayer\LaravelConnector\Commands\RegisterWebhooksCommand;
use PublishLayer\LaravelConnector\Commands\ToggleInboxCommand;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;
use PublishLayer\LaravelConnector\Services\InboxContentRenderer;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use PublishLayer\LaravelConnector\Services\SiteKeyResolver;

class PublishLayerServiceProvider extends ServiceProvider
{
    private static int $migrationCounter = 0;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer_connector.php', 'publishlayer_connector');
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer_inbox.php', 'publishlayer_inbox');

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
        $this->app->singleton(PublishLayerInbox::class);
        $this->app->singleton(SiteKeyResolver::class);
        $this->app->singleton(InboxContentRenderer::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/publishlayer_connector.php' => config_path('publishlayer_connector.php'),
            __DIR__ . '/../config/publishlayer_inbox.php' => config_path('publishlayer_inbox.php'),
        ], 'publishlayer-connector-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/create_publishlayer_webhook_events_table.php.stub' => $this->getMigrationPath('create_publishlayer_webhook_events_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_drafts_table.php.stub' => $this->getMigrationPath('create_publishlayer_drafts_table'),
            __DIR__ . '/../database/migrations/add_connector_fields_to_publishlayer_webhook_events_table.php.stub' => $this->getMigrationPath('add_connector_fields_to_publishlayer_webhook_events_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_deliveries_table.php.stub' => $this->getMigrationPath('create_publishlayer_deliveries_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_content_mappings_table.php.stub' => $this->getMigrationPath('create_publishlayer_content_mappings_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_connector_heartbeats_table.php.stub' => $this->getMigrationPath('create_publishlayer_connector_heartbeats_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_failed_messages_table.php.stub' => $this->getMigrationPath('create_publishlayer_failed_messages_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_settings_table.php.stub' => $this->getMigrationPath('create_publishlayer_settings_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_inbox_briefs_table.php.stub' => $this->getMigrationPath('create_publishlayer_inbox_briefs_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_inbox_drafts_table.php.stub' => $this->getMigrationPath('create_publishlayer_inbox_drafts_table'),
            __DIR__ . '/../database/migrations/rename_pl_inbox_tables_to_publishlayer_inbox_tables.php.stub' => $this->getMigrationPath('rename_pl_inbox_tables_to_publishlayer_inbox_tables'),
        ], 'publishlayer-connector-migrations');

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/inbox.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'publishlayer-connector');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                DoctorCommand::class,
                RegisterWebhooksCommand::class,
                HeartbeatCommand::class,
                ToggleInboxCommand::class,
            ]);
        }
    }

    /**
     * Get migration path with timestamp.
     */
    private function getMigrationPath(string $name): string
    {
        $timestamp = date('Y_m_d_His', time() + self::$migrationCounter++);

        return database_path("migrations/{$timestamp}_{$name}.php");
    }
}
