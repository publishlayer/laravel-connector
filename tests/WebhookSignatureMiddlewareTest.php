<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

class WebhookSignatureMiddlewareTest extends TestCase
{
    public function test_valid_signature_is_accepted(): void
    {
        $payload = ['type' => 'draft.ready', 'id' => 'evt_1'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $response = $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $payload = ['type' => 'draft.ready', 'id' => 'evt_2'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();

        $response = $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => 'bad-signature',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401)->assertJson([
            'ok' => false,
            'error' => 'invalid_signature',
        ]);
    }
}
