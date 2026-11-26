<?php

declare(strict_types=1);

namespace App\Phalcon\Support;

use Phalcon\Logger\Adapter\AbstractAdapter;
use Phalcon\Logger\Item;

/**
 * A logger adapter that keeps the records in memory instead of writing them.
 *
 * Why this exists: the `#features/log` demo has to SHOW what the logger did, and every
 * other app does that by reading back the entries it just wrote (azera's MemoryLogger,
 * CI4's log_message capture, Cake's explicit entry array). Streaming to a file would make
 * the endpoint measure disk I/O, which is not the feature under test and is not comparable.
 *
 * `AbstractAdapter` supplies the queue, transactions, formatter and level
 * handling; only the two abstract members — `process()` and `close()` — are
 * implemented here, so this adapter behaves like the framework's own ones.
 */
final class MemoryLoggerAdapter extends AbstractAdapter
{
    /** @var list<Item> */
    private array $items = [];

    /**
     * Collect one record.
     *
     * `AbstractAdapter` calls this for every message that passes the level
     * filter, after the transaction queue has been flushed.
     */
    public function process(Item $item): void
    {
        $this->items[] = $item;
    }

    public function close(): bool
    {
        return true;
    }

    /**
     * Drop everything collected so far.
     *
     * The endpoints clear before each demo so a resident worker's previous
     * request cannot leak entries into this one's response.
     */
    public function clear(): void
    {
        $this->items = [];
    }

    /**
     * The collected records as plain arrays.
     *
     * `level`/`message`/`context` is the same triple every other entrant
     * reports, so the JSON payload compares like for like.
     *
     * @return list<array{level:string,message:string,context:array<string,mixed>}>
     */
    public function entries(): array
    {
        $out = [];
        foreach ($this->items as $item) {
            $out[] = [
                'level'   => $item->getLevelName(),
                'message' => $item->getMessage(),
                'context' => $item->getContext(),
            ];
        }

        return $out;
    }
}
