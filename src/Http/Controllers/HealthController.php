<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use PublishLayer\LaravelConnector\Services\ConnectorHealthService;

class HealthController extends Controller
{
    public function show(Request $request, ConnectorHealthService $healthService): JsonResponse
    {
        $configuredSiteId = trim((string) config('publishlayer.site_id', ''));
        $providedSiteId = trim((string) ($request->header('X-PublishLayer-Site', $request->input('site_id', ''))));

        if ($configuredSiteId !== '' && $providedSiteId !== '' && ! hash_equals($configuredSiteId, $providedSiteId)) {
            return response()->json([
                'ok' => false,
                'message' => 'The provided site identifier does not match this connector.',
                'errors' => [
                    'site_id' => ['The provided site identifier does not match this connector.'],
                ],
            ], 422);
        }

        $summary = $healthService->summary();

        return response()->json([
            'ok' => $summary['ok'],
            'has_warnings' => $summary['has_warnings'],
            'mode' => $summary['mode'],
            'checks' => $summary['checks'],
            'latest_sync_log' => $summary['latest_sync_log'],
        ], $summary['ok'] ? 200 : 503);
    }
}
