<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PublishLayer\LaravelConnector\Events\DraftImageDownloaded;
use PublishLayer\LaravelConnector\Jobs\ProcessDraftReadyJob;
use PublishLayer\LaravelConnector\Models\PublishLayerDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;

class ProcessDraftReadyJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    public function test_job_downloads_featured_image_and_stores_draft(): void
    {
        Event::fake([DraftImageDownloaded::class]);

        // Create fake image content
        $imageContent = $this->createFakeImageContent();

        Http::fake([
            'https://example.com/image.webp' => Http::response($imageContent, 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        $webhookEvent = PublishLayerWebhookEvent::create([
            'event_id' => 'evt_job_test',
            'event_type' => 'draft.ready',
            'payload' => [
                'pl_draft_id' => 'draft_job_test',
                'content_id' => 'content_123',
                'title' => 'Test Draft',
                'content_html' => '<p>Test</p>',
                'featured_image_url' => 'https://example.com/image.webp',
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($webhookEvent->id);
        $job->handle(app(\PublishLayer\LaravelConnector\Services\ImageDownloadService::class));

        // Assert webhook event is processed
        $webhookEvent->refresh();
        $this->assertEquals(PublishLayerWebhookEvent::STATUS_PROCESSED, $webhookEvent->status);

        // Assert draft was created
        $draft = PublishLayerDraft::where('pl_draft_id', 'draft_job_test')->first();
        $this->assertNotNull($draft);
        $this->assertEquals('Test Draft', $draft->title);
        $this->assertEquals(PublishLayerDraft::STATUS_READY, $draft->status);

        // Assert image was downloaded
        $this->assertNotNull($draft->featured_image_path);
        Storage::disk('public')->assertExists($draft->featured_image_path);
        $this->assertEquals('https://example.com/image.webp', $draft->featured_image_original_url);

        // Assert event was dispatched
        Event::assertDispatched(DraftImageDownloaded::class, function ($event) use ($draft) {
            return $event->draft->id === $draft->id;
        });
    }

    public function test_job_skips_download_when_no_image_url(): void
    {
        $webhookEvent = PublishLayerWebhookEvent::create([
            'event_id' => 'evt_no_image',
            'event_type' => 'draft.ready',
            'payload' => [
                'pl_draft_id' => 'draft_no_image',
                'title' => 'No Image Draft',
                'content_html' => '<p>No image</p>',
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($webhookEvent->id);
        $job->handle(app(\PublishLayer\LaravelConnector\Services\ImageDownloadService::class));

        $draft = PublishLayerDraft::where('pl_draft_id', 'draft_no_image')->first();
        $this->assertNotNull($draft);
        $this->assertNull($draft->featured_image_path);
        $this->assertEquals(PublishLayerDraft::STATUS_READY, $draft->status);
    }

    public function test_job_does_not_redownload_unchanged_image(): void
    {
        $imageContent = $this->createFakeImageContent();

        Http::fake([
            'https://example.com/image.webp' => Http::response($imageContent, 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        // Create initial draft with image
        $draft = PublishLayerDraft::create([
            'pl_draft_id' => 'draft_no_redownload',
            'title' => 'Existing Draft',
            'featured_image_path' => 'publishlayer/drafts/draft_no_redownload/featured.webp',
            'featured_image_original_url' => 'https://example.com/image.webp',
            'status' => PublishLayerDraft::STATUS_READY,
        ]);

        // Store a fake existing image
        Storage::disk('public')->put($draft->featured_image_path, $imageContent);

        $webhookEvent = PublishLayerWebhookEvent::create([
            'event_id' => 'evt_no_redownload',
            'event_type' => 'draft.ready',
            'payload' => [
                'pl_draft_id' => 'draft_no_redownload',
                'title' => 'Updated Draft',
                'featured_image_url' => 'https://example.com/image.webp', // Same URL
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($webhookEvent->id);
        $job->handle(app(\PublishLayer\LaravelConnector\Services\ImageDownloadService::class));

        // HTTP should not be called for same URL
        Http::assertSentCount(0);
    }

    public function test_job_redownloads_when_url_changes(): void
    {
        $imageContent1 = $this->createFakeImageContent();
        $imageContent2 = $this->createFakeImageContent();

        Http::fake([
            'https://example.com/new-image.webp' => Http::response($imageContent2, 200, [
                'Content-Type' => 'image/webp',
            ]),
        ]);

        // Create initial draft with old image
        $draft = PublishLayerDraft::create([
            'pl_draft_id' => 'draft_url_change',
            'title' => 'Existing Draft',
            'featured_image_path' => 'publishlayer/drafts/draft_url_change/featured.webp',
            'featured_image_original_url' => 'https://example.com/old-image.webp',
            'status' => PublishLayerDraft::STATUS_READY,
        ]);

        Storage::disk('public')->put($draft->featured_image_path, $imageContent1);

        $webhookEvent = PublishLayerWebhookEvent::create([
            'event_id' => 'evt_url_change',
            'event_type' => 'draft.ready',
            'payload' => [
                'pl_draft_id' => 'draft_url_change',
                'title' => 'Updated Draft',
                'featured_image_url' => 'https://example.com/new-image.webp', // Different URL
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($webhookEvent->id);
        $job->handle(app(\PublishLayer\LaravelConnector\Services\ImageDownloadService::class));

        // HTTP should be called for new URL
        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/new-image.webp';
        });

        $draft->refresh();
        $this->assertEquals('https://example.com/new-image.webp', $draft->featured_image_original_url);
    }

    public function test_job_skips_already_processed_event(): void
    {
        $webhookEvent = PublishLayerWebhookEvent::create([
            'event_id' => 'evt_already_processed',
            'event_type' => 'draft.ready',
            'payload' => [
                'pl_draft_id' => 'draft_processed',
                'title' => 'Already Processed',
            ],
            'status' => PublishLayerWebhookEvent::STATUS_PROCESSED,
            'processed_at' => now(),
            'received_at' => now(),
        ]);

        $job = new ProcessDraftReadyJob($webhookEvent->id);
        $job->handle(app(\PublishLayer\LaravelConnector\Services\ImageDownloadService::class));

        // Draft should not be created
        $this->assertNull(PublishLayerDraft::where('pl_draft_id', 'draft_processed')->first());
    }

    /**
     * Create fake image content (minimal valid WebP).
     */
    private function createFakeImageContent(): string
    {
        // Minimal WebP file header + some data
        return "RIFF" . pack('V', 100) . "WEBP" . str_repeat("\x00", 88);
    }
}
