<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\Jobs\ProcessDraftReadyJob;
use PublishLayer\LaravelConnector\Models\PublishLayerFailedMessage;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;

class ProcessWebhookFailureTest extends TestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_failed_processing_writes_publishlayer_failed_messages(): void
    {
        $event = PublishLayerWebhookEvent::create([
            'site_key' => 'site_fail',
            'event_id' => 'evt_fail_001',
            'event_type' => 'draft.ready',
            'payload' => [
                // Missing draft identifiers will trigger InvalidArgumentException in upsertFromPayload
                'title' => 'Invalid draft payload',
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($event->id);

        try {
            $job->handle(app(ImageDownloadService::class));
            $this->fail('Expected ProcessDraftReadyJob to throw an exception.');
        } catch (\InvalidArgumentException) {
            // Expected
        }

        $event->refresh();
        $this->assertSame(PublishLayerWebhookEvent::STATUS_FAILED, $event->status);

        $failedMessage = PublishLayerFailedMessage::query()
            ->where('event_id', 'evt_fail_001')
            ->first();

        $this->assertNotNull($failedMessage);
        $this->assertSame('site_fail', $failedMessage->site_key);
        $this->assertSame('draft_processing', $failedMessage->context['stage'] ?? null);
    }
}
