<?php

declare(strict_types=1);

/**
 * Feature demo controller ??? the enterprise-feature endpoints.
 *
 * CakePHP 5 participates in the features it genuinely supports:
 *  - aop-cache-direct ??? Cake Cache (ArrayEngine pool, 10s TTL) with the cache
 *                     lookup written by hand; Cake has no attribute-to-advice
 *                     layer, so the AOP form of this row is not Cake's to serve
 *  - db-events      ??? the connection driver's query logger (LoggedQuery)
 *  - events         ??? Cake EventManager (its own observer system)
 *  - validation     ??? Cake Validation (Validator)
 *  - config         ??? plain config class + property reads
 *  - rate-limiter   ??? fixed-window facade over Cake cache (same contract)
 *
 * AOP (#[Transactional]/#[Cache]/#[Log]) has no native
 * Cake equivalent ??? those rows are excluded via $adapterFeatures in
 * run.php.
 */

namespace App\Cake\Controller;

use App\Cake\Benchmark;
use App\Cake\Db;
use App\Cake\Support\DbEventLog;
use App\Cake\Support\RateLimiter;
use Cake\Cache\Cache;
use Cake\Event\Event;
use Cake\Event\EventManager;

final class FeatureController extends AppController
{
    /** Fixed rows written by the feature demos ??? the row count stays stable across benchmark runs. */
    private const FEATURE_SENTINEL_ID = 888800; // aop + db-events
    private const EVENT_SENTINEL_ID = 888801;   // events

