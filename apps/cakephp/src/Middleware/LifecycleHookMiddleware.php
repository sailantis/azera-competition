<?php

declare(strict_types=1);

namespace App\Cake\Middleware;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Lifecycle-hook middleware ??? the CakePHP arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that wraps the handler and rewrites what comes
 * back: it stamps a marker header and overrides the status to 203. That is the smallest
 * honest shape of request lifecycle interception ??? the hook has to SEE the response,
 * which a before-only filter could not do.
 *
 * SCOPED TO ONE ROUTE. Cake's `MiddlewareQueue` is global, so this middleware checks the
 * resolved routing result and returns early on every other route. That is the only way to
 * scope it without reimplementing the pipeline: a global hook would rewrite the status of
 * all 26 benchmark rows, and no other entrant runs a middleware stack on them at all.
 *
 * WHY A MIDDLEWARE AND NOT AN EVENT. PSR-15 middleware is Cake's native mechanism for
 * wrapping a request, and Cake is one of the five entrants that really has a middleware
 * layer (Symfony and Phalcon do not ??? they use kernel listeners and events-manager hooks,
 * which is why the row is named for the request lifecycle rather than for middleware).
 * See BenchmarkConfig::featureMap().
 */
final class LifecycleHookMiddleware implements MiddlewareInterface
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    /** The controller this hook applies to ??? kept in sync with Application.php. */
    public const CONTROLLER = 'Feature';

    public const ACTION = 'lifecycleHooks';

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // The queue is global, so act only on the demo route.
        //
        // Cake exposes the resolved routing result as a `params` ARRAY attribute, not as
        // flat `controller` / `action` attributes — RoutingMiddleware writes
        // `withAttribute('params', $params)` (see its line ~105). Reading the flat keys
        // returns null on every request, which makes the guard silently match nothing and
        // the hook never run.
        $params = (array) $request->getAttribute('params', []);

        if (($params['controller'] ?? null) !== self::CONTROLLER
            || ($params['action'] ?? null) !== self::ACTION
        ) {
            return $response;
        }

        if (!$response instanceof Response) {
            return $response;
        }

        return $response
            ->withHeader(self::HEADER, self::VALUE)
            ->withStatus(self::STATUS);
    }
}

