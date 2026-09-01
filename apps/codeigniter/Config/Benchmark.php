<?php

declare(strict_types=1);

/**
 * Benchmark config — plain CI4 config class exposing the values the
 * /features/config endpoint reads.  CI4 config classes use properties (no
 * nested dot-path arrays); the endpoint documents that difference.
 */

namespace Ci4App\Config;

use CodeIgniter\Config\BaseConfig;

final class Benchmark extends BaseConfig
{
    public string $name = 'Azera Competition (CodeIgniter 4)';

    public string $version = '1.0.0';

    public int $pageSize = 20;

    /**
     * @var array<string, int>
     */
    public array $sentinelIds = [
        'orm' => 999999,
        'qb'  => 999997,
        'api' => 999998,
    ];
}