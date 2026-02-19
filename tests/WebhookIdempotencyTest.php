<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class WebhookIdempotencyTest extends TestCase
{
    public function test_duplicate_event_id_is_ignored(): void
    {
        $payload = ['type' => 'draft.ready', 'id' => 'evt_payload'];
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
    }
}
