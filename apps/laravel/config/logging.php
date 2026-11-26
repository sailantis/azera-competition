<?php

/**
 * Logging configuration — one in-memory channel for the `/features/log` demo.
 *
 * Laravel ships a full logging stack: `Log` facade → `LogManager` → Monolog →
 * handlers. The default channel here was `stack` → `single` → Monolog
 * `StreamHandler`, which writes to `storage/logs/laravel.log` on disk. A
 * benchmark row must not measure disk I/O it is not testing — the other entrants
 * all capture into memory (azera's MemoryLogger, Phalcon's MemoryLoggerAdapter,
 * Cake's `ArrayLog`, CI4's buffering handler) — so this app names its own channel
 * with Monolog's in-memory `TestHandler`.
 *
 * Naming a channel rather than replacing the default is deliberate: `Log::channel('bench')`
 * is how a Laravel application selects a sink, so the demo uses the framework's own
 * mechanism to choose where records go, and the rest of the app keeps the stock
 * configuration a real Laravel app ships.
 *
 * `TestHandler` is Monolog's own buffering handler (`getRecords()`, `clear()`); using
 * it keeps the sink framework/Monolog-owned instead of hand-written application code.
 */

return [
    'default'  => 'stack',
    'deprecations' => [
        'channel' => null,
        'trace'   => false,
    ],
    'channels' => [
        'stack' => [
            'driver'   => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path'   => dirname(__DIR__, 2) . '/writable/laravel/logs/laravel.log',
            'level'  => 'debug',
        ],

        /*
         * The demo channel: Monolog TestHandler, in memory, cleared per request.
         */
        'bench' => [
            'driver'  => 'monolog',
            'handler' => \Monolog\Handler\TestHandler::class,
            'level'   => 'debug',
        ],
    ],
];
