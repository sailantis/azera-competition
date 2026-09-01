<?php

declare(strict_types=1);

/**
 * Feature demo controller — the enterprise-feature endpoints.
 *
 * CodeIgniter 4.7 participates in the features it genuinely supports:
 *  - cache          → CodeIgniter cache (array handler, PSR-16-ish API)
 *  - db-events      → the native `DBQuery` event (Events::on)
 *  - events         → CodeIgniter Events (its own observer/static system)
 *  - validation     → CodeIgniter Validation service
 *  - config         → CodeIgniter Config objects (dot-notation equivalent:
 *                     config(class)->prop, no nested dot paths — we expose
 *                     the page size + sentinel ids from a dedicated config)
 *  - request-scoped → per-request state via Services (shared instances are
 *                     process-wide in CI4 — we reset the counter per request
 *                     the way a worker-mode unmount would)
 *  - rate-limiter   → the built-in Throttler (token bucket) behind a thin
 *                     fixed-window facade to mirror azera's contract
 *
 * AOP (#[Transactional]/#[Cache]/#[Log]/#[Retry]/pipeline) has no native
 * CI4 equivalent (no annotation/interceptor pipeline) — those rows will be
 * excluded via $adapterFeatures in run.php.
 */

namespace Ci4App\Controllers;

use Ci4App\Support\DbEventLog;
use Ci4App\Support\DbEventLogInstance;
use Ci4App\Support\EventsReceiver;
use Ci4App\Support\RateLimiter;
use Ci4App\Support\RequestCounter;

final class Feature extends BaseController
{
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
                ['url' => '/features/aop', 'title' => 'Transactional Insert', 'desc' => 'Insert a row inside an explicit DB transaction'],
                ['url' => '/features/cache', 'title' => 'Cache', 'desc' => 'Cache a method result via the CI4 cache service (10s TTL)'],
                ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Log method entry/exit/duration via PSR-3 log_message()'],
                ['url' => '/features/retry', 'title' => 'Retry', 'desc' => 'Retry a failing operation up to N times (manual loop)'],
                ['url' => '/features/pipeline', 'title' => 'Pipeline (direct)', 'desc' => 'Explicit middleware/handler pipeline — closure composition'],
                ['url' => '/features/db-events', 'title' => 'Db Events (DBQuery)', 'desc' => 'Observe queries via the native DBQuery event tap'],
                ['url' => '/features/events', 'title' => 'CodeIgniter Events', 'desc' => 'Dispatch an event via Events::trigger and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate and coerce input via the Validation service'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Configuration access via CI4 config classes'],
                ['url' => '/features/request-scoped', 'title' => 'RequestScoped', 'desc' => 'Per-request state with explicit worker-style reset'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter (max 5 per 60s)'],
            ]
        ] + $this->viewGlobals());
    }

    /**
     * GET /features/aop — closest native CI4 write path: a transactional
     * model insert.  No interceptor pipeline exists, so this runs the row
     * insert inside Database Transactions via the connection API and
     * reports the outcome (the "aop" feature row is excluded from the
     * winners table via $adapterFeatures, but the endpoint must exist for
     * verify.php parity).
     */
    public function aop(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $db   = \Config\Database::connect();
        $now  = date('Y-m-d H:i:s');
        $rand = random_int(1000, 9999);

        $db->transBegin();
        $db->table('items')->insert([
            'title'      => 'AOP Item ' . $now . ' #' . $rand,
            'created_at' => $now,
        ]);
        $id = $db->insertID();
        $db->transCommit();

        return $this->response->setJSON([
            'feature' => 'CI4 transactional insert',
            'new_id'  => $id,
            'title'   => 'AOP Item ' . $now . ' #' . $rand,
        ]);
    }

    /**
     * GET /features/cache — cached DB count (array handler, 10s TTL).
     *
     * Matches the 50ms simulated cold query the azera #[Cache] demo pays.
     */
    public function cache(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $cache = \Config\Services::cache();
        $key   = 'items-count';
        $count = $cache->get($key);

        $start = microtime(true);
        if ($count === null) {
            usleep(50_000); // parity with azera's #[Cache] demo (50ms miss)
            $count = (new \Ci4App\Models\Item())->countAll();
            $cache->save($key, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 2);

        $start2     = microtime(true);
        $count2     = $cache->get($key);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 2);

        return $this->response->setJSON([
            'feature'        => 'Cache (CI4 cache service)',
            'item_count'     => $count,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — method-level logging demo.
     *
     * CI4 has no AOP; the closest native hook is log_message() through its
     * PSR-3 logger.  We log explicitly and echo into the same shape azera
     * produces.
     */
    public function log(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $start  = microtime(true);
        $result = 'logged call result';
        log_message('info', 'Ci4App logged call', ['result' => $result]);

        return $this->response->setJSON([
            'feature'     => 'Logging (PSR-3, log_message)',
            'result'      => $result,
            'log_entries' => [
                ['level' => 'info', 'message' => 'Ci4App logged call', 'elapsed_ms' => round((microtime(true) - $start) * 1000, 2)],
            ],
        ]);
    }

    /**
     * GET /features/retry — manual retry loop (no AOP in CI4).
     */
    public function retry(): \CodeIgniter\HTTP\ResponseInterface|string
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

        return $this->response->setJSON([
            'feature' => 'Retry (manual loop)',
            'result'  => $result,
        ]);
    }

    /**
     * GET /features/pipeline — direct handler composition via closures.
     *
     * CI4's equivalent of an interceptor pipeline is nested middleware /
     * event observers.  We time a wrapped callable the same way azera's
     * direct Pipeline demo does.
     */
    public function pipeline(): \CodeIgniter\HTTP\ResponseInterface|string
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

        return $this->response->setJSON([
            'feature'     => 'Pipeline (closure composition)',
            'result'      => $result,
            'log_entries' => $entries,
        ]);
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
        $db->table('items')->insert(['title' => 'DbEvent Item ' . $now, 'created_at' => $now]);
        $count = (new \Ci4App\Models\Item())->countAll();

        return $this->response->setJSON([
            'feature'    => 'Db Events (CI4 DBQuery event)',
            'item_count' => $count,
            'events'     => $log->all(),
        ]);
    }

    /**
     * GET /features/events — event dispatch + observer demo via CI4 Events.
     */
    public function events(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');
        $db->table('items')->insert(['title' => 'Event Item ' . $now, 'created_at' => $now]);
        $id = (int) $db->insertID();

        $receiver = new EventsReceiver();
        \CodeIgniter\Events\Events::on('item.created', $receiver);
        \CodeIgniter\Events\Events::trigger('item.created', $id, 'Event Item ' . $now);
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
     * GET /features/request-scoped — per-request state demo.
     *
     * CI4 is FPM-style: every request bootstraps a fresh process, so
     * per-request state is the default.  In worker mode the framework
     * calls CodeIgniter::resetForWorkerMode(); a shared counter service
     * would serialize per request.  We expose the same contract as the
     * other frameworks: increment then show reset-to-zero semantics.
     */
    public function requestScoped(): \CodeIgniter\HTTP\ResponseInterface|string
    {
        $counter = RequestCounter::instance();

        $before = $counter->count();
        $after  = $counter->increment();
        $counter->reset();
        $afterReset = $counter->count();

        return $this->response->setJSON([
            'feature'           => 'RequestScoped (per-request process in FPM)',
            'count_before'      => $before,
            'count_after'       => $after,
            'count_after_reset' => $afterReset,
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