<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Listeners;

use PublishLayer\LaravelConnector\Services\SchemaState;

class FlushSchemaStateCache
{
    public function __construct(
        private readonly SchemaState $schemaState,
    ) {
    }

    public function handle(): void
    {
        $this->schemaState->flush();
    }
}
