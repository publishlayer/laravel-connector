<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLayerDelivery extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_IGNORED = 'ignored';

    protected $table = 'publishlayer_deliveries';

    protected $fillable = [
        'webhook_event_id',
        'site_key',
        'action',
        'status',
        'payload',
        'result',
        'error',
        'attempted_at',
        'completed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'result' => 'array',
        'attempted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(PublishLayerWebhookEvent::class, 'webhook_event_id');
    }

    /**
     * @param array<string, mixed>|null $result
     */
    public function markProcessed(?array $result = null): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSED,
            'result' => $result,
            'error' => null,
            'completed_at' => now(),
        ]);
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'attempted_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => mb_substr($error, 0, 65535),
            'completed_at' => now(),
        ]);
    }

    /**
     * @param array<string, mixed>|null $result
     */
    public function markIgnored(?array $result = null): void
    {
        $this->update([
            'status' => self::STATUS_IGNORED,
            'result' => $result,
            'completed_at' => now(),
        ]);
    }
}
