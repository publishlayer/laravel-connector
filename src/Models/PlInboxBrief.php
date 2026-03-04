<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $site_key
 * @property string $pl_brief_id
 * @property string|null $title
 * @property string $status
 * @property array<string, mixed>|null $brief_payload
 * @property \Illuminate\Support\Carbon|null $received_at
 */
class PlInboxBrief extends Model
{
    protected $table = 'pl_inbox_briefs';

    protected $fillable = [
        'site_key',
        'pl_brief_id',
        'title',
        'status',
        'brief_payload',
        'received_at',
    ];

    protected $casts = [
        'brief_payload' => 'array',
        'received_at' => 'datetime',
    ];
}
