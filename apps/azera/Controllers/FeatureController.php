<?php
/**
 * Feature demo controller — exercises all new enterprise subsystems.
 *
 * Endpoints:
 *   GET  /features           — overview page
 *   GET  /features/aop        — #[Transactional] AOP demo (insert in a transaction)
 *   GET  /features/cache      — #[Cache] AOP demo (cached DB count)
 *   GET  /features/events     — PSR-14 event dispatch + listener demo
 *   GET  /features/rate-limit — RateLimiter demo (max 5 requests / 60s)
 *   GET  /features/security   — Hasher + CSRF token demo
 *   POST /features/security   — CSRF-protected POST demo
 */

namespace App\Controllers;

use App\Events\ItemCreated;
use App\Services\FeatureService;
use Azera\AppContext;
use Azera\Core\Controller;
use Azera\Http\Response;
use Azera\Security\CsrfMiddleware;
use Azera\Security\Hasher;
use Azera\Security\RateLimiter;

class FeatureController extends Controller
{
    /**
     * GET /features — overview page listing all feature demos.
     */
    public function indexAction(): Response
    {
        $html = $this->view()->render('features.index', [
            'features' => [
                ['url' => '/features/aop', 'title' => 'AOP #[Transactional]', 'desc' => 'Insert a row inside an AOP-managed DB transaction'],
                ['url' => '/features/cache', 'title' => 'AOP #[Cache]', 'desc' => 'Cache a method result for 10 seconds via #[Cache] attribute'],
                ['url' => '/features/events', 'title' => 'PSR-14 Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Allow max 5 requests per 60 seconds'],
                ['url' => '/features/security', 'title' => 'Security (Hasher + CSRF)', 'desc' => 'Password hashing and CSRF token protection'],
            ],
        ]);
        return Response::html($html);
    }

    /**
     * GET /features/aop — create an item via #[Transactional] AOP.
     */
    public function aopAction(FeatureService $service): Response
    {
        $title = 'AOP Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999);
        $id    = $service->createItemTransactional($title);

        return Response::json([
            'feature'     => 'AOP #[Transactional]',
            'description' => 'Inserted a row inside a DB transaction managed by the #[Transactional] interceptor — no manual begin/commit/rollback.',
            'new_id'      => $id,
            'title'       => $title,
            'proxy_class' => $service::class,
            'is_proxied'  => $service::class !== FeatureService::class,
        ]);
    }

    /**
     * GET /features/cache — demonstrate #[Cache] AOP caching.
     */
    public function cacheAction(FeatureService $service): Response
    {
        $start     = microtime(true);
        $count     = $service->countItems();
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        // Call again — should be instant (cache hit)
        $start2     = microtime(true);
        $count2     = $service->countItems();
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return Response::json([
            'feature'        => 'AOP #[Cache]',
            'description'    => 'First call runs the query (~50ms); second call hits the cache (~0ms).',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/events — dispatch a PSR-14 event and show listener output.
     */
    public function eventsAction(FeatureService $service): Response
    {
        $title = 'Event Item ' . date('Y-m-d H:i:s');
        $id    = $service->createItemTransactional($title);

        // The listener already ran during dispatch inside createItemTransactional.
        // To show the event/log, we dispatch a fresh event and capture it.
        $event = new ItemCreated($id, $title);
        $this->context()->events()->dispatch($event);

        return Response::json([
            'feature'      => 'PSR-14 Events',
            'description'  => 'Dispatched ItemCreated; the listener stamped the event with a log entry.',
            'event_class'  => ItemCreated::class,
            'item_id'      => $event->id,
            'item_title'   => $event->title,
            'listener_log' => $event->log(),
        ]);
    }

    /**
     * GET /features/rate-limit — rate limiter demo (5 requests per 60s).
     */
    public function rateLimitAction(): Response
    {
        $ctx     = $this->context();
        $limiter = new RateLimiter($ctx->cache());
        $ip      = $ctx->request()->server('REMOTE_ADDR', '127.0.0.1');

        $allowed = $limiter->limit('demo:' . $ip, 5, 60);
        $hits    = $limiter->hits('demo:' . $ip);

        return Response::json([
            'feature'     => 'RateLimiter',
            'description' => 'Max 5 requests per 60 seconds per IP. After 5, requests are denied.',
            'ip'          => $ip,
            'hits'        => $hits,
            'allowed'     => $allowed,
            'remaining'   => max(0, 5 - $hits),
        ], $allowed ? 200 : 429);
    }

    /**
     * GET /features/security — show CSRF token + password hash demo.
     */
    public function securityAction(): Response
    {
        $ctx    = $this->context();
        $hasher = new Hasher();

        // Demo: hash a sample password and verify it
        $plainPassword = 's3cur3-d3m0-p@ss';
        $hash          = $hasher->make($plainPassword);
        $verifyOk      = $hasher->verify($plainPassword, $hash);
        $verifyWrong   = $hasher->verify('wrong-password', $hash);

        // Generate a CSRF token (if session is available)
        $csrfMiddleware = new CsrfMiddleware();
        $session        = $ctx->session();
        $csrfToken      = null;
        if ($session !== null) {
            $csrfToken = $csrfMiddleware->ensureToken($session);
        }

        return Response::json([
            'feature'        => 'Security (Hasher + CSRF)',
            'description'    => 'Password hashing via PHP password_hash() and CSRF token generation.',
            'hash'           => $hash,
            'verify_correct' => $verifyOk,
            'verify_wrong'   => $verifyWrong,
            'needs_rehash'   => $hasher->needsRehash($hash),
            'csrf_token'     => $csrfToken,
            'csrf_note'      => $csrfToken !== null
                ? 'Submit this token as _csrf_token in a POST to /features/security'
                : 'No session available — SessionMiddleware must run first',
        ]);
    }

    /**
     * POST /features/security — CSRF-protected endpoint.
     *
     * Validates the CSRF token from the POST body or X-CSRF-Token header.
     * Returns 419 if the token is missing or mismatched.
     */
    public function securityPostAction(): Response
    {
        $ctx        = $this->context();
        $middleware = new CsrfMiddleware();

        $called = false;
        $result = $middleware->process($ctx, function () use (&$called) {
            $called = true;
            return null;
        });

        if ($result !== null) {
            // CSRF validation failed — middleware returned a 419 response
            return $result;
        }

        return Response::json([
            'feature'     => 'CSRF Protection',
            'description' => 'POST request passed CSRF token validation.',
            'csrf_valid'  => true,
            'post_data'   => $ctx->request()->post(),
        ]);
    }
}