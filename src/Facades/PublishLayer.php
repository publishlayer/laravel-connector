<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static bool ping()
 * @method static array health()
 * @method static array createBrief(array $payload)
 * @method static array createDraft(array $payload)
 * @method static array listSites()
 */
class PublishLayer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'publishlayer';
    }
}
