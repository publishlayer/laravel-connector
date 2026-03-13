<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;

class KnowledgeMetadataTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_article_page_renders_title_canonical_and_meta_description(): void
    {
        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_meta',
            'title' => 'Metadata Article',
            'slug' => 'metadata-article',
            'summary' => 'Metadata summary',
            'content_html' => '<p>Metadata body.</p>',
            'seo_title' => 'SEO Metadata Article',
            'seo_description' => 'SEO metadata description',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/knowledge/metadata-article')
            ->assertOk()
            ->assertSee('<title>SEO Metadata Article</title>', false)
            ->assertSee('<link rel="canonical" href="http://localhost/knowledge/metadata-article">', false)
            ->assertSee('<meta name="description" content="SEO metadata description">', false);
    }

    public function test_search_results_can_be_marked_noindex(): void
    {
        $this->get('/knowledge?q=search')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }
}
