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
                $this->entries[] = [
                    'event'    => 'DBQuery',
                    'query'    => $query->getQuery(),
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