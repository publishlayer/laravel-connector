<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $event_id
 * @property string|null $event_type
 * @property array<string, mixed> $payload
 * @property string $status
 * @property string|null $error
 * @property int $attempts
 * @property \Illuminate\Support\Carbon $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PublishLayerWebhookEvent extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'publishlayer_webhook_events';

    protected $fillable = [
        'event_id',
        'event_type',
        'payload',
        'status',
        'error',
        'attempts',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempts' => $this->attempts + 1,
        ]);
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'processed_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => mb_substr($error, 0, 65535),
        ]);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isProcessed(): bool
    {
        return $this->status === self::STATUS_PROCESSED;
    }

    public function getDraftId(): ?string
    {
        return $this->payload['pl_draft_id']
            ?? $this->payload['draft_id']
            ?? $this->payload['id']
            ?? null;
    }

    public function getContentId(): ?string
    {
        return $this->payload['content_id'] ?? null;
    }

    public function getFeaturedImageUrl(): ?string
    {
        return $this->payload['featured_image_url'] ?? null;
    }

    public function getOgImageUrl(): ?string
    {
        return $this->payload['og_image_url'] ?? null;
    }
}
