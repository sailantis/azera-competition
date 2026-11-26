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
use Spiral\Logger\LogsInterface;

final class AopService
{
    /** Fixed row used by every feature-demo write — the row count stays stable across benchmark runs. */
    public const FEATURE_SENTINEL_ID = 888800;

    /** @var list<string> */
    private array $entries = [];

    public function __construct(
        private readonly LogsInterface $logFactory,
        private readonly LogListener $logListener,
    ) {}

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
     * Create the same item with NO interceptor pipeline — the direct twin of
     * {@see createItem()}.
     *
     * Same insert, same connection, no `PipelineBuilder`. The pair exists so the
     * pipeline's cost can be measured on identical work.
     */
    public function createItemDirect(string $title): int
    {
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
    }

    /**
     * The same insert with NO advice and NO transaction — the body wrapped by
     * the explicit `PipelineBuilder` demo.
     *
     * `/features/aop-tx-explicit` composes a real
     * {@see \App\Spiral\Interceptor\TransactionalInterceptor} through Spiral's
     * own `PipelineBuilder`, so the BEGIN/COMMIT measured on that row is executed
     * by the framework's pipeline rather than by this method. The body therefore
     * must contain no transaction handling at all.
     */
    public function createItemUnmanaged(string $title): int
    {
        return $this->createItemDirect($title);
    }

    /**
     * Wire a plain callable through LogInterceptor with Spiral's PSR-3 logger.
     *
     * `LogsInterface::getLogger(LogListener::CHANNEL)` is the framework's log factory; the
     * logger it returns builds `LogEvent`s and hands them to the listeners on
     * `ListenerRegistryInterface`, including the `LogListener` registered in
     * AppBootloader. `logRecords()` reads back what those listeners captured.
     */
    public function loggedCall(callable $fn): mixed
    {
        $this->entries = [];

        $this->logListener->clear();

        return $this->pipeline(new LogInterceptor($this->logFactory->getLogger(LogListener::CHANNEL)))->handle(
            self::context($fn),
        );
    }

    /**
     * Records Spiral's log listeners captured for the demo channel.
     *
     * The logger is the framework's (`LogsInterface::getLogger(LogListener::CHANNEL)`), so this
     * is a readback of what the logging path emitted rather than a re-description
     * of the call.
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>, channel: string}>
     */
    public function logRecords(): array
    {
        return $this->logListener->records();
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

    /**
     * Wrap a plain callable in Spiral's `PipelineBuilder` with the given
     * interceptors — the framework's explicit AOP entry point.
     *
     * Public so a controller can run an arbitrary interceptor chain (the
     * `/features/aop-tx-explicit` demo needs a `TransactionalInterceptor` the two
     * demo helpers above do not use).
     *
     * @param \Spiral\Interceptors\InterceptorInterface ...$interceptors
     */
    public function pipeline(\Spiral\Interceptors\InterceptorInterface ...$interceptors): \Spiral\Interceptors\HandlerInterface
    {
        return (new PipelineBuilder())
            ->withInterceptors(...$interceptors)
            ->build(new CallableHandler());
    }

    /**
     * Run a callable through Spiral's explicit pipeline. Kept beside
     * {@see pipeline()} so the demo controller does not have to know how a
     * `CallContext` is built.
     */
    public function runThrough(callable $fn, \Spiral\Interceptors\InterceptorInterface ...$interceptors): mixed
    {
        return $this->pipeline(...$interceptors)->handle(self::context($fn));
    }

    private static function context(callable $fn): CallContext
    {
        return new CallContext(Target::fromClosure($fn(...)));
    }
}
