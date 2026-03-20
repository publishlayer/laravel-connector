<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Schema;

class WebhookEventMigrationTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_add_connector_fields_migration_is_safe_when_indexes_already_exist(): void
    {
        $migration = require __DIR__ . '/../database/migrations/2024_01_01_000003_add_connector_fields_to_publishlayer_webhook_events_table.php';

        $migration->up();

        $indexNames = array_map(
            static fn (array $index): ?string => $index['name'] ?? $index['index'] ?? null,
            Schema::getIndexes('publishlayer_webhook_events')
        );

        self::assertContains('pl_webhook_events_site_received_idx', $indexNames);
        self::assertContains('pl_webhook_events_site_status_idx', $indexNames);
    }
}
