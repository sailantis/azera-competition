<?php
/**
 * Feature demo controller — exercises all new enterprise subsystems.
 *
 * Endpoints:
 *   GET  /features           — overview page
 *   GET  /features/aop-tx    — #[Transactional] AOP demo (insert in a transaction)
 *   GET  /features/aop-tx-explicit   — the same advice via Azera\Aop\Pipeline
 *   GET  /features/aop-tx-direct     — the same insert with a hand-written begin/commit
 *   GET  /features/aop-cache — #[Cache] AOP demo (cached DB count, advice applied by the proxy)
 *   GET  /features/aop-cache-direct  — the same cached count with the cache read written by hand
 *   GET  /features/log       — logging (method entry/exit via the PSR-3 logger)
 *   GET  /features/db-events  — Db event pipeline demo
 *   GET  /features/validation — Validator demo (valid + invalid payloads)
 *   GET  /features/config     — Config dot-notation demo
 *   GET  /features/events     — PSR-14 event dispatch + listener demo
 *   GET  /features/rate-limit — RateLimiter demo (max 5 requests / 60s)
 *   GET  /features/lifecycle-hooks — request/response hook demo (middleware sets 203)
 *   GET  /features/security   — Hasher + CSRF token demo
 *   POST /features/security   — CSRF-protected POST demo
 */

namespace App\Azera\Controllers;

use App\Azera\Events\ItemCreated;
use App\Azera\Services\DbEventLog;
use App\Azera\Services\FeatureService;
use App\Azera\Services\MemoryLogger;
use Azera\Aop\TransactionalInterceptor;
use Azera\AppContext;
use Azera\Core\Controller;
use Azera\Http\Response;
use Azera\Security\CsrfMiddleware;
use Azera\Security\Hasher;
use Azera\Security\RateLimiter;
use Azera\Validation\Validator;

class FeatureController extends Controller
{
    /**
     * GET /features — overview page listing all feature demos.
     */
    public function indexAction(): Response
    {
        $html = $this->view()
            ->render('features.index', [
            'features' => [
                ['url' => '/features/aop-tx', 'title' => 'AOP #[Transactional]', 'desc' => 'Insert a row inside an AOP-managed DB transaction'],
                ['url' => '/features/aop-tx-explicit', 'title' => 'AOP explicit Pipeline', 'desc' => 'Same interceptor composed by hand via Azera\Aop\Pipeline — no proxy generation'],
                ['url' => '/features/aop-tx-direct', 'title' => 'AOP control (hand-written)', 'desc' => 'The same insert with a plain begin/commit — no AOP on either side'],
                ['url' => '/features/aop-cache', 'title' => 'AOP #[Cache]', 'desc' => 'Cache a method result for 10 seconds via #[Cache] attribute'],
                ['url' => '/features/aop-cache-direct', 'title' => 'AOP #[Cache] control (hand-written)', 'desc' => 'The same cached count with the get/miss/set written in application code'],
                ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Entry/exit logs via the registered PSR-3 logger, read back from it'],
                ['url' => '/features/db-events', 'title' => 'Db Events', 'desc' => 'Observe the framework\'s query events around one write and one COUNT'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate and coerce input via the Validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Dot-notation access to a nested config array'],
                ['url' => '/features/events', 'title' => 'PSR-14 Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Allow max 5 requests per 60 seconds'],
                ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'A middleware wraps the handler, stamps a header and sets 203'],
                ['url' => '/features/security', 'title' => 'Security (Hasher + CSRF)', 'desc' => 'Password hashing and CSRF token protection'],
            ],
        ]);
        return Response::html($html);
    }

