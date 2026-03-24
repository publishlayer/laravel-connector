<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Route;

class ConnectorDisabledRoutesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('publishlayer.enabled', false);
    }

    public function test_package_routes_are_not_registered_when_connector_is_disabled(): void
    {
        self::assertFalse(Route::has('publishlayer.api.health'));
        self::assertFalse(Route::has('publishlayer.api.sync'));
        self::assertFalse(Route::has('publishlayer.api.webhook'));
        self::assertFalse(Route::has('publishlayer.knowledge.index'));

        $this->get('/publishlayer/connector/activity')->assertNotFound();
        $this->post('/publishlayer/webhook')->assertNotFound();
        $this->get('/app/content')->assertNotFound();
    }
}
