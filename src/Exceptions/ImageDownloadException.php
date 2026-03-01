<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Exceptions;

use Exception;

class ImageDownloadException extends Exception
{
    private bool $isTransient;

    public function __construct(string $message, bool $isTransient = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->isTransient = $isTransient;
    }

    /**
     * Create an exception for transient failures (retry-able).
     */
    public static function transientFailure(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, true, $previous);
    }

    /**
     * Create an exception for permanent failures (do not retry).
     */
    public static function permanentFailure(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, false, $previous);
    }

    /**
     * Whether this failure is transient and should be retried.
     */
    public function isTransient(): bool
    {
        return $this->isTransient;
    }

    /**
     * Whether this failure is permanent and should not be retried.
     */
    public function isPermanent(): bool
    {
        return ! $this->isTransient;
    }
}
