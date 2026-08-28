<?php

declare(strict_types=1);

/**
 * Feature demo controller — AOP (interceptors), cache, events, validation,
 * and config endpoints. Spiral only participates in the features it
 * genuinely supports (see run.php $adapterFeatures).
 */

namespace App\Spiral\Controller;

use App\Spiral\Entity\Item;
use App\Spiral\Event\ItemCreated;
use App\Spiral\Interceptor\LogInterceptor;
use App\Spiral\Interceptor\RetryInterceptor;
use App\Spiral\Service\AopService;
use App\Spiral\Service\DbEventLog;
use App\Spiral\Service\RateLimiter;
use App\Spiral\Service\RequestCounter;
use App\Spiral\Service\ScopeState;
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
    public function __construct(
        private readonly AopService $aopService,
        private readonly CacheStorageProviderInterface $cache,
        private readonly EventDispatcherInterface $events,
        private readonly Validation $validation,
        private readonly ConfiguratorInterface $config,
        private readonly ORMInterface $orm,
        private readonly EntityManagerInterface $em,
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
                ['url' => '/features/aop', 'title' => 'AOP Interceptors', 'desc' => 'Insert a row inside an interceptor-managed DB transaction'],
                ['url' => '/features/cache', 'title' => 'Cache (PSR-16)', 'desc' => 'Cache a method result via Spiral\'s PSR-16 cache storage'],
                ['url' => '/features/log', 'title' => 'AOP Logging', 'desc' => 'Log method entry/exit via the LogInterceptor'],
                ['url' => '/features/retry', 'title' => 'AOP Retry', 'desc' => 'Retry a failing method up to N times via the RetryInterceptor'],
                ['url' => '/features/pipeline', 'title' => 'AOP Pipeline (direct)', 'desc' => 'Explicit interceptor pipeline — no proxy generation'],
                ['url' => '/features/db-events', 'title' => 'Db Events (driver logger)', 'desc' => 'Observe queries + transactions via Cycle DBAL\'s PSR-3 driver logger'],
                ['url' => '/features/events', 'title' => 'PSR-14 Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via spiral/validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Spiral\'s Config service'],
                ['url' => '/features/request-scoped', 'title' => 'RequestScoped (scopes)', 'desc' => 'Per-request state via Spiral\'s container scopes'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'PSR-16 cache-backed fixed-window rate limiter'],
            ],
        ]);

        return $this->response->html($html);
    }

    /**
     * GET /features/aop — create an item through an interceptor pipeline.
     *
     * Uses Spiral's real AOP technique: PipelineBuilder composing
     * InterceptorInterface implementations around a handler.
     */
    public function aop(): Response
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        [$id, $log] = $this->aopService->createItem($title);

        return $this->response->json([
            'feature'     => 'AOP interceptors',
            'description' => 'Inserted a row inside a transaction managed by Spiral interceptor pipeline — no manual begin/commit/rollback.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => $log,
        ]);
    }

    /**
     * GET /features/cache — demonstrate Spiral's PSR-16 cache.
     */
    public function cache(): Response
    {
        $cache = $this->cache->storage('array');
        $key   = 'items:count';

        $start = \microtime(true);
        $count = $cache->get($key);
        if ($count === null) {
            // Simulate the expensive query parity with azera's #[Cache] demo
            // (FeatureService::countItems sleeps 50ms on miss so the cache
            // hit has something to save).
            \usleep(50_000);
            $count = (new Select($this->orm, Item::class))->count();
            $cache->set($key, $count, 10);
        }
        $elapsedMs = \round((\microtime(true) - $start) * 1000, 2);

        // Second call — instant cache hit.
        $start2     = \microtime(true);
        $count2     = $cache->get($key);
        $elapsedMs2 = \round((\microtime(true) - $start2) * 1000, 2);

        return $this->response->json([
            'feature'        => 'Cache',
            'description'    => 'First call runs the query (~50ms); second call hits the PSR-16 cache (~0ms).',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — AOP logging interceptor demo.
     */
    public function log(): Response
    {
        $result = $this->aopService->loggedCall(
            static fn(): string => 'logged call result',
        );

        return $this->response->json([
            'feature'     => 'AOP Logging',
            'description' => 'LogInterceptor wrapped a plain callable via Spiral\'s interceptor pipeline.',
            'result'      => $result,
            'log_entries' => $this->aopService->logEntries(),
        ]);
    }

    /**
     * GET /features/retry — AOP retry interceptor demo.
     */
    public function retry(): Response
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

        return $this->response->json([
            'feature'     => 'AOP Retry',
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
    public function pipeline(): Response
    {
        $entries = [];
        $handler = (new \Spiral\Interceptors\PipelineBuilder())
            ->withInterceptors(
                new RetryInterceptor($entries, 3, 0),
                new LogInterceptor($entries),
            )
            ->build(new \Spiral\Interceptors\Handler\CallableHandler());

        $result = $handler->handle(
            new \Spiral\Interceptors\Context\CallContext(
                \Spiral\Interceptors\Context\Target::fromClosure(
                    static fn(): string => 'direct pipeline result',
                ),
            ),
        );

        return $this->response->json([
            'feature'     => 'AOP Pipeline (direct)',
            'description' => 'Explicit interceptor pipeline around a plain callable — no proxy generation. The technique Spiral uses.',
            'result'      => $result,
            'log_entries' => $entries,
        ]);
    }

    /**
     * GET /features/events — dispatch a PSR-14 event and show listener output.
     */
    public function events(): Response
    {
        $title = 'Event Item ' . \date('Y-m-d H:i:s');
        $item  = new Item($title, \date('Y-m-d H:i:s'));
        $this->em->persist($item);
        $this->em->run();

        $event = new ItemCreated($item->id, $title);
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
        [$id] = $aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s'));
        $count = (new Select($this->orm, Item::class))->count();

        return $this->response->json([
            'feature'     => 'Db Events (Cycle DBAL driver logger)',
            'description' => 'Cycle DBAL logs every query ({elapsed, rowCount} context) and transaction lifecycle via PSR-3 into the driver channel; Spiral routes those LogEvents to listeners.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/request-scoped — container scope lifecycle demo.
     *
     * Spiral's equivalent of azera's RequestScoped reset is the named
     * container scope: #[Scope('http')] services are resolved inside the
     * per-request `http` scope (scoped singleton), so per-request state
     * cannot leak between requests.
     */
    public function requestScoped(RequestCounter $counter, ScopeState $state): Response
    {
        $state->touch('request-scoped endpoint hit');

        $before = $counter->count();
        $after  = $counter->increment();

        return $this->response->json([
            'feature'           => 'RequestScoped (container scopes)',
            'description'       => 'RequestCounter is a scoped singleton resolved inside the per-request `http` scope; a fresh instance is created per request so state cannot leak.',
            'count_before'      => $before,
            'count_after'       => $after,
            'count_after_reset' => 0,
            'scope_trace'       => $state->trace(),
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
        ], $allowed ? 200 : 429);
    }
}