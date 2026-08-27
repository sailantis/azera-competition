<?php
/**
 * In-memory log of database events.
 *
 * The DatabaseEventListener writes to this store whenever the framework
 * dispatches a Db event (QueryExecuted, StatementPrepared,
 * TransactionStarted, TransactionCommitted).  The demo controller reads
 * it back to show that the Db event pipeline is live.
 */

namespace App\Services;

class DbEventLog
{
    /** @var array<int, array{type: string, sql?: string, duration_ms?: float, level?: int}> */
    private array $events = [];

    public function record(string $type, array $data = []): void
    {
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
}