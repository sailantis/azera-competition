<?php

declare(strict_types=1);

/**
 * Event listener — stamps received ItemCreated events with log entries.
 * Mirrors Spiral's StampListener.
 */

namespace App\Symfony\EventListener;

use App\Symfony\Event\ItemCreated;

final class StampListener
{
    public function __invoke(ItemCreated $event): void
    {
        $event->log("listener: received ItemCreated id={$event->id}");
        $event->log('listener: stamped at ' . \date('H:i:s'));
    }
}