<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PublishLayer\LaravelConnector\Http\Controllers\InboxDraftController;
use PublishLayer\LaravelConnector\Http\Controllers\InboxPublicController;

$portalPrefix = trim((string) config('publishlayer_inbox.portal.prefix', 'app/content'), '/');
$portalMiddleware = config('publishlayer_inbox.portal.middleware', ['web']);

if (is_string($portalMiddleware)) {
    $portalMiddleware = array_values(array_filter(array_map('trim', explode(',', $portalMiddleware)), static fn (string $value): bool => $value !== ''));
}

if (! is_array($portalMiddleware)) {
    $portalMiddleware = ['web'];
}

Route::prefix($portalPrefix)->middleware($portalMiddleware)->name('publishlayer-inbox.portal.')->group(function (): void {
    Route::get('/', [InboxDraftController::class, 'index'])->name('index');
    Route::get('/{draft}', [InboxDraftController::class, 'show'])
        ->whereNumber('draft')
        ->name('show');
    Route::post('/{draft}/approve', [InboxDraftController::class, 'approve'])
        ->whereNumber('draft')
        ->name('approve');
    Route::post('/{draft}/publish', [InboxDraftController::class, 'publish'])
        ->whereNumber('draft')
        ->name('publish');
});

$publicPath = trim((string) config('publishlayer_inbox.public.path', 'content/{slug}'), '/');

Route::get($publicPath, [InboxPublicController::class, 'show'])
    ->where('slug', '[A-Za-z0-9\-]+')
    ->name('publishlayer-inbox.public.show');
