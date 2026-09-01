<?php

declare(strict_types=1);

/**
 * PSR-3 logger that records DBAL query + transaction activity into the
 * DbEventLog service (the Symfony-side implementation of the db-events
 * feature race).
 *
 * Doctrine DBAL 4.x idiomatic observation hook is a PSR-3 logger attached
 * via the `doctrine.dbal.logging` middleware. This logger forwards the
 * query/transaction messages to DbEventLog.
 */

namespace App\Symfony\Service;

use Psr\Log\AbstractLogger;

final class DbEventLogger extends AbstractLogger
{
    public function __construct(
        private readonly DbEventLog $log,
    )
    {
    }

    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $msg = (string) $message;

        if (isset($context['sql'])) {
            $this->log->recordQuery($context['sql'], 0.0);
        } elseif (\str_starts_with($msg, 'Beginning transaction')) {
            $this->log->recordTransaction('TransactionStarted');
        } elseif (\str_starts_with($msg, 'Committing transaction')) {
            $this->log->recordTransaction('TransactionCommitted');
        } elseif (\str_starts_with($msg, 'Rolling back transaction')) {
            $this->log->recordTransaction('TransactionRolledBack');
        }
    }
}