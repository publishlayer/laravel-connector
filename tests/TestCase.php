<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PublishLayer\LaravelConnector\PublishLayerConnectorServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            PublishLayerConnectorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('view.paths', [__DIR__ . '/fixtures/views']);
        $app['config']->set('publishlayer.enabled', true);
        $app['config']->set('publishlayer.mode', 'hosted_views');
        $app['config']->set('publishlayer.api_key', 'test-sync-key');
        $app['config']->set('publishlayer.route_prefix', 'knowledge');
        $app['config']->set('publishlayer.api_prefix', 'api/publishlayer');
        $app['config']->set('publishlayer.layout', 'layouts.app');
        $app['config']->set('publishlayer.site_id', 'site-test');
        $app['config']->set('publishlayer.web_middleware', ['web']);
        $app['config']->set('publishlayer.api_middleware', ['api']);
        $app['config']->set('publishlayer_connector.webhooks.signing_secret', 'test-secret');
        $app['config']->set('publishlayer_connector.connections.default.base_url', 'https://api.publishlayer.com');
        $app['config']->set('publishlayer_connector.connections.default.api_key', 'test-api-key');
        $app['config']->set('publishlayer_connector.connections.default.workspace_id', 'workspace-test');
        $app['config']->set('publishlayer_connector.webhooks.path', 'publishlayer/webhook');
        $app['config']->set('publishlayer_inbox.enabled', true);
        $app['config']->set('publishlayer_inbox.portal.middleware', []);
        $app['config']->set('queue.default', 'sync');
    }
}
