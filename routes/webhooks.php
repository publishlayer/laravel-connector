<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\ConnectorActivityController;
use PublishLayer\LaravelConnector\Http\Controllers\ConnectorHeartbeatController;
use PublishLayer\LaravelConnector\Http\Controllers\WebhookController;
use PublishLayer\LaravelConnector\Http\Middleware\VerifyPublishLayerSignature;

Route::middleware([VerifyPublishLayerSignature::class])->group(function (): void {
    Route::post(config('publishlayer_connector.webhooks.path', 'publishlayer/webhook'), [WebhookController::class, 'handle']);
});

$activityPath = (string) config('publishlayer_connector.activity.path', 'publishlayer/connector/activity');
$legacyActivityPath = (string) config('publishlayer_connector.activity.legacy_path', 'publishlayer/activity');

Route::match(['GET', 'POST'], $activityPath, [ConnectorActivityController::class, 'show']);

if ($legacyActivityPath !== $activityPath) {
    Route::match(['GET', 'POST'], $legacyActivityPath, [ConnectorActivityController::class, 'show']);
}

$heartbeatPath = (string) config('publishlayer_connector.heartbeat.path', 'publishlayer/connector/heartbeat');
$legacyHeartbeatPath = (string) config('publishlayer_connector.heartbeat.legacy_path', 'publishlayer/heartbeat');

Route::post($heartbeatPath, [ConnectorHeartbeatController::class, 'store']);

if ($legacyHeartbeatPath !== $heartbeatPath) {
    Route::post($legacyHeartbeatPath, [ConnectorHeartbeatController::class, 'store']);
}
