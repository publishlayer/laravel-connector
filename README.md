# PublishLayer Laravel Connector

`publishlayer/laravel-connector` lets a Laravel application receive PublishLayer content, store it locally, and either render a hosted knowledge base or expose the synced content to a custom frontend.

It is a package, not a starter app. It ships with:

- authenticated sync and health endpoints
- hosted knowledge base views with publishable Blade templates
- optional headless mode for custom frontends
- canonical Markdown delivery with `llms.txt` discovery endpoints
- signed webhook handling for PublishLayer Inbox flows
- install, diagnostics, heartbeat, and demo-content commands

## Compatibility

- PHP: `^8.2`
- Laravel components: `^11.0|^12.0`
- Testbench coverage in CI:
  - PHP 8.2 + Laravel 11
  - PHP 8.3 + Laravel 11
  - PHP 8.4 + Laravel 12

## Installation

```bash
composer require publishlayer/laravel-connector
php artisan publishlayer:install --publish-config --publish-migrations --publish-views
php artisan migrate
```

For a one-command local setup after publishing files:

```bash
php artisan publishlayer:install --migrate
```

## Quick Start

Add the core connector settings to `.env`:

```env
PUBLISHLAYER_ENABLED=true
PUBLISHLAYER_MODE=hosted_views
PUBLISHLAYER_API_KEY=your-shared-sync-key
PUBLISHLAYER_SITE_ID=your-site-id
PUBLISHLAYER_ROUTE_PREFIX=knowledge
PUBLISHLAYER_API_PREFIX=api/publishlayer
PUBLISHLAYER_LAYOUT=layouts.app
```

Optional sync signing:

```env
PUBLISHLAYER_SYNC_SIGNING_SECRET=your-hmac-secret
```

Optional Markdown and hosted UI settings:

```env
PUBLISHLAYER_MARKDOWN_ENABLED=true
PUBLISHLAYER_MARKDOWN_ACCEPT_NEGOTIATION=true
PUBLISHLAYER_MARKDOWN_CACHE_TTL=300
PUBLISHLAYER_CATEGORY_OVERVIEW_LIMIT=6
PUBLISHLAYER_RELATED_ARTICLES_LIMIT=4
```

Smoke test the install:

```bash
php artisan publishlayer:health-check
php artisan publishlayer:seed-demo-content
```

## Package Modes

### Hosted views

`PUBLISHLAYER_MODE=hosted_views` registers:

- `GET /llms.txt`
- `GET /llms-full.txt`
- `GET /{route_prefix}`
- `GET /{route_prefix}/categories/{slug}`
- `GET /{route_prefix}/{slug}`
- `GET /{route_prefix}/{slug}.md`

The default route prefix is `knowledge`, so the category route becomes `/knowledge/categories/{slug}`.

### Headless

`PUBLISHLAYER_MODE=headless` disables the hosted knowledge base and discovery routes. Sync, health, webhook, activity, heartbeat, and inbox routes stay available while the package is enabled.

## Routes and Endpoints

### Sync API

These routes use the configured API middleware and are always available while `PUBLISHLAYER_ENABLED=true`:

- `GET /api/publishlayer/health`
- `POST /api/publishlayer/sync`
- `POST /api/publishlayer/webhook`

`POST /api/publishlayer/webhook` is a compatibility alias to the same sync controller used by `/sync`.

### Webhooks and connector telemetry

These routes are separate from the sync API:

- `POST /publishlayer/webhook`
- `GET|POST /publishlayer/connector/activity`
- `GET|POST /publishlayer/activity`
- `POST /publishlayer/connector/heartbeat`
- `POST /publishlayer/heartbeat`

The webhook endpoint is protected by `PUBLISHLAYER_WEBHOOK_SECRET` and is used for inbox-style events such as `draft.ready`.

### Inbox routes

Inbox routes are enabled by default and use the `publishlayer_inbox.php` config:

- Portal: `GET /app/content`
- Draft detail: `GET /app/content/{draft}`
- Approve draft: `POST /app/content/{draft}/approve`
- Publish draft: `POST /app/content/{draft}/publish`
- Public draft page: `GET /content/{slug}`

## Configuration

The package publishes three config files:

- `config/publishlayer.php`
- `config/publishlayer_connector.php`
- `config/publishlayer_inbox.php`

### Core connector settings

Use `config/publishlayer.php` for:

- package enablement
- hosted vs headless mode
- sync authentication
- route prefixes
- hosted view layout
- Markdown delivery
- pagination, labels, SEO, categories, and related articles

### PublishLayer API client settings

Use `config/publishlayer_connector.php` for:

- `PUBLISHLAYER_BASE_URL`
- `PUBLISHLAYER_WORKSPACE_ID`
- `PUBLISHLAYER_CLIENT_SITE_ID`
- `PUBLISHLAYER_SITE_KEY`
- `PUBLISHLAYER_TIMEOUT`
- `PUBLISHLAYER_CONNECTOR_PUBLIC_URL`
- `PUBLISHLAYER_WEBHOOK_SECRET`
- webhook header and queue settings
- image download behavior
- schema cache behavior

