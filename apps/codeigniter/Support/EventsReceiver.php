<?php

declare(strict_types=1);

/**
 * Events receiver — invoked by CodeIgniter's Events system for the
 * `item.created` demo.  Mirrors azera's ItemCreated listener output.
 */

namespace Ci4App\Support;

final class EventsReceiver
{
    /** @var list<string> */
    private array $log = [];

    /**
     * Called by Events::trigger('item.created', $id, $title).
     */
    public function __invoke(int $id, string $title): void
    {
        $this->log[] = 'ItemCreated handled: id=' . $id . ' title=' . $title;
    }

    /**
     * @return list<string>
     */
    public function log(): array
    {
        return $this->log;
    }
}