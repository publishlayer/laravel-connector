<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class KnowledgeBreadcrumbsTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_article_page_renders_breadcrumb_navigation(): void
    {
        $category = PublishLayerCategory::query()->create([
            'source_publishlayer_id' => 'cat_breadcrumbs',
            'name' => 'Operations',
            'slug' => 'operations',
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_breadcrumbs',
            'title' => 'Breadcrumb Article',
            'slug' => 'breadcrumb-article',
            'summary' => 'Breadcrumb summary',
            'content_html' => '<p>Breadcrumb body.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $this->get('/knowledge/breadcrumb-article')
            ->assertOk()
            ->assertSee('aria-label="Breadcrumbs"', false)
            ->assertSee('/knowledge', false)
            ->assertSee('/knowledge/categorie/operations', false)
            ->assertSee('Breadcrumb Article');
    }
}
