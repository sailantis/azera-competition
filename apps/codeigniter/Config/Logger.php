<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Logger config.
 *
 * `$threshold` decides which levels are loggable at all: CI4 resolves it as
 * `range(1, (int) $threshold)` over its internal severity map, so `0` yields an
 * EMPTY set and `Logger::log()` returns before touching a handler.
 *
 * This used to be `0`, documented as "nothing is written anywhere". That made
 * `/features/log` a demo of nothing: `log_message('info', …)` was discarded and
 * the endpoint printed a hand-built array literal, so the row measured array
 * construction and claimed a log entry the logger never saw.
 *
 * Threshold 7 = every PSR-3 level through `info` (CI4's map counts DOWN in
 * severity: 1 emergency … 7 info, 8 debug), which is what an application
 * enabling info-level logging gets.
 *
 * The single handler is an in-memory buffering handler, not Errorlog/File: the
 * row must not measure disk or stderr I/O it is not testing. Same choice azera,
 * CakePHP and Phalcon make.
 */

namespace Config;

use Ci4App\Support\MemoryLogHandler;
use CodeIgniter\Config\BaseConfig;

class Logger extends BaseConfig
{
    public int $threshold = 7;

    public string $dateFormat = 'Y-m-d H:i:s';

    /**
     * @var array<class-string, array<string, mixed>>
     */
    public array $handlers = [
        MemoryLogHandler::class => [
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