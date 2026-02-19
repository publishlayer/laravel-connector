<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use PublishLayer\LaravelConnector\Events\DraftReady;
use PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived;
use PublishLayer\LaravelConnector\Events\PublishRequested;
use PublishLayer\LaravelConnector\Events\RevisionReady;

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

        $eventIdHeader = (string) config('publishlayer.webhooks.id_header', 'X-PublishLayer-Event-Id');
        $eventId = $request->header($eventIdHeader);

        if (! is_string($eventId) || $eventId === '') {
            $eventId = isset($payload['id']) && is_scalar($payload['id']) ? (string) $payload['id'] : null;
        }

        if (is_string($eventId) && $eventId !== '') {
            $cacheKey = sprintf('publishlayer:webhook:%s', $eventId);
            $ttlSeconds = (int) config('publishlayer.webhooks.idempotency_cache_ttl_seconds', 86400);

            if (! Cache::add($cacheKey, true, $ttlSeconds)) {
                return response()->json([
                    'ok' => true,
                    'duplicate' => true,
                ]);
            }
        }

        $type = isset($payload['type']) && is_string($payload['type']) ? $payload['type'] : null;
        $headers = [
            'signature' => $request->header((string) config('publishlayer.webhooks.header_name', 'X-PublishLayer-Signature')),
            'timestamp' => $request->header((string) config('publishlayer.webhooks.timestamp_header', 'X-PublishLayer-Timestamp')),
            'event_id' => $eventId,
        ];

        event(new PublishLayerWebhookReceived($payload, $eventId, $type, $headers));

        match ($type) {
            'draft.ready' => event(new DraftReady($payload)),
            'revision.ready' => event(new RevisionReady($payload)),
            'publish.requested' => event(new PublishRequested($payload)),
            default => null,
        };

        return response()->json(['ok' => true]);
    }
}
