<?php

declare(strict_types=1);

/**
 * In-memory PSR-3 logger for the `/features/log` demo.
 *
 * Symfony ships no logger package by itself: `symfony/http-kernel` provides
 * `Symfony\Component\HttpKernel\Log\Logger` (PSR-3, and the `logger` service the
 * framework registers), and monolog is what a real Symfony app installs on top.
 * Both write to stderr or to a file, so neither can be read back, and a benchmark
 * row must not measure disk or stderr I/O that it is not testing — the other
 * entrants all capture into memory (azera's MemoryLogger, Laravel's Monolog
 * TestHandler, Cake's `ArrayLog`, CI4's buffering handler).
 *
 * This is a plain PSR-3 logger (`Psr\Log\AbstractLogger`) registered as the
 * `logger` service, so application code depends on the PSR interface and Symfony's
 * container decides the sink — the framework's own mechanism, not a side channel.
 */

namespace App\Symfony\Service;

use Psr\Log\AbstractLogger;

final class MemoryLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level'   => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * The captured records as plain arrays.
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
     * leak entries into this one's response.
     */
    public function clear(): void
    {
        $this->records = [];
    }

    /**
     * `kernel.reset` hook — Symfony's services_resetter calls this between requests
     * in a resident worker, so the buffer never holds two requests' records.
     */
    public function resetState(): void
    {
        $this->records = [];
    }
}
