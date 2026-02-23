<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;

class PublishLayerRequestException extends RuntimeException
{
    /**
     * @param array<string, mixed> $responseBody
     */
    public function __construct(
        string $message,
        public readonly int $statusCode,
        public readonly array $responseBody = []
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(Response $response): self
    {
        /** @var mixed $decoded */
        $decoded = $response->json();
        $body = is_array($decoded) ? $decoded : [];

        $message = (string) ($body['message'] ?? $response->reason() ?? 'PublishLayer API request failed.');
        $status = $response->status();

        return new self("PublishLayer API request failed ({$status}): {$message}", $status, $body);
    }
}
