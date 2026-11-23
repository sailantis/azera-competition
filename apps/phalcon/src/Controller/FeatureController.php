<?php

declare(strict_types=1);

namespace App\Phalcon\Controller;

use App\Phalcon\Bootstrap;
use App\Phalcon\Support\DbEventLog;
use App\Phalcon\Support\MemoryLoggerAdapter;
use App\Phalcon\Support\RateLimiter;
use Phalcon\Cache\CacheInterface;
use Phalcon\Config\Config;
use Phalcon\Events\ManagerInterface;
use Phalcon\Filter\Validation;
use Phalcon\Filter\Validation\Validator\Between;
use Phalcon\Filter\Validation\Validator\Email;
use Phalcon\Filter\Validation\Validator\Numericality;
use Phalcon\Filter\Validation\Validator\PresenceOf;
use Phalcon\Filter\Validation\Validator\StringLength;
use Phalcon\Logger\Logger;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View\Simple as SimpleView;

/**
 * The feature endpoints, mirroring the other six apps' FeatureController.
 *
 * WHY THIS EXISTS. The harness measures 25 endpoints against every entrant, and
 * 11 of them are the feature demos. This app shipped with 14, so every real run
 * died at the first missing one:
 *
 *   [ABORT] fpm/phalcon GET /features/aop: HTTP status 404
 *
 * Worse, the volucer arm "passed" the same block, because a 404 body went out as
 * 200 OK and the client guard only recognised `Not Found`. Eleven endpoints were
 * 404s and the run reported "250201 requests, 0 failed" (2026-11-21). Both halves
 * are fixed: this controller supplies the routes, and response-guard.php makes a
 * 4xx body fatal wherever it is served.
 *
 * WHAT PHALCON ACTUALLY HAS. Following the CI4 and CakePHP entrants — which also
 * lack an interceptor pipeline — the endpoints are built on the primitives the
 * framework does ship, and the AOP-only rows stay excluded from the winners table
 * through adapterFeatures() in scripts/report/BenchmarkConfig.php:
 *
 *   aop            → the closest native write path: an upsert inside an explicit
 *                    DB transaction (no #[Transactional] interceptor exists)
 *   log, retry,    → demonstrated with plain code + Phalcon's Logger, which is
 *   pipeline          what CI4 and Cake do for the same rows
 *   db-events      → Phalcon's own db events manager (`db:beforeQuery`,
 *                    `db:afterQuery`, `db:beginTransaction`, `db:commitTransaction`)
 *   events         → Phalcon\Events\Manager
 *   validation     → Phalcon\Filter\Validation
 *   config         → Phalcon\Config (path() gives the dot-notation access)
 *   request-scoped → an explicit reset, the resident-worker contract the
 *                    bootstrap's cleanup() implements for the DI container
 *   rate-limiter   → fixed-window facade over Phalcon's cache, same contract
 *                    as the CI4 and Cake limiter
 *
 * Payload shapes are fixed by the benchmark contract: verify.php asserts each
 * response contains a specific key, and the report compares the JSON that comes
 * back, so the keys stay identical to every other app's.
 */
final class FeatureController extends Controller
{
    /**
     * Fixed row written by the write demos.
     *
     * An upsert, not a fresh INSERT per request: these endpoints run once per
     * benchmark iteration, and unbounded INSERTs would grow the shared table
     * until every COUNT-style read measured different work per app. Same
     * sentinel ids as the other entrants.
     */
    private const FEATURE_SENTINEL_ID = 888800; // aop + db-events
    private const EVENT_SENTINEL_ID = 888801;   // events


