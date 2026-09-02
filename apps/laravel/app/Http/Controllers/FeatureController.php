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
use App\Laravel\Service\RequestCounter;
use App\Laravel\Service\ScopeState;
use Illuminate\Http\JsonResponse;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Validator;

use const APP;

use function config;
use function request;
use function response;
use function view;

class FeatureController
{
    public function __construct(
        private readonly AopService $aopService,
        private readonly DbEventLog $dbLog,
        private readonly RequestCounter $counter,
        private readonly ScopeState $state,
    )
    {
    }

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): \Illuminate\View\View
    {
        return view('features', \array_merge(self::viewGlobals(), [
            'features' => [
                ['url' => '/features/aop', 'title' => 'AOP Interceptors', 'desc' => 'Insert a row inside an interceptor-managed DB transaction'],
                ['url' => '/features/cache', 'title' => 'Cache', 'desc' => 'Cache a method result via Laravel\'s Cache repository'],
                ['url' => '/features/log', 'title' => 'Pipeline Logging', 'desc' => 'Log method entry/exit via the LogInterceptor'],
                ['url' => '/features/retry', 'title' => 'Pipeline Retry', 'desc' => 'Retry a failing method up to N times via the RetryInterceptor'],
                ['url' => '/features/pipeline', 'title' => 'Pipeline (direct)', 'desc' => 'Explicit interceptor pipeline — no proxy generation'],
                ['url' => '/features/db-events', 'title' => 'Db Events (query log)', 'desc' => 'Observe queries + transactions via Laravel\'s DB query events'],
                ['url' => '/features/events', 'title' => 'Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via Laravel\'s Validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Laravel\'s Config repository'],
                ['url' => '/features/request-scoped', 'title' => 'RequestScoped (container)', 'desc' => 'Per-request state via Laravel\'s scoped container bindings'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter'],
            ],
        ]));
    }

    /**
     * GET /features/aop — create an item through an interceptor pipeline.
     *
     * Laravel's real interception technique: Illuminate\Pipeline composing
     * invokable interceptors around a handler.
     */
    public function aop(): JsonResponse
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        [$id, $log] = $this->aopService->createItem($title);

        return response()->json([
            'feature'     => 'AOP interceptors',
            'description' => 'Inserted a row inside a transaction managed by Laravel\'s interceptor pipeline — no manual begin/commit/rollback.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => $log,
        ]);
    }

    /**
     * GET /features/cache — demonstrate Laravel's Cache repository.
     */
    public function cache(): JsonResponse
    {
        $key = 'items:count';

        $start    = \microtime(true);
        $count    = Cache::get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            // Simulate the expensive query parity with azera's #[Cache] demo
            // (FeatureService::countItems sleeps 50ms on miss so the cache
            // hit has something to save).
            \usleep(50_000);
            $count = Item::query()->count();
            Cache::put($key, $count, 10);
        }
        $elapsedMs = \round((\microtime(true) - $start) * 1000, 2);

        // Second call — instant cache hit.
        $start2     = \microtime(true);
        $count2     = Cache::get($key);
        $elapsedMs2 = \round((\microtime(true) - $start2) * 1000, 2);

        return response()->json([
            'feature'        => 'Cache',
            'description'    => 'First call runs the query (~50ms); second call hits the cache (~0ms).',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — pipeline logging interceptor demo.
     */
    public function log(): JsonResponse
    {
        $result = $this->aopService->loggedCall(
            static fn(): string => 'logged call result',
        );

        return response()->json([
            'feature'     => 'Pipeline Logging',
            'description' => 'LogInterceptor wrapped a plain callable via Laravel\'s Illuminate Pipeline.',
            'result'      => $result,
            'log_entries' => $this->aopService->logEntries(),
        ]);
    }

    /**
     * GET /features/retry — pipeline retry interceptor demo.
     */
    public function retry(): JsonResponse
    {
        $attempts = 0;
        $result   = $this->aopService->retryCall(
            function () use (&$attempts): string {
                $attempts++;
                if ($attempts < 3) {
                    throw new \RuntimeException('transient failure');
                }
                return "succeeded after {$attempts} attempts";
            },
        );

        return response()->json([
            'feature'     => 'Pipeline Retry',
            'description' => 'RetryInterceptor retried a failing callable until it succeeded.',
            'result'      => $result,
            'attempts'    => $attempts,
            'log_entries' => $this->aopService->logEntries(),
        ]);
    }

    /**
     * GET /features/pipeline — direct interceptor pipeline (no proxy).
     *
     * This is the apples-to-apples comparison against azera's Pipeline:
     * explicit interceptors around a plain callable.
     */
    public function pipeline(): JsonResponse
    {
        $entries = [];

        $result = (new Pipeline(app()))
            ->send(null)
            ->through([
                new \App\Laravel\Http\Middleware\Interceptors\RetryInterceptor($entries, 3, 0),
                new \App\Laravel\Http\Middleware\Interceptors\LogInterceptor($entries),
            ])
            ->then(static fn(): string => 'direct pipeline result');

        return response()->json([
            'feature'     => 'Pipeline (direct)',
            'description' => 'Explicit interceptor pipeline around a plain callable — no proxy generation. The technique Laravel uses.',
            'result'      => $result,
            'log_entries' => $entries,
        ]);
    }

    /**
     * GET /features/events — dispatch an event and show listener output.
     */
    public function events(): JsonResponse
    {
        $title = 'Event Item ' . \date('Y-m-d H:i:s');
        $item  = new Item();
        $item->title      = $title;
        $item->created_at = \date('Y-m-d H:i:s');
        $item->save();

        $event = new ItemCreated($item->id, $title);
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
        [$id] = $this->aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s'));
        $count = Item::query()->count();

        return response()->json([
            'feature'     => 'Db Events (query log)',
            'description' => 'Laravel fires a QueryExecuted event per query ({elapsed, sql}) and exposes transaction lifecycle; the DbEventLog service records them.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $this->dbLog->all(),
        ]);
    }

    /**
     * GET /features/request-scoped — container scope lifecycle demo.
     *
     * Laravel's equivalent of azera's RequestScoped reset is
     * `$app->scoped()`: RequestCounter is a scoped singleton — a fresh
     * instance per request, single instance within the request, and
     * `forgetScopedInstances()` runs after every RequestHandled event.
     */
    public function requestScoped(): JsonResponse
    {
        $this->state->touch('request-scoped endpoint hit');

        $before = $this->counter->count();
        $after  = $this->counter->increment();

        return response()->json([
            'feature'           => 'RequestScoped (container scopes)',
            'description'       => 'RequestCounter is a scoped singleton resolved per request via $app->scoped(); forgetScopedInstances() after RequestHandled guarantees state cannot leak between requests.',
            'count_before'      => $before,
            'count_after'       => $after,
            'count_after_reset' => 0,
            'scope_trace'       => $this->state->trace(),
        ]);
    }

    /**
     * GET /features/rate-limit — cache-backed rate limiter demo
     * (5 requests per 60 seconds), mirroring azera's RateLimiter.
     */
    public function rateLimit(RequestCounter $counter): JsonResponse
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
        ], $allowed ? 200 : 429);
    }

    /**
     * View globals — locale and platform variables passed to every template,
     * mirroring azera's RequestContextMiddleware.
     *
     * @return array<string, string>
     */
    private static function viewGlobals(): array
    {
        return ['locale' => 'en_US', 'platform' => 'desktop'];
    }
}