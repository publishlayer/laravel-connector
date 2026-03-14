<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Services\ConnectorHealthService;
use PublishLayer\LaravelConnector\Services\SchemaState;

class InstallConnectorCommand extends Command
{
    protected $signature = 'publishlayer:install
        {--publish-config : Publish the package config files}
        {--publish-views : Publish the default knowledge base views}
        {--publish-migrations : Publish the package migration stubs}
        {--force : Overwrite publishable assets when publishing}
        {--seed-demo : Seed demo knowledge base content after setup checks pass}';

    protected $description = 'Prepare the PublishLayer Laravel connector for local knowledge sync and rendering';

    public function handle(ConnectorHealthService $healthService, SchemaState $schemaState): int
    {
        $configPublished = file_exists(config_path('publishlayer.php'));
        $packageEnabled = (bool) config('publishlayer.enabled', true);
        $mode = (string) config('publishlayer.mode', 'hosted_views');
        $routePrefix = trim((string) config('publishlayer.route_prefix', 'knowledge'), '/');
        $apiPrefix = trim((string) config('publishlayer.api_prefix', 'api/publishlayer'), '/');
        $summary = $healthService->summary();
        $hasCriticalFailures = false;
        $knowledgeTablesReady = $schemaState->hasTable('publishlayer_articles');
        $webhookTablesReady = $schemaState->hasTable('publishlayer_webhook_events');

        $this->info('PublishLayer Laravel Connector');
        $this->newLine();

        $this->renderCheck(
            $configPublished ? 'pass' : 'warn',
            'config.file',
            $configPublished
                ? 'Config file already published.'
                : 'Config file not published yet. Use --publish-config to publish it.'
        );

        foreach ($summary['checks'] as $check) {
            $status = (string) $check['status'];
            if ($status === 'fail') {
                $hasCriticalFailures = true;
            }

            $this->renderCheck(
                $status,
                (string) $check['key'],
                (string) $check['message']
            );
        }

        $this->renderCheck(
            $knowledgeTablesReady ? 'pass' : 'warn',
            'database.migrations',
            $knowledgeTablesReady
                ? 'Knowledge base migrations are already applied.'
                : 'Knowledge base tables are not present yet. Run php artisan migrate.'
        );

        $this->renderCheck(
            $webhookTablesReady ? 'pass' : 'warn',
            'database.webhooks',
            $webhookTablesReady
                ? 'Webhook event tables are available for durable idempotency and audit logging.'
                : 'Webhook tables are missing. Webhooks will fall back to cache idempotency until php artisan migrate is run.'
        );

        $this->renderCheck(
            Route::has('publishlayer.knowledge.index') || $mode !== 'hosted_views' ? 'pass' : 'warn',
            'route.knowledge',
            $mode === 'hosted_views'
                ? 'Hosted knowledge routes are available.'
                : 'Hosted knowledge routes are intentionally disabled in headless mode.'
        );

        $publishForce = (bool) $this->option('force');

        if ((bool) $this->option('publish-config')) {
            $this->call('vendor:publish', [
                '--tag' => 'publishlayer-connector-config',
                '--force' => $publishForce,
            ]);
        }

        if ((bool) $this->option('publish-views')) {
            $this->call('vendor:publish', [
                '--tag' => 'publishlayer-connector-views',
                '--force' => $publishForce,
            ]);
        }

        if ((bool) $this->option('publish-migrations')) {
            $this->call('vendor:publish', [
                '--tag' => 'publishlayer-connector-migrations',
                '--force' => $publishForce,
            ]);
        }

        if ((bool) $this->option('seed-demo')) {
            $this->call('publishlayer:seed-demo-content');
        }

        $this->newLine();
        $this->info('Next steps');
        $step = 1;

        $this->line(sprintf('%d. Set PUBLISHLAYER_API_KEY and PUBLISHLAYER_SITE_ID in %s.', $step++, base_path('.env')));
        $this->line(sprintf('%d. Optionally set PUBLISHLAYER_SYNC_SIGNING_SECRET if you want HMAC-signed sync requests.', $step++));
        if (! $knowledgeTablesReady) {
            $this->line(sprintf('%d. Run php artisan migrate to create the PublishLayer knowledge tables.', $step++));
        } else {
            $this->line(sprintf('%d. Database tables are ready.', $step++));
        }
        $this->line(sprintf('%d. Sync endpoint: /%s/sync', $step++, $apiPrefix));
        $this->line(sprintf('%d. Health endpoint: /%s/health', $step++, $apiPrefix));
        if (! $webhookTablesReady) {
            $this->line(sprintf('%d. Run php artisan migrate before accepting production webhooks so idempotency is stored durably.', $step++));
        }
        if ($mode === 'hosted_views') {
            $this->line(sprintf('%d. Hosted knowledge base: /%s', $step++, $routePrefix));
            $this->line(sprintf('%d. Override views in resources/views/vendor/publishlayer/knowledge if needed.', $step++));
        } else {
            $this->line(sprintf('%d. Hosted knowledge routes are disabled because the connector runs in headless mode.', $step++));
        }
        $this->line(sprintf('%d. Run php artisan publishlayer:health-check after configuration changes.', $step));

        Log::info('PublishLayer connector install command executed.', [
            'config_published' => $configPublished,
            'package_enabled' => $packageEnabled,
            'mode' => $mode,
            'published_config' => (bool) $this->option('publish-config'),
            'published_views' => (bool) $this->option('publish-views'),
            'published_migrations' => (bool) $this->option('publish-migrations'),
            'seeded_demo_content' => (bool) $this->option('seed-demo'),
        ]);

        return $hasCriticalFailures || ! $packageEnabled ? self::FAILURE : self::SUCCESS;
    }

    private function renderCheck(string $status, string $key, string $message): void
    {
        $label = match ($status) {
            'pass' => 'PASS',
            'warn' => 'WARN',
            default => 'FAIL',
        };

        $line = sprintf('[%s] %s - %s', $label, $key, $message);

        match ($status) {
            'pass' => $this->line($line),
            'warn' => $this->warn($line),
            default => $this->error($line),
        };
    }
}
