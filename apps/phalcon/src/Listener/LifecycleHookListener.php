<?php

declare(strict_types=1);

namespace App\Phalcon\Listener;

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;

/**
 * Lifecycle hook — the Phalcon arm of `GET /features/lifecycle-hooks`.
 *
 * WHAT IT DEMONSTRATES. A component that runs after the controller and rewrites what
 * comes back: it stamps a marker header on the response and overrides the status to 203.
 *
 * WHY AN EVENT AND NOT MIDDLEWARE. Phalcon's full-MVC `Application` has NO middleware
 * pipeline — `Phalcon\Mvc\Micro\MiddlewareInterface` exists, but the benchmark runs the
 * full Mvc application, whose lifecycle is a sequence of events-manager hooks
 * (`application:beforeHandleRequest`, `dispatch:afterExecuteRoute`,
 * `application:afterHandleRequest`, …). This listener is therefore the framework's own
 * mechanism for the same work, and it is why the row is named for the request lifecycle
 * rather than for middleware: five of seven entrants have a middleware layer and two
 * (Symfony, Phalcon) do not. See BenchmarkConfig::featureMap().
 *
 * SCOPED TO ONE ROUTE. `dispatch:afterExecuteRoute` fires for every dispatched action, so
 * the listener compares the controller/action before acting. A global rewrite would change
 * the status of all 26 benchmark rows.
 */
final class LifecycleHookListener
{
    /** Header the hook stamps, and the value it writes. */
    public const HEADER = 'X-Bench-Lifecycle';

    public const VALUE = 'hooked';

    /** Status the hook overrides onto every response it wraps. */
    public const STATUS = 203;

    /** The controller/action this hook applies to — kept in sync with Bootstrap.php. */
    public const CONTROLLER = 'feature';

    public const ACTION = 'lifecycleHooks';

    /**
     * @param Event<Dispatcher> $event
     */
    public function afterExecuteRoute(Event $event, Dispatcher $dispatcher): void
    {
        if ($dispatcher->getControllerName() !== self::CONTROLLER
            || $dispatcher->getActionName() !== self::ACTION
        ) {
            return;
        }

        // The dispatcher's own response service, which Bootstrap::handle() reads back
        // after `Application::handle()`. Setting the status here is what a Phalcon
        // middleware would do in `Mvc\Micro`; the events manager reaches the same object.
        $di = $dispatcher->getDI();
        if (!$di->has('response')) {
            return;
        }

        $response = $di->getShared('response');
        $response->setStatusCode(self::STATUS);
        $response->setHeader(self::HEADER, self::VALUE);
    }
}
