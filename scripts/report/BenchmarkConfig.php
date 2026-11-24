<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

/**
 * Single source of truth for the report layer: which requests belong to which
 * feature, which frameworks support which feature, and how each framework is
 * styled.
 *
 * This mirrors the maps in run.php. run.php now loads this class so the
 * benchmark harness and the report generator can never drift apart.
 */
final class BenchmarkConfig
{
    /**
     * Maps a request label ("GET /items") to a competition feature.
     *
     * @return array<string,string>
     */
    public static function featureMap(): array
    {
        return [
            // `routing` is anchored on the typed route, not on `/`: `/` renders a
            // template, so it measures rendering and cannot separate route
            // matching. featurePrimaryRequest() returns the FIRST mapping for a
            // feature, so this entry is what the routing chart and its heading
            // sentence are drawn on.
            'GET /api/routing/1'           => 'routing',
            'GET /'                        => 'routing',
            'GET /echo'                    => 'throughput',
            'GET /items'                   => 'orm',
            'GET /items-fast'              => 'orm',
            'GET /items/1'                 => 'orm',
            'POST /items'                  => 'orm',
            'GET /items-qb'                => 'query-builder',
            'GET /items-qb/1'              => 'query-builder',
            'POST /items-qb'               => 'query-builder',
            'GET /api/items'               => 'rest-api',
            'GET /api/items-fast'          => 'rest-api',
            'GET /api/items/1'             => 'rest-api',
            'POST /api/items'              => 'rest-api',
            // `GET /features/log`, `/features/retry` and `/features/pipeline` are
            // NOT 'aop'. They demonstrate a logger, a retry loop and an explicit
            // closure pipeline — facilities every entrant has, which is why
            // CodeIgniter, CakePHP and Phalcon serve them with plain code.
            // Grouping them under 'aop' hid them from three entrants on a
            // technicality: the exclusion that keeps Phalcon out of the
            // proxy-vs-handwritten comparison also removed its logger, its retry
            // loop and its pipeline from the report, none of which are in doubt.
            'GET /features/aop'            => 'aop',
            // Same work as /features/aop, transaction written by hand. Kept in
            // the SAME feature so the pair is read together: the gap between the
            // two rows is the cost of transparent interception.
            'GET /features/aop-direct'     => 'aop',
            'GET /features/cache'          => 'cache',
            'GET /features/log'            => 'logging',
            'GET /features/retry'          => 'retry',
            'GET /features/pipeline'       => 'pipeline',
            'GET /features/db-events'      => 'db-events',
            'GET /features/events'         => 'events',
            'GET /features/validation'     => 'validation',
            'GET /features/config'         => 'config',
            'GET /features/request-scoped' => 'request-scoped',
            'GET /features/rate-limit'     => 'rate-limiter',
        ];
    }

    /**
     * Which features each adapter supports. An adapter that lacks a feature is
     * excluded from that feature's comparison (e.g. a framework without AOP
     * simply doesn't take part in the AOP race).
     *
     * @return array<string,list<string>>
     */
    public static function adapterFeatures(): array
    {
        return [
            'azera'       => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'laravel'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'symfony'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'spiral'      => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'codeigniter' => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'cakephp'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            // Phalcon 6 has no AOP/interceptor layer, so it sits out ONE ENDPOINT:
            // `GET /features/aop`, which compares generated proxies against
            // hand-written calls. That exclusion is expressed per endpoint in
            // excludedRequests(), not by dropping the whole 'aop' feature — the
            // feature also covers `GET /features/aop-direct`, the hand-written
            // CONTROL that every entrant serves and that prices the abstraction.
            // Dropping 'aop' hid Phalcon's control row from the one entrant that
            // reads it best (see excludedRequests()).
            //
            // `logging`, `retry` and `pipeline` are SEPARATE features, and Phalcon
            // competes in all three — they are Phalcon's Logger, a retry loop and a
            // closure pipeline. They used to be folded into 'aop' (featureMap() now
            // keeps them apart) and that is what hid a logger and a retry loop from
            // three entrants on a technicality.
            //
            // Everything else is a native Phalcon subsystem: Phalcon\Cache, its db
            // adapter's events manager, Phalcon\Events\Manager,
            // Phalcon\Filter\Validation, Phalcon\Config, an explicit request-scope
            // reset, and a cache-backed rate limiter.
            'phalcon'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop', 'logging', 'retry', 'pipeline', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
        ];
    }

