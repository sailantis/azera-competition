<?php

declare(strict_types=1);

/**
 * Feature demo controller — the enterprise-feature endpoints.
 *
 * CodeIgniter 4.7 participates in the features it genuinely supports:
 *  - aop-cache-direct → CodeIgniter cache (array handler) with the cache
 *                     lookup written by hand; there is no attribute-to-advice
 *                     layer, so the AOP form of this row is not CI4's to serve
 *  - db-events      → the native `DBQuery` event (Events::on)
 *  - events         → CodeIgniter Events (its own observer/static system)
 *  - validation     → CodeIgniter Validation service
 *  - config         → CodeIgniter Config objects (dot-notation equivalent:
 *                     config(class)->prop, no nested dot paths — we expose
 *                     the page size + sentinel ids from a dedicated config)
 *  - rate-limiter   → the built-in Throttler (token bucket) behind a thin
 *                     fixed-window facade to mirror azera's contract
 *
 * AOP (#[Transactional]/#[Cache]/#[Log]) has no native
 * CI4 equivalent (no annotation/interceptor pipeline) — those rows will be
 * excluded via $adapterFeatures in run.php.
 */

namespace Ci4App\Controllers;

use Ci4App\Support\DbEventLog;
use Ci4App\Support\DbEventLogInstance;
use Ci4App\Support\EventsReceiver;
use Ci4App\Support\MemoryLogHandler;
use Ci4App\Support\RateLimiter;

final class Feature extends BaseController
{
    /** Fixed rows written by the feature demos — the row count stays stable across benchmark runs. */
    private const FEATURE_SENTINEL_ID = 888800; // aop + db-events
    private const EVENT_SENTINEL_ID = 888801;   // events

    public function __construct()
    {
        // Enable the DBQuery tap the first time any feature endpoint runs.
        DbEventLogInstance::instance()->register();
    }

