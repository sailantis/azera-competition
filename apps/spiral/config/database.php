<?php

declare(strict_types=1);

/**
 * Cycle ORM / DBAL configuration for the benchmark app.
 *
 * Points Cycle at the shared SQLite benchmark database (same file the azera
 * app uses). The ORM schema is compiled from annotated entities in
 * App\Spiral\Entity (via the Tokenizer + AnnotatedBootloader).
 */

use Cycle\Database\Config;

return [
    'logger' => [
        // Route all Cycle DBAL driver logs (query lines with {elapsed,
        // rowCount} context, Begin/Commit transaction messages) into the
        // `database` channel. Spiral's LoggerFactory reads this config;
        // the DbEventLog listener in AppBootloader consumes the LogEvents.
        'default' => 'database',
        'drivers' => [],
    ],

    'default' => 'default',

    'databases' => [
        'default' => ['driver' => 'default'],
    ],

    'drivers' => [
        'default' => new Config\SQLiteDriverConfig(
            connection: new Config\SQLite\FileConnectionConfig(
                database: __DIR__ . '/../../../data/bench.sqlite',
            ),
            queryCache: true,
        ),
    ],
];