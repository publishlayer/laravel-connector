<?php

declare(strict_types=1);

$portalMiddleware = env('PUBLISHLAYER_INBOX_PORTAL_MIDDLEWARE', 'web');

if (is_string($portalMiddleware)) {
    $portalMiddleware = array_values(array_filter(array_map('trim', explode(',', $portalMiddleware)), static fn (string $value): bool => $value !== ''));
}

if (! is_array($portalMiddleware)) {
    $portalMiddleware = ['web'];
}

return [
    'enabled' => (bool) env('PUBLISHLAYER_INBOX_ENABLED', true),

    // Optional static overrides keyed by site key, e.g. ['site_a' => false].
    'site_overrides' => [],

    'portal' => [
        'prefix' => env('PUBLISHLAYER_INBOX_PORTAL_PREFIX', 'app/content'),
        'middleware' => $portalMiddleware,
    ],

    'public' => [
        'path' => env('PUBLISHLAYER_INBOX_PUBLIC_PATH', 'content/{slug}'),
    ],

    'images' => [
        'path_prefix' => env('PUBLISHLAYER_INBOX_IMAGE_PATH_PREFIX', 'pl-inbox'),
    ],
];
