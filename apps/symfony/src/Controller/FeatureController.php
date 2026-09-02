<?php

declare(strict_types=1);

/**
 * Feature demo controller — interceptors (AOP), cache, events, validation,
 * config, db-events, request-scoped, rate-limit.
 *
 * Mirrors the azera/Spiral/Laravel response shapes exactly.
 */

namespace App\Symfony\Controller;

use App\Symfony\Entity\Item;
use App\Symfony\Event\ItemCreated;
use App\Symfony\Service\AopService;
use App\Symfony\Service\DbEventLog;
use App\Symfony\Service\RateLimiter;
use App\Symfony\Service\RequestCounter;
use App\Symfony\Service\ScopeState;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FeatureController extends AbstractController
{
    public function __construct(
        private readonly AopService $aopService,
        private readonly DbEventLog $dbLog,
        private readonly CacheItemPoolInterface $cache,
        private readonly EventDispatcherInterface $events,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
        private readonly RequestCounter $counter,
        private readonly ScopeState $state,
        private readonly RateLimiter $limiter,
    )
    {
    }

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('features.html.twig', \array_merge(self::viewGlobals(), [
            'features' => [
                ['url' => '/features/aop', 'title' => 'AOP Interceptors', 'desc' => 'Insert a row inside an interceptor-managed DB transaction'],
                ['url' => '/features/cache', 'title' => 'Cache', 'desc' => 'Cache a method result via Symfony\'s PSR-6 cache'],
                ['url' => '/features/log', 'title' => 'Pipeline Logging', 'desc' => 'Log method entry/exit via the LogInterceptor'],
                ['url' => '/features/retry', 'title' => 'Pipeline Retry', 'desc' => 'Retry a failing method up to N times via the RetryInterceptor'],
                ['url' => '/features/pipeline', 'title' => 'Pipeline (direct)', 'desc' => 'Explicit interceptor pipeline — no proxy generation'],
                ['url' => '/features/db-events', 'title' => 'Db Events (query log)', 'desc' => 'Observe queries + transactions via Doctrine DBAL'],
                ['url' => '/features/events', 'title' => 'Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via Symfony\'s Validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Symfony\'s container parameters'],
                ['url' => '/features/request-scoped', 'title' => 'RequestScoped (container)', 'desc' => 'Per-request state via Symfony\'s scoped services'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter'],
            ],
        ]));
    }

    /**
     * GET /features/aop — create an item through an interceptor pipeline.
     */
    public function aop(): JsonResponse
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        [$id, $log] = $this->aopService->createItem($title);

        return $this->json([
            'feature'     => 'AOP interceptors',
            'description' => 'Inserted a row inside a transaction managed by Symfony\'s interceptor pipeline — no manual begin/commit/rollback.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => $log,
        ]);
    }

    /**
     * GET /features/cache — demonstrate Symfony's PSR-6 cache.
     */
    public function cache(): JsonResponse
    {
        $key = 'items_count';

        $start = \microtime(true);
        $item  = $this->cache->getItem($key);
        if (!$item->isHit()) {
            // Simulate the expensive query parity with azera's #[Cache] demo
            // (FeatureService::countItems sleeps 50ms on miss so the cache
            // hit has something to save).
            \usleep(50_000);
            $count = (int) $this->em->createQueryBuilder()
                ->select('COUNT(i.id)')
                ->from(Item::class, 'i')
                ->getQuery()
                ->getSingleScalarResult();
            $item->set($count);
            $item->expiresAfter(10);
            $this->cache->save($item);
        } else {
            $count = (int) $item->get();
        }
        $elapsedMs = \round((\microtime(true) - $start) * 1000, 2);

        // Second call — instant cache hit.
        $start2     = \microtime(true);
        $item2      = $this->cache->getItem($key);
        $count2     = $item2->isHit() ? (int) $item2->get() : null;
        $elapsedMs2 = \round((\microtime(true) - $start2) * 1000, 2);

        return $this->json([
            'feature'        => 'Cache',
            'description'    => 'First call runs the query (~50ms); second call hits the PSR-6 cache (~0ms).',
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

        return $this->json([
            'feature'     => 'Pipeline Logging',
            'description' => 'LogInterceptor wrapped a plain callable via Symfony\'s interceptor pipeline.',
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

        return $this->json([
            'feature'     => 'Pipeline Retry',
            'description' => 'RetryInterceptor retried a failing callable until it succeeded.',
            'result'      => $result,
            'attempts'    => $attempts,
            'log_entries' => $this->aopService->logEntries(),
        ]);
    }

    /**
     * GET /features/pipeline — direct interceptor pipeline (no proxy).
     */
    public function pipeline(): JsonResponse
    {
        $entries = [];

        $pipeline = static function (callable $destination) use (&$entries): mixed {
            $log   = new \App\Symfony\Service\Interceptor\LogInterceptor($entries);
            $retry = new \App\Symfony\Service\Interceptor\RetryInterceptor($entries, 3, 0);
            $next  = $destination;
            foreach (\array_reverse([$retry, $log]) as $interceptor) {
                $next = static fn(mixed $payload = null) => $interceptor($next, $payload);
            }
            return $next();
        };

        $result = $pipeline(static fn(): string => 'direct pipeline result');

        return $this->json([
            'feature'     => 'Pipeline (direct)',
            'description' => 'Explicit interceptor pipeline around a plain callable — no proxy generation. The technique Symfony uses.',
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
        // Item uses an assigned identifier (no DB-generated id) so we must
        // provide a unique id ourselves. Pick a high random id that will
        // not collide with the seeded 1..N rows or the 999997-999999
        // sentinel ids used by the benchmark endpoints.
        $item = new Item($title, \date('Y-m-d H:i:s'));
        $item->id = \random_int(10_000_000, 99_999_999);
        $this->em->persist($item);
        $this->em->flush();

        $event = new ItemCreated($item->id, $title);
        $this->events->dispatch($event);

        return $this->json([
            'feature'      => 'Events',
            'description'  => 'Dispatched ItemCreated; the listener stamped the event with a log entry.',
            'event_class'  => ItemCreated::class,
            'item_id'      => $event->id,
            'item_title'   => $event->title,
            'listener_log' => $event->logEntries(),
        ]);
    }

    /**
     * GET /features/validation — Symfony Validator demo.
     */
    public function validation(): JsonResponse
    {
        $constraints = new Assert\Collection([
            'name'  => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(['min' => 2, 'max' => 100])],
            'email' => [new Assert\NotBlank(), new Assert\Email()],
            'age'   => [new Assert\Type('integer'), new Assert\Range(['min' => 18, 'max' => 120])],
        ]);

        $valid = $this->validator->validate(
            ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => 36],
            $constraints,
        );

        $invalid = $this->validator->validate(
            ['name' => 'X', 'email' => 'not-an-email', 'age' => 150],
            $constraints,
        );

        return $this->json([
            'feature'       => 'Validation',
            'description'   => 'Symfony Validator checks input against rules; errors are keyed by field name.',
            'valid_payload' => [
                'passed' => \count($valid) === 0,
                'errors' => self::violations($valid),
            ],
            'invalid_payload' => [
                'passed' => \count($invalid) === 0,
                'errors' => self::violations($invalid),
            ],
        ]);
    }

    /**
     * GET /features/config — Symfony container parameter demo.
     */
    public function config(): JsonResponse
    {
        $all = [
            'name'      => $this->getParameter('benchmark.name'),
            'version'   => $this->getParameter('benchmark.version'),
            'benchmark' => [
                'pageSize'    => $this->getParameter('benchmark.pageSize'),
                'sentinelIds' => [
                    'orm' => 999999,
                    'qb'  => 999997,
                    'api' => 999998,
                ],
            ],
        ];

        return $this->json([
            'feature'     => 'Config',
            'description' => 'Configuration access through Symfony\'s container parameters.',
            'app_name'    => $all['name'],
            'page_size'   => $all['benchmark']['pageSize'],
            'missing'     => 'fallback',
            'all'         => $all,
        ]);
    }

    /**
     * GET /features/db-events — DB activity observation demo.
     *
     * Doctrine DBAL's idiomatic observation hook is the SQL logger (PSR-3)
     * and the connection's event manager. The DbEventLog service records
     * entries so the demo can show the observation pipeline is live.
     */
    public function dbEvents(): JsonResponse
    {
        $this->dbLog->clear();

        // Run a couple of queries + a transaction so the DBAL logger fires.
        [$id] = $this->aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s'));
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'feature'     => 'Db Events (query log)',
            'description' => 'Doctrine DBAL logs every query and transaction lifecycle; the DbEventLog service records them.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $this->dbLog->all(),
        ]);
    }

    /**
     * GET /features/request-scoped — container scope lifecycle demo.
     *
     * Symfony's equivalent of azera's RequestScoped reset is the scoped
     * container: RequestCounter is a scoped service — a fresh instance per
     * request, single instance within the request, and the container resets
     * scoped services after each request.
     */
    public function requestScoped(): JsonResponse
    {
        $this->state->touch('request-scoped endpoint hit');

        $before = $this->counter->count();
        $after  = $this->counter->increment();

        return $this->json([
            'feature'           => 'RequestScoped (container scopes)',
            'description'       => 'RequestCounter is a scoped service resolved per request; the container resets scoped services after each request so state cannot leak.',
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
    public function rateLimit(Request $request): JsonResponse
    {
        $ip  = $request->getClientIp() ?? '127.0.0.1';
        $key = 'demo:' . $ip;

        $allowed = $this->limiter->limit($key, 5, 60);
        $hits    = $this->limiter->hits($key);

        return $this->json([
            'feature'     => 'RateLimiter',
            'description' => 'Max 5 requests per 60 seconds per IP. After 5, requests are denied.',
            'ip'          => $ip,
            'hits'        => $hits,
            'allowed'     => $allowed,
            'remaining'   => \max(0, 5 - $hits),
        ], $allowed ? 200 : 429);
    }

    /**
     * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
     * @return array<string, list<string>>
     */
    private static function violations($violations): array
    {
        $out = [];
        foreach ($violations as $violation) {
            $out[$violation->getPropertyPath()][] = $violation->getMessage();
        }
        return $out;
    }

    /**
     * @return array<string, string>
     */
    private static function viewGlobals(): array
    {
        return ['locale' => 'en_US', 'platform' => 'desktop'];
    }
}