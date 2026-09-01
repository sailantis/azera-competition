<?php

/**
 * Rate limiter — cache-backed fixed-window counter (Laravel-side).
 *
 * Mirrors azera's RateLimiter and Spiral's PSR-16 implementation: a fixed
 * time-window counter stored in Laravel's Cache repository (array store),
 * TTL equal to the window size.
 */

namespace App\Laravel\Service;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

final class RateLimiter
{
    public function __construct(
        private readonly CacheRepository $cache,
    )
    {
    }

    /**
     * Record a hit for `$key` and report whether the limit is exceeded.
     */
    public function limit(string $key, int $max, int $perSeconds): bool
    {
        $cacheKey = $this->cacheKey($key);

        $current = $this->cache->get($cacheKey, 0);

        if (!is_int($current)) {
            $current = 0;
        }

        if ($current >= $max) {
            return false;
        }

        $this->cache->put($cacheKey, $current + 1, $perSeconds);

        return true;
    }

    /**
     * Get the number of hits recorded for `$key` within the current window.
     */
    public function hits(string $key): int
    {
        $value = $this->cache->get($this->cacheKey($key), 0);
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
        $this->cache->forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        $sanitized = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $key);
        return 'rate_limit.' . $sanitized;
    }
}