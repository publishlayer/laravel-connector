<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use PublishLayer\LaravelConnector\Models\PublishLayerConnectorHeartbeat;
use PublishLayer\LaravelConnector\Services\SchemaState;

class ConnectorHeartbeatController extends Controller
{
    public function store(Request $request, SchemaState $schemaState): JsonResponse
    {
        $siteKey = $request->input('site_key', $request->input('site_token'));

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

        if (! $schemaState->hasTable('publishlayer_connector_heartbeats')) {
            return response()->json([
                'ok' => false,
                'error' => 'connector_heartbeats_table_missing',
            ], 503);
        }

        $siteKey = (string) $siteKey;
        $heartbeat = PublishLayerConnectorHeartbeat::updateOrCreate(
            ['site_key' => $siteKey],
            [
                'last_seen_at' => now(),
                'source' => 'endpoint',
                'meta' => [
                    'ip' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 512),
                ],
            ]
        );

        return response()->json([
            'ok' => true,
            'site_key' => $heartbeat->site_key,
            'last_heartbeat_at' => $heartbeat->last_seen_at?->toIso8601String(),
        ]);
    }
}
