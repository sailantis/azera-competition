<?php

declare(strict_types=1);

/**
 * In-memory log engine for the `/features/log` demo.
 *
 * Cake ships `Cake\Log\Engine\ArrayLog` for exactly this purpose ("can also be
 * used in scenarios where you need to capture logs in application code"), but
 * its `read()` returns formatted STRINGS, and every other entrant reports the
 * `level` / `message` / `context` triple. This subclass keeps that triple so the
 * payload compares like for like.
 *
 * Registering an engine is not optional decoration: `Log::write()` iterates the
 * engines in `LogEngineRegistry` and returns `false` when the registry is empty.
 * Before this engine existed nothing in the benchmark app configured one, so
 * `/features/log` called `Log::write()` and the record was **discarded** â€” the
 * endpoint then printed a hand-built array literal, i.e. it measured array
 * construction and reported a log entry the logger never saw.
 */

namespace App\Cake\Support;

use Cake\Log\Engine\ArrayLog;
use Stringable;

final class FeatureLogEngine extends ArrayLog
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    public function log($level, Stringable|string $message, array $context = []): void
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
     * The demo clears before logging: a resident worker reuses one process for
     * thousands of requests, so entries from the previous request would
     * otherwise leak into this one's response.
     */
    public function clearRecords(): void
    {
        $this->records = [];
        $this->clear();
    }
}
