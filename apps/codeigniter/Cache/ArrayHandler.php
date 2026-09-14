<?php

declare(strict_types=1);

/**
 * CI4 benchmark app — in-memory array cache handler.
 *
 * CI4 4.7 ships no volatile handler (DummyHandler is a no-op, every other
 * handler is persistent: file/apcu/memcached/redis/wincache). The benchmark
 * app needs the same process-lifetime semantics the other five frameworks
 * use for their cache demos (azera's ArrayCache, Symfony ArrayAdapter,
 * Laravel array store, ...):
 *
 *   - warm/worker mode: entries survive between requests (the service
 *     singleton is preserved by BaseService::resetForWorkerMode — 'cache'
 *     is in Config\WorkerMode::$persistentServices), so the #[Cache]-style
 *     demo hits after the first call;
 *   - cold mode: every fresh boot (forked child) starts with an EMPTY
 *     store, so the 50 ms simulated cache-miss is paid again — exactly
 *     what every other framework pays.
 *
 * With the previous file handler the on-disk store survived re-boots, so
 * only iteration 1 of a cold block paid the miss — a parity hole that
 * made CI4's cold /features/cache + /features/db-events rows ~17x faster
 * than the field's (and halved its cold total).
 */

namespace Ci4App\Cache;

use CodeIgniter\Cache\Handlers\BaseHandler;
use Config\Cache;

final class ArrayHandler extends BaseHandler
{
    /**
     * In-memory store: key => ['time' => int, 'ttl' => int, 'data' => mixed].
     *
     * @var array<string, array{time: int, ttl: int, data: mixed}>
     */
    private array $store = [];

    public function __construct(?Cache $config = null)
    {
        $this->prefix = $config->prefix ?? '';
    }

    public function initialize(): void {}

    public function get(string $key): mixed
    {
        $key = static::validateKey($key, $this->prefix);

        if (!isset($this->store[$key])) {
            return null;
        }

        $item = $this->store[$key];

        // ttl 0 = no expiry (CI4 convention).
        if ($item['ttl'] > 0 && (time() > $item['time'] + $item['ttl'])) {
            unset($this->store[$key]);

            return null;
        }

        return $item['data'];
    }

    public function save(string $key, mixed $value, int $ttl = 60): bool
    {
        $key = static::validateKey($key, $this->prefix);

        $this->store[$key] = [
            'time' => time(),
            'ttl'  => $ttl,
            'data' => $value,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        $key = static::validateKey($key, $this->prefix);
        unset($this->store[$key]);

        return true;
    }

    public function deleteMatching(string $pattern): int
    {
        $deleted = 0;
        foreach (array_keys($this->store) as $key) {
            if (@fnmatch($pattern, $key)) {
                unset($this->store[$key]);
                $deleted++;
            }
        }

        return $deleted;
    }

    public function increment(string $key, int $offset = 1): bool|int
    {
        $key = static::validateKey($key, $this->prefix);

        if (!isset($this->store[$key]) || !is_numeric($this->store[$key]['data'])) {
            return false;
        }

        $this->store[$key]['data'] += $offset;

        return $this->store[$key]['data'];
    }

    public function decrement(string $key, int $offset = 1): bool|int
    {
        $key = static::validateKey($key, $this->prefix);

        if (!isset($this->store[$key]) || !is_numeric($this->store[$key]['data'])) {
            return false;
        }

        $this->store[$key]['data'] -= $offset;

        return $this->store[$key]['data'];
    }

    public function clean(): bool
    {
        $this->store = [];

        return true;
    }

    public function getCacheInfo(): array|false|object|null
    {
        return array_map(
            static fn(array $item): array =>
                ['expires' => $item['ttl'] === 0 ? 0 : $item['time'] + $item['ttl']],
            $this->store,
        );
    }

    public function getMetaData(string $key): ?array
    {
        $key = static::validateKey($key, $this->prefix);

        if (!isset($this->store[$key])) {
            return null;
        }

        $item = $this->store[$key];

        return [
            'expire' => $item['ttl'] === 0 ? null : $item['time'] + $item['ttl'],
            'time'   => $item['time'],
            'ttl'    => $item['ttl'],
        ];
    }

    public function isSupported(): bool
    {
        return true;
    }
}