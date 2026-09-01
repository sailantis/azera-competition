<?php

/**
 * Event listener — stamps received ItemCreated events with log entries.
 * Mirrors Spiral's StampListener.
 */

namespace App\Laravel\Listeners;

use App\Laravel\Events\ItemCreated;

final class StampListener
{
    public function handle(ItemCreated $event): void
    {
        $event->log("listener: received ItemCreated id={$event->id}");
        $event->log('listener: stamped at ' . \date('H:i:s'));
    }
}