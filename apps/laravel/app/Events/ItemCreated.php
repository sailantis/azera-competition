<?php

/**
 * Event dispatched when an item is created — mirrors Spiral's ItemCreated.
 */

namespace App\Laravel\Events;

final class ItemCreated
{
    /** @var list<string> */
    private array $log = [];

    public function __construct(
        public readonly int $id,
        public readonly string $title,
    )
    {
    }

    public function log(string $entry): void
    {
        $this->log[] = $entry;
    }

    /** @return list<string> */
    public function logEntries(): array
    {
        return $this->log;
    }
}