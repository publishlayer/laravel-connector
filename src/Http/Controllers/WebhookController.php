<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use PublishLayer\LaravelConnector\Events\DraftReady;
use PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived;
use PublishLayer\LaravelConnector\Events\PublishRequested;
use PublishLayer\LaravelConnector\Events\RevisionReady;
use PublishLayer\LaravelConnector\Jobs\ProcessDraftReadyJob;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;

class WebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (! is_array($payload)) {
            return response()->json([
                'ok' => false,
                'error' => 'invalid_json',
            ], 400);
        }

        $eventIdHeader = (string) config('publishlayer_connector.webhooks.id_header', 'X-PublishLayer-Event-Id');
        $eventId = $request->header($eventIdHeader);

        if (! is_string($eventId) || $eventId === '') {
            $eventId = isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null;
        }

        if (! is_string($eventId) || $eventId === '') {
            $eventId = isset($payload['correlation_id']) && is_scalar($payload['correlation_id'])
                ? (string) $payload['correlation_id']
                : (string) \Illuminate\Support\Str::uuid();
        }

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : null;

        // Check for webhook event type from payload structure
        if ($type === null) {
            $type = $this->inferEventType($payload);
        }

        $headers = [
            'signature' => $request->header((string) config('publishlayer_connector.webhooks.header_name', 'X-PublishLayer-Signature')),
            'timestamp' => $request->header((string) config('publishlayer_connector.webhooks.timestamp_header', 'X-PublishLayer-Timestamp')),
            'event_id' => $eventId,
        ];

        // Try to persist to database for idempotency and audit
        $webhookEvent = $this->persistWebhookEvent($eventId, $type, $payload);

        if ($webhookEvent === null) {
            // Already processed (duplicate)
            return response()->json([
                'ok' => true,
                'duplicate' => true,
            ]);
        }

        // Dispatch generic event
        event(new PublishLayerWebhookReceived($payload, $eventId, $type, $headers));

        // Dispatch type-specific events and jobs
        match ($type) {
            'draft.ready' => $this->handleDraftReady($payload, $webhookEvent),
            'revision.ready' => event(new RevisionReady($payload)),
            'publish.requested' => event(new PublishRequested($payload)),
            default => null,
        };

        return response()->json(['ok' => true]);
    }

    /**
     * Persist webhook event to database for idempotency.
     * Returns null if already processed (duplicate).
     */
    private function persistWebhookEvent(string $eventId, ?string $type, array $payload): ?PublishLayerWebhookEvent
    {
        // Check if table exists (migrations may not have run)
        if (! Schema::hasTable('publishlayer_webhook_events')) {
            // Fallback to cache-based idempotency
            $cacheKey = sprintf('publishlayer:webhook:%s', $eventId);
            $ttlSeconds = (int) config('publishlayer_connector.webhooks.idempotency_cache_ttl_seconds', 86400);

            if (! \Illuminate\Support\Facades\Cache::add($cacheKey, true, $ttlSeconds)) {
                return null;
            }

            // Create a transient event object
            $event = new PublishLayerWebhookEvent();
            $event->event_id = $eventId;
            $event->event_type = $type;
            $event->payload = $payload;
            $event->status = PublishLayerWebhookEvent::STATUS_PENDING;
            $event->received_at = now();

            return $event;
        }

        // Check for existing event (idempotency)
        $existing = PublishLayerWebhookEvent::where('event_id', $eventId)->first();

        if ($existing !== null) {
            return null;
        }

        // Create new event record
        return PublishLayerWebhookEvent::create([
            'event_id' => $eventId,
            'event_type' => $type,
            'payload' => $payload,
            'status' => PublishLayerWebhookEvent::STATUS_PENDING,
            'received_at' => now(),
        ]);
    }

    /**
     * Handle DraftReady event: dispatch event and queue job.
     */
    private function handleDraftReady(array $payload, PublishLayerWebhookEvent $webhookEvent): void
    {
        // Dispatch event for synchronous listeners
        event(new DraftReady($payload));

        // Queue job for async processing (image download, etc.)
        if ($webhookEvent->exists) {
            $queue = (string) config('publishlayer_connector.webhooks.queue', 'default');

            ProcessDraftReadyJob::dispatch($webhookEvent->id)
                ->onQueue($queue);
        }
    }

    /**
     * Infer event type from payload structure.
     */
    private function inferEventType(array $payload): ?string
    {
        // Check for draft-related fields
        if (isset($payload['pl_draft_id']) || isset($payload['draft_id']) || isset($payload['content_html'])) {
            return 'draft.ready';
        }

        return null;
    }
}
