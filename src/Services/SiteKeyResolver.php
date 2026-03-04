<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Http\Request;

class SiteKeyResolver
{
    public function resolveForRequest(Request $request): string
    {
        $siteKeyHeader = (string) config('publishlayer_connector.webhooks.site_key_header', 'X-PublishLayer-Site-Key');
        $siteTokenHeader = (string) config('publishlayer_connector.webhooks.site_token_header', 'X-PublishLayer-Site-Token');

        $user = $request->user();

        $candidate = null;
        if (is_object($user)) {
            $candidate = $user->site_key
                ?? $user->site_token
                ?? $user->tenant_key
                ?? null;
        }

        $candidate = $candidate
            ?? $request->input('site_key')
            ?? $request->input('site_token')
            ?? $request->header($siteKeyHeader)
            ?? $request->header($siteTokenHeader)
            ?? config('publishlayer_connector.site_key')
            ?? 'default';

        $siteKey = is_scalar($candidate) ? trim((string) $candidate) : '';

        return $siteKey !== '' ? mb_substr($siteKey, 0, 128) : 'default';
    }
}
