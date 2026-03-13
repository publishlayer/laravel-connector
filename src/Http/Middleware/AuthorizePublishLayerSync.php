<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizePublishLayerSync
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = trim((string) config('publishlayer.api_key', ''));
        $signingSecret = trim((string) config('publishlayer.sync_signing_secret', ''));

        if ($apiKey === '' && $signingSecret === '') {
            throw new HttpException(500, 'PublishLayer sync authentication is not configured.');
        }

        if ($this->hasValidApiKey($request, $apiKey) || $this->hasValidSignature($request, $signingSecret)) {
            return $next($request);
        }

        Log::warning('PublishLayer sync request rejected by authentication middleware.', [
            'path' => $request->path(),
            'site_id' => $request->input('site_id'),
        ]);

        return $this->unauthorized();
    }

    private function hasValidApiKey(Request $request, string $apiKey): bool
    {
        if ($apiKey === '') {
            return false;
        }

        $headerName = (string) config('publishlayer.auth_header', 'X-PublishLayer-Api-Key');
        $provided = $request->header($headerName);
        if (! is_string($provided) || trim($provided) === '') {
            $provided = $request->header('X-PublishLayer-Key');
        }

        if (! is_string($provided) || trim($provided) === '') {
            $bearer = $request->bearerToken();
            $provided = is_string($bearer) ? $bearer : null;
        }

        return is_string($provided) && hash_equals($apiKey, trim($provided));
    }

    private function hasValidSignature(Request $request, string $signingSecret): bool
    {
        if ($signingSecret === '') {
            return false;
        }

        $timestampHeader = (string) config('publishlayer.signature_timestamp_header', 'X-PublishLayer-Timestamp');
        $signatureHeader = (string) config('publishlayer.signature_header', 'X-PublishLayer-Signature');
        $timestamp = $request->header($timestampHeader);
        $signature = $request->header($signatureHeader);

        if (! is_string($timestamp) || ! ctype_digit($timestamp) || ! is_string($signature) || trim($signature) === '') {
            return false;
        }

        $tolerance = (int) config('publishlayer.signature_tolerance_seconds', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', sprintf('%s.%s', $timestamp, $request->getContent()), $signingSecret);

        return hash_equals($expectedSignature, trim($signature));
    }

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Unauthorized PublishLayer sync request.',
        ], 401);
    }
}
