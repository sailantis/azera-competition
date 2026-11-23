<?php

declare(strict_types=1);

namespace App\Phalcon\Support;

use Phalcon\Cache\CacheInterface;

/**
 * Fixed-window rate limiter over Phalcon's cache, mirroring azera's contract.
 *
 * Phalcon has no rate-limiter component, so — exactly like the CodeIgniter and
 * CakePHP entrants — this is a thin fixed-window facade built on the cache
 * primitive the framework does ship. Same key shape and same `limit()`/`hits()`
 * pair as the other two, so the JSON payloads are interchangeable.
 *
 * The window is a bucket number, not a stored timestamp: `floor(now / window)`
 * changes at a fixed boundary, which is what makes it a FIXED window rather
 * than a sliding one.
 */
final class RateLimiter
{
    public function __construct(private readonly CacheInterface $cache) {}

    public function limit(string $key, int $max, int $windowSeconds): bool
    {
        $bucket   = (string) intdiv((int) (microtime(true) * 1000), $windowSeconds * 1000);
        $cacheKey = self::safeKey($key . '-' . $bucket);

        $hits = $this->cache->get($cacheKey);
        $hits = is_numeric($hits) ? (int) $hits : 0;

        if ($hits >= $max) {
            return false;
        }

        // TTL is a full window past the current one so the counter outlives
        // its own bucket boundary without being stored forever.
        $this->cache->set($cacheKey, $hits + 1, $windowSeconds * 2);

        return true;
    }

    public function hits(string $key): int
    {
        $bucket   = (string) intdiv((int) (microtime(true) * 1000), 60_000);
        $cacheKey = self::safeKey($key . '-' . $bucket);

        $hits = $this->cache->get($cacheKey);

        return is_numeric($hits) ? (int) $hits : 0;
    }

    /** Cache keys must not carry characters the adapter rejects. */
    private static function safeKey(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_\-.]/', '-', $key) ?? 'rate-key';
    }
}
