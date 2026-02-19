<?php

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\WebhookController;
use PublishLayer\LaravelConnector\Http\Middleware\VerifyPublishLayerSignature;

Route::middleware([VerifyPublishLayerSignature::class])->group(function (): void {
    Route::post(config('publishlayer.webhooks.path', 'publishlayer/webhook'), [WebhookController::class, 'handle']);
});
