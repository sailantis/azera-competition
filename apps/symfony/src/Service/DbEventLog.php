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

    /**
     * Statements that describe the CONNECTION or the SCHEMA rather than the demo.
     *
     * Not every entrant's hook sees these — it depends on when the framework
     * attaches its listener relative to when the connection is opened, and on how
     * much schema introspection the ORM does per operation. Recording them made
     * the same logical workload (one upsert + one COUNT) report anywhere from 2 to
     * 9 events, so the row's payload measured when a logger was attached and how
     * curious an ORM is, not the observation facility it exists to compare.
     */
    private const INFRASTRUCTURE_SQL = [
        '/^\s*PRAGMA\b/i',
        '/^\s*SELECT\s+.+FROM\s+pragma_/is',
        '/sqlite_master/i',
        '/^\s*(BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/i',
    ];

    private static function isInfrastructure(string $sql): bool
    {
        foreach (self::INFRASTRUCTURE_SQL as $pattern) {
            if (\preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    public function recordQuery(string $sql, float $durationMs): void
    {
        if (self::isInfrastructure($sql)) {
            return;
        }

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