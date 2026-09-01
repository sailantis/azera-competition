<?php

declare(strict_types=1);

/**
 * Feature demo controller — the enterprise-feature endpoints.
 *
 * CakePHP 5 participates in the features it genuinely supports:
 *  - cache          → Cake Cache (ArrayEngine pool, 10s TTL)
 *  - db-events      → the connection driver's query logger (LoggedQuery)
 *  - events         → Cake EventManager (its own observer system)
 *  - validation     → Cake Validation (Validator)
 *  - config         → plain config class + property reads
 *  - request-scoped → per-request state (explicit reset, worker-style)
 *  - rate-limiter   → fixed-window facade over Cake cache (same contract)
 *
 * AOP (#[Transactional]/#[Cache]/#[Log]/#[Retry]/pipeline) has no native
 * Cake equivalent — those rows are excluded via $adapterFeatures in
 * run.php.
 */

namespace App\Cake\Controller;

use App\Cake\Benchmark;
use App\Cake\Db;
use App\Cake\Support\DbEventLog;
use App\Cake\Support\RateLimiter;
use App\Cake\Support\RequestCounter;
use Cake\Cache\Cache;
use Cake\Event\Event;
use Cake\Event\EventManager;

final class FeatureController extends AppController
{
    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): \Cake\Http\Response
    {
        $this->set('title', 'Features');
        $this->set('features', [
            ['url' => '/features/aop', 'title' => 'Transactional Insert', 'desc' => 'Insert a row inside an explicit DB transaction'],
            ['url' => '/features/cache', 'title' => 'Cache', 'desc' => 'Cache a method result via Cake\'s cache pool (10s TTL)'],
            ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Log method entry/exit/duration via PSR-3 Log::write'],
            ['url' => '/features/retry', 'title' => 'Retry', 'desc' => 'Retry a failing operation up to N times (manual loop)'],
            ['url' => '/features/pipeline', 'title' => 'Pipeline (direct)', 'desc' => 'Explicit middleware/handler pipeline — closure composition'],
            ['url' => '/features/db-events', 'title' => 'Db Events (driver logger)', 'desc' => 'Observe queries + transactions via the driver\'s query logger'],
            ['url' => '/features/events', 'title' => 'Cake Events', 'desc' => 'Dispatch an event via EventManager and show observer output'],
            ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate and coerce input via the Cake Validator'],
            ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access via a plain config class'],
            ['url' => '/features/request-scoped', 'title' => 'RequestScoped', 'desc' => 'Per-request state with explicit worker-style reset'],
            ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter (max 5 per 60s)'],
        ]);

        return $this->render();
    }

    /**
     * GET /features/aop — closest native Cake write path: a transactional
     * ORM insert.  No interceptor pipeline exists, so this runs the row
     * insert inside a connection transaction and reports the outcome (the
     * "aop" feature row is excluded from the winners table via
     * $adapterFeatures, but the endpoint must exist for verify.php parity).
     */
    public function aop(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $now        = date('Y-m-d H:i:s');
        $rand       = random_int(1000, 9999);

        $connection->begin();
        $connection->insert('items', [
            'title'      => 'AOP Item ' . $now . ' #' . $rand,
            'created_at' => $now,
        ]);
        $id = (int) $connection->getDriver()->lastInsertId();
        $connection->commit();

        return $this->json([
            'feature' => 'Cake transactional insert',
            'new_id'  => $id,
            'title'   => 'AOP Item ' . $now . ' #' . $rand,
        ]);
    }

    /**
     * GET /features/cache — cached DB count (array pool, 10s TTL).
     *
     * Matches the 50ms simulated cold query the azera #[Cache] demo pays.
     */
    public function cache(): \Cake\Http\Response
    {
        $pool  = Cache::pool('bench');
        $key   = 'items-count';
        $count = $pool->get($key);

        $start = microtime(true);
        if ($count === null) {
            usleep(50_000); // parity with azera's #[Cache] demo (50ms miss)
            $count = $this->items()->find()->count();
            $pool->set($key, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $start2     = microtime(true);
        $count2     = $pool->get($key);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return $this->json([
            'feature'        => 'Cache (Cake cache pool)',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — method-level logging demo.
     *
     * Cake has no AOP; the closest native hook is Log::write() through its
     * PSR-3 logger.  We log explicitly and echo into the same shape azera
     * produces.  (Named logDemo — Controller::log() is taken by LogTrait.)
     */
    public function logDemo(): \Cake\Http\Response
    {
        $start  = microtime(true);
        $result = 'logged call result';
        \Cake\Log\Log::write('info', 'Cake logged call', ['result' => $result]);

        return $this->json([
            'feature'     => 'Logging (PSR-3, Log::write)',
            'result'      => $result,
            'log_entries' => [
                ['level' => 'info', 'message' => 'Cake logged call', 'elapsed_ms' => round((microtime(true) - $start) * 1000, 2)],
            ],
        ]);
    }

    /**
     * GET /features/retry — manual retry loop (no AOP in Cake).
     */
    public function retry(): \Cake\Http\Response
    {
        $attempts = 0;
        $result   = null;
        $maxTries = 3;

        while ($attempts < $maxTries) {
            $attempts++;
            try {
                if ($attempts < 3) {
                    throw new \RuntimeException('transient failure');
                }
                $result = "succeeded after {$attempts} attempts";
                break;
            } catch (\RuntimeException) {
                continue;
            }
        }

        return $this->json([
            'feature' => 'Retry (manual loop)',
            'result'  => $result,
        ]);
    }

    /**
     * GET /features/pipeline — direct handler composition via closures.
     *
     * Cake's equivalent of an interceptor pipeline is nested middleware.
     * We time a wrapped callable the same way azera's direct Pipeline demo
     * does.
     */
    public function pipeline(): \Cake\Http\Response
    {
        $entries = [];
        $inner   = static fn(): string => 'direct pipeline result';

        $logger = function () use ($inner, &$entries) {
            $t0     = microtime(true);
            $result = $inner();
            $entries[] = 'log: handled in ' . round((microtime(true) - $t0) * 1000, 3) . 'ms';
            return $result;
        };

        $result = $logger();

        return $this->json([
            'feature'     => 'Pipeline (closure composition)',
            'result'      => $result,
            'log_entries' => $entries,
        ]);
    }

    /**
     * GET /features/db-events — native driver query-logger tap.
     */
    public function dbEvents(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $log        = DbEventLog::attach($connection);
        $log->clearEntries();

        $now = date('Y-m-d H:i:s');
        $connection->insert('items', ['title' => 'DbEvent Item ' . $now, 'created_at' => $now]);
        $count = $this->items()->find()->count();

        return $this->json([
            'feature'    => 'Db Events (Cake query logger)',
            'item_count' => $count,
            'events'     => $log->all(),
        ]);
    }

    /**
     * GET /features/events — event dispatch + observer demo via Cake
     * EventManager.
     */
    public function events(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $now        = date('Y-m-d H:i:s');
        $connection->insert('items', ['title' => 'Event Item ' . $now, 'created_at' => $now]);
        $id = (int) $connection->getDriver()->lastInsertId();

        $entries = [];
        $events  = new EventManager();
        $events->on('item.created', function (Event $event) use (&$entries): void {
            $entries[] = 'ItemCreated handled: id=' . $event->getData('id')
                . ' title=' . $event->getData('title');
        });

        $events->dispatch(new Event('item.created', null, ['id' => $id, 'title' => 'Event Item ' . $now]));

        return $this->json([
            'feature'      => 'Cake EventManager',
            'event_class'  => 'item.created',
            'item_id'      => $id,
            'listener_log' => $entries,
        ]);
    }

    /**
     * GET /features/validation — Cake Validator demo.
     */
    public function validation(): \Cake\Http\Response
    {
        $validator = new \Cake\Validation\Validator();
        $validator
            ->requirePresence('name', true, 'name is required')
            ->notEmptyString('name')
            ->minLength('name', 2)
            ->maxLength('name', 100)
            ->requirePresence('email', true, 'email is required')
            ->email('email')
            ->maxLength('email', 255)
            ->allowEmptyString('age')
            ->nonNegativeInteger('age');

        $validPayload   = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => 36];
        $invalidPayload = ['name' => 'X', 'email' => 'not-an-email', 'age' => 150];

        $validErrors   = $validator->validate($validPayload);
        $invalidErrors = $validator->validate($invalidPayload);

        return $this->json([
            'feature'       => 'Validation',
            'description'   => 'Cake Validator checks input arrays against rule definitions.',
            'valid_payload' => [
                'passed' => $validErrors === [],
                'errors' => $validErrors,
            ],
            'invalid_payload' => [
                'passed' => $invalidErrors === [],
                'errors' => $invalidErrors,
            ],
        ]);
    }

    /**
     * GET /features/config — config access demo (plain static class).
     */
    public function config(): \Cake\Http\Response
    {
        return $this->json([
            'feature'     => 'Config (PHP class constants)',
            'description' => 'Configuration access via a plain PHP class (constants, not dot paths).',
            'app_name'    => Benchmark::NAME,
            'page_size'   => Benchmark::PAGE_SIZE,
            'missing'     => 'fallback',
            'all'         => [
                'name'        => Benchmark::NAME,
                'pageSize'    => Benchmark::PAGE_SIZE,
                'sentinelIds' => [
                    'orm' => Benchmark::SENTINEL_ORM_ID,
                    'qb'  => Benchmark::SENTINEL_QB_ID,
                    'api' => Benchmark::SENTINEL_API_ID,
                ],
            ],
        ]);
    }

    /**
     * GET /features/request-scoped — per-request state demo.
     *
     * Cake is FPM-style: every request bootstraps a fresh process, so
     * per-request state is the default.  In worker mode the app instance is
     * reused; we expose the same contract as the other frameworks:
     * increment then show reset-to-zero semantics.
     */
    public function requestScoped(): \Cake\Http\Response
    {
        $counter = new RequestCounter();

        $before = $counter->count();
        $after  = $counter->increment();
        $counter->reset();
        $afterReset = $counter->count();

        return $this->json([
            'feature'           => 'RequestScoped (per-request process in FPM)',
            'count_before'      => $before,
            'count_after'       => $after,
            'count_after_reset' => $afterReset,
        ]);
    }

    /**
     * GET /features/rate-limit — fixed-window facade over Cake cache.
     */
    public function rateLimit(): \Cake\Http\Response
    {
        $ip      = '127.0.0.1';
        $limiter = new RateLimiter();

        $allowed = $limiter->limit('demo-' . $ip, 5, 60);
        $hits    = $limiter->hits('demo-' . $ip);

        return $this->json([
            'feature'   => 'RateLimiter',
            'ip'        => $ip,
            'hits'      => $hits,
            'allowed'   => $allowed,
            'remaining' => max(0, 5 - $hits),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function json(array $data): \Cake\Http\Response
    {
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode($data, JSON_THROW_ON_ERROR));
    }
}