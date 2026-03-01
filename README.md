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

## Publish config and migrations

```bash
php artisan vendor:publish --tag=publishlayer-connector-config
php artisan vendor:publish --tag=publishlayer-connector-migrations
php artisan migrate
```

Or run the install command:

```bash
php artisan publishlayer:install
```

## Environment variables

### Required

- `PUBLISHLAYER_API_KEY` - Your PublishLayer API key
- `PUBLISHLAYER_WORKSPACE_ID` - Your workspace ID
- `PUBLISHLAYER_WEBHOOK_SECRET` - Secret for webhook signature verification

### Optional - API

- `PUBLISHLAYER_BASE_URL` - API base URL (default: `https://api.publishlayer.com`)
- `PUBLISHLAYER_TIMEOUT` - Request timeout in seconds (default: 10)
- `PUBLISHLAYER_HTTP_RETRIES` - Number of retries (default: 2)
- `PUBLISHLAYER_HTTP_RETRY_SLEEP_MS` - Retry sleep in ms (default: 200)

### Optional - Webhooks

- `PUBLISHLAYER_WEBHOOK_PATH` - Webhook endpoint path (default: `publishlayer/webhook`)
- `PUBLISHLAYER_CONNECTOR_PUBLIC_URL` - Your app's public URL for webhook registration
- `PUBLISHLAYER_CLIENT_SITE_ID` - Client site ID for webhook registration
- `PUBLISHLAYER_WEBHOOK_QUEUE` - Queue name for processing jobs (default: `default`)
- `PUBLISHLAYER_WEBHOOK_SIGNATURE_HEADER` - Signature header name (default: `X-PublishLayer-Signature`)
- `PUBLISHLAYER_WEBHOOK_TIMESTAMP_HEADER` - Timestamp header name (default: `X-PublishLayer-Timestamp`)
- `PUBLISHLAYER_WEBHOOK_TOLERANCE_SECONDS` - Signature tolerance (default: 300)
- `PUBLISHLAYER_WEBHOOK_IDEMPOTENCY_TTL_SECONDS` - Event idempotency TTL (default: 86400)

### Optional - Images

- `PUBLISHLAYER_IMAGES_ENABLED` - Enable automatic image download (default: `true`)
- `PUBLISHLAYER_IMAGES_DISK` - Storage disk for images (default: `public`)
- `PUBLISHLAYER_IMAGES_PATH_PREFIX` - Storage path prefix (default: `publishlayer/drafts`)
- `PUBLISHLAYER_IMAGE_MAX_MB` - Max image size in MB (default: 12)
- `PUBLISHLAYER_IMAGE_DOWNLOAD_TIMEOUT` - Download timeout in seconds (default: 30)

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

- `PublishLayer\LaravelConnector\Events\PublishLayerWebhookReceived` - Generic event for all webhooks
- `PublishLayer\LaravelConnector\Events\DraftReady` - Fired when a draft.ready webhook is received
- `PublishLayer\LaravelConnector\Events\DraftImageDownloaded` - Fired after images are downloaded
- `PublishLayer\LaravelConnector\Events\RevisionReady` - Fired when a revision.ready webhook is received
- `PublishLayer\LaravelConnector\Events\PublishRequested` - Fired when a publish.requested webhook is received

## Automatic Image Download

When a `draft.ready` webhook is received, the connector automatically:

1. Persists the webhook event to the database for idempotency
2. Dispatches a queued `ProcessDraftReadyJob`
3. Downloads the featured image and OG image (if provided in the webhook payload)
4. Stores images to the configured storage disk
5. Updates the `publishlayer_drafts` table with image paths
6. Dispatches a `DraftImageDownloaded` event

### Handling downloaded images

```php
<?php

namespace App\Listeners;

use PublishLayer\LaravelConnector\Events\DraftImageDownloaded;

class HandleDraftImages
{
    public function handle(DraftImageDownloaded $event): void
    {
        $draft = $event->draft;

        // Access downloaded image paths
        $featuredImagePath = $draft->featured_image_path; // e.g. "publishlayer/drafts/draft_123/featured.webp"
        $ogImagePath = $draft->og_image_path;

        // Get public URLs
        $featuredUrl = $draft->getFeaturedImageLocalUrl();
        $ogUrl = $draft->getOgImageLocalUrl();

        // Create your blog post with the downloaded images
        Post::create([
            'title' => $draft->title,
            'content' => $draft->content_html,
            'featured_image' => $featuredImagePath,
        ]);
    }
}
```

## Webhook Registration

Register your webhook endpoint with PublishLayer:

```bash
php artisan publishlayer:webhooks:register
```

This requires the following environment variables:

```env
PUBLISHLAYER_API_KEY=your-api-key
PUBLISHLAYER_CLIENT_SITE_ID=your-site-id
PUBLISHLAYER_CONNECTOR_PUBLIC_URL=https://your-app.com
PUBLISHLAYER_WEBHOOK_SECRET=your-secret-or-leave-blank-to-generate
```

Options:
- `--url=` - Override the webhook URL
- `--force` - Re-register even if already registered

## Queue Processing

The image download job runs on a queue. Make sure you have a queue worker running:

```bash
php artisan queue:work --queue=default
```

Or specify a custom queue in your `.env`:

```env
PUBLISHLAYER_WEBHOOK_QUEUE=publishlayer
```

## Database Tables

The package creates two tables:

- `publishlayer_webhook_events` - Stores received webhook events for idempotency and audit
- `publishlayer_drafts` - Stores draft data and downloaded image paths

## Testing

Run the package tests:

```bash
cd publishlayer-laravel-connector
composer install
./vendor/bin/phpunit
```