    /**
     * GET /features — overview page listing every demo.
     */
    public function indexAction(): void
    {
        $this->view->setVars([
            'title'    => 'Features',
            'features' => [
                ['url' => '/features/aop', 'title' => 'Transactional Insert', 'desc' => 'Insert a row inside an explicit DB transaction'],
                ['url' => '/features/cache', 'title' => 'Cache', 'desc' => 'Cache a COUNT(*) via Phalcon\'s cache adapter (10s TTL)'],
                ['url' => '/features/log', 'title' => 'Logging', 'desc' => 'Log method entry/exit via a Phalcon Logger adapter'],
                ['url' => '/features/retry', 'title' => 'Retry', 'desc' => 'Retry a failing operation up to N times'],
                ['url' => '/features/pipeline', 'title' => 'Pipeline (direct)', 'desc' => 'Explicit handler composition — closure pipeline'],
                ['url' => '/features/db-events', 'title' => 'Db Events', 'desc' => 'Observe queries and transactions via the db adapter\'s events manager'],
                ['url' => '/features/events', 'title' => 'Phalcon Events', 'desc' => 'Dispatch an event and show listener output'],
                ['url' => '/features/validation', 'title' => 'Validation', 'desc' => 'Validate input via Phalcon\\Filter\\Validation'],
                ['url' => '/features/config', 'title' => 'Config', 'desc' => 'Dot-notation access via Phalcon\\Config path()'],
                ['url' => '/features/request-scoped', 'title' => 'RequestScoped', 'desc' => 'Per-request state with an explicit worker-style reset'],
                ['url' => '/features/rate-limit', 'title' => 'Rate Limiter', 'desc' => 'Cache-backed fixed-window rate limiter (max 5 per 60s)'],
            ],
        ]);
    }

    /**
     * GET /features/aop — inserted a row inside an explicit DB transaction.
     *
     * Phalcon 6 has no AOP/interceptor layer, so this is the closest native
     * write path. The `aop` row is excluded from the winners table via
     * adapterFeatures(), but the endpoint must exist for verify.php parity.
     */
    public function aopAction(): string
    {
        $db    = $this->db();
        $now   = date('Y-m-d H:i:s');
        $title = $this->writeTitle('AOP Item', $now);

        $db->begin();
        try {
            $this->upsertSentinel($db, self::FEATURE_SENTINEL_ID, $title, $now);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();

            throw $e;
        }

        return $this->json([
            'feature'     => 'Phalcon transactional insert',
            'description' => 'Upserted a row inside an explicit DB transaction — no interceptor available in Phalcon 6.',
            'new_id'      => self::FEATURE_SENTINEL_ID,
            'title'       => $title,
        ]);
    }

    /**
     * GET /features/aop-direct — same insert as /features/aop, explicit
     * begin/commit.
     *
     * Phalcon has no advice layer, so its `/features/aop` already writes the
     * transaction by hand. This endpoint exists so the same-work comparison holds
     * on BOTH sides: Azera's `/features/aop` vs `/features/aop-direct` measures
     * what its proxy costs, and comparing this row against those two keeps the
     * cross-framework numbers on identical work rather than on three different
     * demonstrations.
     *
     * Deliberately identical to aop() — same upsert, same sentinel id — minus the
     * `eventsManager` fire, which `aop()` does not do either.
     */
    public function aopDirectAction(): string
    {
        $db    = $this->db();
        $now   = date('Y-m-d H:i:s');
        $title = $this->writeTitle('AOP Item', $now);

        $db->begin();
        try {
            $this->upsertSentinel($db, self::FEATURE_SENTINEL_ID, $title, $now);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();

            throw $e;
        }

        return $this->json([
            'feature'     => 'Transactional insert (explicit)',
            'description' => 'Upserted a row inside a hand-written begin/commit — the direct twin of /features/aop.',
            'new_id'      => self::FEATURE_SENTINEL_ID,
            'title'       => $title,
        ]);
    }

    /**
     * GET /features/cache — a COUNT(*) cached for 10s.
     *
     * The COUNT is the whole body and nothing else: a fixed delay here would be
     * a constant on the miss path and would measure the harness, not the
     * framework (see tests/WorkloadIntegrityTest.php).
     */
    public function cacheAction(): string
    {
        $cache = $this->cacheStore();
        $key   = 'items-count';

        $start = microtime(true);
        $count = $cache->get($key);
        $firstHit = $count !== null;
        if (!$firstHit) {
            $count = $this->itemCount();
            $cache->set($key, $count, 10);
        }
        $elapsedMs = round((microtime(true) - $start) * 1000, 4);

        // Second call — a cache hit, and the proof that it returns the same value.
        $start2 = microtime(true);
        $count2 = $cache->get($key);
        $elapsedMs2 = round((microtime(true) - $start2) * 1000, 4);

        return $this->json([
            'feature'        => 'Cache (Phalcon cache adapter)',
            'description'    => 'First call runs the COUNT query (miss); the second hits the cache.',
            'item_count'     => $count,
            'first_hit'      => $firstHit,
            'first_call_ms'  => $elapsedMs,
            'second_call_ms' => $elapsedMs2,
            'same_result'    => $count === $count2,
        ]);
    }

