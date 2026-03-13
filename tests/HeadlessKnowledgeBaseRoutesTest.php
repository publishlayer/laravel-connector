<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Route;

class HeadlessKnowledgeBaseRoutesTest extends TestCase
{
    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('publishlayer.mode', 'headless');
    }

    public function test_web_routes_are_disabled_in_headless_mode(): void
    {
        self::assertFalse(Route::has('publishlayer.knowledge.index'));
        self::assertFalse(Route::has('publishlayer.knowledge.category'));
        self::assertFalse(Route::has('publishlayer.knowledge.show'));
        self::assertTrue(Route::has('publishlayer.api.sync'));
        self::assertTrue(Route::has('publishlayer.api.webhook'));
    }
}