    /**
     * Endpoints an entrant MEASURED but must not be raced on, per app.
     *
     * WHY THIS IS SEPARATE FROM adapterFeatures(). A feature is a coarse thing —
     * `'aop'` covers both `/features/aop` and `/features/aop-direct` — so omitting it
     * removes both rows. That was right for the proxy comparison and wrong for its
     * control: `GET /features/aop-direct` exists precisely to be served by everyone
     * (the same insert with the transaction written out by hand), and it is the
     * denominator that says what the abstraction costs. Phalcon's `/features/aop`
     * already IS the hand-written version, so it is the clearest reader of that
     * number, and a feature-level exclusion hid it.
     *
     * So `'aop'` is now NOT omitted for Phalcon; this map suppresses the single
     * endpoint instead. `/features/aop` still cannot be raced — comparing a bare
     * begin/commit against generated proxies is comparing machinery — but
     * `/features/aop-direct` is measured, published, and counted in the totals.
     *
     * @return array<string,list<string>> app => request keys it must not be raced on
     */
    public static function excludedRequests(): array
    {
        return [
            // Phalcon 6 has no interceptor layer, so its `/features/aop` runs the
            // hand-written transaction that every other entrant reaches through
            // advice. Its `aopDirectAction()` is that same work by design, which is
            // exactly what the control endpoint is for.
            'phalcon' => ['GET /features/aop'],
        ];
    }

    /**
     * Whether an entrant may be raced on this endpoint.
     *
     * Consulted by the table, the totals and the feature charts, so an excluded
     * endpoint is absent everywhere rather than blank in one place and counted in
     * another.
     */
    public static function racesOn(string $app, string $request): bool
    {
        return !in_array($request, self::excludedRequests()[$app] ?? [], true);
    }

    /**
     * Canonical display order for frameworks.
     *
     * @return list<string>
     */
    public static function appOrder(): array
    {
        return ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp', 'phalcon'];
    }

    /**
     * Canonical feature order (as they appear in run.php's $featureMap).
     *
     * @return list<string>
     */
    public static function featureOrder(): array
    {
        return [
            'routing',
            'orm',
            'query-builder',
            'rest-api',
            'aop',
            'logging',
            'retry',
            'pipeline',
            'cache',
            'db-events',
            'events',
            'validation',
            'config',
            'request-scoped',
            'rate-limiter',
        ];
    }

    /**
     * Benchmark requests in canonical order, used for chart ordering.
     *
     * @return list<string>
     */
    public static function requestOrder(): array
    {
        return array_keys(self::featureMap());
    }

    /**
     * Display label per framework key.
     */
    public static function appLabel(string $app): string
    {
        return self::appMeta()[$app]['label'] ?? ucfirst($app);
    }

    /**
     * Brand-ish categorical colour per framework. Chosen so all six stay
     * distinguishable in a grouped bar chart and read on a light card.
     */
    public static function appColor(string $app): string
    {
        return self::appMeta()[$app]['color'] ?? '#64748b';
    }

    /**
     * @return array<string,array{label:string,color:string}>
     */
    public static function appMeta(): array
    {
        return [
            'azera'       => ['label' => 'Azera', 'color' => '#3459e6'],
            'laravel'     => ['label' => 'Laravel', 'color' => '#e5484d'],
            'symfony'     => ['label' => 'Symfony', 'color' => '#7048e8'],
            'spiral'      => ['label' => 'Spiral', 'color' => '#0ca678'],
            'codeigniter' => ['label' => 'CodeIgniter', 'color' => '#e8590c'],
            'cakephp'     => ['label' => 'CakePHP', 'color' => '#d6409f'],
            'phalcon'     => ['label' => 'Phalcon 6 (RC)', 'color' => '#8b5a2b'],
        ];
    }

