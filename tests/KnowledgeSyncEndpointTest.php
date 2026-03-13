<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerArticleRelation;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;

class KnowledgeSyncEndpointTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_sync_endpoint_upserts_article_category_relations_and_log(): void
    {
        $response = $this->withHeaders([
            'X-PublishLayer-Api-Key' => 'test-sync-key',
        ])->postJson('/api/publishlayer/sync', $this->payload());

        $response->assertOk()->assertJson([
            'ok' => true,
            'article' => [
                'source_publishlayer_id' => 'art_100',
                'slug' => 'network-automation',
                'status' => 'published',
            ],
        ]);

        $category = PublishLayerCategory::query()->where('slug', 'automation')->first();
        $article = PublishLayerArticle::query()->where('source_publishlayer_id', 'art_100')->first();

        self::assertNotNull($category);
        self::assertNotNull($article);
        self::assertSame($category->id, $article->category_id);
        self::assertSame(1, PublishLayerArticleRelation::query()->where('article_id', $article->id)->count());

        $relatedArticle = PublishLayerArticle::query()->where('source_publishlayer_id', 'art_200')->first();
        self::assertNotNull($relatedArticle);
        self::assertSame(PublishLayerArticle::STATUS_REFERENCE, $relatedArticle->status);

        $syncLog = PublishLayerSyncLog::query()->latest('id')->first();
        self::assertNotNull($syncLog);
        self::assertSame(PublishLayerSyncLog::STATUS_SUCCESS, $syncLog->status);
        self::assertSame('art_100', $syncLog->source_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'type' => 'knowledge_article',
            'site_id' => 'site-test',
            'article' => [
                'id' => 'art_100',
                'title' => 'Network Automation',
                'slug' => 'network-automation',
                'summary' => 'Automation summary.',
                'content_html' => '<p>Trusted synced content.</p>',
                'seo_title' => 'Automation SEO title',
                'seo_description' => 'Automation SEO description',
                'featured_image_url' => 'https://example.test/image.jpg',
                'status' => 'published',
                'published_at' => '2026-03-13T10:00:00Z',
                'source_updated_at' => '2026-03-13T10:01:00Z',
                'category' => [
                    'id' => 'cat_10',
                    'name' => 'Automation',
                    'slug' => 'automation',
                    'description' => 'Automation category',
                ],
                'related_articles' => [
                    [
                        'source_publishlayer_id' => 'art_200',
                        'slug' => 'related-reference',
                        'title' => 'Related reference',
                        'relation_type' => 'related',
                    ],
                ],
            ],
        ];
    }
}
