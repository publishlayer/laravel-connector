<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Schema;

class MigrationTablesTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_knowledge_tables_exist_after_migration(): void
    {
        self::assertTrue(Schema::hasTable('publishlayer_articles'));
        self::assertTrue(Schema::hasTable('publishlayer_categories'));
        self::assertTrue(Schema::hasTable('publishlayer_article_relations'));
        self::assertTrue(Schema::hasTable('publishlayer_sync_logs'));
    }
}
