<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\ServiceProvider;
use PublishLayer\LaravelConnector\PublishLayerConnectorServiceProvider;
use PublishLayer\LaravelConnector\PublishLayerServiceProvider;
use PublishLayer\LaravelConnector\Contracts\PublishLayerClientContract;

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

    public function test_publish_groups_are_registered(): void
    {
        self::assertNotSame([], ServiceProvider::pathsToPublish(null, 'publishlayer-connector-config'));
        self::assertNotSame([], ServiceProvider::pathsToPublish(null, 'publishlayer-connector-views'));
        self::assertNotSame([], ServiceProvider::pathsToPublish(null, 'publishlayer-connector-migrations'));
    }

    public function test_publishlayer_alias_resolves_to_the_client_contract(): void
    {
        self::assertSame(
            $this->app->make(PublishLayerClientContract::class),
            $this->app->make('publishlayer')
        );
    }
}
