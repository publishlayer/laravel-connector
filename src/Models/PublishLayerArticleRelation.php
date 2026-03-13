<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishLayerArticleRelation extends Model
{
    protected $table = 'publishlayer_article_relations';

    protected $fillable = [
        'article_id',
        'related_article_id',
        'relation_type',
        'sort_order',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(PublishLayerArticle::class, 'article_id');
    }

    public function relatedArticle(): BelongsTo
    {
        return $this->belongsTo(PublishLayerArticle::class, 'related_article_id');
    }
}
