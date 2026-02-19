<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Event;
use PublishLayer\LaravelConnector\Events\DraftReady;

class WebhookEventDispatchTest extends TestCase
{
    public function test_draft_ready_event_is_dispatched(): void
    {
        Event::fake();

        $payload = ['type' => 'draft.ready', 'id' => 'evt_dispatch_1'];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $body, 'test-secret');

        $this->call('POST', '/publishlayer/webhook', [], [], [], [
            'HTTP_X_PUBLISHLAYER_TIMESTAMP' => $timestamp,
            'HTTP_X_PUBLISHLAYER_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertOk();

        Event::assertDispatched(DraftReady::class);
    }
}
