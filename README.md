# PublishLayer Laravel Connector

`publishlayer/laravel-connector` gives a Laravel site a local PublishLayer knowledge base renderer and sync receiver.

It is opinionated by default:
- local database storage for synced articles and categories
- hosted knowledge base routes and Blade views
- authenticated sync and health endpoints
- headless mode support
- publishable config, migrations, and overridable views

## Installation

```bash
composer require publishlayer/laravel-connector:^0.1
php artisan publishlayer:install --publish-config --publish-migrations --publish-views
php artisan migrate
```

Set these values in `.env`:

```env
PUBLISHLAYER_ENABLED=true
PUBLISHLAYER_MODE=hosted_views
PUBLISHLAYER_API_KEY=your-shared-api-key
PUBLISHLAYER_SITE_ID=your-site-id
PUBLISHLAYER_ROUTE_PREFIX=knowledge
PUBLISHLAYER_API_PREFIX=api/publishlayer
PUBLISHLAYER_LAYOUT=layouts.app
```

Optional:

```env
PUBLISHLAYER_SYNC_SIGNING_SECRET=shared-hmac-secret
PUBLISHLAYER_ENABLE_CATEGORIES=true
PUBLISHLAYER_ENABLE_RELATED_ARTICLES=true
PUBLISHLAYER_AUTO_PUBLISH=false
```

Seed demo content if you want an immediate smoke test:

```bash
php artisan publishlayer:seed-demo-content
```

## Commands

- `php artisan publishlayer:install`
- `php artisan publishlayer:health-check`
- `php artisan publishlayer:seed-demo-content`

Useful options:

```bash
php artisan publishlayer:install --publish-config --publish-views --publish-migrations --seed-demo
```

## Modes

### `hosted_views`

- registers public knowledge base routes
- renders default Blade templates under the `publishlayer::` namespace
- keeps the host application's layout, header, footer, and branding

### `headless`

- disables the hosted knowledge base routes
- keeps sync and health endpoints active
- still stores content locally for custom API or frontend rendering

## Routes

Default hosted routes in `hosted_views` mode:

- `GET /knowledge`
- `GET /knowledge/categorie/{slug}`
- `GET /knowledge/{slug}`

Default API routes whenever the connector is enabled:

- `GET /api/publishlayer/health`
- `POST /api/publishlayer/sync`
- `POST /api/publishlayer/webhook`

All route names are prefixed with `publishlayer.`.

## Sync payload

The connector accepts knowledge article payloads from PublishLayer:

```json
{
  "type": "knowledge_article",
  "site_id": "client-site-id",
  "article": {
    "id": "article-uuid",
    "title": "Example title",
    "slug": "example-title",
    "summary": "Short summary",
    "content_html": "<p>Trusted synced HTML</p>",
    "status": "published"
  }
}
```

Supported article statuses:

- `published`
- `draft`
- `archived`
- `unpublished`
- `deleted`

`deleted` removes the local article record idempotently. `unpublished` is stored as an archived article and no longer appears on hosted public routes.

## Webhook auth

The connector accepts either:

- `X-PublishLayer-Key: {api key}`
- `Authorization: Bearer {api key}`
- HMAC request signing with `PUBLISHLAYER_SYNC_SIGNING_SECRET`

If `PUBLISHLAYER_SITE_ID` is configured, incoming `site_id` values must match it exactly.

## View overrides

Publish the default views:

```bash
php artisan vendor:publish --tag=publishlayer-connector-views
```

Then override them in:

- `resources/views/vendor/publishlayer/knowledge/index.blade.php`
- `resources/views/vendor/publishlayer/knowledge/category.blade.php`
- `resources/views/vendor/publishlayer/knowledge/show.blade.php`

Default views extend:

```php
config('publishlayer.layout', 'layouts.app')
```

## Troubleshooting

Run:

```bash
php artisan publishlayer:health-check
```

Common issues:

- `FAIL app_key`: set `APP_KEY` and run `php artisan key:generate` if needed
- `FAIL database.tables`: publish migrations and run `php artisan migrate`
- `FAIL route.sync`: clear route cache and confirm `PUBLISHLAYER_ENABLED=true`
- `FAIL site_id`: set `PUBLISHLAYER_SITE_ID` to the exact value configured in PublishLayer
- `401 Unauthorized PublishLayer sync request`: verify the shared API key or HMAC secret
- `422 The provided site_id does not match`: confirm the destination site identifier matches the connector config

## Health endpoint

The platform can test the connector with:

```http
GET /api/publishlayer/health
X-PublishLayer-Key: your-shared-api-key
X-PublishLayer-Site: your-site-id
```

The response includes config, route, database, and latest sync log checks.
