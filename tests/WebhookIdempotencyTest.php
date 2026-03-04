<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;

class WebhookIdempotencyTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_duplicate_event_id_is_ignored(): void
    {
        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_payload',
            'site_key' => 'site_test_a',
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $server = [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'HTTP_X_PUBLISHLAYER_EVENT_ID' => 'evt_header_1',
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call('POST', '/publishlayer/webhook', [], [], [], $server, $body)
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->call('POST', '/publishlayer/webhook', [], [], [], $server, $body)
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'duplicate' => true,
            ]);

        $event = PublishLayerWebhookEvent::query()
            ->where('event_id', 'evt_header_1')
            ->first();

        $this->assertNotNull($event);
        $this->assertSame('site_test_a', $event->site_key);
        $this->assertSame('draft.ready', $event->event_type);
        $this->assertSame(1, PublishLayerWebhookEvent::where('event_id', 'evt_header_1')->count());
    }
}
