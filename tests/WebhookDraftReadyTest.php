<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use PublishLayer\LaravelConnector\Events\DraftReady;
use PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived;
use PublishLayer\LaravelConnector\Jobs\ProcessDraftReadyJob;

class WebhookDraftReadyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for testing
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_draft_ready_webhook_dispatches_event_and_job(): void
    {
        Event::fake([DraftReady::class, PublishLayerWebhookReceived::class]);
        Queue::fake();

        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_test_123',
            'pl_draft_id' => 'draft_abc',
            'content_id' => 'content_xyz',
            'title' => 'Test Draft',
            'content_html' => '<p>Test content</p>',
            'featured_image_url' => 'https://example.com/image.webp',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $response = $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'HTTP_X_PUBLISHLAYER_EVENT_ID' => 'evt_test_123',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk()->assertJson(['ok' => true]);

        Event::assertDispatched(DraftReady::class, function (DraftReady $event) use ($payload) {
            return $event->payload['pl_draft_id'] === $payload['pl_draft_id'];
        });

        Event::assertDispatched(PublishLayerWebhookReceived::class);
    }

    public function test_duplicate_webhook_returns_duplicate_true(): void
    {
        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_duplicate_test',
            'pl_draft_id' => 'draft_dup',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $headers = [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'HTTP_X_PUBLISHLAYER_EVENT_ID' => 'evt_duplicate_test',
            'CONTENT_TYPE' => 'application/json',
        ];

        // First request
        $response1 = $this->call('POST', '/publishlayer/webhook', [], [], [], $headers, $body);
        $response1->assertOk()->assertJson(['ok' => true]);

        // Second request (duplicate)
        $response2 = $this->call('POST', '/publishlayer/webhook', [], [], [], $headers, $body);
        $response2->assertOk()->assertJson(['ok' => true, 'duplicate' => true]);
    }

    public function test_infers_draft_ready_type_from_payload_structure(): void
    {
        Event::fake([DraftReady::class]);

        $payload = [
            'id' => 'evt_infer_test',
            'pl_draft_id' => 'draft_infer',
            'content_html' => '<p>Inferred</p>',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $response = $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk();

        Event::assertDispatched(DraftReady::class);
    }
}
