<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use PublishLayer\LaravelConnector\PublishLayerConnectorServiceProvider;
use PublishLayer\LaravelConnector\PublishLayerServiceProvider;

class PackageBootstrappingTest extends TestCase
{
    public function test_service_provider_loads(): void
    {
        self::assertTrue($this->app->providerIsLoaded(PublishLayerConnectorServiceProvider::class));
        self::assertTrue(is_subclass_of(PublishLayerServiceProvider::class, PublishLayerConnectorServiceProvider::class));
    }

    public function test_publishlayer_config_is_merged(): void
    {
        self::assertTrue((bool) config('publishlayer.enabled'));
        self::assertSame('hosted_views', config('publishlayer.mode'));
        self::assertSame('knowledge', config('publishlayer.route_prefix'));
        self::assertSame('api/publishlayer', config('publishlayer.api_prefix'));
    }
}
