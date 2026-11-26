<?php
/**
 * Lifecycle-hook middleware — the Azera arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that wraps the handler and rewrites what comes
 * back: it stamps a marker header on the response and overrides the status to 203
 * ("Non-Authoritative Information"). That is the smallest honest shape of request
 * lifecycle interception — the hook has to SEE the response and act on it, which a
 * before-only filter could not do.
 *
 * WHY AZERA USES MIDDLEWARE FOR THIS ROW. The row is named for what is measured, not
 * for the abstraction, because the seven frameworks do not agree on one: Azera,
 * Laravel, Spiral and CakePHP have middleware pipelines, CodeIgniter has filters,
 * Symfony has `kernel.request` / `kernel.response` event listeners, and Phalcon's
 * full-MVC application only has events-manager hooks. Each entrant uses its own
 * facility and the comparison is of the intercepted request, not of the machinery.
 * See BenchmarkConfig::featureMap().
 *
 * SEPARATE FROM `RequestContextMiddleware`. That one resolves `locale` / `platform`
 * for the layout footer and runs on the HTML routes. This one exists only to make the
 * lifecycle row measurable, and runs on exactly one route — so neither is dragged into
 * the other's rows.
 */

namespace App\Azera\Middleware;

use Azera\AppContext;
use Azera\Core\MiddlewareInterface;
use Azera\Http\Response;

final class LifecycleHookMiddleware implements MiddlewareInterface
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';
    public const VALUE  = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    public function process(AppContext $ctx, callable $next): ?Response
    {
        $response = $next();

        // A middleware that returned early (or a handler that produced nothing) is
        // passed through untouched: rewriting a non-response would turn a framework
        // short-circuit into a fabricated body.
        if (!$response instanceof Response) {
            return $response;
        }

        $response->setHeader(self::HEADER, self::VALUE);
        $response->setStatus(self::STATUS);

        return $response;
    }
}
