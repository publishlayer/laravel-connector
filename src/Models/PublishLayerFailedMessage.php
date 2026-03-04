<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLayerFailedMessage extends Model
{
    protected $table = 'publishlayer_failed_messages';

    protected $fillable = [
        'webhook_event_id',
        'delivery_id',
        'site_key',
        'event_id',
        'error_class',
        'error_message',
        'payload',
        'context',
        'failed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'context' => 'array',
        'failed_at' => 'datetime',
    ];

    public function webhookEvent(): BelongsTo
    {
        return $this->belongsTo(PublishLayerWebhookEvent::class, 'webhook_event_id');
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(PublishLayerDelivery::class, 'delivery_id');
    }
}
