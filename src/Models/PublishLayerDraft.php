<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $pl_draft_id
 * @property string|null $pl_content_id
 * @property string|null $pl_brief_id
 * @property string|null $external_key
 * @property string|null $title
 * @property string|null $output_type
 * @property string|null $content_html
 * @property array<string, mixed>|null $meta
 * @property array<int, mixed>|null $links
 * @property string|null $featured_image_path
 * @property string|null $featured_image_url
 * @property string|null $featured_image_original_url
 * @property string|null $featured_image_version
 * @property string|null $og_image_path
 * @property string|null $og_image_url
 * @property string|null $og_image_original_url
 * @property string $status
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class PublishLayerDraft extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READY = 'ready';
    public const STATUS_PROCESSED = 'processed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'publishlayer_drafts';

    protected $fillable = [
        'pl_draft_id',
        'pl_content_id',
        'pl_brief_id',
        'external_key',
        'title',
        'output_type',
        'content_html',
        'meta',
        'links',
        'featured_image_path',
        'featured_image_url',
        'featured_image_original_url',
        'featured_image_version',
        'og_image_path',
        'og_image_url',
        'og_image_original_url',
        'status',
        'last_error',
        'received_at',
        'processed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'links' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the full URL to the locally stored featured image.
     */
    public function getFeaturedImageLocalUrl(): ?string
    {
        if (empty($this->featured_image_path)) {
            return null;
        }

        $disk = config('publishlayer_connector.images.disk', 'public');

        return Storage::disk($disk)->url($this->featured_image_path);
    }

    /**
     * Get the full URL to the locally stored OG image.
     */
    public function getOgImageLocalUrl(): ?string
    {
        if (empty($this->og_image_path)) {
            return null;
        }

        $disk = config('publishlayer_connector.images.disk', 'public');

        return Storage::disk($disk)->url($this->og_image_path);
    }

    /**
     * Check if featured image needs to be re-downloaded based on URL change.
     */
    public function needsFeaturedImageDownload(?string $newUrl): bool
    {
        if (empty($newUrl)) {
            return false;
        }

        // No image stored yet
        if (empty($this->featured_image_path)) {
            return true;
        }

        // URL changed
        if ($this->featured_image_original_url !== $newUrl) {
            return true;
        }

        // Check version parameter
        $newVersion = $this->extractVersionFromUrl($newUrl);
        if ($newVersion !== null && $newVersion !== $this->featured_image_version) {
            return true;
        }

        return false;
    }

    /**
     * Check if OG image needs to be re-downloaded based on URL change.
     */
    public function needsOgImageDownload(?string $newUrl): bool
    {
        if (empty($newUrl)) {
            return false;
        }

        if (empty($this->og_image_path)) {
            return true;
        }

        if ($this->og_image_original_url !== $newUrl) {
            return true;
        }

        return false;
    }

    /**
     * Extract version parameter from URL.
     */
    private function extractVersionFromUrl(string $url): ?string
    {
        $parsed = parse_url($url);
        if (empty($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $query);

        return isset($query['v']) && is_scalar($query['v']) ? (string) $query['v'] : null;
    }

    /**
     * Update from webhook payload.
     *
     * @param array<string, mixed> $payload
     */
    public static function upsertFromPayload(array $payload): self
    {
        $draftId = $payload['pl_draft_id'] ?? $payload['draft_id'] ?? $payload['id'] ?? null;

        if (empty($draftId)) {
            throw new \InvalidArgumentException('Missing draft ID in payload.');
        }

        return self::updateOrCreate(
            ['pl_draft_id' => (string) $draftId],
            [
                'pl_content_id' => $payload['content_id'] ?? null,
                'pl_brief_id' => $payload['brief_id'] ?? null,
                'external_key' => $payload['external_key'] ?? null,
                'title' => isset($payload['title']) ? mb_substr((string) $payload['title'], 0, 512) : null,
                'output_type' => $payload['output_type'] ?? null,
                'content_html' => $payload['content_html'] ?? null,
                'meta' => $payload['meta'] ?? null,
                'links' => $payload['links'] ?? null,
                'status' => self::STATUS_PENDING,
                'received_at' => now(),
            ]
        );
    }
}
