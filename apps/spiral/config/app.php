<?php

declare(strict_types=1);

/**
 * Benchmark app configuration — the "config" feature endpoint reads these
 * values through Spiral's Config service (dot-notation access).
 */

return [
    'name' => 'Azera Competition (Spiral)',
    'version' => '1.0.0',
    'benchmark' => [
        'pageSize' => 20,
        'sentinelIds' => [
            'orm' => 999999,
            'qb' => 999997,
            'api' => 999998,
        ],
    ],
];