# PublishLayer Laravel Connector

A Composer installable Laravel package for receiving PublishLayer webhooks and making API calls.

## Install

### VCS / tagged release

```bash
composer require publishlayer/laravel-connector:^0.1
```

### Local path development

In your Laravel app `composer.json`:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../publishlayer-laravel-connector",
      "options": {
        "symlink": true,
        "versions": {
          "publishlayer/laravel-connector": "0.1.0"
        }
      }
    }
  ]
}
```

Then:

```bash
composer require publishlayer/laravel-connector:^0.1
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
- `PUBLISHLAYER_TIMEOUT`
- `PUBLISHLAYER_WEBHOOK_PATH`
- `PUBLISHLAYER_WEBHOOK_SECRET`
- `PUBLISHLAYER_WEBHOOK_SIGNATURE_HEADER`
- `PUBLISHLAYER_WEBHOOK_EVENT_ID_HEADER`
- `PUBLISHLAYER_WEBHOOK_TIMESTAMP_HEADER`
- `PUBLISHLAYER_WEBHOOK_TOLERANCE_SECONDS`
- `PUBLISHLAYER_WEBHOOK_IDEMPOTENCY_TTL_SECONDS`
- `PUBLISHLAYER_HTTP_TIMEOUT_SECONDS` (legacy fallback)
- `PUBLISHLAYER_HTTP_RETRIES`
- `PUBLISHLAYER_HTTP_RETRY_SLEEP_MS`

## Usage

```php
use PublishLayer\LaravelConnector\Client\PublishLayerClient;

Route::get('/publishlayer-health', function (PublishLayerClient $client) {
    return $client->health();
});
```

Facade usage:

```php
use PublishLayer\LaravelConnector\Facades\PublishLayer;

$draft = PublishLayer::createDraft([
    'title' => 'My draft',
]);
```

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
