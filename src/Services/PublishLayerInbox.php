<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PublishLayer\LaravelConnector\Models\PlInboxDraft;
use PublishLayer\LaravelConnector\Models\PublishLayerSetting;

class PublishLayerInbox
{
    public const SETTING_KEY = 'pl_inbox_enabled';

    public function __construct(
        private readonly SchemaState $schemaState,
    ) {
    }

    /**
     * @param Model|string|null $clientSite
     */
    public function enabledFor(Model|string|null $clientSite): bool
    {
        $globalDefault = (bool) config('publishlayer_inbox.enabled', true);
        $siteKey = $this->normalizeSiteKey($clientSite);

        $overrides = (array) config('publishlayer_inbox.site_overrides', []);
        if ($siteKey !== null && array_key_exists($siteKey, $overrides)) {
            return (bool) $overrides[$siteKey];
        }

        if ($siteKey === null || ! $this->schemaState->hasTable('publishlayer_settings')) {
            return $globalDefault;
        }

        $setting = PublishLayerSetting::query()
            ->where('site_key', $siteKey)
            ->where('setting_key', self::SETTING_KEY)
            ->first();

        if (! $setting) {
            return $globalDefault;
        }

        return $this->toBoolean($setting->setting_value, $globalDefault);
    }

    public function setEnabledFor(string $siteKey, bool $enabled): void
    {
        $siteKey = $this->normalizeSiteKey($siteKey) ?? 'default';

        PublishLayerSetting::query()->updateOrCreate(
            [
                'site_key' => $siteKey,
                'setting_key' => self::SETTING_KEY,
            ],
            [
                'setting_value' => ['enabled' => $enabled],
            ]
        );
    }

    public function ensureDraftSlug(PlInboxDraft $draft): string
    {
        if (is_string($draft->slug) && trim($draft->slug) !== '') {
            return $draft->slug;
        }

        $title = is_string($draft->title) ? trim($draft->title) : '';
        $baseSlug = Str::slug($title !== '' ? $title : 'draft');
        $base = trim((string) preg_replace('/-+/', '-', mb_substr($baseSlug, 0, 120)), '-');
        if ($base === '') {
            $base = 'draft';
        }

        $hash = substr(hash('sha256', $draft->site_key . '|' . $draft->pl_draft_id), 0, 6);
        $candidate = mb_substr($base . '-' . $hash, 0, 191);
        $i = 2;

        while (PlInboxDraft::query()
            ->where('slug', $candidate)
            ->when($draft->exists, fn ($query) => $query->where('id', '!=', $draft->id))
            ->exists()) {
            $suffix = '-' . $i;
            $candidate = mb_substr($base . '-' . $hash, 0, max(1, 191 - strlen($suffix))) . $suffix;
            $i++;
        }

        $draft->slug = $candidate;

        return $candidate;
    }

    /**
     * @param Model|string|null $clientSite
     */
    private function normalizeSiteKey(Model|string|null $clientSite): ?string
    {
        $candidate = null;

        if (is_string($clientSite)) {
            $candidate = $clientSite;
        } elseif ($clientSite instanceof Model) {
            $candidate = $clientSite->getAttribute('site_key')
                ?? $clientSite->getAttribute('site_token')
                ?? $clientSite->getAttribute('tenant_key');
        }

        if (! is_scalar($candidate)) {
            return null;
        }

        $siteKey = trim((string) $candidate);

        if ($siteKey === '') {
            return null;
        }

        return mb_substr($siteKey, 0, 128);
    }

    private function toBoolean(mixed $value, bool $fallback): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }

            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->toBoolean($decoded, $fallback);
            }
        }

        if (is_array($value)) {
            if (array_key_exists('enabled', $value)) {
                return (bool) $value['enabled'];
            }
        }

        return $fallback;
    }
}
