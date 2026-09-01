<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Exception-handler config (app-required by Services::exceptions — the
 * Config\ namespace is framework-fixed). Error views point at the
 * framework's built-in system/Views/errors so the worker never renders a
 * missing app view. Logging is disabled for benchmark parity (threshold 0).
 */
final class Exceptions extends BaseConfig
{
    public bool $log = false;

    public array $ignoreCodes = [404];

    /**
     * CI4's dist package keeps the error views in its vendor app dir
     * (app/Views/errors/{html,cli}) — there is no system/Views/errors.
     * Point straight there so 404/500 pages render without shipping copies.
     */
    public string $errorViewPath = ROOTPATH . 'vendor/codeigniter4/framework/app/Views/errors';

    public array $sensitiveDataInTrace = [];

    public bool $logDeprecations = false;

    public string $deprecationLogLevel = LogLevel::DEBUG;

    public function handler(int $statusCode, Throwable $exception): ExceptionHandlerInterface
    {
        return new ExceptionHandler($this);
    }
}