<?php
/**
 * In-memory log of database events.
 *
 * The DatabaseEventListener writes to this store whenever the framework
 * dispatches a Db event (QueryExecuted, StatementPrepared,
 * TransactionStarted, TransactionCommitted).  The demo controller reads
 * it back to show that the Db event pipeline is live.
 */

namespace App\Azera\Services;

use Azera\Lifecycle\RequestScoped;

class DbEventLog implements RequestScoped
{
    /** @var array<int, array{type: string, sql?: string, duration_ms?: float, level?: int}> */
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
        '/^\s*PRAGMA\b/i',                              // SQLite connection setup + schema reflection
        '/^\s*SELECT\s+.+FROM\s+pragma_/is',            // CI4's index-introspection union query
        "/sqlite_master/i",                            // CakePHP's table-DDL reflection
        '/^\s*(BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/i',
    ];

    private static function isInfrastructure(string $sql): bool
    {
        foreach (self::INFRASTRUCTURE_SQL as $pattern) {
            if (preg_match($pattern, $sql) === 1) {
                return true;
            }
        }

        return false;
    }

    public function record(string $type, array $data = []): void
    {
        if (isset($data['sql']) && is_string($data['sql']) && self::isInfrastructure($data['sql'])) {
            return;
        }

        $this->events[] = ['type' => $type] + $data;
    }

    /** @return array<int, array{type: string, sql?: string, duration_ms?: float, level?: int}> */
    public function all(): array
    {
        return $this->events;
    }

    public function clear(): void
    {
        $this->events = [];
    }

    /**
     * Request-scoped hook: the Db event log records one entry per executed
     * statement; in a persistent worker it must be wiped between requests
     * or it grows without bound (memory + GC pressure).
     */
    public function resetState(): void
    {
        $this->events = [];
    }
}