<?php

declare(strict_types=1);

namespace App\Phalcon\Support;

/**
 * DB activity log — Phalcon's native DB-event observation hook.
 *
 * Phalcon's PDO adapter is `EventsAware`: it fires `db:beforeQuery`,
 * `db:afterQuery`, `db:beginTransaction` and `db:commitTransaction` on whatever
 * events manager is attached to it. This class is the listener that records
 * them, in the same shape the CI4 and CakePHP entrants emit for the same row.
 *
 * The `/features/db-events` demo writes on an autocommit connection, so only
 * `db:afterQuery` reaches this log there; the transaction types are recorded for
 * the `aop-*` rows, which do open one.
 *
 * ATTACHED PERMANENTLY, ON PURPOSE — and that is not free. `setEventsManager()`
 * only accepts a manager (it rejects `null` with a TypeError), and
 * `detachAll()` leaves the manager itself attached, so there is no way to
 * de-register the hook for the endpoints that do not use it. The manager simply
 * has no listeners between demos. Measured on the bench VM, an attached-but-
 * empty manager costs +0.17 µs/query (~3.7 %) on a local SQLite count — under a
 * quarter of the noise on the smallest measured endpoint, and the honest price
 * of having the feature at all.
 *
 * The CI4 and Cake entrants made the same call for the same reason: their query
 * taps are registered once per process, not per request.
 */
final class DbEventLog
{
    /** Cap, so a long resident run cannot grow the log without bound. */
    private const MAX_ENTRIES = 50;

    /** @var list<array<string,mixed>> */
    private array $entries = [];

    /**
     * The `db:` event types this log records.
     *
     * `beforeQuery` is deliberately NOT here. Phalcon fires that BEFORE the
     * statement runs, and it also fires for statements that then fail — so
     * recording it alongside `afterQuery` reports every statement twice and makes
     * Phalcon's payload look twice as busy as the other entrants', whose hooks fire
     * once per statement after execution. `afterQuery` is the exact analogue of
     * `QueryExecuted`.
     *
     * Phalcon fires more events than these (`db:createSavepoint`,
     * `db:rollbackSavepoint`, `db:connectionLost`), but they only occur on paths
     * the demo does not take.
     */
    private const RECORDED = [
        'afterQuery',
        'beginTransaction',
        'commitTransaction',
        'rollbackTransaction',
    ];

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

    /**
     * The listener attached to the db adapter's events manager.
     *
     * Phalcon calls listeners as `($event, $source, $data)`; `$data` is the
     * bound-parameter array for the query events and null for the transaction
     * ones.
     */
    public function __invoke(object $event, object $source, mixed $data = null): void
    {
        $type = (string) $event->getType();

        if (!in_array($type, self::RECORDED, true)) {
            return;
        }

        $entry = ['event' => $type];

        if ($type === 'afterQuery') {
            // The adapter exposes the statement it just ran; the log is the
            // point of this endpoint, so the SQL belongs in the payload.
            $sql = (string) $source->getSqlStatement();

            foreach (self::INFRASTRUCTURE_SQL as $pattern) {
                if (preg_match($pattern, $sql) === 1) {
                    return;
                }
            }

            $entry['query'] = $sql;
        }

        if (is_array($data) && $data !== []) {
            $entry['params'] = $data;
        }

        $this->entries[] = $entry;

        if (count($this->entries) > self::MAX_ENTRIES) {
            array_shift($this->entries);
        }
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function all(): array
    {
        return $this->entries;
    }
}
