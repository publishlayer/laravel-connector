<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Tests;

use Illuminate\Database\QueryException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PublishLayer\LaravelConnector\Services\KnowledgeSyncService;

class KnowledgeSyncRetryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_sync_retries_retryable_database_failures(): void
    {
        config()->set('publishlayer_connector.database.transaction_attempts', 2);
        config()->set('publishlayer_connector.database.retry_sleep_ms', 0);

        $service = Mockery::mock(KnowledgeSyncService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $retryableException = new QueryException(
            'sqlite',
            'insert into "publishlayer_articles"',
            [],
            new \RuntimeException('Deadlock found when trying to get lock')
        );

        $expected = [
            'operation' => KnowledgeSyncService::OPERATION_SYNCED,
            'article' => null,
            'source_id' => 'art_retry',
        ];

        $service->shouldReceive('performSync')
            ->twice()
            ->andReturnUsing(function () use ($retryableException, $expected) {
                static $attempt = 0;

                if ($attempt === 0) {
                    $attempt++;

                    throw $retryableException;
                }

                return $expected;
            });

        $service->shouldReceive('pauseBeforeRetry')->once()->with(0);

        self::assertSame($expected, $service->syncKnowledgeArticle([
            'article' => [
                'id' => 'art_retry',
            ],
        ]));
    }
}
