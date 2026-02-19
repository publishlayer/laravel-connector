<?php

return [
    'base_url' => env('PUBLISHLAYER_BASE_URL', 'https://api.publishlayer.com'),
    'connections' => [
        'default' => [
            'api_key' => env('PUBLISHLAYER_API_KEY'),
            'workspace_id' => env('PUBLISHLAYER_WORKSPACE_ID'),
            'base_url' => env('PUBLISHLAYER_BASE_URL', env('PUBLISHLAYER_BASE_URL', 'https://api.publishlayer.com')),
        ],
    ],
    'webhooks' => [
        'path' => env('PUBLISHLAYER_WEBHOOK_PATH', 'publishlayer/webhook'),
        'signing_secret' => env('PUBLISHLAYER_WEBHOOK_SECRET'),
        'header_name' => env('PUBLISHLAYER_WEBHOOK_SIGNATURE_HEADER', 'X-PublishLayer-Signature'),
        'id_header' => env('PUBLISHLAYER_WEBHOOK_EVENT_ID_HEADER', 'X-PublishLayer-Event-Id'),
        'timestamp_header' => env('PUBLISHLAYER_WEBHOOK_TIMESTAMP_HEADER', 'X-PublishLayer-Timestamp'),
        'tolerance_seconds' => (int) env('PUBLISHLAYER_WEBHOOK_TOLERANCE_SECONDS', 300),
        'idempotency_cache_ttl_seconds' => (int) env('PUBLISHLAYER_WEBHOOK_IDEMPOTENCY_TTL_SECONDS', 86400),
    ],
    'http' => [
        'timeout_seconds' => (int) env('PUBLISHLAYER_HTTP_TIMEOUT_SECONDS', 10),
        'retries' => (int) env('PUBLISHLAYER_HTTP_RETRIES', 2),
        'retry_sleep_ms' => (int) env('PUBLISHLAYER_HTTP_RETRY_SLEEP_MS', 200),
    ],
];
