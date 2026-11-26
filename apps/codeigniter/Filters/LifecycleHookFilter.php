<?php

declare(strict_types=1);

namespace Ci4App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Lifecycle-hook filter ??? the CodeIgniter 4 arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that wraps the handler and rewrites what comes
 * back: an `after` filter stamps a marker header and overrides the status to 203. That
 * is the smallest honest shape of request lifecycle interception ??? the hook has to SEE
 * the response, which a before-only filter could not do.
 *
 * WHY A FILTER AND NOT MIDDLEWARE. CI4 has no middleware: its mechanism is the filter
 * pipeline (`before` / `after` around the controller), which is the direct analogue. The
 * row is named for the request lifecycle rather than for middleware because only five of
 * the seven entrants have a middleware pipeline ??? Symfony uses kernel event listeners
 * and Phalcon uses events-manager hooks. See BenchmarkConfig::featureMap().
 *
 * SCOPED TO ONE ROUTE. Registered in Config\Filters::$filters for
 * `features/lifecycle-hooks` only, not in `$globals`/`$required`: no other entrant runs a
 * filter/middleware stack on the benchmark rows, so a global registration would charge
 * the work to every row.
 */
final class LifecycleHookFilter implements FilterInterface
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    /**
     * Nothing to do before the controller: the work is on the RESPONSE, which only the
     * `after` phase is handed.
     *
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader(self::HEADER, self::VALUE);
        $response->setStatusCode(self::STATUS);

        return $response;
    }
}

