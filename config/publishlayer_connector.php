<?php

return [
    // Support both legacy PL_CONNECTOR_* keys and PUBLISHLAYER_* keys.
    'base_url' => env('PUBLISHLAYER_BASE_URL', env('PL_CONNECTOR_BASE_URL', 'https://api.publishlayer.com')),
    'api_key' => env('PUBLISHLAYER_API_KEY', env('PL_CONNECTOR_API_KEY')),
    'workspace_id' => env('PUBLISHLAYER_WORKSPACE_ID', env('PL_CONNECTOR_WORKSPACE_ID')),
    'client_site_id' => env('PUBLISHLAYER_CLIENT_SITE_ID', env('PL_CONNECTOR_CLIENT_SITE_ID')),
    'site_key' => env('PUBLISHLAYER_SITE_KEY', env('PL_CONNECTOR_SITE_KEY', env('PL_CONNECTOR_API_KEY'))),
    'timeout' => (int) env('PUBLISHLAYER_TIMEOUT', (int) env('PUBLISHLAYER_HTTP_TIMEOUT_SECONDS', (int) env('PL_CONNECTOR_TIMEOUT', 10))),
    'connections' => [
        'default' => [
            'api_key' => env('PUBLISHLAYER_API_KEY', env('PL_CONNECTOR_API_KEY')),
            'workspace_id' => env('PUBLISHLAYER_WORKSPACE_ID', env('PL_CONNECTOR_WORKSPACE_ID')),
            'base_url' => env('PUBLISHLAYER_BASE_URL', env('PL_CONNECTOR_BASE_URL', 'https://api.publishlayer.com')),
        ],
    ],
    'webhooks' => [
        'path' => env('PUBLISHLAYER_WEBHOOK_PATH', 'publishlayer/webhook'),
        'public_url' => env('PUBLISHLAYER_CONNECTOR_PUBLIC_URL'),
        'signing_secret' => env('PUBLISHLAYER_WEBHOOK_SECRET'),
        'header_name' => env('PUBLISHLAYER_WEBHOOK_SIGNATURE_HEADER', 'X-PublishLayer-Signature'),
        'id_header' => env('PUBLISHLAYER_WEBHOOK_EVENT_ID_HEADER', 'X-PublishLayer-Event-Id'),
        'timestamp_header' => env('PUBLISHLAYER_WEBHOOK_TIMESTAMP_HEADER', 'X-PublishLayer-Timestamp'),
        'site_key_header' => env('PUBLISHLAYER_WEBHOOK_SITE_KEY_HEADER', 'X-PublishLayer-Site-Key'),
        'site_token_header' => env('PUBLISHLAYER_WEBHOOK_SITE_TOKEN_HEADER', 'X-PublishLayer-Site-Token'),
        'tolerance_seconds' => (int) env('PUBLISHLAYER_WEBHOOK_TOLERANCE_SECONDS', 300),
        'idempotency_cache_ttl_seconds' => (int) env('PUBLISHLAYER_WEBHOOK_IDEMPOTENCY_TTL_SECONDS', 86400),
        'queue' => env('PUBLISHLAYER_WEBHOOK_QUEUE', 'default'),
    ],
    'activity' => [
        'path' => env('PUBLISHLAYER_ACTIVITY_PATH', 'publishlayer/connector/activity'),
        'legacy_path' => env('PUBLISHLAYER_ACTIVITY_LEGACY_PATH', 'publishlayer/activity'),
    ],
    'heartbeat' => [
        'path' => env('PUBLISHLAYER_CONNECTOR_HEARTBEAT_PATH', 'publishlayer/connector/heartbeat'),
        'legacy_path' => env('PUBLISHLAYER_CONNECTOR_HEARTBEAT_LEGACY_PATH', 'publishlayer/heartbeat'),
    ],
    'http' => [
        'timeout_seconds' => (int) env('PUBLISHLAYER_TIMEOUT', (int) env('PUBLISHLAYER_HTTP_TIMEOUT_SECONDS', 10)),
        'retries' => (int) env('PUBLISHLAYER_HTTP_RETRIES', 2),
        'retry_sleep_ms' => (int) env('PUBLISHLAYER_HTTP_RETRY_SLEEP_MS', 200),
    ],
    'database' => [
        'transaction_attempts' => (int) env('PUBLISHLAYER_DB_TRANSACTION_ATTEMPTS', 3),
        'retry_sleep_ms' => (int) env('PUBLISHLAYER_DB_RETRY_SLEEP_MS', 150),
    ],
    'schema_cache' => [
        'enabled' => (bool) env('PUBLISHLAYER_SCHEMA_CACHE_ENABLED', true),
        'ttl_seconds' => (int) env('PUBLISHLAYER_SCHEMA_CACHE_TTL_SECONDS', 300),
        'cache_key_prefix' => env('PUBLISHLAYER_SCHEMA_CACHE_PREFIX', 'publishlayer:schema-state:'),
    ],
    'images' => [
        'enabled' => (bool) env('PUBLISHLAYER_IMAGES_ENABLED', true),
        'disk' => env('PUBLISHLAYER_IMAGES_DISK', 'public'),
        'path_prefix' => env('PUBLISHLAYER_IMAGES_PATH_PREFIX', 'publishlayer/drafts'),
        'max_size_mb' => (int) env('PUBLISHLAYER_IMAGE_MAX_MB', 12),
        'download_timeout_seconds' => (int) env('PUBLISHLAYER_IMAGE_DOWNLOAD_TIMEOUT', 30),
        'allowed_mime_types' => [
            'image/webp',
            'image/jpeg',
            'image/png',
            'image/gif',
        ],
    ],
];
