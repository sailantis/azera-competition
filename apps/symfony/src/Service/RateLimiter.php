<?php

declare(strict_types=1);

/**
 * Rate limiter — cache-backed fixed-window counter (Symfony-side).
 *
 * Mirrors azera's RateLimiter and Spiral's PSR-16 implementation: a fixed
 * time-window counter stored in the app cache (array adapter), TTL equal
 * to the window size.
 */

namespace App\Symfony\Service;

use Psr\Cache\CacheItemPoolInterface;

final class RateLimiter
{
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
    )
    {
    }

    /**
     * Record a hit for `$key` and report whether the limit is exceeded.
     */
    public function limit(string $key, int $max, int $perSeconds): bool
    {
        $cacheKey = $this->cacheKey($key);

        $item    = $this->cache->getItem($cacheKey);
        $current = $item->isHit() ? (int) $item->get() : 0;

        if ($current >= $max) {
            return false;
        }

        $item->set($current + 1);
        $item->expiresAfter($perSeconds);
        $this->cache->save($item);

        return true;
    }

    /**
     * Get the number of hits recorded for `$key` within the current window.
     */
    public function hits(string $key): int
    {
        $item = $this->cache->getItem($this->cacheKey($key));
        return $item->isHit() ? (int) $item->get() : 0;
    }

    /**
     * Check whether `$key` has reached its limit without recording a hit.
     */
    public function isLimited(string $key, int $max): bool
    {
        return $this->hits($key) >= $max;
    }

    private function cacheKey(string $key): string
    {
        return 'rate_limiter_' . \str_replace([':', '/', '\\', '@', '{', '}', '(', ')'], '_', $key);
    }
}