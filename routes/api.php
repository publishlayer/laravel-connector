<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\HealthController;
use PublishLayer\LaravelConnector\Http\Controllers\SyncController;

Route::middleware('publishlayer.sync')->group(function (): void {
    Route::get('/health', [HealthController::class, 'show'])->name('api.health');
    Route::post('/sync', [SyncController::class, 'store'])->name('api.sync');
    Route::post('/webhook', [SyncController::class, 'store'])->name('api.webhook');
});
