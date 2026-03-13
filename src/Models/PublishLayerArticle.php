<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PublishLayerArticle extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_REFERENCE = 'reference';

    protected $table = 'publishlayer_articles';

    protected $fillable = [
        'source_publishlayer_id',
        'title',
        'slug',
        'summary',
        'content_html',
        'seo_title',
        'seo_description',
        'featured_image_url',
        'status',
        'category_id',
        'published_at',
        'source_updated_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'source_updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PublishLayerCategory::class, 'category_id');
    }

    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'publishlayer_article_relations',
            'article_id',
            'related_article_id'
        )->withPivot(['relation_type', 'sort_order'])->withTimestamps()->orderByPivot('sort_order');
    }

    public function relatedToArticles(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'publishlayer_article_relations',
            'related_article_id',
            'article_id'
        )->withPivot(['relation_type', 'sort_order'])->withTimestamps();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