    /**
     * GET /features/log — log a call in and out through Phalcon's Logger.
     *
     * The entries are read back from an in-memory adapter, so the endpoint shows
     * what was logged without paying for disk I/O it is not measuring.
     */
    public function logAction(): string
    {
        $adapter = $this->loggerAdapter();
        $adapter->clear();

        $logger = $this->logger();

        $start = microtime(true);
        $logger->info('entering logDemo', ['feature' => 'log']);
        $result = 'logged call result';
        $logger->info('leaving logDemo', [
            'result'     => $result,
            'elapsed_ms' => round((microtime(true) - $start) * 1000, 4),
        ]);

        return $this->json([
            'feature'     => 'Logging (Phalcon Logger)',
            'description' => 'A Phalcon Logger records method entry and exit; the entries are shown as they were written.',
            'result'      => $result,
            'log_entries' => $adapter->entries(),
        ]);
    }

    /**
     * GET /features/retry — retry a failing operation up to N times.
     *
     * Phalcon has no #[Retry] advice, so this is the manual loop the CI4 and
     * CakePHP entrants use for the same row.
     */
    public function retryAction(): string
    {
        $attempts = 0;
        $maxTries = 3;
        $result   = null;

        while ($attempts < $maxTries) {
            $attempts++;
            try {
                // Fail the first two attempts so the retry path is real work,
                // not a loop that always succeeds on the first pass.
                if ($attempts < 3) {
                    throw new \RuntimeException('transient failure');
                }
                $result = "succeeded after {$attempts} attempts";
            } catch (\RuntimeException) {
                continue;
            }
        }

        return $this->json([
            'feature'     => 'Retry (manual loop)',
            'description' => 'Retries a failing operation up to 3 attempts — Phalcon has no retry advice.',
            'attempts'    => $attempts,
            'result'      => $result,
        ]);
    }

    /**
     * GET /features/pipeline — explicit handler composition, no proxy generation.
     *
     * The same technique the Spiral entrant uses for its "direct pipeline" arm:
     * nested closures around a plain callable, which is the fair comparison for
     * a framework with no AOP container.
     */
    public function pipelineAction(): string
    {
        $adapter = $this->loggerAdapter();
        $adapter->clear();

        $logger = $this->logger();
        $entries = [];

        // The innermost handler: what the pipeline ultimately runs.
        $handler = static fn(): string => 'direct pipeline result';

        // One interceptor around it, timed and logged the way the AOP entrants'
        // LogInterceptor reports its work.
        $logged = function () use ($handler, $logger, &$entries): string {
            $t0     = microtime(true);
            $logger->info('pipeline: before handler');
            $entries[] = 'log: entering handler';

            $result = $handler();

            $elapsed = round((microtime(true) - $t0) * 1000, 4);
            $logger->info('pipeline: after handler', ['elapsed_ms' => $elapsed]);
            $entries[] = 'log: leaving handler in ' . $elapsed . 'ms';

            return $result;
        };

        // A retry interceptor wrapping that one: it runs the inner pipeline and,
        // being a healthy request, succeeds on the first pass.
        $attempts = 0;
        $result   = null;
        while ($attempts < 3) {
            $attempts++;
            try {
                $result = $logged();
                break;
            } catch (\Throwable) {
                continue;
            }
        }

        return $this->json([
            'feature'     => 'Pipeline (closure composition)',
            'description' => 'Explicit interceptor pipeline around a plain callable — no proxy generation.',
            'result'      => $result,
            'attempts'    => $attempts,
            'log_entries' => $adapter->entries(),
        ]);
    }

    /**
     * GET /features/db-events — Phalcon's native DB event pipeline.
     *
     * The adapter's events manager is attached once in Bootstrap (the same
     * place the CI4 and Cake entrants register their query taps), so this
     * endpoint only has to collect what fires during its own queries.
     */
    public function dbEventsAction(): string
    {
        $log = $this->dbEventLog();
        $log->clear();

        $db  = $this->db();
        $now = date('Y-m-d H:i:s');

        // A transaction plus a query, so the demo shows the transaction events
        // as well as the two per-statement ones. The count deliberately uses the
        // plain query path: a cache hit fires no DB event at all.
        $db->begin();
        try {
            $this->upsertSentinel($db, self::FEATURE_SENTINEL_ID, $this->writeTitle('DbEvent Item', $now), $now);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollback();

            throw $e;
        }

        $count = $this->itemCount();

        return $this->json([
            'feature'     => 'Db Events (db adapter events manager)',
            'description' => 'The db adapter fires beforeQuery/afterQuery per statement and beginTransaction/commitTransaction per transaction.',
            'item_count'  => $count,
            'events'      => $log->all(),
        ]);
    }

