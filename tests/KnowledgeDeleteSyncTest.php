<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;

class KnowledgeDeleteSyncTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_sync_endpoint_deletes_existing_articles_when_status_is_deleted(): void
    {
        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_delete_me',
            'title' => 'Delete me',
            'slug' => 'delete-me',
            'summary' => 'Delete me summary',
            'content_html' => '<p>Delete me</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
        ]);

        $this->withHeaders([
            'X-PublishLayer-Key' => 'test-sync-key',
        ])->postJson('/api/publishlayer/sync', [
            'type' => 'knowledge_article',
            'site_id' => 'site-test',
            'article' => [
                'id' => 'art_delete_me',
                'status' => 'deleted',
                'slug' => 'delete-me',
            ],
        ])->assertOk()
            ->assertJsonPath('operation', 'deleted');

        self::assertNull(PublishLayerArticle::query()->where('source_publishlayer_id', 'art_delete_me')->first());
        self::assertSame(
            PublishLayerSyncLog::STATUS_SUCCESS,
            PublishLayerSyncLog::query()->latest('id')->value('status')
        );
    }
}
