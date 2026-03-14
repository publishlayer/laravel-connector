<?php

declare(strict_types=1);

namespace PublishLayer\LaravelConnector\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class SchemaState
{
    /**
     * @var array<string, bool>
     */
    private array $tableCache = [];

    /**
     * @var array<string, bool>
     */
    private array $columnCache = [];

    public function __construct(
        private readonly ConnectionResolverInterface $connections,
        private readonly CacheRepository $cache,
    ) {
    }

    public function hasTable(string $table): bool
    {
        $key = $this->tableKey($table);

        if (array_key_exists($key, $this->tableCache)) {
            return $this->tableCache[$key];
        }

        return $this->tableCache[$key] = $this->remember(
            $key,
            fn (): bool => $this->schemaBuilder()->hasTable($table)
        );
    }

    public function hasColumn(string $table, string $column): bool
    {
        if (! $this->hasTable($table)) {
            return false;
        }

        $key = $this->columnKey($table, $column);

        if (array_key_exists($key, $this->columnCache)) {
            return $this->columnCache[$key];
        }

        return $this->columnCache[$key] = $this->remember(
            $key,
            fn (): bool => $this->schemaBuilder()->hasColumn($table, $column)
        );
    }

    /**
     * @param list<string> $tables
     * @param array<string, list<string>> $columns
     */
    public function warm(array $tables = [], array $columns = []): void
    {
        foreach ($tables as $table) {
            $this->hasTable($table);
        }

        foreach ($columns as $table => $columnNames) {
            foreach ($columnNames as $column) {
                $this->hasColumn($table, $column);
            }
        }
    }

    public function flush(): void
    {
        foreach ($this->allKnownCacheKeys() as $key) {
            $this->forgetCacheKey($key);
        }

        $this->forgetIndex();
        $this->tableCache = [];
        $this->columnCache = [];
    }

    private function remember(string $key, callable $resolver): bool
    {
        if (! $this->persistentCacheEnabled()) {
            return (bool) $resolver();
        }

        $cacheKey = $this->cachePrefix() . $key;

        try {
            $cached = $this->cache->get($cacheKey);
            if (is_bool($cached)) {
                return $cached;
            }

            $value = (bool) $resolver();
            $this->cache->put($cacheKey, $value, now()->addSeconds($this->ttlSeconds()));
            $this->rememberCacheKey($key);

            return $value;
        } catch (Throwable $exception) {
            Log::warning('PublishLayer schema state cache failed; falling back to direct schema lookup.', [
                'error' => $exception->getMessage(),
            ]);

            return (bool) $resolver();
        }
    }

    private function forgetCacheKey(string $key): void
    {
        if (! $this->persistentCacheEnabled()) {
            return;
        }

        try {
            $this->cache->forget($this->cachePrefix() . $key);
        } catch (Throwable $exception) {
            Log::warning('PublishLayer schema state cache flush failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function rememberCacheKey(string $key): void
    {
        if (! $this->persistentCacheEnabled()) {
            return;
        }

        try {
            $index = $this->cache->get($this->indexKey());
            $keys = is_array($index) ? $index : [];
            $keys[] = $key;
            $this->cache->put(
                $this->indexKey(),
                array_values(array_unique(array_filter($keys, 'is_string'))),
                now()->addSeconds($this->ttlSeconds())
            );
        } catch (Throwable $exception) {
            Log::warning('PublishLayer schema state cache index update failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    private function allKnownCacheKeys(): array
    {
        $keys = [
            ...array_keys($this->tableCache),
            ...array_keys($this->columnCache),
        ];

        if (! $this->persistentCacheEnabled()) {
            return array_values(array_unique($keys));
        }

        try {
            $cachedKeys = $this->cache->get($this->indexKey());
            if (is_array($cachedKeys)) {
                $keys = [...$keys, ...array_filter($cachedKeys, 'is_string')];
            }
        } catch (Throwable $exception) {
            Log::warning('PublishLayer schema state cache index read failed.', [
                'error' => $exception->getMessage(),
            ]);
        }

        return array_values(array_unique($keys));
    }

    private function forgetIndex(): void
    {
        if (! $this->persistentCacheEnabled()) {
            return;
        }

        try {
            $this->cache->forget($this->indexKey());
        } catch (Throwable $exception) {
            Log::warning('PublishLayer schema state cache index flush failed.', [
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function schemaBuilder(): \Illuminate\Database\Schema\Builder
    {
        return $this->connections->connection()->getSchemaBuilder();
    }

    private function tableKey(string $table): string
    {
        return 'table:' . $this->connectionName() . ':' . $table;
    }

    private function columnKey(string $table, string $column): string
    {
        return 'column:' . $this->connectionName() . ':' . $table . ':' . $column;
    }

    private function connectionName(): string
    {
        return (string) ($this->connections->connection()->getName() ?? config('database.default', 'default'));
    }

    private function persistentCacheEnabled(): bool
    {
        if (app()->runningUnitTests()) {
            return false;
        }

        return (bool) config('publishlayer_connector.schema_cache.enabled', true)
            && $this->ttlSeconds() > 0;
    }

    private function ttlSeconds(): int
    {
        return max(0, (int) config('publishlayer_connector.schema_cache.ttl_seconds', 300));
    }

    private function cachePrefix(): string
    {
        return (string) config('publishlayer_connector.schema_cache.cache_key_prefix', 'publishlayer:schema-state:');
    }

    private function indexKey(): string
    {
        return $this->cachePrefix() . 'index';
    }
}
