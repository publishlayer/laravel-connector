<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;
use PublishLayer\LaravelConnector\Services\KnowledgeSyncService;
use PublishLayer\LaravelConnector\Services\SyncLogService;
use Throwable;

class SyncController extends Controller
{
    public function store(
        Request $request,
        KnowledgeSyncService $knowledgeSyncService,
        SyncLogService $syncLogService,
    ): JsonResponse {
        $payload = $request->all();
        $payloadHash = hash('sha256', $request->getContent());
        $articleStatus = strtolower(trim((string) data_get($payload, 'article.status', 'published')));
        $isDeleteOperation = $articleStatus === 'deleted';

        $validator = Validator::make($payload, [
            'type' => ['required', 'string', 'in:knowledge_article'],
            'site_id' => ['required', 'string', 'max:255'],
            'article' => ['required', 'array'],
            'article.id' => ['required_without:article.source_publishlayer_id', 'nullable', 'string', 'max:255'],
            'article.source_publishlayer_id' => ['required_without:article.id', 'nullable', 'string', 'max:255'],
            'article.title' => [Rule::requiredIf(! $isDeleteOperation), 'nullable', 'string', 'max:255'],
            'article.slug' => [Rule::requiredIf(! $isDeleteOperation), 'nullable', 'string', 'max:255'],
            'article.summary' => ['nullable', 'string'],
            'article.content_html' => [Rule::requiredIf(! $isDeleteOperation), 'nullable', 'string'],
            'article.seo_title' => ['nullable', 'string', 'max:255'],
            'article.seo_description' => ['nullable', 'string'],
            'article.featured_image_url' => ['nullable', 'url', 'max:2048'],
            'article.status' => ['required', 'string', Rule::in(['published', 'draft', 'archived', 'unpublished', 'deleted', 'reference'])],
            'article.published_at' => ['nullable', 'date'],
            'article.source_updated_at' => ['nullable', 'date'],
            'article.category' => ['nullable', 'array'],
            'article.category.id' => ['nullable', 'string', 'max:255'],
            'article.category.name' => ['required_with:article.category', 'nullable', 'string', 'max:255'],
            'article.category.slug' => ['required_with:article.category', 'nullable', 'string', 'max:255'],
            'article.category.description' => ['nullable', 'string'],
            'article.related_articles' => ['nullable', 'array'],
            'article.related_articles.*.source_publishlayer_id' => ['required_without:article.related_articles.*.id', 'nullable', 'string', 'max:255'],
            'article.related_articles.*.id' => ['required_without:article.related_articles.*.source_publishlayer_id', 'nullable', 'string', 'max:255'],
            'article.related_articles.*.slug' => ['required', 'string', 'max:255'],
            'article.related_articles.*.title' => ['required', 'string', 'max:255'],
            'article.related_articles.*.relation_type' => ['nullable', 'string', 'max:64'],
        ], [
            'article.title.required' => 'A title is required unless the article status is deleted.',
            'article.slug.required' => 'A slug is required unless the article status is deleted.',
            'article.content_html.required' => 'Trusted HTML content is required unless the article status is deleted.',
            'article.status.in' => 'The article status must be one of published, draft, archived, unpublished, deleted, or reference.',
        ]);

        $configuredSiteId = trim((string) config('publishlayer.site_id', ''));
        $payloadSiteId = is_scalar($request->input('site_id')) ? trim((string) $request->input('site_id')) : '';

        if ($configuredSiteId !== '' && ($payloadSiteId === '' || ! hash_equals($configuredSiteId, $payloadSiteId))) {
            $validator->errors()->add('site_id', 'The provided site_id does not match the configured PublishLayer site.');
        }

        $sourceId = $this->resolveSourceId($payload);

        if ($validator->fails()) {
            $syncLogService->record([
                'source' => 'sync_endpoint',
                'source_id' => $sourceId,
                'event_type' => (string) $request->input('type', 'unknown'),
                'status' => PublishLayerSyncLog::STATUS_REJECTED,
                'message' => $validator->errors()->first(),
                'payload_hash' => $payloadHash,
                'synced_at' => null,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $result = $knowledgeSyncService->syncKnowledgeArticle($payload);
            $article = $result['article'];
            $operation = $result['operation'];
            $message = match ($operation) {
                KnowledgeSyncService::OPERATION_DELETED => 'Knowledge article deleted successfully.',
                KnowledgeSyncService::OPERATION_ARCHIVED => 'Knowledge article archived successfully.',
                default => 'Knowledge article synced successfully.',
            };

            $syncLogService->record([
                'source' => 'sync_endpoint',
                'source_id' => (string) $result['source_id'],
                'event_type' => (string) $request->input('type'),
                'status' => PublishLayerSyncLog::STATUS_SUCCESS,
                'message' => $message,
                'payload_hash' => $payloadHash,
                'synced_at' => now(),
            ]);

            Log::info('PublishLayer knowledge article sync processed.', [
                'source_publishlayer_id' => $result['source_id'],
                'slug' => $article?->slug ?? data_get($payload, 'article.slug'),
                'status' => $article?->status ?? $articleStatus,
                'operation' => $operation,
            ]);

            return response()->json([
                'ok' => true,
                'message' => $message,
                'operation' => $operation,
                'article' => [
                    'id' => $article?->id,
                    'source_publishlayer_id' => $result['source_id'],
                    'slug' => $article?->slug ?? data_get($payload, 'article.slug'),
                    'status' => $article?->status ?? $articleStatus,
                ],
            ]);
        } catch (Throwable $exception) {
            $syncLogService->record([
                'source' => 'sync_endpoint',
                'source_id' => $sourceId,
                'event_type' => (string) $request->input('type', 'unknown'),
                'status' => PublishLayerSyncLog::STATUS_FAILED,
                'message' => $exception->getMessage(),
                'payload_hash' => $payloadHash,
                'synced_at' => null,
            ]);

            Log::error('PublishLayer knowledge sync failed.', [
                'source_publishlayer_id' => $sourceId,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Knowledge article sync failed.',
                'error' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveSourceId(array $payload): ?string
    {
        $article = $payload['article'] ?? null;

        if (! is_array($article)) {
            return null;
        }

        $sourceId = $article['source_publishlayer_id'] ?? $article['id'] ?? null;

        return is_scalar($sourceId) && trim((string) $sourceId) !== '' ? trim((string) $sourceId) : null;
    }
}
