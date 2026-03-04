<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

class PublishLayerConnectorHeartbeat extends Model
{
    protected $table = 'publishlayer_connector_heartbeats';

    protected $fillable = [
        'site_key',
        'last_seen_at',
        'source',
        'meta',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'meta' => 'array',
    ];
}
