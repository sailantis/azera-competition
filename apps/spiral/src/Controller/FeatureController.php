<?php

declare(strict_types=1);

/**
 * Feature demo controller — AOP (interceptors), cache, events, validation,
 * and config endpoints. Spiral only participates in the features it
 * genuinely supports (see BenchmarkConfig::$adapterFeatures). Its cache row is
 * the hand-written control `aop-cache-direct`: Spiral ships no
 * attribute-to-advice layer, so the `aop-cache` row itself is Azera's alone.
 */

namespace App\Spiral\Controller;

use App\Spiral\Entity\Item;
use App\Spiral\Event\ItemCreated;
use App\Spiral\Interceptor\TransactionalInterceptor;
use App\Spiral\Service\AopService;
use App\Spiral\Service\DbEventLog;
use App\Spiral\Service\RateLimiter;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Http\ResponseWrapper;
use Spiral\Logger\ListenerRegistryInterface;
use Spiral\Views\ViewsInterface;
use Spiral\Validator\FilterDefinition;
use Spiral\Validator\Validation;

class FeatureController
{
    /** Fixed row written by the events demo — the row count stays stable across benchmark runs. */
    private const EVENT_SENTINEL_ID = 888801;

    public function __construct(
        private readonly AopService $aopService,
        private readonly CacheStorageProviderInterface $cache,
        private readonly EventDispatcherInterface $events,
        private readonly Validation $validation,
        private readonly ConfiguratorInterface $config,
        private readonly ORMInterface $orm,
        private readonly EntityManagerInterface $em,
        private readonly \Cycle\Database\DatabaseInterface $db,
        private readonly ResponseWrapper $response,
        private readonly ViewsInterface $views,
        private readonly ServerRequestInterface $request,
    ) {}

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): Response
    {
        $html = $this->views->render('features', [
            'locale'   => 'en_US',
            'platform' => 'desktop',
            'features' => [
                ['url' => '/features/aop-tx-explicit', 'title' => 'AOP explicit Pipeline', 'desc' => 'PipelineBuilder composing a TransactionalInterceptor around the handler'],
                ['url' => '/features/aop-cache-direct', 'title' => 'Cache (hand-written control)', 'desc' => 'The same cached COUNT with the cache lookup written in application code (PSR-16, 10s TTL)'],
                ['url' => '/features/log', 'title' => 'AOP Logging', 'desc' => 'Entry/exit records written by Spiral\'s PSR-3 logger and captured by its log listeners'],
                ['url' => '/features/db-events', 'title' => 'Db Events (driver logger)', 'desc' => 'Observe queries + transactions via Cycle DBAL\'s PSR-3 driver logger'],
                ['url' => '/features/events', 'title' => 'PSR-14 Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via spiral/validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Spiral\'s Config service'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'PSR-16 cache-backed fixed-window rate limiter'],
                ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'A PSR-15 middleware wraps the handler: it stamps a header and sets 203'],
            ],
        ]);

        return $this->response->html($html);
    }

    /**
     * GET /features/lifecycle-hooks — a PSR-15 middleware wraps this handler.
     *
     * The work is on the ROUTE, not here: App\Spiral\Middleware\LifecycleHookMiddleware is
     * attached with `Route::withMiddleware()` and sets the marker header plus status 203.
     * The handler is constant, so the row measures the interception and not the handler.
     *
     * PSR-15 middleware, not Spiral's interceptor layer: this row is about the request
     * lifecycle and PSR-15 is the framework's own mechanism for wrapping a handler. The
     * interceptor layer is what `aop-tx-explicit` exercises. See
     * BenchmarkConfig::featureMap().
     */
    public function lifecycleHooks(): Response
    {
        return $this->response->json([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'A PSR-15 middleware wraps the handler, stamps a header and sets 203. Spiral uses its middleware pipeline.',
            'lifecycle'   => ['handler' => 'ran'],
        ]);
    }

    /**
     * GET /features/aop-tx-explicit — AOP through Spiral's explicit interceptor
     * pipeline, the mode `PipelineBuilder` exists for.
     *
     * Spiral does not serve `/features/aop-tx`: it ships no attribute-to-advice
     * layer, so it cannot answer the TRANSPARENT row. What it does ship is the
     * interceptor abstraction, and this endpoint composes a real
     * {@see \App\Spiral\Interceptor\TransactionalInterceptor} through
     * `PipelineBuilder`, so the BEGIN/COMMIT on the measured path is executed by
     * Spiral's own pipeline. Spiral ships the abstraction but no transaction
     * interceptor, so the class is application code — the same split a Spiral app
     * has.
     *
     * Only Azera and Spiral are raced here: they are the two entrants with a
     * dedicated interceptor abstraction. See
     * BenchmarkConfig::adapterFeatures().
     */
    public function aopExplicit(): Response
    {
        $title = 'Explicit AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);

        $id = $this->aopService->runThrough(
            fn(): int => $this->aopService->createItemUnmanaged($title),
            new TransactionalInterceptor($this->db),
        );

        return $this->response->json([
            'feature'     => 'AOP explicit Pipeline',
            'description' => 'Inserted through Spiral\Interceptors\PipelineBuilder with a TransactionalInterceptor — Spiral ships the interceptor abstraction, the application supplies the transaction interceptor.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-tx-direct — the same upsert with no interceptor pipeline.
     *
     * Control for the `aop-tx` row, which Spiral does not serve: it has no
     * attribute-to-advice layer. `aop-tx-direct` is a feature of its own, raced by
     * all seven (see BenchmarkConfig::adapterFeatures()).
     */
    public function aopDirect(): Response
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        $id    = $this->aopService->createItemDirect($title);

        return $this->response->json([
            'feature'     => 'Direct transaction (no pipeline)',
            'description' => 'An upsert on an autocommit connection with no interceptor around it — the hand-written control. Spiral has no attribute-to-advice layer, so it does not serve `/features/aop-tx` at all.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-cache-direct — demonstrate Spiral's PSR-16 cache.
     *
     * The hand-written CONTROL for the `aop-cache` feature. Spiral has no
     * attribute-to-advice layer that applies caching to a service method, so the
     * cache read, the miss decision and the store are written out here; Azera
     * serves the same work through a generated `#[Cache]` proxy.
     */
    public function aopCacheDirect(): Response
    {
        $cache = $this->cache->storage('array');
        $key   = 'items:count';

        $start = \microtime(true);
        $count = $cache->get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            $count = (new Select($this->orm, Item::class))->count();
            $cache->set($key, $count, 10);
        }
        $elapsedMs = \round((\microtime(true) - $start) * 1000, 2);

        // Second call — instant cache hit.
        $start2     = \microtime(true);
        $count2     = $cache->get($key);
        $elapsedMs2 = \round((\microtime(true) - $start2) * 1000, 2);

        return $this->response->json([
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
     * GET /features/log — Spiral's logger behind an interceptor.
     *
     * `LogInterceptor` writes through `Psr\Log\LoggerInterface`, which comes from
     * Spiral's own log factory (`LogsInterface::getLogger('bench')`). That logger
     * turns each call into a `Spiral\Logger\Event\LogEvent` and hands it to the
     * listeners on `ListenerRegistryInterface`; the records below are what those
     * listeners captured.
     *
     * This used to be an interceptor appending strings to an array the
     * application owned: no logger was involved at all, and the report's
     * "PSR-3 everywhere" sentence was false for this entrant.
     */
    public function log(): Response
    {
        $result = $this->aopService->loggedCall(
            static fn(): string => 'logged call result',
        );

        return $this->response->json([
            'feature'     => 'AOP Logging',
            'description' => 'LogInterceptor wrote entry and exit records through Spiral\'s PSR-3 logger (LogsInterface → ListenerRegistry), read back from the listeners.',
            'result'      => $result,
            'log_entries' => $this->aopService->logRecords(),
        ]);
    }

    /**
     * GET /features/events — dispatch a PSR-14 event and show listener output.
     */
    public function events(): Response
    {
        $title = 'Event Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) — the row count must stay stable across benchmark runs.
        $this->db->insert('items')
            ->values([
                'id'         => self::EVENT_SENTINEL_ID,
                'title'      => $title,
                'created_at' => \date('Y-m-d H:i:s'),
            ])
            ->onConflict('id')
            ->run();

        $event = new ItemCreated(self::EVENT_SENTINEL_ID, $title);
        $this->events->dispatch($event);

        return $this->response->json([
            'feature'      => 'PSR-14 Events',
            'description'  => 'Dispatched ItemCreated; the listener stamped the event with a log entry.',
            'event_class'  => ItemCreated::class,
            'item_id'      => $event->id,
            'item_title'   => $event->title,
            'listener_log' => $event->logEntries(),
        ]);
    }

    /**
     * GET /features/validation — spiral/validator demo.
     */
    public function validation(): Response
    {
        // Rule format: ['checker::method', 'args' => [...]]. Args are
        // positional (passed after the value, e.g. range(value, min, max)).
        $rules = [
            'name'  => [['string::range', 'args' => [2, 100]]],
            'email' => [['string::regexp', 'args' => ['/^[^@]+@[^@]+\.[a-z]{2,}$/i']]],
            'age'   => [['number::range', 'args' => [18, 120]]],
        ];

        $valid = $this->validation->validate(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => 36],
            $rules,
        );

        $invalid = $this->validation->validate(
            ['name' => 'X', 'email' => 'not-an-email', 'age' => 150],
            $rules,
        );

        return $this->response->json([
            'feature'       => 'Validation',
            'description'   => 'Spiral validator checks input against rules; errors are keyed by field name.',
            'valid_payload' => [
                'passed' => $valid->isValid(),
                'errors' => $valid->getErrors(),
            ],
            'invalid_payload' => [
                'passed' => $invalid->isValid(),
                'errors' => $invalid->getErrors(),
            ],
        ]);
    }

    /**
     * GET /features/config — Spiral Config dot-notation demo.
     */
    public function config(): Response
    {
        $all = $this->config->getConfig('app');

        return $this->response->json([
            'feature'     => 'Config',
            'description' => 'Configuration access through Spiral\'s Config service.',
            'app_name'    => $all['name'] ?? null,
            'page_size'   => $all['benchmark']['pageSize'] ?? null,
            'missing'     => $all['does']['not']['exist'] ?? 'fallback',
            'all'         => $all,
        ]);
    }

    /**
     * GET /features/db-events — DB activity observation demo.
     *
     * Cycle DBAL has no PSR-14 DB event pipeline; the idiomatic Spiral
     * observation hook is the driver's PSR-3 logger (query messages with
     * {elapsed, rowCount} context + Begin/Commit transaction messages),
     * routed through Spiral's LogsInterface as LogEvents. The DbEventLog
     * listener is attached in AppBootloader.
     */
    public function dbEvents(AopService $aopService, DbEventLog $log): Response
    {
        $log->clear();

        // Run a couple of queries + a transaction so the DBAL logger fires.
        [$id] = $aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999));
        $count = (new Select($this->orm, Item::class))->count();

        return $this->response->json([
            'feature'     => 'Db Events (Cycle DBAL driver logger)',
            'description' => 'Cycle DBAL logs every executed query with {elapsed, rowCount} context to the driver channel; Spiral routes those LogEvents to listeners. This write runs on an autocommit connection, so no transaction events fire here.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/rate-limit — PSR-16 cache-backed rate limiter demo
     * (5 requests per 60 seconds), mirroring azera's RateLimiter.
     */
    public function rateLimit(RateLimiter $limiter): Response
    {
        $ip  = $this->request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1';
        $key = 'demo:' . $ip;

        $allowed = $limiter->limit($key, 5, 60);
        $hits    = $limiter->hits($key);

        return $this->response->json([
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
}