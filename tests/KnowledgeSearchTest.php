<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class KnowledgeSearchTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_index_search_filters_knowledge_articles(): void
    {
        $category = PublishLayerCategory::query()->create([
            'source_publishlayer_id' => 'cat_search',
            'name' => 'Search',
            'slug' => 'search',
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_search_match',
            'title' => 'Connector Search Guide',
            'slug' => 'connector-search-guide',
            'summary' => 'How to search articles.',
            'content_html' => '<p>Searchable connector body.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_search_other',
            'title' => 'Webhook Setup',
            'slug' => 'webhook-setup',
            'summary' => 'Webhook article.',
            'content_html' => '<p>Webhook body.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $this->get('/knowledge?q=Search')
            ->assertOk()
            ->assertSee('Connector Search Guide')
            ->assertDontSee('Webhook Setup')
            ->assertSee('value="Search"', false);
    }

    public function test_category_search_filters_articles_within_category(): void
    {
        $category = PublishLayerCategory::query()->create([
            'source_publishlayer_id' => 'cat_ops',
            'name' => 'Operations',
            'slug' => 'operations',
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_ops_match',
            'title' => 'Network Automation',
            'slug' => 'network-automation',
            'summary' => 'Automation summary',
            'content_html' => '<p>Automation body.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_ops_other',
            'title' => 'Release Planning',
            'slug' => 'release-planning',
            'summary' => 'Planning summary',
            'content_html' => '<p>Planning body.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
        ]);

        $this->get('/knowledge/categorie/operations?q=Automation')
            ->assertOk()
            ->assertSee('Network Automation')
            ->assertDontSee('Release Planning');
    }
}
