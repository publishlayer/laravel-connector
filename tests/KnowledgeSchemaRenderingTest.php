<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;
use PublishLayer\LaravelConnector\Tests\Fixtures\Support\TestFaqSchemaResolver;

class KnowledgeSchemaRenderingTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_article_page_renders_article_and_breadcrumb_schema(): void
    {
        $category = PublishLayerCategory::query()->create([
            'source_publishlayer_id' => 'cat_schema',
            'name' => 'Schema',
            'slug' => 'schema',
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_schema',
            'title' => 'Schema Article',
            'slug' => 'schema-article',
            'summary' => 'Schema summary',
            'content_html' => '<p>Schema body content for testing.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'category_id' => $category->id,
            'published_at' => now(),
            'source_updated_at' => now(),
        ]);

        $this->get('/knowledge/schema-article')
            ->assertOk()
            ->assertSee('"@type":"Article"', false)
            ->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('"headline":"Schema Article"', false);
    }

    public function test_article_page_can_render_optional_faq_schema(): void
    {
        config()->set('publishlayer.seo.faq_schema', true);
        config()->set('publishlayer.seo.faq_schema_resolver', TestFaqSchemaResolver::class);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_faq_schema',
            'title' => 'FAQ Schema Article',
            'slug' => 'faq-schema-article',
            'summary' => 'FAQ schema summary',
            'content_html' => '<p>FAQ schema body content.</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/knowledge/faq-schema-article')
            ->assertOk()
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"name":"What is FAQ Schema Article?"', false);
    }
}
