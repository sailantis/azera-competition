<?php

declare(strict_types=1);

/**
 * Shared SQLite boot for the CakePHP benchmark app.
 *
 * ConnectionManager::setConfig with the Sqlite driver against the same
 * bench.sqlite file all other adapters use (WAL + busy_timeout parity).
 * TableRegistry::get('Items') then hands out the Items table.
 */

namespace App\Cake;

use Cake\Cache\Cache;
use Cake\Database\Connection;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;

final class Db
{
    private const CONNECTION = 'default';

    private function __construct() {}

    /**
     * Register the shared SQLite connection (idempotent).
     */
    public static function init(string $dbPath): void
    {
        if (ConnectionManager::getConfig(self::CONNECTION) !== null) {
            return;
        }

        ConnectionManager::setConfig(self::CONNECTION, [
            'className' => Connection::class,
            'driver'    => Sqlite::class,
            'database'  => $dbPath,
            // Cake 5's Sqlite driver has no journalMode/busyTimeout keys —
            // PRAGMAs go through `init` (executed right after connect).
            'init' => [
                'PRAGMA journal_mode = wal',
                'PRAGMA busy_timeout = 5000',
                'PRAGMA foreign_keys = ON',
            ],
        ]);

        Cache::setConfig('bench', ['className' => 'Cake\Cache\Engine\ArrayEngine', 'duration' => '+10 seconds']);
    }

    /**
     * The shared connection.
     */
    public static function connection(): Connection
    {
        return ConnectionManager::get(self::CONNECTION);
    }

    /**
     * Table locator for ORM access (TableRegistry-backed).
     */
    public static function tableLocator(): \Cake\ORM\Locator\LocatorInterface
    {
        return \Cake\ORM\TableRegistry::getTableLocator();
    }
}