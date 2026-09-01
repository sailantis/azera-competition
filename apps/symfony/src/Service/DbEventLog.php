<?php

declare(strict_types=1);

/**
 * In-memory log of database activity — the Symfony-side implementation of
 * the db-events feature race.
 *
 * Doctrine DBAL's idiomatic observation hook is the SQL logger (PSR-3) and
 * the connection's event manager. This service records entries so the demo
 * controller can show the observation pipeline is live.
 */

namespace App\Symfony\Service;

final class DbEventLog
{
    /** @var list<array{type: string, sql?: string, duration_ms?: float, level?: string}> */
    private array $events = [];

    public function recordQuery(string $sql, float $durationMs): void
    {
        $this->events[] = [
            'type'        => 'QueryExecuted',
            'sql'         => $sql,
            'duration_ms' => \round($durationMs, 2),
            'level'       => 'info',
        ];
    }

    public function recordTransaction(string $type): void
    {
        $this->events[] = [
            'type'  => $type,
            'level' => 'info',
        ];
    }

    /** @return list<array{type: string, sql?: string, duration_ms?: float, level?: string}> */
    public function all(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }
}