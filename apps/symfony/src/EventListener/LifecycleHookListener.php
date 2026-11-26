<?php

declare(strict_types=1);

namespace App\Symfony\EventListener;

use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Lifecycle hook — the Symfony arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that sees the finished response and rewrites it:
 * it stamps a marker header and overrides the status to 203. That is the smallest
 * honest shape of request lifecycle interception — the hook has to SEE the response,
 * which a before-only listener could not do.
 *
 * WHY A LISTENER AND NOT MIDDLEWARE. Symfony's HttpKernel has no middleware layer at
 * all: it dispatches kernel events (`kernel.request`, `kernel.response`,
 * `kernel.terminate`). A `kernel.response` listener is the framework's own mechanism
 * for exactly this hook, and it is the reason the row is named for the lifecycle rather
 * than for middleware — five of the seven entrants have a middleware pipeline and two
 * (Symfony, Phalcon) do not. The comparison is of the intercepted request, not of the
 * machinery. See BenchmarkConfig::featureMap().
 *
 * SCOPED TO ONE ROUTE. `getRequest()->attributes` is checked before acting, because a
 * listener tagged `kernel.response` runs on EVERY response in the app. Rewriting the
 * status of every page would change what the benchmark measures on all 26 rows; the
 * `_route` attribute is only `feature_lifecycle_hooks` on the demo route (see
 * config/routes.yaml).
 */
final class LifecycleHookListener
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    /** The route this hook applies to — kept in sync with routes.yaml. */
    public const ROUTE = 'feature_lifecycle_hooks';

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') !== self::ROUTE) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set(self::HEADER, self::VALUE);
        $response->setStatusCode(self::STATUS);
    }
}
