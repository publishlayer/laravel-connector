<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class WebhookCacheFallbackTest extends TestCase
{
    public function test_webhook_uses_cache_based_idempotency_when_migrations_are_missing(): void
    {
        $payload = [
            'type' => 'draft.ready',
            'id' => 'evt_cache_fallback',
            'site_key' => 'site_cache_fallback',
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $server = [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'HTTP_X_PUBLISHLAYER_EVENT_ID' => 'evt_cache_fallback',
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
