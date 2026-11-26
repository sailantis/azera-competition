<?php

declare(strict_types=1);

/**
 * Captures Spiral's own log events for the `/features/log` demo.
 *
 * Spiral ships a logger abstraction (the `spiral/logger` package, bundled in
 * `spiral/framework`): `Spiral\Logger\LogsInterface::getLogger($channel)` returns a
 * PSR-3 logger (`NullLogger` by default) whose `log()` builds a
 * `Spiral\Logger\Event\LogEvent` and hands it to every listener in
 * `ListenerRegistryInterface`. Monolog is an optional bridge
 * (`spiral/framework`'s `Bridge/Monolog`), and this app does not install it.
 *
 * So the framework's native readback is a REGISTERED LISTENER, not a buffer the
 * application appends to. This class is that listener: the records are the events
 * Spiral emitted, which is what makes the row a measurement of the framework's
 * logging path rather than of an array.
 *
 * The buffer is a container singleton (see AppBootloader) because the listener is
 * registered once per process while the demo controller is built per request.
 */

namespace App\Spiral\Service;

use Spiral\Logger\Event\LogEvent;

final class LogListener
{
    /**
     * The channel the demo logs to.
     *
     * Spiral's log factory routes by channel; `/features/log` uses a dedicated one
     * so a reader can tell the demo's events from Cycle's query logging, which feeds
     * the very same `ListenerRegistry`. Kept out of the reported records so the
     * payload is the same `level`/`message`/`context` triple the other entrants
     * report.
     */
    public const CHANNEL = 'bench';

    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /**
     * `ListenerRegistryInterface` accepts any callable; `__invoke` is the shape a
     * listener class uses.
     *
     * Only the demo channel is captured: the registry also carries Cycle DBAL's
     * query events (see DbEventLog), which belong to `/features/db-events`.
     */
    public function __invoke(LogEvent $event): void
    {
        if ($event->getChannel() !== self::CHANNEL) {
            return;
        }

        $this->records[] = [
            'level'   => $event->getLevel(),
            'message' => $event->getMessage(),
            'context' => $event->getContext(),
        ];
    }

    /**
     * The captured events as plain arrays.
     *
     * @return list<array{level: string, message: string, context: array<string, mixed>}>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * Drop everything captured so far.
     *
     * Called before each demo write so a resident worker's previous request cannot
     * leak events into this one's response.
     */
    public function clear(): void
    {
        $this->records = [];
    }
}
