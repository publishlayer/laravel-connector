<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PublishLayer\LaravelConnector\Exceptions\ImageDownloadException;

class ImageDownloadService
{
    private const MIME_TO_EXT = [
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
    ];

    /**
     * Download an image from a URL and store it locally.
     *
     * @return array{path: string, size: int, mime: string}
     *
     * @throws ImageDownloadException
     */
    public function download(string $url, string $storagePath, string $filename): array
    {
        $this->validateUrl($url);

        $timeout = (int) config('publishlayer_connector.images.download_timeout_seconds', 30);
        $maxSizeBytes = (int) config('publishlayer_connector.images.max_size_mb', 12) * 1024 * 1024;
        $disk = (string) config('publishlayer_connector.images.disk', 'public');

        try {
            $response = Http::timeout($timeout)
                ->withOptions([
                    'stream' => true,
                    'verify' => app()->environment('local') ? false : true,
                ])
                ->get($url);

            if ($response->failed()) {
                $status = $response->status();

                // 4xx errors (except 429) are permanent failures
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    throw ImageDownloadException::permanentFailure(
                        sprintf('HTTP %d: Failed to download image from %s', $status, $url)
                    );
                }

                // 5xx and 429 are transient
                throw ImageDownloadException::transientFailure(
                    sprintf('HTTP %d: Temporary failure downloading image from %s', $status, $url)
                );
            }

            // Validate content type
            $contentType = $response->header('Content-Type');
            $mime = $this->parseMimeType($contentType);

            if (! $this->isAllowedMimeType($mime)) {
                throw ImageDownloadException::permanentFailure(
                    sprintf('Invalid content type: %s for URL %s', $mime, $url)
                );
            }

            // Get body as string (streamed)
            $body = $response->body();
            $size = strlen($body);

            if ($size === 0) {
                throw ImageDownloadException::permanentFailure('Downloaded image is empty.');
            }

            if ($size > $maxSizeBytes) {
                throw ImageDownloadException::permanentFailure(
                    sprintf(
                        'Image size %d bytes exceeds maximum allowed %d bytes.',
                        $size,
                        $maxSizeBytes
                    )
                );
            }

            // Determine extension
            $extension = $this->resolveExtension($mime, $url);
            $fullFilename = $filename . '.' . $extension;
            $fullPath = rtrim($storagePath, '/') . '/' . $fullFilename;

            // Store the file
            Storage::disk($disk)->put($fullPath, $body);

            return [
                'path' => $fullPath,
                'size' => $size,
                'mime' => $mime,
            ];
        } catch (ImageDownloadException $e) {
            throw $e;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            throw ImageDownloadException::transientFailure(
                'Connection error: ' . $e->getMessage()
            );
        } catch (\Throwable $e) {
            throw ImageDownloadException::transientFailure(
                'Unexpected error: ' . $e->getMessage()
            );
        }
    }

    /**
     * Check if an image already exists at the given path with content.
     */
    public function exists(string $path): bool
    {
        $disk = (string) config('publishlayer_connector.images.disk', 'public');

        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $size = Storage::disk($disk)->size($path);

        return $size > 0;
    }

    /**
     * Delete an image at the given path.
     */
    public function delete(string $path): bool
    {
        $disk = (string) config('publishlayer_connector.images.disk', 'public');

        return Storage::disk($disk)->delete($path);
    }

    private function validateUrl(string $url): void
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw ImageDownloadException::permanentFailure(
                sprintf('Invalid URL: %s', $url)
            );
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw ImageDownloadException::permanentFailure(
                sprintf('URL must use http or https scheme: %s', $url)
            );
        }
    }

    private function parseMimeType(?string $contentType): string
    {
        if (empty($contentType)) {
            return 'application/octet-stream';
        }

        // Handle "image/jpeg; charset=utf-8" style headers
        $parts = explode(';', $contentType);

        return strtolower(trim($parts[0]));
    }

    private function isAllowedMimeType(string $mime): bool
    {
        $allowed = (array) config('publishlayer_connector.images.allowed_mime_types', [
            'image/webp',
            'image/jpeg',
            'image/png',
            'image/gif',
        ]);

        return in_array($mime, $allowed, true);
    }

    private function resolveExtension(string $mime, string $url): string
    {
        // First try mime type mapping
        if (isset(self::MIME_TO_EXT[$mime])) {
            return self::MIME_TO_EXT[$mime];
        }

        // Fallback to URL extension
        $path = parse_url($url, PHP_URL_PATH);
        if ($path !== null && $path !== false) {
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($extension, ['webp', 'jpg', 'jpeg', 'png', 'gif'], true)) {
                return $extension === 'jpeg' ? 'jpg' : $extension;
            }
        }

        // Default to webp
        return 'webp';
    }
}
