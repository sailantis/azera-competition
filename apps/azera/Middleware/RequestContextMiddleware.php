<?php
/**
 * Request-context middleware.
 *
 * Resolves `locale` and `platform` for the layout footer from the request's
 * `Accept-Language` and `User-Agent` headers, and stamps them into the view
 * engine.
 *
 * THE PARSING IS NOT HERE. It lives in `bench-view-globals.php`, shared with all
 * six other entrants. That file explains why: this middleware used to be the only
 * implementation, so Azera's HTML rows paid for header parsing while the other
 * six returned the same two constants from a `viewGlobals()` array — and Phalcon
 * rendered no footer at all. One entrant doing the work and five asserting the
 * result is a comparison between different work, whichever side you are reading.
 *
 * This class keeps the part that is genuinely Azera's: middleware is the framework
 * mechanism for per-request view context, and it is attached to the routes that
 * render the layout (see Bootstrap::boot() for the group definition and why it is
 * route-scoped). The parsing it delegates is now the same code every entrant runs.
 *
 * WHY IT MUST BE PER REQUEST, NOT A BOOT STEP. It briefly ran from
 * `Bootstrap::buildRequestContext()`, copying Laravel's `View::share()` in
 * `AppServiceProvider::boot()`. At boot no request exists yet, so `$ctx->request()`
 * resolves the container's empty default and the resident arms stamped the values
 * `$_SERVER` happened to hold at process start — every client got `en_US` while the
 * FPM arm, whose entry script re-runs per request, got the real header. Here the
 * request is the live one: `Kernel::handle()` publishes it into the context before
 * the middleware chain runs.
 *
 * Modeled after the sailantis-homepage GeoCurrencyMiddleware but without external
 * API calls or session dependency, so it is self-contained and deterministic.
 */

namespace App\Azera\Middleware;

use Azera\AppContext;
use Azera\Core\MiddlewareInterface;
use Azera\Http\Response;

final class RequestContextMiddleware implements MiddlewareInterface
{
    public function process(AppContext $ctx, callable $next): ?Response
    {
        $request = $ctx->request();

        // Shared parser — see bench-view-globals.php. The two raw header values are
        // read through Azera's own request API and handed over as strings, which is
        // the interface all seven apps use.
        $acceptLanguage = $request->header('Accept-Language');

        $globals = benchViewGlobals(
            is_string($acceptLanguage) ? $acceptLanguage : null,
            $request->userAgent(),
        );

        // Only the two vars a template actually reads. A third, `requestCount`, used to
        // be written here for the since-removed request-scoped demo; no template ever
        // rendered it, so it was per-request work whose only product was an unused
        // array entry.
        $ctx->view()
            ->setVar('locale', $globals['locale'])
            ->setVar('platform', $globals['platform']);

        return $next();
    }
}
