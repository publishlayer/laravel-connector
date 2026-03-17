<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Http;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;

class KnowledgeMarkdownDeliveryTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_markdown_route_returns_canonical_markdown_from_publishlayer(): void
    {
        $this->createPublishedArticle('article-md', 'art_markdown');

        Http::fake([
            'https://api.publishlayer.com/api/sites/site-test/content/art_markdown/markdown' => Http::response([
                'content_id' => 'art_markdown',
                'slug' => 'article-md',
                'status' => 'published',
                'rendered_markdown' => "# Article MD\n\nCanonical body",
                'markdown_checksum' => 'checksum-md',
                'markdown_generated_at' => '2026-03-16T12:00:00Z',
                'updated_at' => '2026-03-16T12:00:00Z',
            ], 200),
        ]);

        $this->get('/knowledge/article-md.md')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('X-PublishLayer-Markdown-Cache', 'miss')
            ->assertSee('# Article MD')
            ->assertSee('Canonical body');
    }

    public function test_accept_header_negotiation_returns_markdown_on_article_route(): void
    {
        $this->createPublishedArticle('negotiated-article', 'art_negotiated');

        Http::fake([
            'https://api.publishlayer.com/api/sites/site-test/content/art_negotiated/markdown' => Http::response([
                'content_id' => 'art_negotiated',
                'slug' => 'negotiated-article',
                'status' => 'published',
                'rendered_markdown' => "# Negotiated article\n\nMarkdown body",
                'markdown_checksum' => 'checksum-negotiated',
                'markdown_generated_at' => '2026-03-16T13:00:00Z',
                'updated_at' => '2026-03-16T13:00:00Z',
            ], 200),
        ]);

        $this->get('/knowledge/negotiated-article', [
            'Accept' => 'text/markdown, text/html;q=0.8',
        ])
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertSee('# Negotiated article');
    }

    public function test_markdown_route_uses_local_cache_before_refetching(): void
    {
        $this->createPublishedArticle('cached-article', 'art_cached');

        Http::fake([
            'https://api.publishlayer.com/api/sites/site-test/content/art_cached/markdown' => Http::response([
                'content_id' => 'art_cached',
                'slug' => 'cached-article',
                'status' => 'published',
                'rendered_markdown' => "# Cached article\n\nCached body",
                'markdown_checksum' => 'checksum-cached',
                'markdown_generated_at' => '2026-03-16T14:00:00Z',
                'updated_at' => '2026-03-16T14:00:00Z',
            ], 200),
        ]);

        $this->get('/knowledge/cached-article.md')->assertOk()->assertHeader('X-PublishLayer-Markdown-Cache', 'miss');
        $this->get('/knowledge/cached-article.md')->assertOk()->assertHeader('X-PublishLayer-Markdown-Cache', 'fresh');

        Http::assertSentCount(1);
    }

    public function test_markdown_route_serves_stale_cache_when_api_is_unavailable(): void
    {
        config()->set('publishlayer.markdown.cache_ttl', 0);

        $this->createPublishedArticle('stale-article', 'art_stale');

        Http::fake([
            'https://api.publishlayer.com/api/sites/site-test/content/art_stale/markdown' => Http::response([
                'content_id' => 'art_stale',
                'slug' => 'stale-article',
                'status' => 'published',
                'rendered_markdown' => "# Stale article\n\nCached fallback body",
                'markdown_checksum' => 'checksum-stale',
                'markdown_generated_at' => '2026-03-16T15:00:00Z',
                'updated_at' => '2026-03-16T15:00:00Z',
            ], 200),
        ]);

        $this->get('/knowledge/stale-article.md')
            ->assertOk()
            ->assertHeader('X-PublishLayer-Markdown-Cache', 'miss')
            ->assertSee('Cached fallback body');

        Http::fake([
            'https://api.publishlayer.com/api/sites/site-test/content/art_stale/markdown' => Http::response([
                'message' => 'temporarily unavailable',
            ], 503),
        ]);

        $this->get('/knowledge/stale-article.md')
            ->assertOk()
            ->assertSee('Cached fallback body');
    }

    public function test_non_public_articles_are_not_exposed_via_markdown_route(): void
    {
        PublishLayerArticle::query()->create([
            'source_publishlayer_id' => 'art_draft',
            'title' => 'Draft article',
            'slug' => 'draft-article',
            'summary' => 'Hidden',
            'content_html' => '<p>Hidden</p>',
            'status' => PublishLayerArticle::STATUS_DRAFT,
        ]);

        Http::fake();

        $this->get('/knowledge/draft-article.md')->assertNotFound();

        Http::assertNothingSent();
    }

    private function createPublishedArticle(string $slug, string $sourceId): PublishLayerArticle
    {
        return PublishLayerArticle::query()->create([
            'source_publishlayer_id' => $sourceId,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'summary' => 'Summary',
            'content_html' => '<p>HTML body</p>',
            'status' => PublishLayerArticle::STATUS_PUBLISHED,
            'published_at' => now()->subMinute(),
            'source_updated_at' => now()->subMinute(),
        ]);
    }
}
