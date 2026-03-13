<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class KnowledgeSyncAuthRejectionTest extends TestCase
{
    public function test_webhook_route_rejects_requests_without_authentication(): void
    {
        $this->postJson('/api/publishlayer/webhook', [
            'type' => 'knowledge_article',
            'site_id' => 'site-test',
            'article' => [
                'id' => 'art_denied',
                'title' => 'Denied article',
                'slug' => 'denied-article',
                'content_html' => '<p>Denied</p>',
                'status' => 'published',
            ],
        ])->assertStatus(401)->assertJson([
            'ok' => false,
            'message' => 'Unauthorized PublishLayer sync request.',
        ]);
    }
}