    /**
     * Human label per feature key.
     */
    public static function featureLabel(string $feature): string
    {
        return [
            'routing'        => 'Routing',
            'throughput'     => 'Throughput (static)',
            'orm'            => 'ORM / Active Record',
            'query-builder'  => 'Query Builder',
            'rest-api'       => 'REST API (JSON)',
            'aop'            => 'AOP (Aspect-Oriented)',
            'logging'        => 'Logging',
            'retry'          => 'Retry',
            'pipeline'       => 'Pipeline (explicit)',
            'cache'          => 'Cache',
            'db-events'      => 'Database Events',
            'events'         => 'Event Dispatcher',
            'validation'     => 'Validation',
            'config'         => 'Config',
            'request-scoped' => 'Request-Scoped Services',
            'rate-limiter'   => 'Rate Limiter',
            'memory'         => 'Peak Memory',
            'all'            => 'All Endpoints',
        ][$feature] ?? $feature;
    }

    /**
     * What each benchmarked endpoint actually reads or writes, per request —
     * the workload behind the number. The DB is seeded with 1000 item rows
     * (seed.php default, re-seeded per app × mode) and every list endpoint
     * serves page 1 of 20; every write upserts exactly one sentinel row, so
     * the row count stays stable across runs. All six adapters hardcode the
     * same PAGE_SIZE = 20, so the payload is identical no matter the
     * framework.
     *
     * @return array<string,string>
     */
    public static function requestWorkload(): array
    {
        return [
            'GET /'                        => 'no DB — routing + template only',
            'GET /echo'                    => 'no DB — static route, constant body (throughput floor)',
            'GET /api/routing/1'               => 'no DB — typed route + constant body (routing only)',
            'GET /items'                   => '20 of 1000 items (page 1, + COUNT)',
            'GET /items-fast'              => '20 of 1000 items (page 1, + COUNT), hydrated rows not built',
            'GET /items/1'                 => '1 item by id',
            'POST /items'                  => '1 row upserted (sentinel #999999)',
            'GET /items-qb'                => '20 of 1000 items (page 1, + COUNT)',
            'GET /items-qb/1'              => '1 item by id',
            'POST /items-qb'               => '1 row upserted (sentinel #999997)',
            'GET /api/items'               => '20 of 1000 items as JSON',
            'GET /api/items-fast'          => '20 of 1000 items as JSON, rows not hydrated',
            'GET /api/items/1'             => '1 item by id as JSON',
            'POST /api/items'              => '1 row upserted (sentinel #999998)',
            'GET /features/aop'            => '1 row upserted (sentinel #888800)',
            'GET /features/aop-direct'     => '1 row upserted (sentinel #888800)',
            'GET /features/cache'          => 'COUNT(*) of 1000 rows, cached 10s (miss = query)',
            'GET /features/log'            => 'no DB — buffered log handlers',
            'GET /features/retry'          => 'no DB — retry policy',
            'GET /features/pipeline'       => 'no DB — middleware pipeline',
            'GET /features/db-events'      => '1 row upserted (sentinel #888800) + COUNT(*), events collected',
            'GET /features/events'         => 'no DB — in-process listeners',
            'GET /features/validation'     => 'no DB — validator run',
            'GET /features/config'         => 'no DB — config lookup',
            'GET /features/request-scoped' => 'no DB — scoped service resolve',
            'GET /features/rate-limit'     => 'no DB — cache-backed limiter',
        ];
    }

    /**
     * Workload description for a request label; '' when unmapped.
     */
    public static function workloadFor(string $request): string
    {
        return self::requestWorkload()[$request] ?? '';
    }

