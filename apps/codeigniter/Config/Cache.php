<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Cache config.
 *
 * File handler (the only always-available persistent handler in CI4 4.7;
 * the 50ms cache-miss sleep in /features/cache dominates its cost anyway).
 */

namespace Config;

use CodeIgniter\Cache\CacheInterface;
use CodeIgniter\Cache\Handlers\DummyHandler;
use CodeIgniter\Cache\Handlers\FileHandler;
use CodeIgniter\Config\BaseConfig;

class Cache extends BaseConfig
{
    public string $handler = 'file';

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
        'file'  => FileHandler::class,
    ];

    public bool|array $cacheQueryString = false;

    public array $cacheStatusCodes = [];
}