    /**
     * GET /features/aop-tx — create an item via #[Transactional] AOP.
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
     * GET /features/aop-tx-direct — the SAME insert as /features/aop-tx, but with the
     * transaction written by hand instead of applied by the `#[Transactional]`
     * proxy.
     *
     * This is the CONTROL row, and a feature of its own: every entrant can serve it
     * because it needs no framework facility. `/features/aop-tx` answers "what does this
     * request cost with transparent interception"; this answers "…and implemented
     * directly, with none". The difference is the price of the abstraction on
     * identical work.
     *
     * It is deliberately NOT the framework's explicit-AOP facility — that is
     * `/features/aop-tx-explicit`, which composes a real `Azera\Aop\Pipeline`.
     * Conflating the two published a hand-written `try/catch` as though it exercised
     * the framework's interceptor layer.
     */
    public function aopDirectAction(FeatureService $service): Response
    {
        $title = 'AOP Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999);
        $id    = $service->createItemDirect($title);

        return Response::json([
            'feature'     => 'AOP #[Transactional] — direct twin',
            'description' => 'Identical insert and event dispatch to /features/aop-tx, but the transaction is an explicit begin/commit rather than a #[Transactional] interceptor. The difference between the two rows is the cost of transparent interception.',
            'new_id'      => $id,
            'title'       => $title,
            'proxy_class' => $service::class,
            'is_proxied'  => $service::class !== FeatureService::class,
        ]);
    }

    /**
     * GET /features/aop-tx-explicit — AOP through the framework's explicit
     * `Pipeline`, the second real AOP mode Azera ships.
     *
     * The proxy route above applies advice because an attribute sits on a method.
     * This one composes the SAME `TransactionalInterceptor` by hand and wraps a
     * plain callable with it — no proxy class, no attribute, and the transaction
     * is still opened and committed by framework machinery rather than by
     * application code. That is `Azera\Aop\Pipeline`, documented as "Explicit AOP"
     * in the framework guide.
     *
     * It is deliberately NOT the same row as `/features/aop-tx-direct`, which is the
     * hand-written control every entrant serves (plain `begin`/`commit` in the
     * app). Only Azera and Spiral ship an interceptor abstraction, so only those
     * two are raced on this endpoint — see BenchmarkConfig::adapterFeatures().
     */
    public function aopExplicitAction(FeatureService $service): Response
    {
        $ctx   = $this->context();
        $title = 'Explicit AOP Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999);

        // Composed once per request, which is the unit a reader cares about: the
        // pipeline is application wiring, not a per-call allocation. `pipeline()`
        // is the framework accessor; the interceptor is the very same class the
        // proxy registers, so both modes execute identical BEGIN/COMMIT logic.
        $pipeline = $ctx->pipeline([
            new TransactionalInterceptor($ctx->dbManager()),
        ]);

        $id = $pipeline->call(
            fn(): int => $service->createItemUnmanaged($title),
            identity: FeatureService::class . '::createItemUnmanaged',
        );

        return Response::json([
            'feature'     => 'AOP explicit Pipeline',
            'description' => 'Inserted through Azera\Aop\Pipeline with a TransactionalInterceptor composed by hand — the same interceptor the #[Transactional] proxy registers, with no generated proxy and no attribute.',
            'new_id'      => $id,
            'title'       => $title,
            'proxy_class' => FeatureService::class,
            'is_proxied'  => false,
        ]);
    }

