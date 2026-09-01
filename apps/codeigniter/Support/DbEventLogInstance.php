<?php

declare(strict_types=1);

/**
 * Per-process singletons used by the CI4 benchmark app.
 *
 * CI4's FPM model gives per-request isolation for free, but our harness
 * reuses ONE process for many requests (worker style).  These holders give
 * the same per-request semantics the other frameworks demo explicitly.
 */

namespace Ci4App\Support;

final class DbEventLogInstance
{
    private static ?DbEventLog $instance = null;

    public static function instance(): DbEventLog
    {
        return self::$instance ??= new DbEventLog();
    }
}