    /**
     * What each feature comparison actually exercises, in one sentence.
     *
     * This is the static counterpart of the feature charts: the charts and the
     * latency table carry every measured number, so the prose beside them
     * explains the WORKLOAD instead of reprinting a reading. Keeping it here
     * (rather than inline in the renderer) means the description travels with
     * the feature key, exactly like featureLabel().
     *
     * @return array<string,string>
     */
    public static function featureDescription(): array
    {
        return [
            'routing'        => 'matches a typed route and dispatches it to a constant handler — no '
                . 'template and no database access — so the band measures route matching and '
                . 'dispatch and nothing else. The endpoint sits in a deep bucket of the route '
                . 'table, which is where a compiled index actually saves work. `/echo` is drawn '
                . 'beside it: the same constant handler behind a STATIC route, i.e. the floor '
                . 'that route matching is measured against — and, like any floor, it is mostly '
                . 'the SERVER rather than the framework. Measured 2026-11-24 with '
                . '`temp/echo-ab.php` on the bench VM, the entire framework layer on `/echo` '
                . 'costs single-digit microseconds, inside rows that print two to three orders '
                . 'of magnitude more. A difference of a few microseconds there is below the '
                . 'run-to-run spread, so read `/echo` as the common floor and NOT as a '
                . 'comparison between entrants; the charts that separate frameworks are the '
                . 'ones with real work in them.',
            'orm'            => 'loads one page of the 1,000 seeded rows through each framework\'s '
                . 'ORM / Active Record layer: 20 items plus a COUNT for the pagination total. '
                . 'The `-fast` band repeats that page with hydration switched off, so the cost '
                . 'of building one object per row can be read directly instead of inferred.',
            'query-builder'  => 'builds the same page of 20 items with each framework\'s query builder '
                . 'instead of its ORM, so the two data-access styles can be compared directly.',
            'rest-api'       => 'serves the same page of items as a JSON response rather than HTML, '
                . 'which adds serialization to the ORM work. The `-fast` band repeats that response '
                . 'with hydration switched off, so serialization cost and hydration cost can be '
                . 'told apart.',
            'aop'            => 'runs a request through interceptor machinery — logging, retry and '
                . 'transaction advice wrapped around the handler. The entrants split into two '
                . 'styles, and this row means different work on each side. TRANSPARENT '
                . 'interception — advice declared on the method and applied by generated code, '
                . 'handler body untouched — is what Azera does (`#[Advised]`, `#[Transactional]`, '
                . '`#[Cache]`, `#[Log]`, `#[Retry]`). DIRECT interception — the pipeline written '
                . 'out in the code — is what everyone else does: Laravel composes an Illuminate '
                . 'Pipeline and Symfony chains invokable interceptor classes, both documenting '
                . 'that they have no proxy-based AOP; Spiral builds a `PipelineBuilder` pipeline; '
                . 'CodeIgniter, CakePHP and Phalcon have no interceptor layer at all and write the '
                . 'transactional insert by hand. Do NOT read the ranking across styles as a speed '
                . 'verdict: the two sides pay for different machinery, and the same caution applies '
                . 'to `cache`, `log` and `retry`, which on Azera also route through generated '
                . 'proxies while every other entrant implements them plainly. `GET '
                . '/features/aop-direct` is the control: the identical insert with the transaction '
                . 'written by hand, served by every entrant, so the pair prices the abstraction on '
                . 'the same work. Phalcon sits the aop race out; it competes in logging, retry '
                . 'and pipeline.',
            'logging'        => 'logs a method entry and exit through the framework\'s own logger — '
                . 'PSR-3 everywhere. On Azera the call goes through a generated `#[Log]` proxy, so '
                . 'this row includes interception cost the other entrants do not pay here.',
            'retry'          => 'runs a deliberately flaky operation through a retry policy of up to '
                . 'three attempts — an explicit loop or interceptor everywhere, and on Azera a '
                . 'generated `#[Retry]` proxy, so this row includes interception cost the others '
                . 'do not pay here.',
            'pipeline'       => 'composes the handler with explicit interceptor steps — no proxy '
                . 'generation, so this row separates "the pipeline mechanism" from "the advice '
                . 'declaration mechanism".',
            'cache'          => 'reads a COUNT(*) over the 1,000 rows through the framework\'s cache '
                . 'with a 10-second TTL, so a hit costs no database work and a miss runs the query. '
                . 'On Azera the read goes through a generated `#[Cache]` proxy, so this row includes '
                . 'interception cost the other entrants do not pay here.',
            'db-events'      => 'inserts one event row per request and lets the framework\'s database '
                . 'events fire around that write. On Azera the insert goes through a generated '
                . '`#[Transactional]` proxy, so this row includes interception cost the other '
                . 'entrants do not pay here.',
            'events'         => 'dispatches an in-process event to registered listeners. On Azera the '
                . 'item it writes goes through a generated `#[Transactional]` proxy, so this row '
                . 'includes interception cost the other entrants do not pay here.',
            'validation'     => 'validates a payload with the framework\'s own validator.',
            'config'         => 'resolves a value from the framework\'s config repository.',
            'request-scoped' => 'resolves a service scoped to the request from the container.',
            // "checks" not "enforces": every app's limiter cache is
            // process-lifetime, so the 5-hit budget is spent within the first few
            // iterations and nearly every timed request is a DENIED one. The
            // endpoint therefore always answers 200 and reports the verdict in
            // `allowed`/`remaining`; a 429 aborted the run and would have made the
            // row measure "reject a request". See tests/RateLimitStatusTest.php.
            'rate-limiter'   => 'checks a cache-backed rate limiter, which has long since denied the caller.',
        ];
    }

