<?php

/**
 * In-memory log of database activity — the Laravel-side implementation of
 * the db-events feature race.
 *
 * Laravel's idiomatic DB observation hook is the query event dispatcher:
 * `DB::listen()` receives a QueryExecuted event per query (with elapsed ms
 * and SQL) and transaction lifecycle is observable via the connection's
 * begin/commit/rollback callbacks. This service registers those hooks once
 * (from AppServiceProvider-booted listeners) and records entries so the
 * demo controller can show the observation pipeline is live.
 */

namespace App\Laravel\Service;

final class DbEventLog
{
    /** @var list<array{type: string, sql?: string, duration_ms?: float, level?: string}> */
    private array $events = [];

    public function recordQuery(string $sql, float $durationMs): void
    {
        $this->events[] = [
            'type'        => 'QueryExecuted',
            'sql'         => $sql,
            'duration_ms' => round($durationMs, 2),
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