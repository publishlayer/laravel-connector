<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

class PublishLayerContentMapping extends Model
{
    protected $table = 'publishlayer_content_mappings';

    protected $fillable = [
        'site_key',
        'mapping_type',
        'publishlayer_content_id',
        'publishlayer_draft_id',
        'external_type',
        'external_id',
        'meta',
        'last_synced_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
