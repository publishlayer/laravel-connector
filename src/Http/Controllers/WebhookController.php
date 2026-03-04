<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Events\DraftReady;
use PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived;
use PublishLayer\LaravelConnector\Events\PublishRequested;
use PublishLayer\LaravelConnector\Events\RevisionReady;
use PublishLayer\LaravelConnector\Jobs\ProcessWebhookEventJob;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\PublishLayerInbox;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse|Response
    {
        $rawBody = $request->getContent();
        $payload = json_decode($rawBody, true);

        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_json',
            ], 400);
        }

        $siteKey = $this->resolveSiteKey($request, $payload);
        $eventId = $this->resolveEventId($request, $payload, $siteKey, $rawBody);

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : null;

        // Check for webhook event type from payload structure
        if ($type === null) {
            $type = $this->inferEventType($payload);
        }

        if ($this->isInboxEventType($type) && ! app(PublishLayerInbox::class)->enabledFor($siteKey)) {
            return response()->noContent();
        }

        $headers = [
            'signature' => $request->header((string) config('publishlayer_connector.webhooks.header_name', 'X-PublishLayer-Signature')),
            'timestamp' => $request->header((string) config('publishlayer_connector.webhooks.timestamp_header', 'X-PublishLayer-Timestamp')),
            'event_id' => $eventId,
            'site_key' => $siteKey,
        ];

        // Try to persist to database for idempotency and audit
        $webhookEvent = $this->persistWebhookEvent($siteKey, $eventId, $type, $payload, $headers);

        if ($webhookEvent === null) {
            // Already processed (duplicate)
            return response()->json([
                'ok' => true,
                'duplicate' => true,
            ]);
        }

        // Dispatch generic event
        event(new PublishLayerWebhookReceived($payload, $eventId, $type, $headers));

        // Dispatch type-specific events for synchronous listeners
        match ($type) {
            'draft.ready' => $this->handleDraftReady($payload),
            'revision.ready' => event(new RevisionReady($payload)),
            'publish.requested' => event(new PublishRequested($payload)),
            default => null,
        };

        if ($webhookEvent->exists) {
            ProcessWebhookEventJob::dispatch($webhookEvent->id)
                ->onQueue((string) config('publishlayer_connector.webhooks.queue', 'default'));
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Persist webhook event to database for idempotency.
     * Returns null if already processed (duplicate).
     *
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     */
    private function persistWebhookEvent(
        string $siteKey,
        string $eventId,
        ?string $type,
        array $payload,
        array $headers
    ): ?PublishLayerWebhookEvent {
        // Check if table exists (migrations may not have run)
        if (! Schema::hasTable('publishlayer_webhook_events')) {
            // Fallback to cache-based idempotency
            $cacheKey = sprintf('publishlayer:webhook:%s', $eventId);
            $ttlSeconds = (int) config('publishlayer_connector.webhooks.idempotency_cache_ttl_seconds', 86400);

            if (! Cache::add($cacheKey, true, $ttlSeconds)) {
                return null;
            }

            // Create a transient event object
            $event = new PublishLayerWebhookEvent();
            $event->site_key = $siteKey;
            $event->event_id = $eventId;
            $event->event_type = $type;
            $event->payload = $payload;
            $event->request_headers = $headers;
            $event->status = PublishLayerWebhookEvent::STATUS_PENDING;
            $event->received_at = now();

            return $event;
        }

        try {
            $createPayload = [
                'event_type' => $type,
                'payload' => $payload,
                'status' => PublishLayerWebhookEvent::STATUS_PENDING,
                'received_at' => now(),
            ];

            if (Schema::hasColumn('publishlayer_webhook_events', 'site_key')) {
                $createPayload['site_key'] = $siteKey;
            }

            if (Schema::hasColumn('publishlayer_webhook_events', 'request_headers')) {
                $createPayload['request_headers'] = $headers;
            }

            $event = PublishLayerWebhookEvent::firstOrCreate(
                [
                    'event_id' => $eventId,
                ],
                $createPayload
            );

            return $event->wasRecentlyCreated ? $event : null;
        } catch (QueryException) {
            // Unique key race conditions should be treated as duplicates.
            return null;
        }
    }

    /**
     * Handle DraftReady event for synchronous listeners.
     */
    private function handleDraftReady(array $payload): void
    {
        event(new DraftReady($payload));
    }

    /**
     * Infer event type from payload structure.
     *
     * @param array<string, mixed> $payload
     */
    private function inferEventType(array $payload): ?string
    {
        // Check for draft-related fields
        if (isset($payload['pl_draft_id']) || isset($payload['draft_id']) || isset($payload['content_html'])) {
            return 'draft.ready';
        }

        if (isset($payload['pl_brief_id']) || isset($payload['brief_id']) || isset($payload['brief'])) {
            return 'brief.created';
        }

        return null;
    }

    private function isInboxEventType(?string $type): bool
    {
        return in_array($type, ['draft.ready', 'brief.created', 'brief.ready'], true);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveSiteKey(Request $request, array $payload): string
    {
        $siteKeyHeader = (string) config('publishlayer_connector.webhooks.site_key_header', 'X-PublishLayer-Site-Key');
        $siteTokenHeader = (string) config('publishlayer_connector.webhooks.site_token_header', 'X-PublishLayer-Site-Token');

        $siteKey = $request->input('site_key')
            ?? $request->input('site_token')
            ?? $request->header($siteKeyHeader)
            ?? $request->header($siteTokenHeader)
            ?? ($payload['site_key'] ?? null)
            ?? ($payload['site_token'] ?? null)
            ?? data_get($payload, 'site.key')
            ?? config('publishlayer_connector.site_key')
            ?? 'default';

        $siteKey = is_scalar($siteKey) ? trim((string) $siteKey) : '';

        return $siteKey !== '' ? mb_substr($siteKey, 0, 128) : 'default';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveEventId(Request $request, array $payload, string $siteKey, string $rawBody): string
    {
        $eventIdHeader = (string) config('publishlayer_connector.webhooks.id_header', 'X-PublishLayer-Event-Id');
        $eventId = $request->header($eventIdHeader);

        if (! is_string($eventId) || trim($eventId) === '') {
            $eventId = isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null;
        }

        if (! is_string($eventId) || trim($eventId) === '') {
            $eventId = isset($payload['correlation_id']) && is_scalar($payload['correlation_id'])
                ? (string) $payload['correlation_id']
                : null;
        }

        if (! is_string($eventId) || trim($eventId) === '') {
            $eventId = hash('sha256', $siteKey . '|' . $rawBody);
        }

        return mb_substr(trim($eventId), 0, 64);
    }
}
