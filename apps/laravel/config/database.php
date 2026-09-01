<?php

/**
 * Database configuration — single shared SQLite benchmark database.
 *
 * Mirrors the other benchmark apps: WAL journal mode + busy timeout so
 * concurrent CLI harness runs don't trip over database locks.
 */

use Illuminate\Support\Str;

// config/ -> apps/laravel/ -> apps/ — the benchmark DB lives at repo root.
$database = dirname(__DIR__, 3) . '/data/bench.sqlite';

return [
    'default'    => 'sqlite',
    'migrations' => [
        'table'  => 'migrations',
        'update' => false,
    ],
    'connections' => [
        'sqlite' => [
            'driver'                  => 'sqlite',
            'url'                     => null,
            'database'                => $database,
            'prefix'                  => '',
            'foreign_key_constraints' => false,
            'busy_timeout'            => 5000,
            'journal_mode'            => 'wal',
            'synchronous'             => 'NORMAL',
        ],
    ],
    'redis' => [
        'client'  => 'phpredis',
        'options' => [
            'cluster' => 'redis',
            'prefix'  => Str::slug('bench', '_') . '_database_',
        ],
    ],
];