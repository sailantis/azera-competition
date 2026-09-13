<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Cache config.
 *
 * In-memory array handler (Ci4App\Cache\ArrayHandler): process-lifetime
 * store, matching the cache semantics of the other five benchmark apps
 * (azera ArrayCache, Symfony ArrayAdapter, Laravel array store, ...).
 * Entries survive between warm/worker requests but die with each fresh
 * boot — so cold mode pays the 50ms simulated cache-miss on every
 * iteration, exactly like every other framework. (The previous file
 * handler survived re-boots from disk, letting CI4 skip the miss that
 * dominates the cold /features/cache + /features/db-events rows.)
 */

namespace Config;

use Ci4App\Cache\ArrayHandler;
use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    public string $handler = 'array';

    public string $backupHandler = 'dummy';

    public string $prefix = '';

    public int $ttl = 60;

    public string $reservedCharacters = '{}()/\@:';

    /**
     * @var array{storePath?: string, mode?: int}
     */
    public array $file = [
        'storePath' => WRITEPATH . 'cache/',
        'mode'      => 0640,
    ];

    public array $memcached = [
        'host'   => '127.0.0.1',
        'port'   => 11211,
        'weight' => 1,
        'raw'    => false,
    ];

    public array $redis = [
        'host'       => '127.0.0.1',
        'password'   => null,
        'port'       => 6379,
        'timeout'    => 0,
        'async'      => false,
        'persistent' => false,
        'database'   => 0,
    ];

    /**
     * @var array<string, class-string<CacheInterface>>
     */
    public array $validHandlers = [
        'dummy' => DummyHandler::class,
        'array' => ArrayHandler::class,
        'file'  => FileHandler::class,
    ];

    public bool|array $cacheQueryString = false;

    public array $cacheStatusCodes = [];
}