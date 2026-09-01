<?php

/**
 * Cache configuration — array store only (in-process PSR-16-equivalent).
 *
 * The rate-limit + cache feature endpoints use Laravel's Cache repository
 * backed by the array driver, mirroring Spiral's `storage('array')`.
 */

return [
    'default' => 'array',
    'stores'  => [
        'array' => [
            'driver'    => 'array',
            'serialize' => false,
        ],
    ],
    'prefix' => 'bench_cache',
];