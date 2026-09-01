<?php

declare(strict_types=1);

/**
 * Fixed-window rate limiter mirroring azera's RateLimiter, built on
 * Cake's cache layer (ArrayEngine pool).  Max 5 requests per 60 seconds
 * per key — same contract as the azera/Spiral/CI4 demos.
 */

namespace App\Cake\Support;

use Cake\Cache\Cache;
use Psr\SimpleCache\CacheInterface;

final class RateLimiter
{
    public function limit(string $key, int $max, int $windowSeconds): bool
    {
        $bucket   = (string) intdiv((int) (microtime(true) * 1000), $windowSeconds * 1000);
        $cacheKey = $this->safeKey($key . '-' . $bucket);

        $pool = $this->pool();
        $hits = $pool->get($cacheKey);
        $hits = is_numeric($hits) ? (int) $hits : 0;

        if ($hits >= $max) {
            return false;
        }

        $pool->set($cacheKey, $hits + 1, $windowSeconds * 2);

        return true;
    }

    public function hits(string $key): int
    {
        $bucket   = (string) intdiv((int) (microtime(true) * 1000), 60_000);
        $cacheKey = $this->safeKey($key . '-' . $bucket);

        $hits = $this->pool()->get($cacheKey);

        return is_numeric($hits) ? (int) $hits : 0;
    }

    private function pool(): CacheInterface
    {
        return Cache::pool('bench');
    }

    private function safeKey(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_\-\.]/', '-', $key) ?? 'rate-key';
    }
}