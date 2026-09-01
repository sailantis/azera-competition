<?php

declare(strict_types=1);

/**
 * AOP demo service — wraps operations in an interceptor pipeline.
 *
 * Symfony has no proxy-based AOP (no annotations / generated classes like
 * Spiral's PipelineBuilder). Its idiomatic interception technique is a
 * pipeline of invokable interceptor classes composed around a core handler —
 * the same mechanism Symfony uses internally for HTTP middleware. This is
 * the apples-to-apples comparison against azera's, Spiral's and Laravel's
 * pipelines.
 */

namespace App\Symfony\Service;

use App\Symfony\Service\Interceptor\LogInterceptor;
use App\Symfony\Service\Interceptor\RetryInterceptor;
use Doctrine\DBAL\Connection;

final class AopService
{
    /** @var list<string> */
    private array $entries = [];

    public function __construct(
        private readonly Connection $connection,
    )
    {
    }

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
            $this->connection->insert('items', [
                'title'      => $title,
                'created_at' => \date('Y-m-d H:i:s'),
            ]);

            return (int) $this->connection->lastInsertId();
        });

        return [$id, $this->entries];
    }

    /**
     * Wrap a plain callable with LogInterceptor via the pipeline.
     */
    public function loggedCall(callable $fn): mixed
    {
        $this->entries = [];
        return $this->pipeline(new LogInterceptor($this->entries))($fn);
    }

    /**
     * Wrap a plain callable with RetryInterceptor via the pipeline.
     */
    public function retryCall(callable $fn): mixed
    {
        return $this->pipeline(new RetryInterceptor($this->entries, 3, 0))($fn);
    }

    /** @return list<string> */
    public function logEntries(): array
    {
        return $this->entries;
    }

    /**
     * Build an explicit interceptor pipeline around a destination callable.
     */
    private function pipeline(LogInterceptor|RetryInterceptor ...$interceptors): callable
    {
        return static function (callable $destination) use ($interceptors): mixed {
            $next = $destination;
            foreach (\array_reverse($interceptors) as $interceptor) {
                $next = static fn(mixed $payload = null) => $interceptor($next, $payload);
            }
            return $next();
        };
    }
}