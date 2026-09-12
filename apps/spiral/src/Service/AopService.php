<?php

declare(strict_types=1);

/**
 * AOP demo service — wraps operations in Spiral's interceptor pipeline.
 */

namespace App\Spiral\Service;

use App\Spiral\Interceptor\LogInterceptor;
use App\Spiral\Interceptor\RetryInterceptor;
use Spiral\Interceptors\Context\CallContext;
use Spiral\Interceptors\Context\Target;
use Spiral\Interceptors\Handler\CallableHandler;
use Spiral\Interceptors\PipelineBuilder;

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
            \spiral(\Cycle\Database\DatabaseInterface::class)
                ->insert('items')
                ->values([
                    'id'         => self::FEATURE_SENTINEL_ID,
                    'title'      => $title,
                    'created_at' => \date('Y-m-d H:i:s'),
                ])
                ->onConflict('id')
                ->run();
            return self::FEATURE_SENTINEL_ID;
        });

        return [$id, $this->entries];
    }

    /**
     * Wrap a plain callable with LogInterceptor via the pipeline builder.
     */
    public function loggedCall(callable $fn): mixed
    {
        $this->entries = [];
        return $this->pipeline(new LogInterceptor($this->entries))->handle(
            self::context($fn),
        );
    }

    /**
     * Wrap a plain callable with RetryInterceptor via the pipeline builder.
     */
    public function retryCall(callable $fn): mixed
    {
        return $this->pipeline(new RetryInterceptor($this->entries, 3, 0))->handle(
            self::context($fn),
        );
    }

    /**
     * @return list<string>
     */
    public function logEntries(): array
    {
        return $this->entries;
    }

    private function pipeline(LogInterceptor|RetryInterceptor ...$interceptors): \Spiral\Interceptors\HandlerInterface
    {
        return (new PipelineBuilder())
            ->withInterceptors(...$interceptors)
            ->build(new CallableHandler());
    }

    private static function context(callable $fn): CallContext
    {
        return new CallContext(Target::fromClosure($fn(...)));
    }
}