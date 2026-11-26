<?php

declare(strict_types=1);

/**
 * Transactional interceptor — wraps a handler in a Cycle DB transaction.
 *
 * WHY THIS EXISTS. Spiral ships an interceptor abstraction
 * ({@see \Spiral\Interceptors\InterceptorInterface} +
 * {@see \Spiral\Interceptors\PipelineBuilder}) but no transaction interceptor of
 * its own, so this is the same shape a Spiral application writes: the framework
 * supplies the pipeline, the application supplies the interceptor. That is the
 * point of the `/features/aop-tx-explicit` row — `PipelineBuilder` really does
 * compose the chain, and this class really does open and commit the transaction,
 * so the measured work is framework machinery rather than application control
 * flow.
 *
 * It deliberately mirrors {@see \Azera\Aop\TransactionalInterceptor}: same
 * begin/commit/rollback contract, same "roll back before re-throwing", same
 * commit-failure path. Two entrants running the same logic through their own
 * pipelines is what makes the row comparable.
 */
namespace App\Spiral\Interceptor;

use Cycle\Database\DatabaseInterface;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;

final class TransactionalInterceptor implements InterceptorInterface
{
    public function __construct(
        private readonly DatabaseInterface $db,
    ) {}

    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        $this->db->begin();

        try {
            $result = $handler->handle($context);
        } catch (\Throwable $e) {
            $this->db->rollback();

            throw $e;
        }

        // COMMIT is handled apart from the body so its own failure is reported
        // as-is: rolling back in the same try would let "There is no active
        // transaction" replace the real cause.
        try {
            $this->db->commit();
        } catch (\Throwable $commitError) {
            $this->db->rollback();

            throw $commitError;
        }

        return $result;
    }
}
