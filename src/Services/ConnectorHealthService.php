<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;

class ConnectorHealthService
{
    /**
     * @return array{
     *   ok: bool,
     *   has_warnings: bool,
     *   mode: string,
     *   checks: array<int, array{key:string,status:string,message:string}>,
     *   latest_sync_log: array<string,mixed>|null
     * }
     */
    public function summary(): array
    {
        $mode = (string) config('publishlayer.mode', 'hosted_views');
        $checks = [
            $this->check('enabled', (bool) config('publishlayer.enabled', true), 'Connector is enabled.', 'Connector is disabled.'),
            $this->check('app_key', $this->appKeyIsConfigured(), 'Application key is configured.', 'Application key is missing.'),
            $this->check('mode', in_array($mode, ['hosted_views', 'headless'], true), 'Connector mode is valid.', 'Connector mode must be hosted_views or headless.'),
            $this->check('site_id', trim((string) config('publishlayer.site_id', '')) !== '', 'Site ID is configured.', 'Site ID is missing.'),
            $this->check('auth', $this->authIsConfigured(), 'Sync authentication is configured.', 'Set PUBLISHLAYER_API_KEY or PUBLISHLAYER_SYNC_SIGNING_SECRET.'),
            $this->databaseConnectionCheck(),
            $this->databaseTablesCheck(),
            $this->check('route.sync', Route::has('publishlayer.api.sync'), 'Sync route is registered.', 'Sync route is missing.'),
            $this->check('route.health', Route::has('publishlayer.api.health'), 'Health route is registered.', 'Health route is missing.'),
            $this->check('route.webhook', Route::has('publishlayer.api.webhook'), 'Webhook route is registered.', 'Webhook route is missing.'),
            $this->hostedRoutesCheck($mode),
        ];

        $hasFailures = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'fail');
        $hasWarnings = collect($checks)->contains(fn (array $check): bool => $check['status'] === 'warn');

        return [
            'ok' => ! $hasFailures,
            'has_warnings' => $hasWarnings,
            'mode' => $mode,
            'checks' => $checks,
            'latest_sync_log' => $this->latestSyncLog(),
        ];
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function check(string $key, bool $passed, string $passMessage, string $failMessage): array
    {
        return [
            'key' => $key,
            'status' => $passed ? 'pass' : 'fail',
            'message' => $passed ? $passMessage : $failMessage,
        ];
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function databaseConnectionCheck(): array
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            return [
                'key' => 'database.connection',
                'status' => 'fail',
                'message' => 'Database connection failed: '.$exception->getMessage(),
            ];
        }

        return [
            'key' => 'database.connection',
            'status' => 'pass',
            'message' => 'Database connection succeeded.',
        ];
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function databaseTablesCheck(): array
    {
        $requiredTables = [
            'publishlayer_articles',
            'publishlayer_categories',
            'publishlayer_article_relations',
            'publishlayer_sync_logs',
        ];

        $missing = array_values(array_filter($requiredTables, static fn (string $table): bool => ! Schema::hasTable($table)));
        if ($missing !== []) {
            return [
                'key' => 'database.tables',
                'status' => 'fail',
                'message' => 'Missing tables: '.implode(', ', $missing),
            ];
        }

        return [
            'key' => 'database.tables',
            'status' => 'pass',
            'message' => 'Knowledge base tables are available.',
        ];
    }

    /**
     * @return array{key:string,status:string,message:string}
     */
    private function hostedRoutesCheck(string $mode): array
    {
        $hasKnowledgeRoutes = Route::has('publishlayer.knowledge.index')
            && Route::has('publishlayer.knowledge.category')
            && Route::has('publishlayer.knowledge.show');

        if ($mode === 'headless') {
            return [
                'key' => 'route.hosted_views',
                'status' => $hasKnowledgeRoutes ? 'fail' : 'pass',
                'message' => $hasKnowledgeRoutes
                    ? 'Hosted view routes should be disabled in headless mode.'
                    : 'Hosted view routes are disabled in headless mode.',
            ];
        }

        return [
            'key' => 'route.hosted_views',
            'status' => $hasKnowledgeRoutes ? 'pass' : 'fail',
            'message' => $hasKnowledgeRoutes
                ? 'Hosted view routes are registered.'
                : 'Hosted view routes are missing.',
        ];
    }

    private function appKeyIsConfigured(): bool
    {
        return trim((string) config('app.key', '')) !== '';
    }

    private function authIsConfigured(): bool
    {
        return trim((string) config('publishlayer.api_key', '')) !== ''
            || trim((string) config('publishlayer.sync_signing_secret', '')) !== '';
    }

    /**
     * @return array<string,mixed>|null
     */
    private function latestSyncLog(): ?array
    {
        if (! Schema::hasTable('publishlayer_sync_logs')) {
            return null;
        }

        $latest = PublishLayerSyncLog::query()->latest('created_at')->first();
        if (! $latest) {
            return null;
        }

        return [
            'source_id' => $latest->source_id,
            'event_type' => $latest->event_type,
            'status' => $latest->status,
            'message' => $latest->message,
            'synced_at' => $latest->synced_at?->toIso8601String(),
            'created_at' => $latest->created_at?->toIso8601String(),
        ];
    }
}
