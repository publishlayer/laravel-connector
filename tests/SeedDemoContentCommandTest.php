<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class SeedDemoContentCommandTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_demo_content_command_seeds_default_knowledge_content(): void
    {
        $this->artisan('publishlayer:seed-demo-content')
            ->expectsOutputToContain('Demo PublishLayer knowledge content seeded.')
            ->assertExitCode(0);

        self::assertSame(1, PublishLayerCategory::query()->where('source_publishlayer_id', 'demo_category_getting_started')->count());
        self::assertSame(2, PublishLayerArticle::query()->where('source_publishlayer_id', 'like', 'demo_article_%')->count());
    }
}