    /**
     * One-line description of a feature; '' when unmapped.
     */
    /**
     * How each entrant implements interception: TRANSPARENT or DIRECT.
     *
     * The distinction the AOP race turns on, verified per entrant rather than
     * assumed:
     *
     *   transparent — the advice is DECLARED on the method and applied by
     *     generated code, so the handler body contains no interception at all.
     *     Only Azera does this: `#[Transactional]`/`#[Cache]`/`#[Log]`/`#[Retry]`
     *     on `App\Azera\Services\FeatureService`, with `AppContext::build()`
     *     generating the proxy. Grepping every other app for an advice
     *     attribute (`#[Transactional]`, `#[Log]`, `#[Retry]`, `#[Cache]`,
     *     `#[Advised]`) finds none.
     *
     *   direct — the interception is WRITTEN OUT in the code. Laravel composes
     *     an Illuminate Pipeline (`AopService`, and its own comment says
     *     "Laravel has no proxy-based AOP"), Symfony chains invokable
     *     interceptor classes for the same stated reason, Spiral builds a
     *     `PipelineBuilder` pipeline whose own comment admits the interceptor
     *     does not manage its transaction, and CodeIgniter, CakePHP and Phalcon
     *     have no interceptor layer at all so they hand-write the transactional
     *     insert with an explicit begin/commit.
     *
     * Why it matters to a reader: an `aop` number from Azera buys "the handler
     * is untouched and the advice is applied for you"; the same row from the
     * other six measures "a pipeline someone wrote by hand". They are both
     * legitimate and both interesting, but a chart that ranks them together
     * without saying which is which invites the conclusion that transparent
     * interception is simply slower, when the two sides are paying for different
     * machinery.
     *
     * WHAT THIS DATASET NOW CAN ANSWER. `GET /features/aop-direct` is the
     * control: the SAME insert, same sentinel, same event dispatch, with the
     * transaction written by hand instead of applied by the generated proxy.
     * Azera serves both, so the pair prices the abstraction on identical work.
     *
     * Measured 2026-11-22 (php-fpm): Azera `aop` 2.3693 ms vs `aop-direct`
     * 2.3435 ms = **+0.0259 ms, +1.1%**, and every other entrant's two rows land
     * inside +-2%, which is the run-to-run noise floor. Transparent interception
     * is therefore NOT what makes those rows slow.
     *
     * WHERE THE REST OF THE TIME GOES — measured, not inferred. The per-query
     * event machinery is negligible: `new QueryExecuted` 0.18 us, `dispatch()`
     * including listener resolution 1.26 us, `DbEventLog::record()` 0.25 us, so
     * ~1.7 us per query and ~5 us (0.2%) on a 2.37 ms request. Removing logging or
     * events would change nothing.
     *
     * The write is the variable cost, and BOTH the journal mode and the
     * synchronous level change it by orders of magnitude. 800 sequential upserts on
     * the VM, one connection, no readers — the shape the harness runs:
     *
     *   WAL / NORMAL        p50 0.008 ms · p99 0.018 · max 0.074
     *   WAL / FULL          p50 0.335 ms · p99 0.442 · max 0.925
     *   delete / NORMAL     p50 0.447 ms · p99 0.561 · max 0.688
     *   delete / FULL       p50 0.529 ms · p99 0.703 · max 0.827
     *   truncate / NORMAL   p50 0.389 ms · p99 0.662 · max 1.573
     *
     * WAL is worth ~55x and is NOT optional: rollback-journal mode fsyncs the
     * journal on every commit even at synchronous=NORMAL, while under WAL a NORMAL
     * commit writes to the -wal file with no fsync (measured: the main database file
     * grows 0 bytes across 200 writes). FULL under WAL still fsyncs, which is why it
     * costs ~42x more than NORMAL. Each setting is wrong without the other, so the
     * pair is pinned together — tests/SqlitePragmaParityTest.php.
     *
     * That pairing is also what the earlier 14x outlier spread between entrants
     * measured: Laravel pinned NORMAL while the rest inherited FULL. It was never a
     * framework difference. (An even earlier revision of this comment claimed the
     * tail was "paid equally" — an assumption, and wrong.)
     *
     * Phalcon's p50 being 22x faster than Azera's is NOT the write and NOT cold
     * boot. It IS the AOP proxy rebuild — established 2026-11-23 by `strace -c`
     * on the live php-fpm worker under the real published workload, after three
     * earlier explanations had been proposed and retracted.
     *
     * REFUTED: COLD BOOT. The php-fpm row is 1000 iterations x 10 runs with
     * `fpm_max_requests = 0`, so a run pays for at most ONE cold request.
     * Explaining a +1.8 ms/request delta that way needs ~1800 ms of one-off work
     * per run, against a measured `boot_probe` of 0.2747 ms — ~6500x too small.
     *
     * REFUTED: A PER-REQUEST DB OR EVENT OVERHEAD. Measured on the VM with
     * classes warm and a fresh container per request (the php-fpm shape):
     *
     *   connect (incl. PRAGMAs)                     0.124 ms
     *   begin(true) + upsert + commit              0.019 ms
     *   ItemCreated dispatch + listener            ~0 (inside noise)
     *   proxy lookup + reflection + autowiring      <0.03 ms
     *
     * and the whole write controller costs 0.291 ms in-process against 0.026 ms
     * for a no-DB route — a 0.265 ms delta, versus 1.80 ms published. Across the
     * dataset Azera is also FASTER than Phalcon on 13 DB endpoints (median
     * -0.133 ms) and 8 no-DB endpoints (-0.139 ms) alike, so there is no DB-side
     * per-request tax at all.
     *
     * THE PROOF. `strace -c -f` on the bench-azera worker, 300 requests to
     * `/features/aop-direct`, original code:
     *
     *   rename     300      chmod    300      unlink   600 (incl. 300 ENOENT)
     *   ftruncate  900      flock    300      fdatasync 1200   total 76,220 calls
     *
     * Exactly one file rewrite per request. The file was
     * `data/aop/Proxy_<md5(class)>.php`, rewritten because
     * `ProxyFactory::writeAndLoadProxyFile()` named the class with
     * `bin2hex(random_bytes(8))` and had no `file_exists()` reuse path — so the
     * declared class name changed on every write, OPcache could never serve it,
     * and every request recompiled and re-required the proxy.
     *
     * Measured A/B of the same 300-request workload on the VM, trimmed mean:
     *
     *   /echo                0.498 -> 0.538 ms   (unchanged, no proxy)
     *   /features/config     0.511 -> 0.530 ms   (unchanged, no proxy)
     *   /items/1             1.264 -> 1.265 ms   (unchanged, no proxy)
     *   /features/aop-direct 2.412 -> 0.696 ms   (-1.72 ms, -71%)
     *
     * After the fix the same strace shows rename/chmod/unlink/ftruncate/flock
     * absent entirely and total calls down from 76,220 to 35,321.
     *
     * CONSEQUENCE FOR THIS COMPARISON. `aop` and `aop-direct` were the two rows
     * paying this rebuild, which is why they were the two where Azera lost
     * biggest — not because of interception. `aop` vs `aop-direct` (+1.1%) is
     * unaffected: both carried the same defect. See
     * {@see self::adviceRoutedEndpoints()} for why those two rows differ at all.
     *
     * @return array<string,string> app => 'transparent'|'direct'
     */
    public static function aopStyle(): array
    {
        return [
            'azera'       => 'transparent',
            'laravel'     => 'direct',
            'symfony'     => 'direct',
            'spiral'      => 'direct',
            'codeigniter' => 'direct',
            'cakephp'     => 'direct',
            'phalcon'     => 'direct',
        ];
    }

