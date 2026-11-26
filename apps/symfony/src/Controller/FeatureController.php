<?php

declare(strict_types=1);

/**
 * Feature demo controller — interceptors (AOP), cache, events, validation,
 * config, db-events, rate-limit.
 *
 * Mirrors the azera/Spiral/Laravel response shapes exactly.
 */

namespace App\Symfony\Controller;

use App\Symfony\Entity\Item;
use App\Symfony\Event\ItemCreated;
use App\Symfony\Service\AopService;
use App\Symfony\Service\DbEventLog;
use App\Symfony\Service\RateLimiter;
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
    /** Fixed row written by the events demo — the row count stays stable across benchmark runs. */
    private const EVENT_SENTINEL_ID = 888801;

    /** Fixed row written by the db-events demo — ditto. */
    private const DBEVENT_SENTINEL_ID = 888802;

    public function __construct(
        private readonly AopService $aopService,
        private readonly DbEventLog $dbLog,
        private readonly CacheItemPoolInterface $cache,
        private readonly EventDispatcherInterface $events,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
        private readonly RateLimiter $limiter,
        private readonly \Doctrine\DBAL\Connection $connection,
    ) {}

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        return $this->render('features.html.twig', \array_merge(self::viewGlobals($request), [
            'features' => [
                ['url' => '/features/aop-cache-direct', 'title' => 'Cache (hand-written control)', 'desc' => 'The same cached COUNT with the cache lookup written in application code (PSR-6)'],
                ['url' => '/features/log', 'title' => 'Pipeline Logging', 'desc' => 'Entry/exit records written by Symfony\'s PSR-3 logger'],
                ['url' => '/features/db-events', 'title' => 'Db Events (query log)', 'desc' => 'Observe queries + transactions via Doctrine DBAL'],
                ['url' => '/features/events', 'title' => 'Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via Symfony\'s Validator'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access through Symfony\'s container parameters'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter'],
                ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'A kernel.response listener wraps the handler: it stamps a header and sets 203'],
            ],
        ]));
    }

    /**
     * GET /features/lifecycle-hooks — a kernel.response listener rewrites the response.
     *
     * The work is on the LISTENER, not here: App\Symfony\EventListener\LifecycleHookListener
     * runs on `kernel.response` and sets the marker header plus status 203. The handler
     * is constant, so the row measures the interception and not the handler.
     *
     * Symfony has no middleware layer — its HttpKernel dispatches kernel events — which
     * is why this demo uses a listener where Laravel and Azera use middleware. The row
     * is named for the lifecycle for exactly that reason. See
     * BenchmarkConfig::featureMap().
     */
    public function lifecycleHooks(): JsonResponse
    {
        return new JsonResponse([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'A kernel.response listener wraps the handler, stamps a header and sets 203. Symfony uses HttpKernel events.',
            'lifecycle'   => ['handler' => 'ran'],
        ]);
    }

    /**
     * GET /features/aop-tx-direct — the same upsert with no interceptor pipeline.
     *
     * Control for the `aop-tx` row, which Symfony does not serve: it has no
     * attribute-to-advice layer. `aop-tx-direct` is a feature of its own, raced by
     * all seven (see BenchmarkConfig::adapterFeatures()).
     */
    public function aopDirect(): JsonResponse
    {
        $title = 'AOP Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999);
        $id    = $this->aopService->createItemDirect($title);

        return $this->json([
            'feature'     => 'Direct transaction (no pipeline)',
            'description' => 'An upsert on an autocommit connection with no interceptor around it — the hand-written control. Symfony has no attribute-to-advice layer, so it does not serve `/features/aop-tx` at all.',
            'new_id'      => $id,
            'title'       => $title,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-cache-direct — demonstrate Symfony's PSR-6 cache.
     *
     * The hand-written CONTROL for the `aop-cache` feature: Symfony ships no
     * attribute-to-advice layer (its lazy proxies only wrap services; they do
     * not apply caching to a method), so the cache read, the miss decision and
     * the store are written out here. Azera serves the same work through a
     * generated `#[Cache]` proxy.
     */
    public function aopCacheDirect(): JsonResponse
    {
        $key = 'items_count';

        $start = \microtime(true);
        $item  = $this->cache->getItem($key);
        $firstHit = $item->isHit();
        if (!$firstHit) {
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
     * GET /features/log — Symfony's PSR-3 logger behind a pipeline interceptor.
     *
     * `LogInterceptor` writes through `Psr\Log\LoggerInterface`, which Symfony's
     * container resolves to the in-memory logger named in `services.yaml`. The
     * records come back from that logger, so this reports what was logged rather
     * than rebuilding it in the controller.
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

        return $this->json([
            'feature'     => 'Pipeline Logging',
            'description' => 'LogInterceptor wrote entry and exit records through Symfony\'s PSR-3 logger, read back from it.',
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
        // A high id far above the seeded 1..N rows and the 999997-999999
        // benchmark sentinels.
        $this->connection->executeStatement(
            'INSERT INTO items (id, title, created_at) VALUES (:id, :title, :created_at)'
                . ' ON CONFLICT(id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at',
            [
                'id'         => self::EVENT_SENTINEL_ID,
                'title'      => $title,
                'created_at' => \date('Y-m-d H:i:s'),
            ],
            ['id' => \Doctrine\DBAL\ParameterType::INTEGER],
        );

        $event = new ItemCreated(self::EVENT_SENTINEL_ID, $title);
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

        // Write the FIXED feature sentinel row + a transaction so the DBAL
        // logger fires. Upsert (not a fresh INSERT per request) — the row
        // count must stay stable across benchmark runs, otherwise the
        // COUNT below would measure ever-growing work.
        [$id] = $this->aopService->createItem('DbEvent Item ' . \date('Y-m-d H:i:s') . ' #' . \random_int(1000, 9999));
        $count = (int) $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(Item::class, 'i')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'feature'     => 'Db Events (query log)',
            'description' => 'Doctrine DBAL\'s PSR-3 query logger records every executed statement as it passes through the driver middleware. This write runs on an autocommit connection, so no transaction events fire here.',
            'new_id'      => $id,
            'item_count'  => $count,
            'events'      => $this->dbLog->all(),
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
     * View globals — locale and platform for the layout footer, resolved by the
     * SAME parser every other entrant runs (`bench-view-globals.php`).
     *
     * See BenchController::viewGlobals() for why this is not a constant pair.
     *
     * @return array{locale: string, platform: string}
     */
    private static function viewGlobals(?Request $request = null): array
    {
        if ($request === null) {
            return benchViewGlobalsFromGlobals();
        }

        return benchViewGlobals(
            $request->headers->get('Accept-Language'),
            $request->headers->get('User-Agent'),
        );
    }
}