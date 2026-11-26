<?php

declare(strict_types=1);

/**
 * Feature demo controller — pipeline interceptors, cache, events,
 * validation, config, request scoping, rate limiting.
 *
 * Mirrors Spiral's FeatureController response shapes exactly, using each
 * feature's idiomatic Laravel technique.
 */

namespace App\Laravel\Http\Controllers;

use App\Laravel\Events\ItemCreated;
use App\Laravel\Models\Item;
use App\Laravel\Service\AopService;
use App\Laravel\Service\DbEventLog;
use App\Laravel\Service\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

use const APP;

use function config;
use function request;
use function response;
use function view;

class FeatureController
{
    /** Fixed row written by the events demo — the row count stays stable across benchmark runs. */
    private const EVENT_SENTINEL_ID = 888801;

    public function __construct(
        private readonly AopService $aopService,
        private readonly DbEventLog $dbLog,
    ) {}

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): \Illuminate\View\View|string
    {
        return view('features', \array_merge(self::viewGlobals(), [
            'features' => [
                ['url' => '/features/aop-cache-direct', 'title' => 'Cache (hand-written control)', 'desc' => 'The same cached COUNT with the cache lookup written in application code'],
                ['url' => '/features/log', 'title' => 'Pipeline Logging', 'desc' => 'Entry/exit records written by Laravel\'s PSR-3 logger (LogManager → Monolog)'],
                ['url' => '/features/db-events', 'title' => 'Db Events (query log)', 'desc' => 'Observe queries + transactions via Laravel\'s DB query events'],
                ['url' => '/features/events', 'title' => 'Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via Laravel\'s Validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Laravel\'s Config repository'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter'],
                ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'A middleware wraps the handler via LifecycleHookMiddleware: it stamps a header and sets 203'],
            ],
        ]));
    }

    /**
     * GET /features/lifecycle-hooks — a middleware wraps this handler.
     *
     * The work is on the ROUTE, not here: `LifecycleHookMiddleware` runs around this
     * method and sets the marker header plus status 203. The handler is constant, so
     * the row measures the interception and not the handler.
     *
     * Kept in this controller rather than a dedicated one because the route is a
     * feature demo, exactly like its neighbours; the comparison is of the request
     * lifecycle, and the body only has to prove the handler ran.
     */
    public function lifecycleHooks(): JsonResponse
    {
        return response()->json([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'A middleware wraps the handler, stamps a header and sets 203. Laravel uses its middleware pipeline.',
            'lifecycle'   => ['handler' => 'ran'],
        ]);
    }

    /**
     * GET /features/aop-tx-direct — the same upsert with no interceptor pipeline.
     *
     * Control for the `aop-tx` row, which Laravel does not serve: it has no
     * attribute-to-advice layer. `aop-tx-direct` is a feature of its own, raced by
     * all seven (see BenchmarkConfig::adapterFeatures()).
     */
    public function aopDirect(): JsonResponse
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        $id    = $this->aopService->createItemDirect($title);

        return response()->json([
            'feature'     => 'Direct transaction (no pipeline)',
            'description' => 'An upsert on an autocommit connection with no interceptor around it — the hand-written control. Laravel has no attribute-to-advice layer, so it does not serve `/features/aop-tx` at all.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-cache-direct — demonstrate Laravel's Cache repository.
     *
     * The hand-written CONTROL for the `aop-cache` feature: Laravel has no
     * attribute-to-advice layer that applies caching to a service method, so the
     * cache read, the miss decision and the store are written out here; Azera
     * serves the same work through a generated `#[Cache]` proxy.
     */
    public function aopCacheDirect(): JsonResponse
    {
        $key = 'items:count';

        $start    = \microtime(true);
        $count    = Cache::get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            $count = Item::query()->count();
            Cache::put($key, $count, 10);
        }
        $elapsedMs = \round((\microtime(true) - $start) * 1000, 2);

        // Second call — instant cache hit.
        $start2     = \microtime(true);
        $count2     = Cache::get($key);
        $elapsedMs2 = \round((\microtime(true) - $start2) * 1000, 2);

        return response()->json([
            'feature'        => 'Cache control (hand-written)',
            // Not "first call is a miss": in a resident worker both calls hit,
            // and `first_hit` reports which of the two this request saw.
            'description'    => 'The same COUNT and 10-second TTL with the cache lookup written in application code — no interceptor, no generated proxy.',
            'item_count'     => $count,
            'first_hit'      => $firstHit,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — Laravel's logging stack behind a pipeline interceptor.
     *
     * `LogInterceptor` writes through `LoggerInterface`, which Laravel resolves to
     * `LogManager` → Monolog. The records come back from Monolog's in-memory
     * handler, so this reports what the logger wrote rather than rebuilding it in
     * the controller.
     *
     * This used to be an interceptor appending strings to an array the
     * application owned: no logger was involved at all, and the report's
     * "PSR-3 everywhere" sentence was false for this entrant.
     */
    public function log(): JsonResponse
    {
        $result = $this->aopService->loggedCall(
            static fn(): string => 'logged call result',
        );

        return response()->json([
            'feature'     => 'Pipeline Logging',
            'description' => 'LogInterceptor wrote entry and exit records through Laravel\'s PSR-3 logger (LogManager → Monolog), read back from the channel.',
            'result'      => $result,
            'log_entries' => $this->aopService->logRecords(),
        ]);
    }

    /**
     * GET /features/events — dispatch an event and show listener output.
     */
    public function events(): JsonResponse
    {
        $title = 'Event Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) — the row count must stay stable across benchmark runs.
        DB::table('items')->upsert(
            [
                'id'         => self::EVENT_SENTINEL_ID,
                'title'      => $title,
                'created_at' => \date('Y-m-d H:i:s'),
            ],
            ['id'],
            ['title', 'created_at'],
        );
        $id = self::EVENT_SENTINEL_ID;

        $event = new ItemCreated($id, $title);
        Event::dispatch($event);

        return response()->json([
            'feature'      => 'Events',
            'description'  => 'Dispatched ItemCreated; the listener stamped the event with a log entry.',
            'event_class'  => ItemCreated::class,
            'item_id'      => $event->id,
            'item_title'   => $event->title,
            'listener_log' => $event->logEntries(),
        ]);
    }

    /**
     * GET /features/validation — Laravel Validator demo.
     */
    public function validation(): JsonResponse
    {
        $rules = [
            'name'  => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email'],
            'age'   => ['required', 'integer', 'min:18', 'max:120'],
        ];

        $valid = Validator::make(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => 36],
            $rules,
        );

        $invalid = Validator::make(
            ['name' => 'X', 'email' => 'not-an-email', 'age' => 150],
            $rules,
        );

        return response()->json([
            'feature'       => 'Validation',
            'description'   => 'Laravel Validator checks input against rules; errors are keyed by field name.',
            'valid_payload' => [
                'passed' => $valid->passes(),
                'errors' => (object) $valid->errors()->all(),
            ],
            'invalid_payload' => [
                'passed' => $invalid->passes(),
                'errors' => $invalid->errors()->messages(),
            ],
        ]);
    }

    /**
     * GET /features/config — Laravel Config dot-notation demo.
     */
    public function config(): JsonResponse
    {
        $all = config('benchmark');

        return response()->json([
            'feature'     => 'Config',
            'description' => 'Configuration access through Laravel\'s Config repository.',
            'app_name'    => config('benchmark.name'),
            'page_size'   => config('benchmark.benchmark.pageSize'),
            'missing'     => config('benchmark.does.not.exist', 'fallback'),
            'all'         => $all,
        ]);
    }

    /**
    * GET /features/db-events — DB activity observation demo.
    *
    * Laravel's idiomatic DB observation hook: `DB::listen()` query events
    (with elapsed ms and SQL) plus transaction lifecycle callbacks on the
    connection. The DbEventLog service records entries so the demo can show
    * the observation pipeline is live.
    */
    public function dbEvents(): JsonResponse
    {
        $this->dbLog->clear();

        // Run a couple of queries + a transaction so the query events fire.
        [$id] = $this->aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999));
        $count = Item::query()->count();

        return response()->json([
            'feature'     => 'Db Events (query log)',
            'description' => 'Laravel fires a QueryExecuted event per executed query ({elapsed, sql}). This write runs on an autocommit connection, so no transaction events fire here.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $this->dbLog->all(),
        ]);
    }

    /**
     * GET /features/rate-limit — cache-backed rate limiter demo
     * (5 requests per 60 seconds), mirroring azera's RateLimiter.
     */
    public function rateLimit(): JsonResponse
    {
        $ip  = request()->ip() ?? '127.0.0.1';
        $key = 'demo:' . $ip;

        $limiter = app(RateLimiter::class);

        $allowed = $limiter->limit($key, 5, 60);
        $hits    = $limiter->hits($key);

        return response()->json([
            'feature'     => 'RateLimiter',
            'description' => 'Max 5 requests per 60 seconds per IP. After 5, requests are denied.',
            'ip'          => $ip,
            'hits'        => $hits,
            'allowed'     => $allowed,
            'remaining'   => \max(0, 5 - $hits),
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
     * View globals — locale and platform for the layout footer, resolved by the
     * SAME parser every other entrant runs (`bench-view-globals.php`).
     *
     * These were two `View::share()` constants stamped at boot, which meant the
     * footer asserted a locale and platform Laravel had never resolved while Azera
     * paid to parse them. Resolving per render makes the work the same on both
     * sides; the values are identical because the benchmark sends no header hints.
     *
     * @return array{locale: string, platform: string}
     */
    private static function viewGlobals(): array
    {
        return benchViewGlobalsFromGlobals();
    }
}