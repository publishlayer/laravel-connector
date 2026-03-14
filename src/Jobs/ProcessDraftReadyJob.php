<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use PublishLayer\LaravelConnector\Events\DraftImageDownloaded;
use PublishLayer\LaravelConnector\Exceptions\ImageDownloadException;
use PublishLayer\LaravelConnector\Models\PlInboxBrief;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerContentMapping;
use PublishLayer\LaravelConnector\Models\PublishLayerDelivery;
use PublishLayer\LaravelConnector\Models\PublishLayerDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerFailedMessage;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\ImageDownloadService;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;
use PublishLayer\LaravelConnector\Services\SchemaState;

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

    public function handle(ImageDownloadService $imageService, ?SchemaState $schemaState = null): void
    {
        $schemaState ??= app(SchemaState::class);

        $event = PublishLayerWebhookEvent::find($this->webhookEventId);

        if (! $event) {
            return;
        }

        // Skip if already processed
        if ($event->isProcessed()) {
            return;
        }

        $delivery = null;
        if ($schemaState->hasTable('publishlayer_deliveries')) {
            $delivery = PublishLayerDelivery::updateOrCreate(
                [
                    'webhook_event_id' => $event->id,
                    'action' => 'process_draft_ready',
                ],
                [
                    'site_key' => $this->resolveSiteKey($event),
                    'status' => PublishLayerDelivery::STATUS_QUEUED,
                    'payload' => [
                        'event_type' => $event->event_type,
                    ],
                    'attempted_at' => now(),
                ]
            );
        }

        if ($delivery !== null) {
            $delivery->markProcessing();
        }
        $event->markProcessing();

        try {
            $this->processEvent($event, $imageService);
            $event->markProcessed();
            if ($delivery !== null) {
                $delivery->markProcessed([
                    'message' => 'Draft payload processed.',
                ]);
            }
        } catch (ImageDownloadException $e) {
            if ($delivery !== null) {
                $delivery->markFailed($e->getMessage());
            }
            $this->recordFailure($event, $delivery, $e, ['stage' => 'image_download'], $schemaState);

            if ($e->isPermanent()) {
                $event->markFailed($e->getMessage());

                return;
            }

            $event->markFailed($e->getMessage());

            // Transient failure - let it retry
            throw $e;
        } catch (\Throwable $e) {
            if ($delivery !== null) {
                $delivery->markFailed($e->getMessage());
            }
            $event->markFailed($e->getMessage());
            $this->recordFailure($event, $delivery, $e, ['stage' => 'draft_processing'], $schemaState);

            throw $e;
        }
    }

    private function processEvent(PublishLayerWebhookEvent $event, ImageDownloadService $imageService): void
    {
        $payload = $event->payload;
        $siteKey = $this->resolveSiteKey($event);
        $inbox = app(PublishLayerInbox::class);

        // Upsert or update the draft record
        $draft = PublishLayerDraft::upsertFromPayload($payload);
        $this->syncContentMapping($event, $draft);

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

        $this->upsertInboxRecords($event, $siteKey, $payload, $inbox);

        // Dispatch event for application to handle
        event(new DraftImageDownloaded($draft));
    }

    private function syncContentMapping(PublishLayerWebhookEvent $event, PublishLayerDraft $draft): void
    {
        if (! app(SchemaState::class)->hasTable('publishlayer_content_mappings')) {
            return;
        }

        $contentId = $event->getContentId();

        if ($contentId === null || $contentId === '') {
            return;
        }

        PublishLayerContentMapping::updateOrCreate(
            [
                'site_key' => $this->resolveSiteKey($event),
                'mapping_type' => 'content',
                'publishlayer_content_id' => $contentId,
            ],
            [
                'publishlayer_draft_id' => $draft->pl_draft_id,
                'external_type' => PublishLayerDraft::class,
                'external_id' => (string) $draft->id,
                'meta' => [
                    'event_id' => $event->event_id,
                    'event_type' => $event->event_type,
                ],
                'last_synced_at' => now(),
            ]
        );
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

    /**
     * @param array<string, mixed>|null $context
     */
    private function recordFailure(
        PublishLayerWebhookEvent $event,
        ?PublishLayerDelivery $delivery,
        \Throwable $e,
        ?array $context = null,
        ?SchemaState $schemaState = null,
    ): void {
        $schemaState ??= app(SchemaState::class);

        if (! $schemaState->hasTable('publishlayer_failed_messages')) {
            return;
        }

        PublishLayerFailedMessage::create([
            'webhook_event_id' => $event->id,
            'delivery_id' => $delivery?->id,
            'site_key' => $this->resolveSiteKey($event),
            'event_id' => $event->event_id,
            'error_class' => $e::class,
            'error_message' => mb_substr($e->getMessage(), 0, 65535),
            'payload' => $event->payload,
            'context' => $context,
            'failed_at' => now(),
        ]);
    }

    private function resolveSiteKey(PublishLayerWebhookEvent $event): string
    {
        $siteKey = $event->site_key ?? null;
        if (! is_string($siteKey) || trim($siteKey) === '') {
            $siteKey = (string) config('publishlayer_connector.site_key', 'default');
        }

        $siteKey = trim($siteKey);

        return $siteKey !== '' ? mb_substr($siteKey, 0, 128) : 'default';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function upsertInboxRecords(
        PublishLayerWebhookEvent $event,
        string $siteKey,
        array $payload,
        PublishLayerInbox $inbox
    ): void
    {
        $schemaState = app(SchemaState::class);

        if (! $inbox->enabledFor($siteKey)
            || ! $schemaState->hasTable('publishlayer_inbox_drafts')
            || ! $schemaState->hasTable('publishlayer_inbox_briefs')) {
            return;
        }

        if (! $this->hasExplicitSiteKey($event, $payload, $siteKey)) {
            return;
        }

        $briefId = $this->extractBriefId($payload);
        if ($briefId !== null) {
            PlInboxBrief::query()->updateOrCreate(
                [
                    'site_key' => $siteKey,
                    'pl_brief_id' => $briefId,
                ],
                [
                    'title' => $this->extractTitle($payload),
                    'status' => 'received',
                    'brief_payload' => $payload,
                    'received_at' => now(),
                ]
            );
        }

        $draftId = $this->extractDraftId($payload);
        if ($draftId === null) {
            return;
        }

        $draft = PlInboxDraft::query()->firstOrNew([
            'site_key' => $siteKey,
            'pl_draft_id' => $draftId,
        ]);

        $currentStatus = is_string($draft->status) && $draft->status !== '' ? $draft->status : PlInboxDraft::STATUS_DRAFT;

        $draft->fill([
            'pl_brief_id' => $briefId,
            'title' => $this->extractTitle($payload),
            'body_markdown' => $this->extractMarkdown($payload),
            'body_html' => $this->extractHtml($payload),
            'excerpt' => $this->extractExcerpt($payload),
            'featured_image_url' => $this->extractFeaturedImageUrl($payload),
            'status' => $currentStatus,
            'payload' => $payload,
        ]);

        $inbox->ensureDraftSlug($draft);
        $draft->save();

        if (is_string($draft->featured_image_url) && trim($draft->featured_image_url) !== '') {
            DownloadPlInboxFeaturedImage::dispatch($draft->id)->onQueue($this->queue());
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function hasExplicitSiteKey(PublishLayerWebhookEvent $event, array $payload, string $resolvedSiteKey): bool
    {
        if (isset($payload['site_key']) || isset($payload['site_token']) || data_get($payload, 'site.key') !== null) {
            return true;
        }

        if (is_array($event->request_headers)) {
            $headerSiteKey = $event->request_headers['site_key'] ?? null;

            if (is_scalar($headerSiteKey)) {
                $headerSiteKey = trim((string) $headerSiteKey);
            }

            if (is_string($headerSiteKey) && $headerSiteKey !== '' && $headerSiteKey !== 'default') {
                return true;
            }
        }

        return $resolvedSiteKey !== 'default';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractDraftId(array $payload): ?string
    {
        $draftId = $payload['pl_draft_id'] ?? $payload['draft_id'] ?? $payload['id'] ?? null;

        if (! is_scalar($draftId)) {
            return null;
        }

        $draftId = trim((string) $draftId);

        return $draftId !== '' ? mb_substr($draftId, 0, 128) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractBriefId(array $payload): ?string
    {
        $briefId = $payload['pl_brief_id']
            ?? $payload['brief_id']
            ?? data_get($payload, 'brief.id');

        if (! is_scalar($briefId)) {
            return null;
        }

        $briefId = trim((string) $briefId);

        return $briefId !== '' ? mb_substr($briefId, 0, 128) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTitle(array $payload): ?string
    {
        $title = $payload['title']
            ?? data_get($payload, 'draft.title')
            ?? data_get($payload, 'brief.title')
            ?? null;

        if (! is_scalar($title)) {
            return null;
        }

        $title = trim((string) $title);

        return $title !== '' ? mb_substr($title, 0, 512) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMarkdown(array $payload): ?string
    {
        $markdown = $payload['body_markdown']
            ?? $payload['content_markdown']
            ?? $payload['markdown']
            ?? data_get($payload, 'body.markdown');

        if (! is_scalar($markdown)) {
            return null;
        }

        $markdown = trim((string) $markdown);

        return $markdown !== '' ? $markdown : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractHtml(array $payload): ?string
    {
        $html = $payload['body_html']
            ?? $payload['content_html']
            ?? $payload['html']
            ?? data_get($payload, 'body.html');

        if (! is_scalar($html)) {
            return null;
        }

        $html = trim((string) $html);

        return $html !== '' ? $html : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractExcerpt(array $payload): ?string
    {
        $excerpt = $payload['excerpt']
            ?? $payload['summary']
            ?? data_get($payload, 'meta.excerpt');

        if (! is_scalar($excerpt)) {
            return null;
        }

        $excerpt = trim((string) $excerpt);

        return $excerpt !== '' ? mb_substr($excerpt, 0, 65535) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractFeaturedImageUrl(array $payload): ?string
    {
        $imageUrl = $payload['featured_image_url']
            ?? data_get($payload, 'featured_image.url')
            ?? data_get($payload, 'images.featured.url');

        if (! is_scalar($imageUrl)) {
            return null;
        }

        $imageUrl = trim((string) $imageUrl);

        if ($imageUrl === '' || ! Str::startsWith($imageUrl, ['http://', 'https://'])) {
            return null;
        }

        return mb_substr($imageUrl, 0, 65535);
    }
}
