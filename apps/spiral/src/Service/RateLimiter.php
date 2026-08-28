<?php

declare(strict_types=1);

/**
 * Cache-backed rate limiter — Spiral-side implementation of the
 * rate-limiter feature race.
 *
 * Mirrors azera's {@see \Azera\Security\RateLimiter}: a fixed time-window
 * counter stored in the framework's PSR-16 cache, TTL equal to the window
 * size. Spiral's CacheBootloader binds
 * {@see \Spiral\Cache\CacheStorageProviderInterface}; `storage('array')`
 * returns Spiral's array PSR-16 pool in the benchmark app.
 */

namespace App\Spiral\Service;

use Spiral\Cache\CacheStorageProviderInterface;

final class RateLimiter
{
    public function __construct(
        private readonly CacheStorageProviderInterface $cache,
    ) {}

    /**
     * Record a hit for `$key` and report whether the limit is exceeded.
     */
    public function limit(string $key, int $max, int $perSeconds): bool
    {
        $cacheKey = $this->cacheKey($key);

        $current = $this->cache->storage('array')->get($cacheKey, 0);

        if (!is_int($current)) {
            $current = 0;
        }

        if ($current >= $max) {
            return false;
        }

        $this->cache->storage('array')->set($cacheKey, $current + 1, $perSeconds);

        return true;
    }

    /**
     * Get the number of hits recorded for `$key` within the current window.
     */
    public function hits(string $key): int
    {
        $value = $this->cache->storage('array')->get($this->cacheKey($key), 0);
        return is_int($value) ? $value : 0;
    }

    /**
     * Check whether `$key` has reached its limit without recording a hit.
     */
    public function isLimited(string $key, int $max): bool
    {
        return $this->hits($key) >= $max;
    }

    /**
     * Reset the counter for `$key`.
     */
    public function reset(string $key): void
    {
        $this->cache->storage('array')->delete($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $key);
        return 'rate_limit.' . $sanitized;
    }
}