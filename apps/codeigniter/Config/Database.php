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
        // SQLite's FULL default makes every WAL commit fsync, so an
        // autocheckpoint turns one commit in a few hundred into a multi-millisecond
        // stall. NORMAL(1) matches every other entrant; see
        // tests/SqlitePragmaParityTest.php. (Was null, which left the driver on
        // SQLite's default of FULL.) CI4 requires the INTEGER flag here, not the
        // name: Connection::initialize() validates `int` 0-3 and emits
        // `PRAGMA synchronous = <n>`, so the string 'NORMAL' would be ignored.
        //
        // NOTE ON journal_mode: CI4's SQLite3 connection exposes no option for it
        // (initialize() applies only foreign_keys, busyTimeout and synchronous),
        // so WAL comes from seed.php having put the shared FILE into WAL mode.
        // That is a persistent property of the database file, so it holds for every
        // entrant; CI4 simply cannot state it in config.
        'synchronous'  => 1,
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