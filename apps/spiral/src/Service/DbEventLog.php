<?php

declare(strict_types=1);

/**
 * In-memory log of database activity.
 *
 * Spiral/Cycle has no PSR-14 DB event pipeline like azera's. Its idiomatic
 * observation hook is the PSR-3 logger attached to the Cycle DBAL driver:
 * every executed query is logged with {elapsed, rowCount} context, and
 * transactions produce "Begin transaction" / "Commit transaction" messages.
 *
 * In a Spiral app the channel is wired via config/database.php
 * (`logger.default`) and consumed through Spiral's LogsInterface /
 * ListenerRegistryInterface (LogEvent). This service registers that
 * listener and records DB-related entries so the demo controller can show
 * the observation pipeline is live.
 */

namespace App\Spiral\Service;

use Spiral\Logger\Event\LogEvent;

final class DbEventLog
{
    /** Log channel the Cycle DBAL driver logs are routed to (config/database.php). */
    private const DB_CHANNEL = 'database';

    /** @var list<array{type: string, sql?: string, duration_ms?: float, elapsed?: float, level?: string}> */
    private array $events = [];

    /**
     * LogEvent listener — attach via ListenerRegistryInterface::addListener().
     */
    public function __invoke(LogEvent $event): void
    {
        if ($event->getChannel() !== self::DB_CHANNEL) {
            return;
        }

        $context = $event->getContext();
        $message = $event->getMessage();

        $entry = ['level' => $event->getLevel()];

        if (isset($context['elapsed'])) {
            $entry['duration_ms'] = round($context['elapsed'] * 1000, 2);
        }

        // Query logs carry the SQL as the message; transaction lifecycle
        // messages are named events.
        if (isset($context['rowCount'])) {
            $entry['type'] = 'QueryExecuted';
            $entry['sql']  = $message;
        } elseif (str_starts_with($message, 'Begin transaction')) {
            $entry['type'] = 'TransactionStarted';
        } elseif (str_starts_with($message, 'Commit transaction')) {
            $entry['type'] = 'TransactionCommitted';
        } elseif (str_starts_with($message, 'Rollback transaction')) {
            $entry['type'] = 'TransactionRolledBack';
        } else {
            return;
        }

        $this->events[] = $entry;
    }

    /** @return list<array{type: string, sql?: string, duration_ms?: float, elapsed?: float, level?: string}> */
    public function all(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}