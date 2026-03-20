<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector;

use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\Listeners\FlushSchemaStateCache;
use PublishLayer\LaravelConnector\Client\PublishLayerClient;
use PublishLayer\LaravelConnector\Commands\DoctorCommand;
use PublishLayer\LaravelConnector\Commands\HeartbeatCommand;
use PublishLayer\LaravelConnector\Commands\RegisterWebhooksCommand;
use PublishLayer\LaravelConnector\Commands\ToggleInboxCommand;
use PublishLayer\LaravelConnector\Console\Commands\HealthCheckCommand;
use PublishLayer\LaravelConnector\Console\Commands\InstallConnectorCommand;
use PublishLayer\LaravelConnector\Console\Commands\SeedDemoContentCommand;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Http\Middleware\AuthorizePublishLayerSync;
use PublishLayer\LaravelConnector\Services\ConnectorHealthService;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;
use PublishLayer\LaravelConnector\Services\InboxContentRenderer;
use PublishLayer\LaravelConnector\Services\KnowledgeSyncService;
use PublishLayer\LaravelConnector\Services\KnowledgeDiscoveryService;
use PublishLayer\LaravelConnector\Services\MarkdownContentService;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use PublishLayer\LaravelConnector\Services\SchemaState;
use PublishLayer\LaravelConnector\Services\SiteKeyResolver;
use PublishLayer\LaravelConnector\Services\SyncLogService;
use PublishLayer\LaravelConnector\Support\ConnectorRouteRegistrar;

class PublishLayerConnectorServiceProvider extends ServiceProvider
{
    private static int $migrationCounter = 0;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/publishlayer.php', 'publishlayer');
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

        $this->app->singleton(ImageDownloadService::class);
        $this->app->singleton(PublishLayerInbox::class);
        $this->app->singleton(SiteKeyResolver::class);
        $this->app->singleton(InboxContentRenderer::class);
        $this->app->singleton(ConnectorRouteRegistrar::class);
        $this->app->singleton(KnowledgeSyncService::class);
        $this->app->singleton(KnowledgeDiscoveryService::class);
        $this->app->singleton(MarkdownContentService::class);
        $this->app->singleton(SyncLogService::class);
        $this->app->singleton(ConnectorHealthService::class);
        $this->app->singleton(SchemaState::class);
    }

    public function boot(): void
    {
        if (! $this->hasPublishedConnectorMigrations()) {
            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }

        $this->publishes([
            __DIR__ . '/../config/publishlayer.php' => config_path('publishlayer.php'),
            __DIR__ . '/../config/publishlayer_connector.php' => config_path('publishlayer_connector.php'),
            __DIR__ . '/../config/publishlayer_inbox.php' => config_path('publishlayer_inbox.php'),
        ], 'publishlayer-connector-config');

        $this->publishes([
            __DIR__ . '/../resources/views/knowledge' => resource_path('views/vendor/publishlayer/knowledge'),
        ], 'publishlayer-connector-views');

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
            __DIR__ . '/../database/migrations/create_publishlayer_categories_table.php.stub' => $this->getMigrationPath('create_publishlayer_categories_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_articles_table.php.stub' => $this->getMigrationPath('create_publishlayer_articles_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_article_relations_table.php.stub' => $this->getMigrationPath('create_publishlayer_article_relations_table'),
            __DIR__ . '/../database/migrations/create_publishlayer_sync_logs_table.php.stub' => $this->getMigrationPath('create_publishlayer_sync_logs_table'),
        ], 'publishlayer-connector-migrations');

        $this->app['router']->aliasMiddleware('publishlayer.sync', AuthorizePublishLayerSync::class);
        $this->app->make(ConnectorRouteRegistrar::class)->register();

        $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        $this->loadRoutesFrom(__DIR__ . '/../routes/inbox.php');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'publishlayer');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'publishlayer-connector');

        Event::listen(MigrationsEnded::class, FlushSchemaStateCache::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallConnectorCommand::class,
                HealthCheckCommand::class,
                SeedDemoContentCommand::class,
                DoctorCommand::class,
                RegisterWebhooksCommand::class,
                HeartbeatCommand::class,
                ToggleInboxCommand::class,
            ]);
        }
    }

    private function getMigrationPath(string $name): string
    {
        $timestamp = date('Y_m_d_His', time() + self::$migrationCounter++);

        return database_path("migrations/{$timestamp}_{$name}.php");
    }

    private function hasPublishedConnectorMigrations(): bool
    {
        $migrationDirectory = database_path('migrations');
        $publishedMigrationSuffixes = [
            'create_publishlayer_webhook_events_table.php',
            'create_publishlayer_drafts_table.php',
            'add_connector_fields_to_publishlayer_webhook_events_table.php',
            'create_publishlayer_deliveries_table.php',
            'create_publishlayer_content_mappings_table.php',
            'create_publishlayer_connector_heartbeats_table.php',
            'create_publishlayer_failed_messages_table.php',
            'create_publishlayer_settings_table.php',
            'create_publishlayer_inbox_briefs_table.php',
            'create_publishlayer_inbox_drafts_table.php',
            'rename_pl_inbox_tables_to_publishlayer_inbox_tables.php',
            'create_publishlayer_categories_table.php',
            'create_publishlayer_articles_table.php',
            'create_publishlayer_article_relations_table.php',
            'create_publishlayer_sync_logs_table.php',
        ];

        foreach ($publishedMigrationSuffixes as $suffix) {
            if (glob($migrationDirectory . DIRECTORY_SEPARATOR . '*_' . $suffix) !== []) {
                return true;
            }
        }

        return false;
    }
}
