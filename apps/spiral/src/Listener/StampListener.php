<?php

declare(strict_types=1);

/**
 * PSR-14 listener — stamps received events with a log entry.
 */

namespace App\Spiral\Listener;

use App\Spiral\Event\ItemCreated;

final class StampListener
{
    public function __invoke(ItemCreated $event): void
    {
        $event->log("listener: received ItemCreated id={$event->id}");
        $event->log('listener: stamped at ' . \date('H:i:s'));
    }
}