    /**
     * GET /features/events — dispatch through Phalcon\Events\Manager.
     */
    public function eventsAction(): string
    {
        $db    = $this->db();
        $now   = date('Y-m-d H:i:s');
        $title = $this->writeTitle('Event Item', $now);

        // Fixed sentinel row: the row count must stay stable across a run.
        $this->upsertSentinel($db, self::EVENT_SENTINEL_ID, $title, $now);

        $entries = [];

        /** @var ManagerInterface $events */
        $events = $this->getDI()->getShared('eventsManager');
        $events->attach('item:created', static function ($event, $source, $data = null) use (&$entries): void {
            $entries[] = 'ItemCreated handled: id=' . ($data['id'] ?? '?')
                . ' title=' . ($data['title'] ?? '?');
        });

        $events->fire('item:created', $this, ['id' => self::EVENT_SENTINEL_ID, 'title' => $title]);

        return $this->json([
            'feature'      => 'Phalcon Events',
            'description'  => 'Dispatched item:created through the events manager; the listener recorded what it saw.',
            'event_class'  => 'item:created',
            'item_id'      => self::EVENT_SENTINEL_ID,
            'item_title'   => $title,
            'listener_log' => $entries,
        ]);
    }

    /**
     * GET /features/validation — Phalcon\Filter\Validation, valid and invalid.
     *
     * `validate()` returns a Messages collection: empty means it passed. Both
     * payloads run through the same rule set so the difference in the response
     * is the input, not the configuration.
     */
    public function validationAction(): string
    {
        $valid   = ['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'age' => '36'];
        $invalid = ['name' => 'X', 'email' => 'not-an-email', 'age' => '150'];

        return $this->json([
            'feature'     => 'Validation',
            'description' => 'Phalcon\Filter\Validation coerces and validates input; messages are keyed by field.',
            'valid_payload'   => $this->validationOutcome($valid),
            'invalid_payload' => $this->validationOutcome($invalid),
        ]);
    }

    /**
     * GET /features/config — dot-notation access through Phalcon\Config.
     */
    public function configAction(): string
    {
        /** @var Config $config */
        $config = $this->getDI()->getShared('benchConfig');

        return $this->json([
            'feature'     => 'Config (Phalcon\Config path())',
            'description' => 'Dot-notation access to a nested configuration tree.',
            'app_name'    => $config->path('app.name'),
            'page_size'   => $config->path('app.page_size'),
            'db_dsn'      => $config->path('db.dsn'),
            'missing'     => $config->path('does.not.exist', 'fallback'),
            'has_app'     => $config->has('app'),
            'all'         => $config->toArray(),
        ]);
    }

    /**
     * GET /features/request-scoped — per-request state with an explicit reset.
     *
     * In a resident worker the container survives between requests, so state a
     * request leaves behind must be cleared. The counter is read, incremented
     * and then cleared, showing the reset the worker loop performs.
     */
    public function requestScopedAction(): string
    {
        $counter = $this->getDI()->getShared('requestCounter');

        $before = $counter->count();
        $after  = $counter->increment();
        $counter->reset();
        $afterReset = $counter->count();

        return $this->json([
            'feature'           => 'RequestScoped',
            'description'       => 'Request-scoped state is reset between requests by the worker loop.',
            'count_before'      => $before,
            'count_after'       => $after,
            'count_after_reset' => $afterReset,
        ]);
    }

    /**
     * GET /features/rate-limit — fixed window over Phalcon's cache.
     */
    public function rateLimitAction(): string
    {
        $limiter = new RateLimiter($this->cacheStore());
        $ip      = '127.0.0.1';

        $allowed = $limiter->limit('demo-' . $ip, 5, 60);
        $hits    = $limiter->hits('demo-' . $ip);

        return $this->json([
            'feature'     => 'RateLimiter',
            'description' => 'Max 5 requests per 60 seconds, counted in Phalcon\'s cache.',
            'ip'          => $ip,
            'hits'        => $hits,
            'allowed'     => $allowed,
            'remaining'   => max(0, 5 - $hits),
        ]);
    }

    // --- helpers -------------------------------------------------------------

    private function db(): \Phalcon\Db\Adapter\Pdo\Sqlite
    {
        /** @var \Phalcon\Db\Adapter\Pdo\Sqlite $db */
        $db = $this->getDI()->getShared('db');

        return $db;
    }

    private function cacheStore(): CacheInterface
    {
        /** @var CacheInterface $cache */
        $cache = $this->getDI()->getShared('cache');

        return $cache;
    }

    private function logger(): Logger
    {
        /** @var Logger $logger */
        $logger = $this->getDI()->getShared('benchLogger');

        return $logger;
    }

    private function loggerAdapter(): MemoryLoggerAdapter
    {
        /** @var MemoryLoggerAdapter $adapter */
        $adapter = $this->getDI()->getShared('benchLoggerAdapter');

        return $adapter;
    }

    private function dbEventLog(): DbEventLog
    {
        /** @var DbEventLog $log */
        $log = $this->getDI()->getShared('dbEventLog');

        return $log;
    }

    private function itemCount(): int
    {
        $row = $this->db()->fetchOne('SELECT COUNT(*) AS c FROM items');

        return (int) ($row['c'] ?? 0);
    }

    /**
     * Build the title written by a demo endpoint, INCLUDING a per-request suffix.
     *
     * WHY THE SUFFIX IS MANDATORY. These endpoints upsert a FIXED sentinel row, so
     * the title is the only column that differs between requests. When the title
     * carries nothing finer than a second, consecutive requests inside the same
     * second write byte-identical rows — and SQLite then skips the write entirely
     * rather than committing a no-op page. Measured on the bench VM in
     * rollback-journal mode, where a real commit costs ~0.5 ms and is impossible to
     * miss:
     *
     *   40 identical upserts   -> 4 fdatasync calls
     *   40 unique upserts      -> 160 fdatasync calls
     *
     * This is not a micro-optimisation, it is a silent workload difference. Phalcon
     * was the only entrant writing a date-only title here, so its "0.021 ms" write
     * row measured a cache hit against SQLite's own page cache while every other
     * entrant paid the commit. Giving Phalcon the same unique title every other
     * entrant already used moved the same endpoint from 0.021 ms to 0.486 ms.
     *
     * Every write endpoint on every entrant must therefore include this suffix.
     * `tests/WriteTitleUniquenessTest.php` holds that invariant.
     */
    private function writeTitle(string $prefix, string $now): string
    {
        return $prefix . ' ' . $now . ' #' . random_int(1000, 9999);
    }

    /**
     * Upsert one sentinel row by primary key.
     *
     * `ON CONFLICT DO UPDATE` is the same fixed-id upsert every entrant uses, so
     * the row count — and therefore every COUNT-based read — stays constant
     * across a run.
     */
    private function upsertSentinel(
        \Phalcon\Db\Adapter\Pdo\Sqlite $db,
        int $id,
        string $title,
        string $createdAt,
    ): void {
        $db->execute(
            'INSERT INTO items (id, title, created_at) VALUES (?, ?, ?)'
            . ' ON CONFLICT (id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at',
            [$id, $title, $createdAt],
        );
    }

    /**
     * Run one payload through the feature rule set.
     *
     * @param array<string,string> $data
     * @return array{passed:bool,data:array<string,string>,errors:array<string,string>}
     */
    private function validationOutcome(array $data): array
    {
        $validation = new Validation();

        $validation->rules('name', [new PresenceOf()]);
        $validation->rules('name', [new StringLength(['min' => 2, 'max' => 100])]);
        $validation->rules('email', [new PresenceOf()]);
        $validation->rules('email', [new Email()]);
        $validation->rules('age', [new Numericality()]);
        $validation->rules('age', [new Between(['minimum' => 18, 'maximum' => 120])]);

        // validate() returns the Messages collection; empty means it passed.
        $messages = $validation->validate($data);

        $errors = [];
        if ($messages !== false) {
            foreach ($messages as $message) {
                $errors[$message->getField()] = $message->getMessage();
            }
        }

        return [
            'passed' => $errors === [],
            'data'   => $data,
            'errors' => $errors,
        ];
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return (string) json_encode($data, JSON_UNESCAPED_SLASHES);
    }
}
