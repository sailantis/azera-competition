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
     * Phalcon fires more than these (`db:createSavepoint`, `db:rollbackSavepoint`,
     * `db:connectionLost`), but they only occur on paths the demo does not take;
     * recording the four that actually happen keeps the payload comparable with
     * the other entrants' query logs.
     */
    private const RECORDED = [
        'beforeQuery',
        'afterQuery',
        'beginTransaction',
        'commitTransaction',
        'rollbackTransaction',
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

        if ($type === 'beforeQuery' || $type === 'afterQuery') {
            // The adapter exposes the statement it is running; the log is the
            // point of this endpoint, so the SQL belongs in the payload.
            $entry['query'] = (string) $source->getSqlStatement();
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
