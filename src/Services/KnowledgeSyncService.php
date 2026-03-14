<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PublishLayer\LaravelConnector\Models\PublishLayerArticle;
use PublishLayer\LaravelConnector\Models\PublishLayerArticleRelation;
use PublishLayer\LaravelConnector\Models\PublishLayerCategory;

class KnowledgeSyncService
{
    public const OPERATION_SYNCED = 'synced';
    public const OPERATION_ARCHIVED = 'archived';
    public const OPERATION_DELETED = 'deleted';

    /**
     * @param  array<string, mixed>  $payload
     * @return array{operation:string,article:?PublishLayerArticle,source_id:string}
     */
    public function syncKnowledgeArticle(array $payload): array
    {
        /** @var array<string, mixed> $articlePayload */
        $articlePayload = $payload['article'];
        $sourceId = $this->resolveSourceId($articlePayload);

        if ($sourceId === null) {
            throw new \InvalidArgumentException('The article payload is missing a source PublishLayer identifier.');
        }

        return $this->runTransactionWithRetry(
            fn (): array => $this->performSync($articlePayload, $sourceId),
            $sourceId
        );
    }

    /**
     * @param array<string, mixed> $articlePayload
     * @return array{operation:string,article:?PublishLayerArticle,source_id:string}
     */
    protected function performSync(array $articlePayload, string $sourceId): array
    {
        $operation = $this->resolveOperation($articlePayload);

        if ($operation === self::OPERATION_DELETED) {
            $this->deleteArticle($sourceId, $this->normalizeString($articlePayload['slug'] ?? null));

            return [
                'operation' => self::OPERATION_DELETED,
                'article' => null,
                'source_id' => $sourceId,
            ];
        }

        $category = $this->resolveCategory($articlePayload['category'] ?? null);
        $article = $this->upsertArticle($articlePayload, $category?->id);

        if ((bool) config('publishlayer.enable_related_articles', true)) {
            $this->syncRelatedArticles($article, $articlePayload['related_articles'] ?? []);
        }

        return [
            'operation' => $operation,
            'article' => $article->load(['category', 'relatedArticles']),
            'source_id' => $sourceId,
        ];
    }

