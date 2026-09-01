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
    /** @var list<string> */
    private array $entries = [];

    /**
     * Create an item inside an interceptor-wrapped transaction callback.
     *
     * @return array{0: int, 1: list<string>}
     */
    public function createItem(string $title): array
    {
        $this->entries = [];

        $id = $this->retryCall(function () use ($title): int {
            // In a real app the interceptor would manage the transaction;
            // here the write happens inside the wrapped callable.
            $table = app('db')->table('items');
            return (int) $table->insertGetId([
                'title'      => $title,
                'created_at' => \date('Y-m-d H:i:s'),
            ]);
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
     */
    public function retryCall(callable $fn): mixed
    {
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