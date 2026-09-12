<?php

/**
 * AOP demo service — wraps operations in Laravel's Illuminate Pipeline.
 *
 * Laravel has no proxy-based AOP (no annotations / generated classes like
 * Spiral's PipelineBuilder). Its idiomatic interception technique is the
 * Pipeline: invokable interceptor classes composed around a core handler —
 * the same mechanism Laravel uses internally for HTTP middleware. This is
 * the apples-to-apples comparison against azera's and Spiral's pipelines.
 */

namespace App\Laravel\Service;

use App\Laravel\Http\Middleware\Interceptors\LogInterceptor;
use App\Laravel\Http\Middleware\Interceptors\RetryInterceptor;
use Closure;

final class AopService
{
    /** Fixed row used by every feature-demo write — the row count stays stable across benchmark runs. */
    public const FEATURE_SENTINEL_ID = 888800;

    /** @var list<string> */
    private array $entries = [];

    /**
     * Create an item inside an interceptor-wrapped transaction callback.
     *
     * Writes the FIXED feature sentinel row (upsert semantics) instead of
     * a fresh INSERT per request — the demo runs once per benchmark
     * request, and an unbounded INSERT would grow the shared table every
     * request (the row count must stay stable so COUNT-style reads measure
     * constant work).
     *
     * @return array{0: int, 1: list<string>}
     */
    public function createItem(string $title): array
    {
        $this->entries = [];

        $id = $this->retryCall(function () use ($title): int {
            // In a real app the interceptor would manage the transaction;
            // here the write happens inside the wrapped callable.
            app('db')->table('items')->upsert(
                [
                    'id'         => self::FEATURE_SENTINEL_ID,
                    'title'      => $title,
                    'created_at' => \date('Y-m-d H:i:s'),
                ],
                ['id'],
                ['title', 'created_at'],
            );
            return self::FEATURE_SENTINEL_ID;
        });

        return [$id, $this->entries];
    }

    /**
     * Wrap a plain callable with LogInterceptor via the pipeline.
     */
    public function loggedCall(callable $fn): mixed
    {
        $this->entries = [];
        return $this->pipeline(new LogInterceptor($this->entries))->then($fn);
    }

    /**
     * Wrap a plain callable with RetryInterceptor via the pipeline.
     *
     * Like loggedCall(), the entry buffer is reset first: the service is
     * bound for the process lifetime (warm-mode worker), and the retry
     * interceptor appends per-attempt entries — without the reset the
     * buffer (and the response payload) would grow every request.
     */
    public function retryCall(callable $fn): mixed
    {
        $this->entries = [];
        return $this->pipeline(new RetryInterceptor($this->entries, 3, 0))->then($fn);
    }

    /** @return list<string> */
    public function logEntries(): array
    {
        return $this->entries;
    }

    /**
     * Build an explicit interceptor pipeline around a destination callable.
     */
    private function pipeline(LogInterceptor|RetryInterceptor ...$interceptors): \Illuminate\Pipeline\Pipeline
    {
        $pipeline = new \Illuminate\Pipeline\Pipeline(app());

        foreach ($interceptors as $interceptor) {
            $pipeline->through($interceptor);
        }

        return $pipeline;
    }
}