# PublishLayer Laravel Connector

A Composer installable Laravel package for receiving PublishLayer webhooks and making API calls.

## Install

```bash
composer require publishlayer/laravel-connector
```

## Publish config

```bash
php artisan vendor:publish --tag=publishlayer-config
```

Or run:

```bash
php artisan publishlayer:install
```

## Environment variables

- `PUBLISHLAYER_BASE_URL`
- `PUBLISHLAYER_API_KEY`
- `PUBLISHLAYER_WORKSPACE_ID`
- `PUBLISHLAYER_WEBHOOK_PATH`
- `PUBLISHLAYER_WEBHOOK_SECRET`
- `PUBLISHLAYER_WEBHOOK_SIGNATURE_HEADER`
- `PUBLISHLAYER_WEBHOOK_EVENT_ID_HEADER`
- `PUBLISHLAYER_WEBHOOK_TIMESTAMP_HEADER`
- `PUBLISHLAYER_WEBHOOK_TOLERANCE_SECONDS`
- `PUBLISHLAYER_WEBHOOK_IDEMPOTENCY_TTL_SECONDS`
- `PUBLISHLAYER_HTTP_TIMEOUT_SECONDS`
- `PUBLISHLAYER_HTTP_RETRIES`
- `PUBLISHLAYER_HTTP_RETRY_SLEEP_MS`

## Webhook signature verification

The package verifies webhooks using HMAC SHA-256 with this canonical string:

`{timestamp}.{raw_request_body}`

The computed hex digest is compared against the signature header using `hash_equals`.

## Listening to events

```php
<?php

namespace App\Listeners;

use PublishLayer\LaravelConnector\Events\DraftReady;

class HandleDraftReady
{
    public function handle(DraftReady $event): void
    {
        $payload = $event->payload;

        // Process the draft payload.
    }
}
```

Available events:

- `PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived`
- `PublishLayer\LaravelConnector\Events\DraftReady`
- `PublishLayer\LaravelConnector\Events\RevisionReady`
- `PublishLayer\LaravelConnector\Events\PublishRequested`

## Local path repository development

In a Laravel application `composer.json`, use a path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../laravel-connector"
    }
  ]
}
```

Then require the package normally.
