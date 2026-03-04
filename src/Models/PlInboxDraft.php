<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $site_key
 * @property string $pl_draft_id
 * @property string|null $pl_brief_id
 * @property string|null $title
 * @property string|null $slug
 * @property string|null $body_markdown
 * @property string|null $body_html
 * @property string|null $excerpt
 * @property string|null $featured_image_url
 * @property string|null $featured_image_path
 * @property string $status
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class PlInboxDraft extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';

    protected $table = 'pl_inbox_drafts';

    protected $fillable = [
        'site_key',
        'pl_draft_id',
        'pl_brief_id',
        'title',
        'slug',
        'body_markdown',
        'body_html',
        'excerpt',
        'featured_image_url',
        'featured_image_path',
        'status',
        'payload',
        'published_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'published_at' => 'datetime',
    ];

    public function publicUrl(): ?string
    {
        if ($this->status !== self::STATUS_PUBLISHED || ! is_string($this->slug) || trim($this->slug) === '') {
            return null;
        }

        $pathTemplate = trim((string) config('publishlayer_inbox.public.path', 'content/{slug}'), '/');
        $path = str_replace('{slug}', $this->slug, $pathTemplate);

        return url('/' . ltrim($path, '/'));
    }
}
