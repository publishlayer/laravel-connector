<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublishLayerCategory extends Model
{
    protected $table = 'publishlayer_categories';

    protected $fillable = [
        'source_publishlayer_id',
        'name',
        'slug',
        'description',
    ];

    public function articles(): HasMany
    {
        return $this->hasMany(PublishLayerArticle::class, 'category_id');
    }
}