    /**
     * The `aop` participants grouped by style, for the page that has to say
     * which is which.
     *
     * Derived from adapterFeatures() so an app that does not implement `aop` at all
     * is not named, AND from excludedRequests() so an app that implements the feature
     * but sits out its one raced endpoint is not named either. Phalcon is that second
     * case: it serves `/features/aop-direct`, so `'aop'` is in its feature list, but
     * its `/features/aop` runs the hand-written transaction every other entrant
     * reaches through advice — so it must not be listed as having competed, or the
     * style split would claim a race it did not run.
     *
     * @return array{transparent:list<string>,direct:list<string>}
     */
    public static function aopStylesInRace(): array
    {
        $out = ['transparent' => [], 'direct' => []];

        foreach (self::adapterFeatures() as $app => $features) {
            if (!in_array('aop', $features, true)) {
                continue;
            }
            if (!self::racesOn($app, 'GET /features/aop')) {
                continue;
            }
            $out[self::aopStyle()[$app] ?? 'direct'][] = self::appLabel($app);
        }

        return $out;
    }

    /**
     * Which endpoints route their work through an advised (generated-proxy)
     * method, per entrant.
     *
     * This is narrower than {@see aopStyle()} and cuts across it. `aopStyle()`
     * asks "does this entrant declare advice on methods at all?"; this asks "does
     * THIS endpoint run through advice?" — and the two do not line up, which is
     * exactly what makes the cross-entrant feature ranking misleading.
     *
     * Azera has four advised methods on `FeatureService` — `createItemTransactional`
     * (`#[Transactional]`), `countItems` (`#[Cache]`), `logSomething` (`#[Log]`)
     * and `flakyOperation` (`#[Retry]`) — and FIVE endpoints route through them:
     * `aop`, `events`, `cache`, `log` and `retry`. (`events` calls
     * `createItemTransactional()` too, which is why it belongs here even though
     * its feature is the event dispatcher.) `db-events` also calls it, and
     * `pipeline`, `validation`, `config`, `request-scoped` and `rate-limit` are
     * plain code on both sides.
     *
     * The measured consequence, php-fpm arm of the 2026-11-22 run:
     *
     *   azera proxy-routed vs Phalcon's plain implementation
     *     aop 0.50x · retry 0.68x · log 0.78x · cache 0.87x   (events/db-events 0.79x/0.83x)
     *   azera plain vs Phalcon plain
     *     pipeline 1.36x · validation 1.43x · config 1.34x · request-scoped 1.29x · rate-limit 1.41x
     *
     * Phalcon wins exactly the rows where Azera pays for a proxy and loses every
     * row where neither does. The split is perfect in both directions, so the
     * per-feature ranking is explained by interception cost, not by the cache,
     * the logger or the rate limiter being faster in Phalcon — measured plainly,
     * Azera wins those by 29–43%.
     *
     * Note this is NOT the same as saying the proxy "costs 29-43%": the two lists
     * are different workloads, not the same work done with and without advice.
     * Pricing the proxy needs the identical endpoint implemented both ways, which
     * no entrant provides (see `aopStyle()`).
     *
     * @return array<string,array<string,bool>> app => endpoint => routed through advice
     */
    public static function adviceRoutedEndpoints(): array
    {
        $azera = [
            'GET /features/aop'      => true,   // createItemTransactional #[Transactional]
            'GET /features/events'   => true,   // createItemTransactional #[Transactional]
            'GET /features/cache'    => true,   // countItems #[Cache]
            'GET /features/log'      => true,   // logSomething #[Log]
            'GET /features/retry'    => true,   // flakyOperation #[Retry]
            'GET /features/db-events' => true,  // createItemTransactional #[Transactional]
            'GET /features/pipeline' => false,
            'GET /features/validation' => false,
            'GET /features/config'   => false,
            'GET /features/request-scoped' => false,
            'GET /features/rate-limit' => false,
        ];

        $out = ['azera' => $azera];
        foreach (array_keys(self::adapterFeatures()) as $app) {
            if ($app === 'azera') {
                continue;
            }
            // No other entrant declares advice on a method, so nothing it serves
            // can be proxy-routed — it all hand-composes.
            $out[$app] = array_map(static fn(): bool => false, $azera);
        }

        return $out;
    }