    /**
     * GET /features/aop-cache — demonstrate #[Cache] AOP caching.
     *
     * The cache lookup, the miss decision and the store are performed by the
     * generated `#[Cache]` proxy; this action only times the two calls. That is
     * what makes it the AOP row: the advice is declared on `countItems()` and
     * applied by code the framework generated, not written here.
     *
     * `GET /features/aop-cache-direct` is the control: the same COUNT and the
     * same 10-second TTL with the get/miss/set written out by hand, so the pair
     * prices one generated override plus one interceptor frame on identical work.
     */
    public function aopCacheAction(FeatureService $service): Response
    {
        $start     = microtime(true);
        $count     = $service->countItems();
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        // Second call — the proxy's stored value is returned without the query.
        $start2     = microtime(true);
        $count2     = $service->countItems();
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return Response::json([
            'feature'        => 'AOP #[Cache]',
            'description'    => 'The #[Cache] proxy serves the cached value and runs the COUNT only on a miss; the endpoint itself performs no cache lookup.',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/aop-cache-direct — the CONTROL for `aop-cache`.
     *
     * Deliberately NOT an AOP demo: the cache read, the miss decision and the
     * store are written out here, with no interceptor and no generated proxy.
     * It exists so the `aop-cache` row has a same-work baseline the other six
     * entrants can also serve, exactly as `aop-tx-direct` does for `aop-tx`.
     *
     * The TTL and the key match `FeatureService::$countItems`'s `#[Cache(ttl:
     * 10, key: 'item_count')]`. Sharing the key is what makes the two rows
     * interchangeable: a warm process that ran either one serves the other from
     * the same entry.
     */
    public function aopCacheDirectAction(FeatureService $service): Response
    {
        $cache = $this->context()->cache();

        $start = microtime(true);
        $count = $cache->get(FeatureService::CACHE_KEY);
        if ($count === null) {
            $count = $service->countItemsQuery();
            $cache->set(FeatureService::CACHE_KEY, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $start2     = microtime(true);
        $count2     = $cache->get(FeatureService::CACHE_KEY);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return Response::json([
            'feature'        => 'Cache control (hand-written)',
            'description'    => 'The same COUNT and 10-second TTL with the cache lookup written in application code — no interceptor, no generated proxy.',
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
        $title = 'Event Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999);
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
            // Always 200: the verdict is in `allowed`, not in the status. Every
            // app's limiter cache is process-lifetime, so in BOTH arms the 5-hit
            // budget is spent within the first few iterations and every remaining
            // timed request is a DENIED one. Answering 429 there made the row
            // measure "reject a request" instead of "check a limiter", and it
            // aborted the run once the harness started refusing non-2xx bodies.
            // CakePHP and CodeIgniter always answered 200; this makes all seven
            // agree. See tests/RateLimitStatusTest.php.
        ], 200);
    }

    /**
     * GET /features/lifecycle-hooks — intercept the request/response around the handler.
     *
     * WHAT MAKES THIS COMPARABLE. Every framework has a place to hook the request
     * before the handler runs and the response before it is returned, but they are
     * NOT the same mechanism: Azera, Laravel, Spiral and CakePHP have middleware
     * pipelines, CodeIgniter has filters, Symfony has `kernel.request` /
     * `kernel.response` event listeners, and Phalcon has events-manager hooks. The
     * row is therefore named for what is MEASURED — lifecycle interception — and each
     * entrant uses its own facility. That is the same rule `db-events` follows.
     *
     * THE WORKLOAD. One hook stamps a response header and one sets the status to 203;
     * the handler itself is a constant. Deliberately no database access and no
     * business event, which is what separates this row from `events`.
     *
     * Azera's middleware pipeline is the natural facility here. It is applied to this
     * ONE route rather than globally for the reason Bootstrap::boot() documents: no
     * other entrant runs a middleware stack on the benchmark rows, and charging one
     * would measure the stack instead of the framework.
     */
    public function lifecycleHooksAction(): Response
    {
        // The handler is constant. The 203 status and the marker header are set by the
        // route's middleware (see LifecycleHookMiddleware), so this method's job is to
        // prove the handler ran and nothing more.
        return Response::json([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'A hook wraps the handler, stamps a header and sets 203. Azera uses its middleware pipeline.',
            'lifecycle'   => ['handler' => 'ran'],
        ], 200);
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

    /**
     * GET /features/log — logging through Azera's registered PSR-3 logger.
     *
     * DIRECT, not advised: `FeatureService::logSomething()` writes the entry and
     * exit records itself, so this row is comparable with the six entrants that
     * have no advice layer. Azera's `#[Log]` advice is not wired into this app —
     * intercepting a logger would put proxy cost on a row that is supposed to
     * measure logging, which is the confusion the `aop-*` rows exist to isolate.
     *
     * The records are read back from the logger the service wrote to
     * (`MemoryLogger`), not rebuilt here — a hand-built literal would pass parity
     * while measuring array construction, which is the defect this endpoint used to
     * have on five other entrants.
     */
    public function logAction(MemoryLogger $logger, FeatureService $service): Response
    {
        $logger->clear();
        $result = $service->logSomething('hello from the logging demo');

        return Response::json([
            'feature'     => 'Logging (PSR-3)',
            'description' => 'FeatureService::logSomething() wrote entry and exit records through the registered PSR-3 logger, read back from it.',
            'result'      => $result,
            'log_entries' => $logger->entries(),
        ]);
    }

    /**
     * GET /features/db-events — demonstrate the Db event pipeline.
     */
    public function dbEventsAction(DbEventLog $log, FeatureService $service): Response
    {
        $log->clear();

        // Run a couple of queries so the Db events fire.
        //
        // The write is deliberately the AUTOCOMMIT path, not
        // createItemTransactional(): this row measures the event pipeline, so it
        // must not also charge Azera a transaction proxy the other six entrants
        // do not run. See FeatureService::createItemAutocommit().
        //
        // The count deliberately bypasses the #[Cache] demo (countItems()):
        // a cached hit fires no Db events, so this row must use the plain
        // query path.
        $service->createItemAutocommit('DbEvent Item ' . date('Y-m-d H:i:s') . ' #' . random_int(1000, 9999));
        $service->countItemsQuery();

        return Response::json([
            'feature'     => 'Db Events',
            'description' => 'Database dispatches QueryExecuted per executed statement, StatementPrepared per prepared one, and TransactionStarted/Committed when a transaction is opened, via PSR-14. This handler writes on an autocommit connection, so the log holds queries only.',
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/validation — demonstrate the Validator.
     *
     * Validates a sample payload (valid and invalid) and shows the
     * validated/coerced data plus any error messages.
     */
    public function validationAction(): Response
    {
        // A valid payload — all rules pass.
        $valid = new Validator([
            'name'  => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'age'   => '36',
            'tags'  => ['math', 'code'],
        ]);
        $valid->field('name')->required()->string()->min(2)->max(100);
        $valid->field('email')->required()->email()->max(255);
        $valid->field('age')->optional()->int()->min(18)->max(120);
        $valid->field('tags')->optional()->list(fn($f) => $f->string()->max(50));

        // An invalid payload — several rules fail.
        $invalid = new Validator([
            'name'  => 'X',
            'email' => 'not-an-email',
            'age'   => '150',
        ]);
        $invalid->field('name')->required()->string()->min(2)->max(100);
        $invalid->field('email')->required()->email()->max(255);
        $invalid->field('age')->optional()->int()->min(18)->max(120);

        return Response::json([
            'feature'       => 'Validation',
            'description'   => 'Validator coerces and validates input; errors are keyed by dot-path field name.',
            'valid_payload' => [
                'passed' => !$valid->fails(),
                'data'   => $valid->validated(),
                'errors' => $valid->errors(),
            ],
            'invalid_payload' => [
                'passed' => !$invalid->fails(),
                'data'   => $invalid->validated(),
                'errors' => $invalid->errors(),
            ],
        ]);
    }

    /**
     * GET /features/config — demonstrate the Config dot-notation service.
     */
    public function configAction(): Response
    {
        $config = $this->context()->config();

        return Response::json([
            'feature'     => 'Config',
            'description' => 'Dot-notation access to a nested configuration array.',
            'app_name'    => $config->get('app.name'),
            'db_dsn'      => $config->get('db.dsn'),
            'missing'     => $config->get('does.not.exist', 'fallback'),
            'has_app'     => $config->has('app'),
            'all'         => $config->all(),
        ]);
    }
}