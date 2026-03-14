<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use PublishLayer\LaravelConnector\Models\PublishLayerConnectorHeartbeat;
use PublishLayer\LaravelConnector\Models\PublishLayerFailedMessage;
use PublishLayer\LaravelConnector\Models\PublishLayerSetting;
use PublishLayer\LaravelConnector\Models\PublishLayerWebhookEvent;
use PublishLayer\LaravelConnector\Services\SchemaState;

class ConnectorActivityController extends Controller
{
    public function show(Request $request, SchemaState $schemaState): JsonResponse
    {
        $siteKey = $this->resolveInputSiteKey($request);

        $validator = Validator::make([
            'site_key' => $siteKey,
        ], [
            'site_key' => ['required', 'string', 'max:128'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $siteKey = (string) $siteKey;
        if (! $this->siteKeyExists($siteKey)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'site_key' => ['The selected site_key is invalid.'],
                ],
            ], 422);
        }

        $lastWebhookAt = null;
        $lastProcessedAt = null;
        $recentEventsCount24h = 0;

        if ($schemaState->hasTable('publishlayer_webhook_events')) {
            $lastWebhookAt = PublishLayerWebhookEvent::query()
                ->where('site_key', $siteKey)
                ->max('received_at');

            $lastProcessedAt = PublishLayerWebhookEvent::query()
                ->where('site_key', $siteKey)
                ->max('processed_at');

            $recentEventsCount24h = PublishLayerWebhookEvent::query()
                ->where('site_key', $siteKey)
                ->where('received_at', '>=', now()->subDay())
                ->count();
        }

        $lastHeartbeatAt = null;
        if ($schemaState->hasTable('publishlayer_connector_heartbeats')) {
            $lastHeartbeatAt = PublishLayerConnectorHeartbeat::query()
                ->where('site_key', $siteKey)
                ->max('last_seen_at');
        }

        $failedEventsCount24h = 0;
        if ($schemaState->hasTable('publishlayer_failed_messages')) {
            $failedEventsCount24h = PublishLayerFailedMessage::query()
                ->where('site_key', $siteKey)
                ->where('failed_at', '>=', now()->subDay())
                ->count();
        }

        return response()->json([
            'last_webhook_received_at' => $this->asIso8601($lastWebhookAt),
            'last_processed_at' => $this->asIso8601($lastProcessedAt),
            'last_heartbeat_at' => $this->asIso8601($lastHeartbeatAt),
            'recent_events_count_24h' => $recentEventsCount24h,
            'failed_events_count_24h' => $failedEventsCount24h,
        ]);
    }

    private function siteKeyExists(string $siteKey): bool
    {
        $schemaState = app(SchemaState::class);

        foreach ($this->configuredSiteKeys() as $configuredSiteKey) {
            if (hash_equals($configuredSiteKey, $siteKey)) {
                return true;
            }
        }

        if ($schemaState->hasTable('publishlayer_webhook_events')
            && PublishLayerWebhookEvent::where('site_key', $siteKey)->exists()) {
            return true;
        }

        if ($schemaState->hasTable('publishlayer_connector_heartbeats')
            && PublishLayerConnectorHeartbeat::where('site_key', $siteKey)->exists()) {
            return true;
        }

        if ($schemaState->hasTable('publishlayer_settings')
            && PublishLayerSetting::where('site_key', $siteKey)->exists()) {
            return true;
        }

        return false;
    }

    private function resolveInputSiteKey(Request $request): ?string
    {
        $candidate = $request->input('site_key', $request->input('site_token', $request->input('api_key')));
        $value = trim((string) $candidate);

        return $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function configuredSiteKeys(): array
    {
        $keys = [
            trim((string) config('publishlayer_connector.site_key', '')),
            trim((string) config('publishlayer_connector.api_key', '')),
            trim((string) config('publishlayer_connector.connections.default.api_key', '')),
        ];

        $keys = array_values(array_filter($keys, static fn (string $key): bool => $key !== ''));

        return array_values(array_unique($keys));
    }

    private function asIso8601(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toIso8601String();
    }
}
