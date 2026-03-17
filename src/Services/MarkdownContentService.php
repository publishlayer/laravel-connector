<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;
use PublishLayer\LaravelConnector\Exceptions\PublishLayerRequestException;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use Throwable;

class MarkdownContentService
{
    public function __construct(
        private readonly PublishLayerClientContract $client,
        private readonly CacheRepository $cache,
    ) {
    }

    public function markdownEnabled(): bool
    {
        return (bool) config('publishlayer.markdown.enabled', true);
    }

    public function acceptNegotiationEnabled(): bool
    {
        return (bool) config('publishlayer.markdown.accept_negotiation', true);
    }

    public function requestPrefersMarkdown(Request $request): bool
    {
        return str_contains(strtolower((string) $request->header('Accept', '')), 'text/markdown');
    }

    public function buildResponse(PublishLayerArticle $article): Response
    {
        $payload = $this->resolvePayload($article);
        $body = (string) ($payload['rendered_markdown'] ?? '');

        abort_if($body === '', 503, 'PublishLayer Markdown is currently unavailable.');

        $response = response($body, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-PublishLayer-Markdown-Cache' => (string) ($payload['_cache_state'] ?? 'miss'),
        ]);

        $checksum = trim((string) ($payload['markdown_checksum'] ?? ''));
        if ($checksum !== '') {
            $response->headers->set('ETag', '"' . preg_replace('/[^A-Za-z0-9:_\-]/', '', $checksum) . '"');
        }

        $generatedAt = $payload['markdown_generated_at'] ?? $payload['updated_at'] ?? null;
        if (is_string($generatedAt) && strtotime($generatedAt) !== false) {
            $response->setLastModified(Carbon::parse($generatedAt));
        }

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePayload(PublishLayerArticle $article): array
    {
        $freshKey = $this->freshCacheKey($article);
        $staleKey = $this->staleCacheKey($article);

        /** @var array<string, mixed>|null $fresh */
        $fresh = $this->cache->get($freshKey);
        if (is_array($fresh) && ($fresh['rendered_markdown'] ?? '') !== '') {
            $fresh['_cache_state'] = 'fresh';

            return $fresh;
        }

        /** @var array<string, mixed>|null $stale */
        $stale = $this->cache->get($staleKey);

        try {
            $remote = $this->client->getContentMarkdown(
                $this->configuredSiteId(),
                (string) $article->source_publishlayer_id
            );
            $payload = $this->normalizeRemotePayload($article, $remote);

            if ($payload === []) {
                if (is_array($stale) && ($stale['rendered_markdown'] ?? '') !== '') {
                    $stale['_cache_state'] = 'stale';

                    return $stale;
                }

                abort(404);
            }

            if (is_array($stale) && ($stale['markdown_checksum'] ?? null) === ($payload['markdown_checksum'] ?? null)) {
                $payload = array_merge($stale, $payload);
            }

            $payload['_cache_state'] = 'miss';
            $this->storePayload($freshKey, $staleKey, $payload);

            return $payload;
        } catch (PublishLayerRequestException $exception) {
            if ($exception->statusCode === 404) {
                abort(404);
            }

            if (is_array($stale) && ($stale['rendered_markdown'] ?? '') !== '') {
                $stale['_cache_state'] = 'stale';

                return $stale;
            }

            throw $exception;
        } catch (Throwable $exception) {
            if (is_array($stale) && ($stale['rendered_markdown'] ?? '') !== '') {
                $stale['_cache_state'] = 'stale';

                return $stale;
            }

            throw $exception;
        }
    }

    private function configuredSiteId(): string
    {
        $siteId = trim((string) config('publishlayer.site_id', ''));
        if ($siteId === '') {
            $siteId = trim((string) config('publishlayer_connector.client_site_id', ''));
        }

        if ($siteId === '') {
            abort(503, 'PublishLayer site_id is not configured for Markdown delivery.');
        }

        return $siteId;
    }

    /**
     * @param array<string, mixed> $remote
     * @return array<string, mixed>
     */
    private function normalizeRemotePayload(PublishLayerArticle $article, array $remote): array
    {
        $status = strtolower(trim((string) ($remote['status'] ?? 'published')));
        if (in_array($status, ['draft', 'private', 'internal', 'archived', 'ineligible'], true)) {
            return [];
        }

        $markdown = trim((string) ($remote['rendered_markdown'] ?? ''));
        if ($markdown === '') {
            return [];
        }

        return [
            'content_id' => (string) ($remote['content_id'] ?? $article->source_publishlayer_id),
            'slug' => (string) ($remote['slug'] ?? $article->slug),
            'locale' => (string) ($remote['locale'] ?? 'en'),
            'status' => (string) ($remote['status'] ?? $article->status),
            'rendered_markdown' => $markdown,
            'rendered_html' => (string) ($remote['rendered_html'] ?? $article->content_html),
            'markdown_checksum' => (string) ($remote['markdown_checksum'] ?? ''),
            'markdown_generated_at' => $remote['markdown_generated_at'] ?? null,
            'canonical_url' => (string) ($remote['canonical_url'] ?? ''),
            'updated_at' => $remote['updated_at'] ?? optional($article->source_updated_at ?: $article->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function storePayload(string $freshKey, string $staleKey, array $payload): void
    {
        $ttl = max(0, (int) config('publishlayer.markdown.cache_ttl', 300));

        if ($ttl > 0) {
            $this->cache->put($freshKey, $payload, now()->addSeconds($ttl));
        } else {
            $this->cache->forget($freshKey);
        }

        $this->cache->forever($staleKey, $payload);
    }

    private function freshCacheKey(PublishLayerArticle $article): string
    {
        return 'publishlayer:markdown:fresh:' . $article->getKey() . ':' . sha1((string) $article->source_publishlayer_id);
    }

    private function staleCacheKey(PublishLayerArticle $article): string
    {
        return 'publishlayer:markdown:stale:' . $article->getKey() . ':' . sha1((string) $article->source_publishlayer_id);
    }
}