    public static function featureDescriptionFor(string $feature): string
    {
        return self::featureDescription()[$feature] ?? '';
    }

    /**
     * WHY an entrant sits out a feature, in one sentence, for the page that
     * shows the absence.
     *
     * `adapterFeatures()` decides participation but says nothing to a reader, so
     * the report prints this beside the chart. Kept here rather than in either
     * renderer because both the Markdown page and the HTML figure caption print
     * it, and two spellings of the same caveat is the drift this file exists to
     * prevent.
     *
     * @param string       $feature the race being drawn
     * @param list<string> $absent  display labels of the entrants not in it
     */
    public static function exclusionReason(string $feature, array $absent = []): string
    {
        $named = implode(' and ', $absent);

        if ($feature === 'aop') {
            // The AOP race is the only one where participation is uneven, so the
            // sentence has to work for whichever apps are missing rather than
            // assuming it is Phalcon. Phalcon is the one that stays out today;
            // an earlier draft hard-coded "Phalcon 6 has no interceptor layer"
            // and printed it beside a chart that listed Phalcon — because
            // CodeIgniter and CakePHP had been dropped from the race by mistake
            // and Phalcon was still in it.
            return ($named !== '' ? $named : 'An entrant')
                . ($absent === ['Phalcon 6 (RC)']
                    ? ' has no interceptor layer (no attribute-declared, proxy-generated advice), '
                        . 'so this race would compare its hand-written transaction against the other '
                        . 'entrants\' generated proxies — the machinery, not the framework. It DOES '
                        . 'serve `GET /features/aop-direct`, the hand-written control, and the three '
                        . 'advice demos: the exclusion is one endpoint, not a missing feature.'
                    : ' sits this race out; see `BenchmarkConfig::excludedRequests()`.');
        }

        return ($named !== '' ? $named : 'This entrant')
            . ' does not provide the facility this benchmark measures.';
    }

    /**
     * The request a feature is anchored on — the first one featureMap() assigns
     * to it.
     *
     * Both renderers print this beside the feature's workload sentence, so the
     * Markdown blockquote and the HTML figure caption name the SAME endpoint.
     * Deriving it from featureMap() rather than from whatever a particular
     * dataset happened to measure keeps the two pages identical by
     * construction: a measured-aware lookup would let them disagree on a
     * dataset that is missing the feature's first request.
     */
    public static function featurePrimaryRequest(string $feature): string
    {
        foreach (self::featureMap() as $request => $key) {
            if ($key === $feature) {
                return $request;
            }
        }

        return '';
    }
}
