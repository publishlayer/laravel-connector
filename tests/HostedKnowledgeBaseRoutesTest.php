<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Support\Facades\Route;

class HostedKnowledgeBaseRoutesTest extends TestCase
{
    public function test_hosted_view_routes_are_registered(): void
    {
        self::assertTrue(Route::has('publishlayer.knowledge.index'));
        self::assertTrue(Route::has('publishlayer.knowledge.category'));
        self::assertTrue(Route::has('publishlayer.knowledge.markdown'));
        self::assertTrue(Route::has('publishlayer.knowledge.show'));
        self::assertTrue(Route::has('publishlayer.discovery.llms'));
        self::assertTrue(Route::has('publishlayer.discovery.llms-full'));
        self::assertTrue(Route::has('publishlayer.api.sync'));
        self::assertTrue(Route::has('publishlayer.api.webhook'));

        self::assertSame('/llms.txt', route('publishlayer.discovery.llms', absolute: false));
        self::assertSame('/llms-full.txt', route('publishlayer.discovery.llms-full', absolute: false));
        self::assertSame('/knowledge', route('publishlayer.knowledge.index', absolute: false));
        self::assertSame('/knowledge/categories/networking', route('publishlayer.knowledge.category', ['slug' => 'networking'], false));
        self::assertSame('/knowledge/getting-started.md', route('publishlayer.knowledge.markdown', ['slug' => 'getting-started'], false));
        self::assertSame('/knowledge/getting-started', route('publishlayer.knowledge.show', ['slug' => 'getting-started'], false));
        self::assertSame('/api/publishlayer/sync', route('publishlayer.api.sync', absolute: false));
        self::assertSame('/api/publishlayer/webhook', route('publishlayer.api.webhook', absolute: false));
    }
}