    /**
     * @param callable():array{operation:string,article:?PublishLayerArticle,source_id:string} $callback
     * @return array{operation:string,article:?PublishLayerArticle,source_id:string}
     */
    protected function runTransactionWithRetry(callable $callback, string $sourceId): array
    {
        $attempts = max(1, (int) config('publishlayer_connector.database.transaction_attempts', 3));
        $sleepMs = max(0, (int) config('publishlayer_connector.database.retry_sleep_ms', 150));

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return DB::transaction($callback, 1);
            } catch (QueryException $exception) {
                if (! $this->shouldRetryDatabaseException($exception) || $attempt >= $attempts) {
                    throw $exception;
                }

                Log::warning('PublishLayer knowledge sync transaction failed and will be retried.', [
                    'source_publishlayer_id' => $sourceId,
                    'attempt' => $attempt,
                    'max_attempts' => $attempts,
                    'retry_sleep_ms' => $sleepMs,
                    'error' => $exception->getMessage(),
                ]);

                $this->pauseBeforeRetry($sleepMs);
            }
        }

        throw new \RuntimeException('PublishLayer knowledge sync retry loop exited unexpectedly.');
    }

    protected function pauseBeforeRetry(int $sleepMs): void
    {
        if ($sleepMs > 0) {
            usleep($sleepMs * 1000);
        }
    }

    protected function shouldRetryDatabaseException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach ([
            'deadlock',
            'database is locked',
            'lock wait timeout',
            'try restarting transaction',
            'server has gone away',
            'lost connection',
            'no connection to the server',
            'connection refused',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  mixed  $categoryPayload
     */
    private function resolveCategory(mixed $categoryPayload): ?PublishLayerCategory
    {
        if (! (bool) config('publishlayer.enable_categories', true) || ! is_array($categoryPayload)) {
            return null;
        }

        $sourceId = $this->normalizeString($categoryPayload['id'] ?? null);
        $slug = $this->normalizeString($categoryPayload['slug'] ?? null);

        if ($slug === null) {
            return null;
        }

        $category = PublishLayerCategory::query()
            ->where(function ($query) use ($sourceId, $slug): void {
                if ($sourceId !== null) {
                    $query->where('source_publishlayer_id', $sourceId)->orWhere('slug', $slug);

                    return;
                }

                $query->where('slug', $slug);
            })
            ->first();

        $category ??= new PublishLayerCategory();

        $category->fill([
            'source_publishlayer_id' => $sourceId,
            'name' => (string) $categoryPayload['name'],
            'slug' => $slug,
            'description' => $this->normalizeString($categoryPayload['description'] ?? null),
        ]);
        $category->save();

        return $category;
    }

    /**
     * @param  array<string, mixed>  $articlePayload
     */
    private function upsertArticle(array $articlePayload, ?int $categoryId): PublishLayerArticle
    {
        $sourceId = $this->resolveSourceId($articlePayload);

        if ($sourceId === null) {
            throw new \InvalidArgumentException('The article payload is missing a source PublishLayer identifier.');
        }

        return PublishLayerArticle::query()->updateOrCreate([
            'source_publishlayer_id' => $sourceId,
        ], [
            'title' => (string) $articlePayload['title'],
            'slug' => (string) $articlePayload['slug'],
            'summary' => $this->normalizeString($articlePayload['summary'] ?? null),
            'content_html' => (string) $articlePayload['content_html'],
            'seo_title' => $this->normalizeString($articlePayload['seo_title'] ?? null),
            'seo_description' => $this->normalizeString($articlePayload['seo_description'] ?? null),
            'featured_image_url' => $this->normalizeString($articlePayload['featured_image_url'] ?? null),
            'status' => $this->normalizeStatus($articlePayload['status'] ?? null),
            'category_id' => $categoryId,
            'published_at' => $this->parseTimestamp($articlePayload['published_at'] ?? null),
            'source_updated_at' => $this->parseTimestamp($articlePayload['source_updated_at'] ?? null),
        ]);
    }

    /**
     * @param  array<int, mixed>  $relatedArticles
     */
    private function syncRelatedArticles(PublishLayerArticle $article, array $relatedArticles): void
    {
        PublishLayerArticleRelation::query()
            ->where('article_id', $article->id)
            ->delete();

        foreach (array_values($relatedArticles) as $index => $relatedArticlePayload) {
            if (! is_array($relatedArticlePayload)) {
                continue;
            }

            $relatedArticle = $this->upsertRelatedArticleReference($relatedArticlePayload);

            if ($relatedArticle === null || $relatedArticle->is($article)) {
                continue;
            }

            PublishLayerArticleRelation::query()->updateOrCreate([
                'article_id' => $article->id,
                'related_article_id' => $relatedArticle->id,
                'relation_type' => $this->normalizeString($relatedArticlePayload['relation_type'] ?? null),
            ], [
                'sort_order' => $index,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $relatedArticlePayload
     */
    private function upsertRelatedArticleReference(array $relatedArticlePayload): ?PublishLayerArticle
    {
        $sourceId = $this->normalizeString($relatedArticlePayload['source_publishlayer_id'] ?? $relatedArticlePayload['id'] ?? null);
        $slug = $this->normalizeString($relatedArticlePayload['slug'] ?? null);

        if ($sourceId === null || $slug === null) {
            return null;
        }

        $relatedArticle = PublishLayerArticle::query()
            ->where('source_publishlayer_id', $sourceId)
            ->orWhere('slug', $slug)
            ->first();

        $relatedArticle ??= new PublishLayerArticle();
        $relatedArticle->fill([
            'source_publishlayer_id' => $sourceId,
            'title' => (string) $relatedArticlePayload['title'],
            'slug' => $slug,
            'summary' => null,
            'content_html' => '',
            'seo_title' => null,
            'seo_description' => null,
            'featured_image_url' => null,
            'status' => PublishLayerArticle::STATUS_REFERENCE,
            'category_id' => null,
            'published_at' => null,
            'source_updated_at' => null,
        ]);
        $relatedArticle->save();

        return $relatedArticle;
    }

    /**
     * @param  array<string, mixed>  $articlePayload
     */
    private function resolveSourceId(array $articlePayload): ?string
    {
        return $this->normalizeString($articlePayload['source_publishlayer_id'] ?? $articlePayload['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $articlePayload
     */
    private function resolveOperation(array $articlePayload): string
    {
        $status = $this->normalizeStatus($articlePayload['status'] ?? null);

        return match ($status) {
            'deleted' => self::OPERATION_DELETED,
            PublishLayerArticle::STATUS_ARCHIVED, 'unpublished' => self::OPERATION_ARCHIVED,
            default => self::OPERATION_SYNCED,
        };
    }

    private function deleteArticle(string $sourceId, ?string $slug): void
    {
        $query = PublishLayerArticle::query()->where('source_publishlayer_id', $sourceId);

        if ($slug !== null) {
            $query->orWhere('slug', $slug);
        }

        $article = $query->first();

        if (! $article) {
            return;
        }

        PublishLayerArticleRelation::query()
            ->where('article_id', $article->id)
            ->orWhere('related_article_id', $article->id)
            ->delete();

        $article->delete();
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeStatus(mixed $status): string
    {
        $normalized = $this->normalizeString($status);

        if ($normalized === null) {
            return PublishLayerArticle::STATUS_DRAFT;
        }

        return match ($normalized) {
            'published' => PublishLayerArticle::STATUS_PUBLISHED,
            'archived', 'unpublished' => PublishLayerArticle::STATUS_ARCHIVED,
            'reference' => PublishLayerArticle::STATUS_REFERENCE,
            'deleted' => 'deleted',
            default => $normalized,
        };
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        $normalized = $this->normalizeString($value);

        return $normalized !== null ? Carbon::parse($normalized) : null;
    }
}
