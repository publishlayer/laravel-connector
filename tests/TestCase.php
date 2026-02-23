<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PublishLayer\LaravelConnector\PublishLayerServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PublishLayerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('publishlayer.webhooks.signing_secret', 'test-secret');
        $app['config']->set('publishlayer.connections.default.base_url', 'https://api.publishlayer.com');
        $app['config']->set('publishlayer.connections.default.api_key', 'test-api-key');
        $app['config']->set('publishlayer.connections.default.workspace_id', 'workspace-test');
        $app['config']->set('publishlayer.webhooks.path', 'publishlayer/webhook');
    }
}