    public function index(): \Cake\Http\Response
    {
        $this->set('title', 'Features');
        $this->set('features', [
            ['url' => '/features/aop-cache-direct', 'title' => 'Cache (hand-written control)', 'desc' => 'The same cached COUNT with the cache lookup written in application code (10s TTL)'],
            ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Entry/exit records written by and read back from Cake\'s log engine'],
            ['url' => '/features/db-events', 'title' => 'Db Events (driver logger)', 'desc' => 'Observe queries + transactions via the driver\'s query logger'],
            ['url' => '/features/events', 'title' => 'Cake Events', 'desc' => 'Dispatch an event via EventManager and show observer output'],
            ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate and coerce input via the Cake Validator'],
            ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access via a plain config class'],
            ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter (max 5 per 60s)'],
            ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'A PSR-15 middleware wraps the handler: it stamps a header and sets 203'],
        ]);

        return $this->render();
    }

    /**
     * GET /features/aop-tx-direct ??? the same upsert in the same transaction.
     *
     * CakePHP has no attribute-to-advice layer, so this row is the hand-written work
     * the `aop-tx` row measures through generated advice elsewhere: `aop-tx-direct`
     * is a feature of its own, raced by all seven (see
     * BenchmarkConfig::adapterFeatures()). Cake does NOT serve `/features/aop-tx`
     * ??? it ships no attribute-to-advice layer ??? so on this app the two AOP rows
     * are the control and the cache control, not a proxy/hand-written pair. The
     * docblock used to call the pipeline the one variable, which described a
     * difference that does not exist in this app.
     */
    public function aopDirect(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $now        = date('Y-m-d H:i:s');
        $rand       = random_int(1000, 9999);

        $connection->begin();
        $connection->insertQuery('items', [
            'id'         => self::FEATURE_SENTINEL_ID,
            'title'      => 'AOP Item ' . $now . ' #' . $rand,
            'created_at' => $now,
        ])
            ->epilog('ON CONFLICT (id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at')
            ->execute();
        $connection->commit();

        return $this->json([
            'feature'     => 'Direct transaction (no pipeline)',
            'description' => 'An upsert inside an explicit begin/commit ??? the hand-written control. CakePHP has no attribute-to-advice layer, so it does not serve `/features/aop-tx` at all.',
            'new_id'      => self::FEATURE_SENTINEL_ID,
            'title'       => 'AOP Item ' . $now . ' #' . $rand,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-cache-direct ??? cached DB count (array pool, 10s TTL).
     *
     * The hand-written CONTROL for the `aop-cache` feature: Cake has no
     * attribute-to-advice layer, so the cache read, the miss decision and the
     * store are written out here while Azera serves the same work through a
     * generated `#[Cache]` proxy.
     */
    public function aopCacheDirect(): \Cake\Http\Response
    {
        $pool = Cache::pool('bench');
        $key  = 'items-count';

        // The clock starts BEFORE the lookup: the cache read is part of the work
        // this row prices. Starting it after made `first_call_ms` report 0 on a
        // hit while the other entrants reported the lookup.
        $start    = microtime(true);
        $count    = $pool->get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            $count = $this->items()->find()->count();
            $pool->set($key, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $start2     = microtime(true);
        $count2     = $pool->get($key);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return $this->json([
            'feature'        => 'Cache control (hand-written)',
            // Not "first call is a miss": in a resident worker both calls hit,
            // and `first_hit` reports which of the two this request saw.
            'description'    => 'The same COUNT and 10-second TTL with the cache lookup written in application code ??? no interceptor, no generated proxy.',
            'item_count'     => $count,
            'first_hit'      => $firstHit,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log ??? method-level logging through Cake's own logger.
     *
     * Cake has no AOP, so this is manual instrumentation: one `Log::write()`
     * before the call and one after, which is what a Cake developer writes when
     * they want entry/exit logging. The records are read back from the engine
     * that `Application::bootstrap()` registered ??? not rebuilt here.
     *
     * WHY THE READBACK MATTERS. This action used to call `Log::write()` (which
     * returned `false`: Cake ships no engine by default, so the message was
     * dropped) and then return a hand-built array literal containing a
     * `microtime()` delta measured across the no-op. It reported a log entry the
     * logger never saw, and `elapsed_ms` timed the array construction.
     *
     * `elapsed_ms` now brackets the logged operation itself and travels in the
     * exit record's context, which is where a reader expects the duration of the
     * call it describes.
     *
     * (Named logDemo ??? Controller::log() is taken by LogTrait.)
     */
    public function logDemo(): \Cake\Http\Response
    {
        $engine = \App\Cake\Application::featureLogEngine();

        if ($engine === null) {
            return $this->json([
                'feature'     => 'Logging (PSR-3, Log::write)',
                'description' => 'Cake had no log engine registered, so nothing was logged.',
                'result'      => null,
                'log_entries' => [],
            ]);
        }

        $engine->clearRecords();

        \Cake\Log\Log::write('info', 'Entering ' . __METHOD__, ['method' => __METHOD__]);

        $start  = hrtime(true);
        $result = 'logged call result';
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        \Cake\Log\Log::write('info', 'Completed ' . __METHOD__, [
            'method'     => __METHOD__,
            'result'     => $result,
            'elapsed_ms' => round($elapsedMs, 4),
        ]);

        return $this->json([
            'feature'     => 'Logging (PSR-3, Log::write)',
            'description' => 'Two Log::write() calls through the registered engine, read back from it.',
            'result'      => $result,
            'log_entries' => $engine->records(),
        ]);
    }

    /**
     * GET /features/db-events ??? native driver query-logger tap.
     */
    public function dbEvents(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $log        = DbEventLog::attach($connection);
        $log->clearEntries();

        $now = date('Y-m-d H:i:s');
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) ??? the row count must stay stable across benchmark runs,
        // otherwise the count() below would measure ever-growing work.
        $connection->insertQuery('items', [
            'id'         => self::FEATURE_SENTINEL_ID,
            'title'      => 'DbEvent Item ' . $now . ' #' . random_int(1000, 9999),
            'created_at' => $now,
        ])
            ->epilog('ON CONFLICT (id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at')
            ->execute();
        $count = $this->items()->find()->count();

        return $this->json([
            'feature'     => 'Db Events (Cake query logger)',
            'description' => 'Cake\'s driver logs every executed statement through its PSR-3 query logger; the ArrayLog engine records them. This write runs on an autocommit connection, so the log holds queries only.',
            'item_count'  => $count,
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/events ??? event dispatch + observer demo via Cake
     * EventManager.
     */
    public function events(): \Cake\Http\Response
    {
        $connection = Db::connection();
        $now        = date('Y-m-d H:i:s');
        $title      = 'Event Item ' . $now . ' #' . random_int(1000, 9999);
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) ??? the row count must stay stable across benchmark runs.
        $connection->insertQuery('items', [
            'id'         => self::EVENT_SENTINEL_ID,
            'title'      => $title,
            'created_at' => $now,
        ])
            ->epilog('ON CONFLICT (id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at')
            ->execute();
        $id = self::EVENT_SENTINEL_ID;

        $entries = [];
        $events  = new EventManager();
        $events->on('item.created', function (Event $event) use (&$entries): void {
            $entries[] = 'ItemCreated handled: id=' . $event->getData('id')
                . ' title=' . $event->getData('title');
        });

        $events->dispatch(new Event('item.created', null, ['id' => $id, 'title' => $title]));

        return $this->json([
            'feature'      => 'Cake EventManager',
            'event_class'  => 'item.created',
            'item_id'      => $id,
            'listener_log' => $entries,
        ]);
    }

    /**
     * GET /features/validation ??? Cake Validator demo.
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
     * GET /features/config ??? config access demo (plain static class).
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
     * GET /features/lifecycle-hooks ??? a PSR-15 middleware rewrites the response.
     *
     * The work is on the MIDDLEWARE, not here:
     * App\Cake\Middleware\LifecycleHookMiddleware is appended to the queue in
     * Application::middleware() and sets the marker header plus status 203 on this
     * controller/action only. The handler is constant, so the row measures the
     * interception and not the handler.
     *
     * Cake has a real middleware layer, unlike Symfony (kernel listeners) and Phalcon
     * (events-manager hooks) ??? which is why the row is named for the request lifecycle
     * rather than for middleware. See BenchmarkConfig::featureMap().
     */
    public function lifecycleHooks(): \Cake\Http\Response
    {
        return $this->json([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'A PSR-15 middleware wraps the handler, stamps a header and sets 203. CakePHP uses its middleware queue.',
            'lifecycle'   => ['handler' => 'ran'],
        ]);
    }

    /**
     * GET /features/rate-limit ??? fixed-window facade over Cake cache.
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
