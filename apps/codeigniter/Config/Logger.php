<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Logger config.
 *
 * Threshold 0: nothing is written anywhere. The Logger constructor still
 * requires at least one handler config, so ErrorlogHandler (no filesystem
 * I/O, messages suppressed by the threshold) satisfies that check.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Log\Handlers\ErrorlogHandler;

class Logger extends BaseConfig
{
    public int $threshold = 0;

    public string $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var array<class-string, array<string, mixed>>
     */
    public array $handlers = [
        ErrorlogHandler::class => [
            'handles' => [
                'critical',
                'alert',
                'emergency',
                'debug',
                'error',
                'info',
                'notice',
                'warning',
            ],
            'messageType' => 0,
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $processors = [];

    /**
     * @var array<string, list<string>>
     */
    public array $strip = [];
}