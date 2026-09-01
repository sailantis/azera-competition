<?php

/**
 * Benchmark configuration — mirrors Spiral's config/app.php so the config
 * feature endpoint stays in parity (app_name / page_size / missing keys).
 */

return [
    'name'    => 'Laravel Benchmark App',
    'version' => '1.0.0',

    'benchmark' => [
        'pageSize'    => 20,
        'sentinelIds' => [
            'orm' => 999999,
            'qb'  => 999997,
            'api' => 999998,
        ],
    ],

    'locale'   => 'en_US',
    'platform' => 'desktop',
];