<?php

declare(strict_types=1);

/**
 * DB activity log — CodeIgniter's idiomatic DB observation hook.
 *
 * CodeIgniter fires the `DBQuery` event (CodeIgniter\Events\Events::trigger)
 * for every executed statement with a Query object carrying the SQL, bound
 * params and execution duration.  This is the closest native equivalent to
 * azera's QueryExecuted/StatementPrepared events.
 *
 * The listener is registered once per process in DbEventLog::register()
 * (called from the adapter bootstrap); entries are capped at 50 like the
 * azera DbEventLog.
 */

namespace Ci4App\Support;

final class DbEventLog
{
    /** @var list<array<string, mixed>> */
    private array $entries = [];

    private const MAX_ENTRIES = 50;

    private bool $registered = false;

    /**
     * Statements that describe the CONNECTION or the SCHEMA rather than the demo.
     *
     * Not every entrant's hook sees these — it depends on when the framework
     * attaches its listener relative to when the connection is opened, and on how
     * much schema introspection the model layer does per operation. Recording them
     * made the same logical workload (one upsert + one COUNT) report anywhere from
     * 2 to 9 events, so the row's payload measured when a logger was attached and
     * how curious an ORM is, not the observation facility it exists to compare.
     */
    private const INFRASTRUCTURE_SQL = [
        '/^\s*PRAGMA\b/i',
        '/^\s*SELECT\s+.+FROM\s+pragma_/is',
        '/sqlite_master/i',
        '/^\s*(BEGIN|COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/i',
    ];

    /**
     * Idempotently register the DBQuery listener.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        \CodeIgniter\Events\Events::on(
            'DBQuery',
            function (\CodeIgniter\Database\QueryInterface $query): void {
                $sql = (string) $query->getQuery();

                foreach (self::INFRASTRUCTURE_SQL as $pattern) {
                    if (preg_match($pattern, $sql) === 1) {
                        return;
                    }
                }

                $this->entries[] = [
                    'event'    => 'DBQuery',
                    'query'    => $sql,
                    'duration' => $query->getDuration(5),
                ];

                if (count($this->entries) > self::MAX_ENTRIES) {
                    array_shift($this->entries);
                }
            },
        );

        $this->registered = true;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}