    /**
     * GET /features — overview page listing all feature demos.
     */
    public function index(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        return view('features', [
            'title'    => 'Features',
            'features' => [
                ['url' => '/features/aop-cache-direct', 'title' => 'Cache (hand-written control)', 'desc' => 'The same cached COUNT with the cache lookup written in application code (10s TTL)'],
                ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Entry/exit records written by and read back from CI4\'s Logger handler'],
                ['url' => '/features/db-events', 'title' => 'Db Events (DBQuery)', 'desc' => 'Observe queries via the native DBQuery event tap'],
                ['url' => '/features/events', 'title' => 'CodeIgniter Events', 'desc' => 'Dispatch an event via Events::trigger and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate and coerce input via the Validation service'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access via CI4 config classes'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter (max 5 per 60s)'],
                ['url' => '/features/lifecycle-hooks', 'title' => 'Request Lifecycle Hooks', 'desc' => 'An after-filter wraps the handler: it stamps a header and sets 203'],
            ]
        ] + $this->viewGlobals());
    }

    /**
     * GET /features/lifecycle-hooks — an `after` filter rewrites the response.
     *
     * The work is on the FILTER, not here:
     * App\CodeIgniter\Filters\LifecycleHookFilter runs after this controller and sets the
     * marker header plus status 203 (registered per-route in Config\Filters). The handler
     * is constant, so the row measures the interception and not the handler.
     *
     * A filter, not middleware: CI4 has no middleware layer, its filter pipeline
     * (`before`/`after`) is the analogue. See BenchmarkConfig::featureMap().
     */
    public function lifecycleHooks(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response->setJSON([
            'feature'     => 'Request Lifecycle Hooks',
            'description' => 'An after-filter wraps the handler, stamps a header and sets 203. CodeIgniter uses its filter pipeline.',
            'lifecycle'   => ['handler' => 'ran'],
        ]);
    }

    /**
     * GET /features/aop-tx-direct — the same upsert inside the same transaction.
     *
     * This is the control for the `aop-tx` row, which CI4 does not serve: it ships
     * no attribute-to-advice layer, so there is no generated-proxy row to compare
     * against here. `aop-tx-direct` is a feature of its own, raced by all seven (see
     * BenchmarkConfig::adapterFeatures()). The upsert runs inside a real
     * begin/commit; it used to run with NO transaction, which would have made this
     * row cheaper than the work it is supposed to represent.
     */
    public function aopDirect(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $db   = \Config\Database::connect();
        $now  = date('Y-m-d H:i:s');
        $rand = random_int(1000, 9999);

        $db->transBegin();
        $db->table('items')->upsert([
            'id'         => self::FEATURE_SENTINEL_ID,
            'title'      => 'AOP Item ' . $now . ' #' . $rand,
            'created_at' => $now,
        ]);
        $db->transCommit();

        return $this->response->setJSON([
            'feature'     => 'Direct transaction (no pipeline)',
            'description' => 'An upsert inside an explicit begin/commit — the hand-written control. CI4 has no attribute-to-advice layer, so it does not serve `/features/aop-tx` at all.',
            'new_id'      => self::FEATURE_SENTINEL_ID,
            'title'       => 'AOP Item ' . $now . ' #' . $rand,
            'log_entries' => [],
        ]);
    }

    /**
     * GET /features/aop-cache-direct — cached DB count (array handler, 10s TTL).
     *
     * The hand-written CONTROL for the `aop-cache` feature: the cache read, the
     * miss decision and the store are written out here, because CI4 has no
     * attribute-to-advice layer that could apply them. Azera serves the same
     * work through a generated `#[Cache]` proxy on `/features/aop-cache`.
     */
    public function aopCacheDirect(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $cache = \Config\Services::cache();
        $key   = 'items-count';

        // The clock starts BEFORE the lookup: the cache read is the work this
        // row prices, so leaving it out made `first_call_ms` report 0 on a hit
        // while the other entrants reported the lookup. Every entrant now times
        // the same span.
        $start = microtime(true);
        $count = $cache->get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            $count = (new \Ci4App\Models\Item())->countAll();
            $cache->save($key, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $start2     = microtime(true);
        $count2     = $cache->get($key);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return $this->response->setJSON([
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
     * GET /features/log — method-level logging through CI4's own logger.
     *
     * CI4 has no AOP, so this is manual instrumentation: `log_message()` before
     * the call and after, which is what a CI4 developer writes for entry/exit
     * logging. The records are read back from the handler the Logger config
     * registers — not rebuilt here.
     *
     * WHY THE READBACK MATTERS. This action used to call `log_message('info', …)`
     * while `Config\Logger::$threshold` was `0`, which CI4 resolves to no
     * loggable levels at all: `Logger::log()` returned before reaching a handler,
     * the message was discarded, and the endpoint returned a hand-built array
     * literal containing a `microtime()` delta measured across the no-op. It
     * reported a log entry the logger never saw, and `elapsed_ms` timed the array
     * construction.
     *
     * `elapsed_ms` now brackets the logged operation itself and travels in the
     * exit record's context, which is where a reader expects the duration of the
     * call it describes.
     */
    public function log(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $handler = $this->memoryLogHandler();

        if ($handler === null) {
            return $this->response->setJSON([
                'feature'     => 'Logging (PSR-3, log_message)',
                'description' => 'No in-memory log handler was registered, so nothing was logged.',
                'result'      => null,
                'log_entries' => [],
            ]);
        }

        $handler->clear();

        log_message('info', 'Entering ' . __METHOD__);

        $start  = hrtime(true);
        $result = 'logged call result';
        $elapsedMs = (hrtime(true) - $start) / 1e6;

        log_message('info', 'Completed ' . __METHOD__ . ' elapsed_ms=' . round($elapsedMs, 4));

        return $this->response->setJSON([
            'feature'     => 'Logging (PSR-3, log_message)',
            'description' => 'Two log_message() calls through CI4\'s Logger, read back from the registered handler.',
            'result'      => $result,
            'log_entries' => $handler->records(),
        ]);
    }

    /**
     * The buffering handler from `Config\Logger::handlers`.
     *
     * Resolved from the live Logger (see `MemoryLogHandler::resolve()`), so in
     * worker mode the demo reads the handler of the CURRENT request's logger
     * rather than one from a previous request. Returns null when the config no
     * longer registers one — the honest answer, instead of printing entries
     * nothing wrote.
     */
    private function memoryLogHandler(): ?MemoryLogHandler
    {
        return MemoryLogHandler::resolve();
    }

    /**
     * GET /features/db-events — native DBQuery event tap.
     */
    public function dbEvents(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $log = DbEventLogInstance::instance();
        $log->clear();

        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) — the row count must stay stable across benchmark runs,
        // otherwise the countAll() below would measure ever-growing work.
        $db->table('items')->upsert([
            'id'         => self::FEATURE_SENTINEL_ID,
            'title'      => 'DbEvent Item ' . $now . ' #' . random_int(1000, 9999),
            'created_at' => $now,
        ]);
        $count = (new \Ci4App\Models\Item())->countAll();

        return $this->response->setJSON([
            'feature'     => 'Db Events (CI4 DBQuery event)',
            'description' => 'CodeIgniter triggers a DBQuery event per executed statement; the listener records the SQL and its duration. This write runs on an autocommit connection, so the log holds queries only.',
            'item_count'  => $count,
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/events — event dispatch + observer demo via CI4 Events.
     */
    public function events(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $db    = \Config\Database::connect();
        $now   = date('Y-m-d H:i:s');
        $title = 'Event Item ' . $now . ' #' . random_int(1000, 9999);
        // Fixed feature sentinel row (upsert, not a fresh INSERT per
        // request) — the row count must stay stable across benchmark runs.
        $db->table('items')->upsert([
            'id'         => self::EVENT_SENTINEL_ID,
            'title'      => $title,
            'created_at' => $now,
        ]);
        $id = self::EVENT_SENTINEL_ID;

        $receiver = new EventsReceiver();
        \CodeIgniter\Events\Events::on('item.created', $receiver);
        \CodeIgniter\Events\Events::trigger('item.created', $id, $title);
        // CI4 Events is a process-global static registry — in worker mode the
        // listener must be deregistered or it accumulates one closure per
        // request (memory leak across benchmark iterations).
        \CodeIgniter\Events\Events::removeListener('item.created', $receiver);

        return $this->response->setJSON([
            'feature'      => 'CodeIgniter Events',
            'event_class'  => 'item.created',
            'item_id'      => $id,
            'listener_log' => $receiver->log(),
        ]);
    }

    /**
     * GET /features/validation — CodeIgniter Validation service demo.
     */
    public function validation(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $rules = [
            'name'  => ['label' => 'Name', 'rules' => 'required|string|min_length[2]|max_length[100]'],
            'email' => ['label' => 'Email', 'rules' => 'required|valid_email|max_length[255]'],
            'age'   => ['label' => 'Age', 'rules' => 'permit_empty|is_natural_no_zero|less_than[121]'],
        ];

        $validPayload   = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => 36];
        $invalidPayload = ['name' => 'X', 'email' => 'not-an-email', 'age' => 150];

        $validation = \CodeIgniter\Config\Services::validation();
        $validation->setRules($rules);
        $validPassed     = $validation->run($validPayload);
        $validationError = fn(): array =>
            is_bool($validPassed) && $validPassed ? [] : $validation->getErrors();

        $validErrors = $validationError();

        $validation2 = \CodeIgniter\Config\Services::validation();
        $validation2->setRules($rules);
        $invalidPassed = $validation2->run($invalidPayload);
        $invalidErrors = $invalidPassed ? [] : $validation2->getErrors();

        return $this->response->setJSON([
            'feature'       => 'Validation',
            'description'   => 'CodeIgniter Validation service checks input against rule strings.',
            'valid_payload' => [
                'passed' => $validPassed,
                'errors' => $validErrors,
            ],
            'invalid_payload' => [
                'passed' => $invalidPassed,
                'errors' => $invalidErrors,
            ],
        ]);
    }

    /**
     * GET /features/config — CodeIgniter config access demo.
     */
    public function config(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $config = config(\Ci4App\Config\Benchmark::class);

        return $this->response->setJSON([
            'feature'     => 'Config (CI4 config classes)',
            'description' => 'Configuration access via CodeIgniter config classes (properties, not dot paths).',
            'app_name'    => $config->name,
            'page_size'   => $config->pageSize,
            'missing'     => property_exists($config, 'doesNotExist') ? $config->doesNotExist : 'fallback',
            'all'         => get_object_vars($config),
        ]);
    }

    /**
     * GET /features/rate-limit — Throttler-backed fixed window.
     */
    public function rateLimit(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $ip      = '127.0.0.1';
        $limiter = new RateLimiter(\Config\Services::cache());

        $allowed = $limiter->limit('demo-' . $ip, 5, 60);
        $hits    = $limiter->hits('demo-' . $ip);

        return $this->response->setJSON([
            'feature'   => 'RateLimiter',
            'ip'        => $ip,
            'hits'      => $hits,
            'allowed'   => $allowed,
            'remaining' => max(0, 5 - $hits),
        ]);
    }
}