Legacy `PL_CONNECTOR_*` environment variables are still accepted for existing installs, but new public installs should use the `PUBLISHLAYER_*` names.

### Inbox settings

Use `config/publishlayer_inbox.php` for:

- `PUBLISHLAYER_INBOX_ENABLED`
- `PUBLISHLAYER_INBOX_PORTAL_PREFIX`
- `PUBLISHLAYER_INBOX_PORTAL_MIDDLEWARE`
- `PUBLISHLAYER_INBOX_PUBLIC_PATH`
- `PUBLISHLAYER_INBOX_IMAGE_PATH_PREFIX`

`PUBLISHLAYER_INBOX_ENABLED` is the global default. Per-site overrides can still be stored in `publishlayer_settings`.

## Authentication Model

### Sync and health endpoints

The sync middleware accepts any of the following:

- `X-PublishLayer-Key: {api key}`
- the configured `PUBLISHLAYER_AUTH_HEADER`
- `Authorization: Bearer {api key}`
- HMAC signatures using `PUBLISHLAYER_SYNC_SIGNING_SECRET`

If `PUBLISHLAYER_SITE_ID` is set, incoming `site_id` values must match exactly.

### Signed webhook endpoint

`POST /publishlayer/webhook` requires:

- `X-PublishLayer-Timestamp`
- `X-PublishLayer-Signature`
- a body signature generated from `{timestamp}.{raw_body}` with `PUBLISHLAYER_WEBHOOK_SECRET`

## Markdown Support

When `publishlayer.markdown.enabled` is on:

- `GET /knowledge/{slug}.md` returns canonical Markdown
- `GET /knowledge/{slug}` can return Markdown when `Accept: text/markdown`
- `/llms.txt` and `/llms-full.txt` expose discovery documents

Markdown is only served for locally published articles. The package fetches site-scoped Markdown from PublishLayer, caches it locally, and serves stale cache entries when the upstream API is temporarily unavailable.

## Sync Payload

Minimal working sync payload:

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
- `reference`

The sync service also accepts optional category and related-article payloads.

## Minimal Working Example

```bash
composer require publishlayer/laravel-connector
php artisan publishlayer:install --publish-config --publish-migrations --publish-views
php artisan migrate
php artisan publishlayer:seed-demo-content
php artisan publishlayer:health-check
```

Then open `/knowledge` in hosted mode or start sending signed sync requests to `/api/publishlayer/sync`.

## Typical Integration Flow

1. Install the package and run migrations.
2. Set `PUBLISHLAYER_API_KEY` and `PUBLISHLAYER_SITE_ID`.
3. Choose `hosted_views` or `headless`.
4. If you use inbox-style webhooks, set `PUBLISHLAYER_WEBHOOK_SECRET`.
5. Configure PublishLayer to send article sync payloads to `/api/publishlayer/sync`.
6. Optionally configure signed draft-ready webhooks at `/publishlayer/webhook`.
7. Run `php artisan publishlayer:health-check` to verify the install.

## View Overrides

Publish the package views:

```bash
php artisan vendor:publish --tag=publishlayer-connector-views
```

Override them under:

- `resources/views/vendor/publishlayer/knowledge`
- `resources/views/vendor/publishlayer/inbox`

The hosted knowledge templates extend `config('publishlayer.layout', 'layouts.app')`.

## Commands

- `php artisan publishlayer:install`
- `php artisan publishlayer:health-check`
- `php artisan publishlayer:seed-demo-content`
- `php artisan publishlayer:doctor`
- `php artisan publishlayer:webhooks:register`
- `php artisan publishlayer:heartbeat`
- `php artisan pl-inbox:toggle {siteKey} {on|off}`

## Troubleshooting

- `PublishLayer sync authentication is not configured.`
  Set `PUBLISHLAYER_API_KEY` or `PUBLISHLAYER_SYNC_SIGNING_SECRET`.
- `The provided site_id [...] does not match the configured PublishLayer site [...]`
  Align the inbound `site_id` with `PUBLISHLAYER_SITE_ID`.
- `PublishLayer webhook signing secret is not configured.`
  Set `PUBLISHLAYER_WEBHOOK_SECRET` before accepting signed webhooks.
- Hosted routes return `404`
  Confirm `PUBLISHLAYER_ENABLED=true` and `PUBLISHLAYER_MODE=hosted_views`.
- Markdown route returns `404`
  Confirm the article is locally published and `PUBLISHLAYER_MARKDOWN_ENABLED=true`.

## Upgrading

This is the first public release line. Future upgrade notes and breaking changes will be recorded in this README and in `CHANGELOG.md`.

## Contributing

See `CONTRIBUTING.md` for local setup and pull request expectations.

## License

MIT. See `LICENSE`.
