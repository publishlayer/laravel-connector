<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

class PublishLayerSetting extends Model
{
    protected $table = 'publishlayer_settings';

    protected $fillable = [
        'site_key',
        'setting_key',
        'setting_value',
    ];

    protected $casts = [
        'setting_value' => 'array',
    ];
}
