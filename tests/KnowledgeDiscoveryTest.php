<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerArticle;

class KnowledgeDiscoveryTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_llms_endpoints_render_markdown_links_for_published_articles(): void
    {
        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_doc_1',
            'title' => 'Connector setup',
            'slug' => 'connector-setup',
            'summary' => 'How to configure the connector.',
            'content_html' => '<p>Connector body</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
        ]);

        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_doc_2',
            'title' => 'Webhook signing',
            'slug' => 'webhook-signing',
            'summary' => 'How webhook signing works.',
            'content_html' => '<p>Webhook body</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $this->get('/llms.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('# Laravel', false)
            ->assertSee('## Documentation', false)
            ->assertSee('/knowledge/webhook-signing.md', false);

        $this->get('/llms-full.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('/knowledge/connector-setup.md', false)
            ->assertSee('/knowledge/webhook-signing.md', false);
    }
}
