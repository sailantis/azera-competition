<?php

declare(strict_types=1);

/**
 * Request factory for the RESIDENT worker.
 *
 * WHY THIS EXISTS — it is not a stylistic preference, it is a fix for a
 * measured leak.
 *
 * `ServerRequestFactory::fromGlobals()` calls `Session::create()` on every
 * invocation, and `Cake\Http\Session::__construct()` ends with
 * `session_register_shutdown()` (vendor/cakephp/cakephp/src/Http/Session.php).
 * That call appends an entry to a process-level shutdown-handler list which PHP
 * only releases when the process exits. Under PHP-FPM that is harmless: the
 * process ends after the request, so the list is freed anyway.
 *
 * In a resident worker it is a leak. One worker serves the whole block, so the
 * list grows without bound — measured at ~186 bytes per request, linear and
 * with no ceiling (verified to 80,000 iterations). Nothing in userland can see
 * or collect it: it is not reachable from any class static, static local,
 * `$GLOBALS` entry, closure or container, and `gc_collect_cycles()` does not
 * reclaim it. Over the real benchmark's 210,000 requests that is what produced
 * the resident-memory chart's 5.3 -> 40.6 MB climb for CakePHP while every
 * other framework stayed flat.
 *
 * THE FIX: create the Session ONCE per process and hand the same instance to
 * every request. `Session` is a request-scoped container whose state is
 * `$_SESSION`, not the object itself, so sharing the instance is equivalent for
 * a worker that clears it between requests (see CakePhpAdapter::cleanup()).
 *
 * Everything else is delegated to the parent's own protected marshalling so the
 * request this builds is identical to `fromGlobals()`'s in every other respect.
 */

namespace App\Cake\Support;

use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\Http\Session;
use Cake\Http\UriFactory;
use function Laminas\Diactoros\normalizeServer;

final class WorkerRequestFactory extends ServerRequestFactory
{
    /**
     * The one Session this process shares between requests.
     *
     * Config mirrors what `fromGlobals()` passes — the 'php' default plus the
     * webroot as the cookie path — so the resulting Session is configured
     * identically to the per-request one it replaces.
     */
    private static ?Session $session = null;

    /**
     * The process-wide Session. Exposed so the adapter can clear it between
     * requests, keeping request-scoped semantics.
     */
    public static function session(): Session
    {
        return self::$session ??= Session::create([
            'defaults'   => 'php',
            'cookiePath' => '/',
        ]);
    }

    /**
     * `ServerRequestFactory::fromGlobals()` with a process-wide Session.
     *
     * The only line that differs from the parent is `'session' =>`; the rest is
     * the parent's public implementation plus its own protected marshalling
     * helpers, so nothing about the built request changes.
     *
     * @param array|null $server $_SERVER superglobal
     * @param array|null $query $_GET superglobal
     * @param array|null $parsedBody $_POST superglobal
     * @param array|null $cookies $_COOKIE superglobal
     * @param array|null $files $_FILES superglobal
     */
    public static function fromGlobals(
        ?array $server = null,
        ?array $query = null,
        ?array $parsedBody = null,
        ?array $cookies = null,
        ?array $files = null,
    ): ServerRequest {
        $server = normalizeServer($server ?? $_SERVER);
        ['uri' => $uri, 'base' => $base, 'webroot' => $webroot] = UriFactory::marshalUriAndBaseFromSapi($server);

        $request = new ServerRequest([
            'environment' => $server,
            'uri'         => $uri,
            'cookies'     => $cookies ?? $_COOKIE,
            'query'       => $query ?? $_GET,
            'webroot'     => $webroot,
            'base'        => $base,
            // The one behavioural change: reuse the worker's Session instead of
            // letting the ServerRequest constructor create (and leak) one.
            'session' => static::session(),
            'input'   => $server['CAKEPHP_INPUT'] ?? null,
        ]);

        $request = static::marshalBodyAndRequestMethod($parsedBody ?? $_POST, $request);

        // Required because ServerRequest::scheme() ignores HTTP_X_FORWARDED_PROTO
        // unless trustProxy is enabled, while the Uri built above honours it.
        $uri     = $request->getUri()->withScheme($request->scheme());
        $request = $request->withUri($uri, true);

        return static::marshalFiles($files ?? $_FILES, $request);
    }
}