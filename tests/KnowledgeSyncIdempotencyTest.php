<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;

class KnowledgeSyncIdempotencyTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_resync_updates_existing_article_without_creating_duplicates(): void
    {
        $payload = [
            'type' => 'knowledge_article',
            'site_id' => 'site-test',
            'article' => [
                'id' => 'art_300',
                'title' => 'Initial title',
                'slug' => 'initial-title',
                'summary' => 'Initial summary',
                'content_html' => '<p>Initial content</p>',
                'status' => 'published',
                'published_at' => '2026-03-13T10:00:00Z',
                'source_updated_at' => '2026-03-13T10:01:00Z',
            ],
        ];

        $headers = ['X-PublishLayer-Api-Key' => 'test-sync-key'];

        $this->withHeaders($headers)->postJson('/api/publishlayer/sync', $payload)->assertOk();

        $payload['article']['title'] = 'Updated title';
        $payload['article']['slug'] = 'updated-title';
        $payload['article']['content_html'] = '<p>Updated content</p>';
        $payload['article']['source_updated_at'] = '2026-03-13T11:01:00Z';

        $this->withHeaders($headers)->postJson('/api/publishlayer/sync', $payload)->assertOk();

        self::assertSame(1, PublishLayerArticle::query()->where('source_publishlayer_id', 'art_300')->count());

        $article = PublishLayerArticle::query()->where('source_publishlayer_id', 'art_300')->firstOrFail();
        self::assertSame('Updated title', $article->title);
        self::assertSame('updated-title', $article->slug);
        self::assertSame('<p>Updated content</p>', $article->content_html);
        self::assertSame(2, PublishLayerSyncLog::query()->where('source_id', 'art_300')->count());
    }
}
