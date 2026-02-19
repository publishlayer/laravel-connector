<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VerifyPublishLayerSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('publishlayer.webhooks.signing_secret', '');

        if ($secret === '') {
            throw new HttpException(500, 'PublishLayer webhook signing secret is not configured.');
        }

        $timestampHeader = (string) config('publishlayer.webhooks.timestamp_header', 'X-PublishLayer-Timestamp');
        $signatureHeader = (string) config('publishlayer.webhooks.header_name', 'X-PublishLayer-Signature');

        $timestamp = $request->header($timestampHeader);
        $signature = $request->header($signatureHeader);

        if (! is_string($timestamp) || $timestamp === '' || ! is_string($signature) || $signature === '') {
            return $this->unauthorized('invalid_signature');
        }

        if (! ctype_digit($timestamp)) {
            return $this->unauthorized('invalid_signature');
        }

        $tolerance = (int) config('publishlayer.webhooks.tolerance_seconds', 300);

        if (abs(time() - (int) $timestamp) > $tolerance) {
            return $this->unauthorized('invalid_signature');
        }

        $rawBody = $request->getContent();
        $expected = hash_hmac('sha256', sprintf('%s.%s', $timestamp, $rawBody), $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->unauthorized('invalid_signature');
        }

        return $next($request);
    }

    private function unauthorized(string $error): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $error,
        ], 401);
    }
}
