<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerArticleRelation;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class HostedKnowledgeBaseRenderingTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_hosted_views_render_index_category_and_show_pages(): void
    {
        $category = PublishLayerCategory::query()->create([
            'source_publishlayer_id' => 'cat_100',
            'name' => 'Operations',
            'slug' => 'operations',
            'description' => 'Operations knowledge',
        ]);

        $relatedArticle = PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_related',
            'title' => 'Related article',
            'slug' => 'related-article',
            'summary' => 'Related summary',
            'content_html' => '<p>Related body</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now()->subDay(),
        ]);

        $article = PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_hosted',
            'title' => 'Hosted article',
            'slug' => 'hosted-article',
            'summary' => 'Hosted summary',
            'content_html' => '<p>Hosted body</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        PublishLayerArticleRelation::query()->create([
            'article_id' => $article->id,
            'related_article_id' => $relatedArticle->id,
            'relation_type' => 'related',
            'sort_order' => 0,
        ]);

        $this->get('/knowledge')
            ->assertOk()
            ->assertSee('Knowledge Base')
            ->assertSee('Hosted article')
            ->assertSee('Operations');

        $this->get('/knowledge/categorie/operations')
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Hosted article');

        $this->get('/knowledge/hosted-article')
            ->assertOk()
            ->assertSee('Hosted article')
            ->assertSee('Hosted summary')
            ->assertSee('Hosted body', false)
            ->assertSee('Related article');
    }
}
