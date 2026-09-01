<?php

declare(strict_types=1);

/**
 * Fixed-window rate limiter mirroring azera's RateLimiter, built on
 * CodeIgniter's cache layer (PSR-16 style CacheInterface via the `array`
 * handler).  Max 5 requests per 60 seconds per key — same contract as the
 * azera/Spiral demos.
 */

namespace Ci4App\Support;

use CodeIgniter\Cache\CacheInterface;

final class RateLimiter
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function limit(string $key, int $max, int $windowSeconds): bool
    {
        $bucket   = (string) intdiv((int) (microtime(true) * 1000), $windowSeconds * 1000);
        $cacheKey = str_replace(['{', '}', ':'], '-', $key . '-' . $bucket);

        $hits = $this->cache->get($cacheKey);
        $hits = is_numeric($hits) ? (int) $hits : 0;

        if ($hits >= $max) {
            return false;
        }

        $this->cache->save($cacheKey, $hits + 1, $windowSeconds * 2);

        return true;
    }

    public function hits(string $key): int
    {
        $windowSeconds = 60;
        $bucket        = (string) intdiv((int) (microtime(true) * 1000), $windowSeconds * 1000);
        $cacheKey      = str_replace(['{', '}', ':'], '-', $key . '-' . $bucket);

        $hits = $this->cache->get($cacheKey);

        return is_numeric($hits) ? (int) $hits : 0;
    }
}