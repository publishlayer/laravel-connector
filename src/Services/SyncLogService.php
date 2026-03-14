<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Support\Facades\Log;
use PublishLayer\LaravelConnector\Models\PublishLayerSyncLog;

class SyncLogService
{
    public function __construct(
        private readonly SchemaState $schemaState,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function record(array $attributes): void
    {
        Log::info('PublishLayer sync log entry recorded.', [
            'source' => $attributes['source'] ?? null,
            'source_id' => $attributes['source_id'] ?? null,
            'event_type' => $attributes['event_type'] ?? null,
            'status' => $attributes['status'] ?? null,
        ]);

        if (! $this->schemaState->hasTable('publishlayer_sync_logs')) {
            return;
        }

        PublishLayerSyncLog::query()->create($attributes);
    }
}
