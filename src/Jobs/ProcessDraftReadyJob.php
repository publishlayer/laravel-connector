<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PublishLayer\LaravelConnector\Events\DraftImageDownloaded;
use PublishLayer\LaravelConnector\Exceptions\ImageDownloadException;
use PublishLayer\LaravelConnector\Models\PublishLayerDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;

class ProcessDraftReadyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 120;

    public function __construct(
        public readonly int $webhookEventId
    ) {
    }

    public function handle(ImageDownloadService $imageService): void
    {
        $event = PublishLayerWebhookEvent::find($this->webhookEventId);

        if (! $event) {
            return;
        }

        // Skip if already processed
        if ($event->isProcessed()) {
            return;
        }

        $event->markProcessing();

        try {
            $this->processEvent($event, $imageService);
            $event->markProcessed();
        } catch (ImageDownloadException $e) {
            if ($e->isPermanent()) {
                $event->markFailed($e->getMessage());

                return;
            }

            // Transient failure - let it retry
            throw $e;
        } catch (\Throwable $e) {
            $event->markFailed($e->getMessage());

            throw $e;
        }
    }

    private function processEvent(PublishLayerWebhookEvent $event, ImageDownloadService $imageService): void
    {
        $payload = $event->payload;

        // Upsert or update the draft record
        $draft = PublishLayerDraft::upsertFromPayload($payload);

        $imagesEnabled = (bool) config('publishlayer_connector.images.enabled', true);
        $pathPrefix = (string) config('publishlayer_connector.images.path_prefix', 'publishlayer/drafts');

        // Download featured image if needed
        $featuredImageUrl = $event->getFeaturedImageUrl();
        if ($imagesEnabled && $draft->needsFeaturedImageDownload($featuredImageUrl)) {
            $this->downloadFeaturedImage($draft, $featuredImageUrl, $imageService, $pathPrefix);
        }

        // Download OG image if needed
        $ogImageUrl = $event->getOgImageUrl();
        if ($imagesEnabled && $draft->needsOgImageDownload($ogImageUrl)) {
            $this->downloadOgImage($draft, $ogImageUrl, $imageService, $pathPrefix);
        }

        // Mark draft as ready
        $draft->update([
            'status' => PublishLayerDraft::STATUS_READY,
            'processed_at' => now(),
            'last_error' => null,
        ]);

        // Dispatch event for application to handle
        event(new DraftImageDownloaded($draft));
    }

    private function downloadFeaturedImage(
        PublishLayerDraft $draft,
        string $url,
        ImageDownloadService $imageService,
        string $pathPrefix
    ): void {
        $storagePath = sprintf('%s/%s', $pathPrefix, $draft->pl_draft_id);
        $filename = 'featured';

        // Delete old image if exists and URL changed
        if (! empty($draft->featured_image_path)) {
            $imageService->delete($draft->featured_image_path);
        }

        $result = $imageService->download($url, $storagePath, $filename);

        // Extract version from URL for change detection
        $version = $this->extractVersionFromUrl($url);

        $draft->update([
            'featured_image_path' => $result['path'],
            'featured_image_url' => $draft->getFeaturedImageLocalUrl() ?? $url,
            'featured_image_original_url' => $url,
            'featured_image_version' => $version,
        ]);
    }

    private function downloadOgImage(
        PublishLayerDraft $draft,
        string $url,
        ImageDownloadService $imageService,
        string $pathPrefix
    ): void {
        $storagePath = sprintf('%s/%s', $pathPrefix, $draft->pl_draft_id);
        $filename = 'og';

        // Delete old image if exists and URL changed
        if (! empty($draft->og_image_path)) {
            $imageService->delete($draft->og_image_path);
        }

        $result = $imageService->download($url, $storagePath, $filename);

        $draft->update([
            'og_image_path' => $result['path'],
            'og_image_url' => $draft->getOgImageLocalUrl() ?? $url,
            'og_image_original_url' => $url,
        ]);
    }

    private function extractVersionFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (empty($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $query);

        return isset($query['v']) && is_scalar($query['v']) ? (string) $query['v'] : null;
    }

    /**
     * Determine the queue to use.
     */
    public function queue(): string
    {
        return (string) config('publishlayer_connector.webhooks.queue', 'default');
    }
}
