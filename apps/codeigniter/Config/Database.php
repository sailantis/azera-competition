<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Database config (SQLite3, shared file).
 *
 * Uses the shared items table at data/bench.sqlite so every framework
 * measures against identical data.  WAL + busy_timeout match seed.php.
 */

namespace Config;

use CodeIgniter\Database\Config as DbConfig;

class Database extends DbConfig
{
    public string $filesPath = __DIR__ . '/../Database' . DIRECTORY_SEPARATOR;

    public string $defaultGroup = 'default';

    /**
     * @var array<string, mixed>
     */
    public array $default = [
        'DSN'      => '',
        'hostname' => '',
        'username' => '',
        'password' => '',
        // Shared benchmark DB (same file the other adapters point at). Computed
        // here rather than injected so Factories::reset() between worker-style
        // requests can't lose it. (__DIR__ = apps/codeigniter/Config → 3× up
        // to the competition root.)
        'database'     => __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite',
        'DBDriver'     => 'SQLite3',
        'DBPrefix'     => '',
        'pConnect'     => false,
        'DBDebug'      => true,
        'charset'      => 'utf8',
        'DBCollat'     => '',
        'swapPre'      => '',
        'encrypt'      => false,
        'compress'     => false,
        'strictOn'     => false,
        'failover'     => [],
        'port'         => 0,
        'numberNative' => true,
        'foreignKeys'  => true,
        'busyTimeout'  => 5000,
        'synchronous'  => null,
        'dateFormat'   => [
            'date'     => 'Y-m-d',
            'datetime' => 'Y-m-d H:i:s',
            'time'     => 'H:i:s',
        ],
    ];

    /**
     * @var array<string, mixed>
     */
    public array $tests = [
        'DSN'      => '',
        'database' => ':memory:',
        'DBDriver' => 'SQLite3',
        'DBDebug'  => true,
    ];
}