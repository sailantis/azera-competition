<?php

declare(strict_types=1);

/**
 * DBAL driver middleware — attaches the DbEventLogger to the connection so
 * query + transaction activity is recorded into DbEventLog (the db-events
 * feature race).
 */

namespace App\Symfony\Service;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

final class DbEventLoggerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly DbEventLogger $logger,
    )
    {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        $middleware = new \Doctrine\DBAL\Logging\Middleware($this->logger);
        return $middleware->wrap($driver);
    }
}