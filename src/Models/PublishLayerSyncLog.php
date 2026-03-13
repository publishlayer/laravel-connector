<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

class PublishLayerSyncLog extends Model
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REJECTED = 'rejected';

    protected $table = 'publishlayer_sync_logs';

    protected $fillable = [
        'source',
        'source_id',
        'event_type',
        'status',
        'message',
        'payload_hash',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];
}
