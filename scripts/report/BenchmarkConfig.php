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
            // `GET /features/log` is NOT 'aop-tx'. It demonstrates a logger — a
            // facility every entrant has, which is why CodeIgniter, CakePHP and
            // Phalcon serve it with plain code. Grouping it under 'aop-tx' hid it
            // from three entrants on a technicality: the exclusion that keeps
            // Phalcon out of the proxy-vs-handwritten comparison also removed its
            // logger from the report, which is not in doubt.
            //
            // Two rows that used to sit here were removed:
            //
            //   `GET /features/pipeline` raced a hand-composed interceptor chain
            //   around a constant closure — the same mechanism `aop-tx-explicit`
            //   already measures on real work (an insert with a real transaction).
            //   The two rows asked one question twice, and the second one asked it
            //   on a workload that did no work at all.
            //
            //   `GET /features/retry` meant three different things under one label:
            //   a generated `#[Retry]` proxy on Azera, an explicit interceptor
            //   pipeline on Laravel/Symfony/Spiral, and a bare `while`/`try` loop on
            //   CodeIgniter/CakePHP/Phalcon. It also measured nothing: throwing two
            //   in-process exceptions costs microseconds, so every entrant's retry
            //   row landed within ~3% of its own `config` row (0.0099 vs 0.0092 for
            //   Azera, 0.2218 vs 0.2167 for Laravel in the 2026-09-20 run). The
            //   chart ranked bootstrap, not retry. Retry-as-advice is still
            //   exercised by the `aop-*` rows.
            //
            //   `GET /features/request-scoped` went the same way, for the same
            //   reason plus a correctness one. It resolved a counter service from
            //   the container, incremented it, and reported the reset — but the
            //   seven entrants meant three different things by that: a facility
            //   invoked in-band on Azera (`clearRequestScope()`), a framework
            //   teardown that happens OUTSIDE the measured request and is merely
            //   asserted in the payload on Laravel/Symfony/Spiral, and a
            //   hand-written `reset()` with no scoping facility at all on
            //   CodeIgniter/CakePHP/Phalcon. Spiral's docblocks claimed
            //   `#[Scope('http')]` services; no such attribute existed, and two
            //   resolves inside one `http` scope returned two different objects.
            //   The measurement settled it: every entrant's row sat within ±7% of
            //   its own `config` row in BOTH arms (0.98x/1.02x Azera, 0.96x/1.00x
            //   Laravel, 1.07x/1.00x Spiral), the flattest in the suite — a
            //   container resolve and two increments, not a scoping facility.
            // TRANSPARENT AOP: advice declared on a method and applied by a
            // generated proxy. Azera is the only entrant that ships this facility
            // (see aopStyle()), so it is the only one that competes — every other
            // framework would answer with hand-written code under a label that
            // claims generated interception.
            'GET /features/aop-tx'            => 'aop-tx',
            // The hand-written control, and now a FEATURE OF ITS OWN.
            //
            // It used to share the `aop-tx` feature, which read well while every
            // entrant served both rows. It no longer works: the six frameworks
            // without transparent AOP have left `aop-tx`, and a shared feature key
            // would take this control away from exactly the entrants that can
            // serve it — the mistake this repository already made once, when
            // Phalcon's control row vanished from the report because it sat in a
            // race Phalcon could not run. `aop-tx-direct` asks a different question
            // ("what would this cost implemented by hand?") and every entrant can
            // answer it, so it is raced by all seven.
            'GET /features/aop-tx-direct'     => 'aop-tx-direct',
            // The SECOND real AOP mode: the same advice composed by hand through
            // the framework's explicit pipeline, with no generated proxy. It is a
            // feature of its own rather than another `aop-tx` row because only the
            // entrants that ship an interceptor abstraction can race it — putting
            // it under `aop-tx` would hand four entrants a row they cannot serve.
            'GET /features/aop-tx-explicit'   => 'aop-tx-explicit',
            // TRANSPARENT AOP APPLIED TO CACHING — the same facility as `aop-tx`,
            // exercised on a different workload: `#[Cache(ttl: 10, key: …)]` on
            // `FeatureService::countItems()`, applied by the generated proxy.
            //
            // It used to be called simply `cache`, and that name hid the thing
            // the row actually measures. On Azera the cache lookup, the miss
            // decision and the store are performed by the proxy; on the other six
            // they are hand-written code in the controller. Under one word,
            // "Cache", a reader compared a proxy-routed row against a plain one
            // as if they were the same facility — precisely the mislabelling the
            // aop-tx/aop-tx-direct split already fixed. The name now states which row
            // is which, and leaves room for a `cache-xyz` feature that is NOT
            // about AOP.
            'GET /features/aop-cache'      => 'aop-cache',
            // The same-work CONTROL for `aop-cache`: identical COUNT, identical
            // 10-second TTL, identical cache entry — with the get/miss/set
            // written out in application code. Every entrant serves it because it
            // needs no framework facility, exactly like `aop-tx-direct`.
            'GET /features/aop-cache-direct' => 'aop-cache-direct',
            'GET /features/log'            => 'logging',
            'GET /features/db-events'      => 'db-events',
            'GET /features/events'         => 'events',
            'GET /features/validation'     => 'validation',
            'GET /features/config'         => 'config',
            'GET /features/rate-limit'     => 'rate-limiter',
            // `lifecycle-hooks` measures intercepting the request/response around the
            // handler and rewriting the response. Each entrant uses its OWN mechanism,
            // which is the point — the same principle as `db-events` (each framework's
            // native DB events) and `bench-view-globals.php` (one parser, called from
            // wherever each app hooks it):
            //
            //   Azera, Laravel, Spiral, CodeIgniter, CakePHP -> middleware / filters
            //   Symfony                                      -> kernel.request / kernel.response listeners
            //   Phalcon                                      -> eventsManager hooks
            //
            // Named for what is MEASURED, not for the mechanism, because only five of
            // the seven have a middleware layer at all. A row called `middleware` served
            // by Symfony's event listeners would be the `retry` defect again: one label
            // meaning different things depending on the entrant.
            //
            // WHY IT IS SEPARATE FROM `events`. The `events` row measures a BUSINESS
            // event (`ItemCreated`) dispatched to a listener and carries a DB write.
            // This row measures the REQUEST lifecycle with no database work: the hook
            // stamps a response header and sets the status to 203, and the handler is
            // constant.
            'GET /features/lifecycle-hooks' => 'lifecycle-hooks',
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
            'azera'       => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx', 'aop-tx-direct', 'aop-tx-explicit', 'aop-cache', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            'laravel'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            'symfony'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            'spiral'      => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-tx-explicit', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            'codeigniter' => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            'cakephp'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
            // Phalcon 6 has no interceptor OR advice layer, so it competes in neither
            // `aop-tx` (transparent) nor `aop-tx-explicit`. It DOES compete in
            // `aop-tx-direct`: the hand-written control needs no framework facility,
            // and it is the row Phalcon reads best — its own `/features/aop-tx` used
            // to be that same code.
            //
            // THE SIX ENTRANTS WITHOUT `aop-tx`. `GET /features/aop-tx` measures
            // TRANSPARENT AOP — advice declared on a method and applied by a
            // generated proxy. Only Azera ships it (see aopStyle()); the other six
            // would answer with hand-written code under a label claiming generated
            // interception, which is the "competes in an area where it has nothing
            // to compete with" failure this file exists to prevent. They do not
            // serve the route at all, so `competesOn()` keeps the harness from
            // asking for it.
            //
            // `logging` is a SEPARATE feature, and Phalcon competes in it — it is
            // Phalcon's Logger. It used to be folded into 'aop-tx' (featureMap() now
            // keeps it apart) and that is what hid a logger from three entrants on a
            // technicality.
            //
            // Everything else is a native Phalcon subsystem: Phalcon\Cache, its db
            // adapter's events manager, Phalcon\Events\Manager,
            // Phalcon\Filter\Validation, Phalcon\Config, an explicit request-scope
            // reset, and a cache-backed rate limiter.
            'phalcon'     => ['routing', 'throughput', 'orm', 'query-builder', 'rest-api', 'aop-tx-direct', 'aop-cache-direct', 'logging', 'db-events', 'events', 'validation', 'config', 'rate-limiter', 'lifecycle-hooks'],
        ];
    }

    /**
     * Endpoints an entrant must not be measured on, per app.
     *
     * ONE SOURCE OF TRUTH. This map is read by the MEASUREMENT (run-http.php passes
     * `--requests` minus these to http-bench, so the endpoint is never timed) and by
     * the REPORT (the table, the totals and the feature charts). Before that it was a
     * reporting-only idea, which is why Phalcon's `/features/aop-tx` had to be served by a
     * placeholder action: the harness asks every app for the full endpoint list, so a
     * missing route aborted the whole run with
     *
     *   2026-11-21: "[ABORT] fpm/phalcon GET /features/aop-tx: HTTP status 404"
     *
     * and `aopAction()` was written to stop that abort. Removing the route without
     * teaching the harness about the exclusion would bring the abort straight back.
     *
     * WHY PHALCON SITS OUT `/features/aop-tx`. The endpoint compares AOP MACHINERY:
     * Azera runs a generated `#[Transactional]` proxy, Laravel and Symfony compose
     * interceptor pipelines, Spiral builds a `PipelineBuilder`. Phalcon 6 has no
     * advice layer at all, so the only row it can produce there is a bare
     * begin/upsert/commit — a different workload under a label that claims to be
     * about interception. Its own `aopAction()` docblock says as much.
     *
     * NOT EXCLUDED: `GET /features/aop-tx-direct`. That is the hand-written control every
     * entrant serves, and comparing it is legitimate.
     *
     * @return array<string,list<string>> app => request keys it must not be measured on
     */
    public static function excludedRequests(): array
    {
        return [
            'phalcon' => ['GET /features/aop-tx'],
        ];
    }

    /**
     * The endpoints one app should actually be measured on.
     *
     * This is what the harness passes to http-bench. Kept here — beside the exclusion
     * that defines it — so measurement and reporting cannot drift apart, which is the
     * failure that made a placeholder action necessary in the first place.
     *
     * @return list<string>
     */
    public static function requestsFor(string $app): array
    {
        return array_values(array_filter(
            self::requestOrder(),
            static fn(string $req): bool => self::competesOn($app, $req)
        ));
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
     * Whether an entrant both SERVES this endpoint and COMPETES in its feature.
     *
     * This is the predicate every harness path needs, and `racesOn()` alone is not
     * enough for it. Two independent reasons an app must not be asked for an
     * endpoint exist:
     *
     *   1. It sits the endpoint out while competing in the feature — Phalcon on
     *      `GET /features/aop-tx` (excludedRequests(), i.e. `racesOn()`).
     *   2. It does not compete in the feature at all — the five entrants with no
     *      interceptor abstraction on `GET /features/aop-tx-explicit`
     *      (adapterFeatures()). They have no route for it, so asking would 404.
     *
     * Asking an app for an endpoint it cannot serve aborts the whole run
     * (`abortIfBroken()`/`isBenchErrorBody()`), which is how Phalcon's 404 killed a
     * run before excludedRequests() existed. Expressing the second reason as
     * adapterFeatures() rather than as more entries in excludedRequests() keeps
     * "this app has no such facility" in the map that already says so.
     */
    public static function competesOn(string $app, string $request): bool
    {
        if (!self::racesOn($app, $request)) {
            return false;
        }

        $feature = self::featureMap()[$request] ?? null;
        if ($feature === null) {
            return true; // unclassified request: only the endpoint exclusion applies
        }

        return in_array($feature, self::adapterFeatures()[$app] ?? [], true);
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
            'aop-tx',
            'aop-tx-direct',
            'aop-tx-explicit',
            'aop-cache',
            'aop-cache-direct',
            'logging',
            'db-events',
            'events',
            'validation',
            'config',
            'rate-limiter',
            'lifecycle-hooks',
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
            'aop-tx'            => 'AOP (Aspect-Oriented)',
            'aop-tx-direct'     => 'AOP (hand-written control)',
            'aop-tx-explicit'   => 'AOP explicit (Pipeline)',
            'logging'        => 'Logging',
            'aop-cache'      => 'AOP #[Cache] (attribute-applied)',
            'aop-cache-direct' => 'Cache (hand-written control)',
            'db-events'      => 'Database Events',
            'events'         => 'Event Dispatcher',
            'validation'     => 'Validation',
            'config'         => 'Config',
            'rate-limiter'   => 'Rate Limiter',
            'lifecycle-hooks' => 'Request Lifecycle Hooks',
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
            'GET /features/aop-tx'            => '1 row upserted (sentinel #888800)',
            'GET /features/aop-tx-direct'     => '1 row upserted (sentinel #888800)',
            'GET /features/aop-tx-explicit'   => '1 row upserted (sentinel #888800)',
            'GET /features/aop-cache'      => 'COUNT(*) of 1000 rows, cached 10s by #[Cache] (miss = query)',
            'GET /features/aop-cache-direct' => 'COUNT(*) of 1000 rows, cached 10s by hand (miss = query)',
            'GET /features/log'            => 'no DB — one in-memory log sink per entrant',
            'GET /features/db-events'      => '1 row upserted (sentinel #888800) + COUNT(*), events collected',
            'GET /features/events'         => 'no DB — in-process listeners',
            'GET /features/validation'     => 'no DB — validator run',
            'GET /features/config'         => 'no DB — config lookup',
            'GET /features/rate-limit'     => 'no DB — cache-backed limiter',
            'GET /features/lifecycle-hooks' => 'no DB — a request/response hook stamps a header and sets 203, via each framework\'s own mechanism',
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
            'aop-tx'            => 'measures TRANSPARENT AOP: advice is declared on a method by an attribute '
                . 'and applied by generated code, with the handler body untouched. Azera is the '
                . 'only entrant that ships this facility (`#[Advised]` plus `#[Transactional]` / '
                . '`#[Cache]` / `#[Log]` / `#[Retry]`, compiled into a proxy class by '
                . '`Azera\Aop\ProxyFactory`), so it is the only entrant raced here. The other six '
                . 'have no attribute-to-advice layer at all — Laravel\'s `Pipeline`, Symfony\'s '
                . 'invokable interceptors, Spiral\'s `PipelineBuilder` and the hand-written '
                . '`begin`/`commit` on CodeIgniter, CakePHP and Phalcon are machinery the '
                . 'APPLICATION composes — and answering this row with that code would publish a '
                . 'hand-written implementation under a label claiming generated interception. '
                . 'Their own AOP rows are `aop-tx-explicit` (Azera, Spiral), `aop-cache` (Azera), and '
                . '`aop-tx-direct` / `aop-cache-direct` (all seven). The same caution applies to '
                . '`log`, which on Azera used to route through a generated proxy while every other '
                . 'entrant implemented it plainly; that advice was removed for exactly this reason.',
            'aop-tx-direct'     => 'answers a different question: what the same write costs implemented '
                . 'DIRECTLY, with no AOP at all. The transaction is written out by hand — '
                . '`begin` / `commit` / `rollback` in application code — and there is no '
                . 'interceptor and no generated proxy anywhere. Every entrant can serve it, '
                . 'because it needs no framework facility, so all seven race here and the row is '
                . 'the shared baseline the other three AOP features are read against. On Azera '
                . '`aop-tx - aop-tx-direct` is the cost of the generated proxy and on the six direct '
                . 'entrants it is the cost of their own composition; the difference is small '
                . 'against the write itself, which is the finding the pair was built to show.',
            'aop-tx-explicit'   => 'runs the same transactional insert through the framework\'s OWN '
                . 'explicit interceptor pipeline: the advice is composed by hand around a plain '
                . 'handler, no proxy class is generated and no attribute is read. Only two '
                . 'entrants ship a dedicated interceptor abstraction, so only they race here — '
                . 'Azera (`Azera\\Aop\\InterceptorInterface` + `Pipeline`) and Spiral '
                . '(`Spiral\\Interceptors\\InterceptorInterface` + `PipelineBuilder`). Laravel\'s '
                . 'Pipeline is request middleware rather than an interceptor abstraction, Symfony '
                . 'ships neither, and CodeIgniter, CakePHP and Phalcon have no interceptor layer '
                . 'at all; a hand-written `begin`/`commit` is not this feature, so those three '
                . 'would be measuring workarounds rather than the facility. Both participants '
                . 'execute the SAME transaction contract through their own pipeline, which is '
                . 'what makes the row comparable: read it against `aop-tx` to price generated '
                . 'interception against explicit composition on identical work.',
            'logging'        => 'logs a method entry and exit through the framework\'s own logger, and '
                . 'reports the records that logger captured. Every entrant uses the sink its own stack '
                . 'provides and every one of those sinks is in-process, so the row measures the logging '
                . 'path rather than disk or stderr I/O: Azera its PSR-3 `MemoryLogger`, Laravel `Log` → '
                . '`LogManager` → Monolog\'s in-memory `TestHandler`, Symfony a PSR-3 logger registered '
                . 'as the `logger` service, Spiral `LogsInterface::getLogger()` feeding its '
                . '`ListenerRegistry`, CakePHP `Log::write()` into an `ArrayLog` engine, CodeIgniter '
                . '`log_message()` into a buffering handler, and Phalcon its own '
                . '`Phalcon\Logger\Logger` with an in-memory adapter. NONE of them declares the logging '
                . 'as advice: Azera has `#[Log]`, but this row calls the logger directly so that all '
                . 'seven measure the same work — intercepting a logger would charge proxy cost to a '
                . 'chart about logging, which is what the `aop-*` rows exist to isolate. Phalcon\'s '
                . 'logger is not a PSR-3 implementation — it is the framework\'s own logger API — so '
                . 'the row demonstrates Phalcon logging, not interchangeable PSR-3 logging.',
            'aop-cache'      => 'reads the COUNT(*) through a cache whose lookup, miss decision and store '
                . 'are applied by ATTRIBUTE — `#[Cache(ttl: 10, key: …)]` on the method, run by the '
                . 'generated proxy, with no cache code in the handler. Azera is the only entrant that '
                . 'ships that facility, so it is the only one raced here; the six others would answer '
                . 'this row with the hand-written lookup that `aop-cache-direct` already measures. '
                . 'Read it against `aop-cache-direct` to price the generated override plus one '
                . 'interceptor frame on identical work.',
            'aop-cache-direct' => 'reads the same COUNT(*) that `aop-cache` reads, through the same '
                . '10-second cache the framework would use — same key, same TTL, same entry — but '
                . 'with the lookup, the miss decision and the store written out in application '
                . 'code. No interceptor and no generated proxy anywhere, so every entrant can '
                . 'serve it and all seven race here. This is the control the `aop-cache` row is '
                . 'read against, exactly as `aop-tx-direct` is for `aop-tx`: on Azera the two share one '
                . 'cache entry, so the difference between them is the generated override plus one '
                . 'interceptor frame and not a different workload.',
            'db-events'      => 'writes one row and reads one COUNT on an autocommit connection, so '
                . 'the framework\'s own database-observation hook can be read back: Azera\'s PSR-14 '
                . '`DatabaseEvent` classes, Laravel\'s `DB::listen()`, the PSR-3 logger Doctrine DBAL '
                . 'and Cycle DBAL route through the driver, CodeIgniter\'s `DBQuery` event, CakePHP\'s '
                . 'driver query logger, and Phalcon\'s `db:` events manager. Every listener is attached '
                . 'once per process, so what the row prices is the hook, not its registration. '
                . 'Statements that describe the CONNECTION or the SCHEMA rather than the work — '
                . '`PRAGMA` setup, `pragma_table_info`/`table_xinfo`/`index_list` reflection and '
                . '`sqlite_master` lookups — are filtered out of the reported payload on every entrant, '
                . 'because whether a hook sees them depends on when it was attached relative to '
                . 'connection setup and how much introspection the ORM does per operation. Neither is '
                . 'the feature. All seven therefore report one entry per real statement: '
                . 'the upsert and the COUNT. Phalcon\'s `db:beginTransaction` / '
                . '`db:commitTransaction` capability is real but is exercised by '
                . '`/features/aop-tx-direct`, the control row that does open a '
                . 'transaction; wrapping this row\'s write in one charged it for '
                . 'something the feature is not about and reported two events no other '
                . 'entrant produces.',
            'events'         => 'dispatches an in-process event to registered listeners. On Azera the '
                . 'item it writes goes through a generated `#[Transactional]` proxy, so this row '
                . 'includes interception cost the other entrants do not pay here.',
            'validation'     => 'validates a payload with the framework\'s own validator.',
            'config'         => 'resolves a value from the framework\'s config repository.',
            // "checks" not "enforces": every app's limiter cache is
            // process-lifetime, so the 5-hit budget is spent within the first few
            // iterations and nearly every timed request is a DENIED one. The
            // endpoint therefore always answers 200 and reports the verdict in
            // `allowed`/`remaining`; a 429 aborted the run and would have made the
            // row measure "reject a request". See tests/RateLimitStatusTest.php.
            'rate-limiter'   => 'checks a cache-backed rate limiter, which has long since denied the caller.',
            // "hook", not "middleware": only five of the seven entrants have a
            // middleware pipeline at all — Symfony has kernel listeners and Phalcon
            // has events-manager hooks — so the row is named for the intercepted
            // request rather than for the machinery.
            'lifecycle-hooks' => 'runs a hook around the handler, which rewrites the response with a marker '
                . 'header and status 203, via whichever interception mechanism the framework has.',
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
     *     Only Azera does this: `#[Transactional]`/`#[Cache]` on
     *     `App\Azera\Services\FeatureService`, with `AppContext::build()`
     *     generating the proxy. Grepping every other app for an advice
     *     attribute (`#[Transactional]`, `#[Log]`, `#[Retry]`, `#[Cache]`,
     *     `#[Advised]`) finds none.
     *
     *     Azera's own `#[Log]` is deliberately NOT used by any measured row: the
     *     logging feature calls the logger directly on all seven, so that chart
     *     does not compare a proxy dispatch against a plain call. `#[Retry]` is not
     *     used by any measured row either, for the same reason plus one more: the
     *     whole retry feature was removed. Both advices still ship in `Azera\Aop`
     *     (`Log.php`, `Retry.php` and their interceptors) and are exercised by the
     *     framework's own suite; this app simply does not wire them, so no
     *     benchmark row can claim their cost.
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
     * Why it matters to a reader: an `aop-tx` number from Azera buys "the handler
     * is untouched and the advice is applied for you"; the same row from the
     * other six measures "a pipeline someone wrote by hand". They are both
     * legitimate and both interesting, but a chart that ranks them together
     * without saying which is which invites the conclusion that transparent
     * interception is simply slower, when the two sides are paying for different
     * machinery.
     *
     * WHAT THIS DATASET NOW CAN ANSWER. `GET /features/aop-tx-direct` asks what the
     * same work costs implemented DIRECTLY — no AOP at all — so every entrant
     * answers it and the row is the shared baseline. `GET /features/aop-tx-explicit`
     * is the framework's OWN interceptor pipeline, composed by hand.
     *
     * WHO COMPETES. `aop-tx` (transparent, proxy-applied) is Azera alone, because it
     * is the only entrant that ships the facility; the other six do not serve the
     * route. `aop-tx-explicit` is Azera and Spiral. `aop-tx-direct` is all seven. The
     * style split below still records what each app's own AOP row does — it no
     * longer decides who competes, the facility does.
     *
     * Measured 2026-11-22 (php-fpm): Azera `aop-tx` 2.3693 ms vs `aop-tx-direct`
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
     * `/features/aop-tx-direct`, original code:
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
     *   /features/aop-tx-direct 2.412 -> 0.696 ms   (-1.72 ms, -71%)
     *
     * After the fix the same strace shows rename/chmod/unlink/ftruncate/flock
     * absent entirely and total calls down from 76,220 to 35,321.
     *
     * CONSEQUENCE FOR THIS COMPARISON. `aop-tx` and `aop-tx-direct` were the two rows
     * paying this rebuild, which is why they were the two where Azera lost
     * biggest — not because of interception. `aop-tx` vs `aop-tx-direct` (+1.1%) is
     * unaffected: both carried the same defect. See
     * {@see self::adviceRoutedEndpoints()} for why those two rows differ at all.
     * The same reasoning covers `aop-cache` vs `aop-cache-direct`: the pair differs
     * by the generated override, and both carry the rebuild if it ever returns.
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
     * The `aop-tx` participants grouped by style, for the page that has to say
     * which is which.
     *
     * Derived from adapterFeatures() so an app that does not implement `aop-tx` at all
     * is not named, AND from excludedRequests() so an app that implements the feature
     * but sits out its one raced endpoint is not named either. Phalcon is that second
     * case: it serves `/features/aop-tx-direct`, so `'aop-tx'` is in its feature list, but
     * its `/features/aop-tx` runs the hand-written transaction every other entrant
     * reaches through advice — so it must not be listed as having competed, or the
     * style split would claim a race it did not run.
     *
     * @return array{transparent:list<string>,direct:list<string>}
     */
    public static function aopStylesInRace(): array
    {
        $out = ['transparent' => [], 'direct' => []];

        foreach (self::adapterFeatures() as $app => $features) {
            if (!in_array('aop-tx', $features, true)) {
                continue;
            }
            if (!self::racesOn($app, 'GET /features/aop-tx')) {
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
     * Azera has two advised methods left on `FeatureService` —
     * `createItemTransactional` (`#[Transactional]`) and `countItems` (`#[Cache]`)
     * — and THREE endpoints route through them: `aop-tx`, `events` and
     * `aop-cache`. (`events` calls `createItemTransactional()` too, which is why
     * it belongs here even though its feature is the event dispatcher.)
     * `aop-cache-direct`, `log`, `db-events`, `validation`, `config` and
     * `rate-limit` are plain code on both sides.
     *
     * `log` used to be in this list: `logSomething` carried `#[Log]` and the row
     * paid for a proxy dispatch plus an `LogInterceptor` frame the other six
     * entrants do not have. That made the logging chart compare two different
     * kinds of machinery, so the advice was removed and every entrant now writes
     * its entry/exit records in the method body. The `#[Log]` advice still exists
     * in the framework and is still exercised — by the `aop-*` rows, which are
     * what a row about interception should measure.
     *
     * `db-events` was removed for the same reason. Its demo called
     * `createItemTransactional()`, so Azera alone paid a proxy dispatch and a
     * `TransactionalInterceptor` frame on a row about the event pipeline — and
     * alone opened a transaction, which then leaked into three entrants'
     * descriptions as transaction events their code could not produce. It now
     * calls `createItemAutocommit()`.
     *
     * `retry` had the same problem and a worse one, so the whole row is gone.
     * `flakyOperation` carried `#[Retry]`, which made the label mean three
     * different things across the field (generated proxy / explicit pipeline /
     * bare loop), and the work itself was two in-process exceptions — every
     * entrant's row landed within ~3% of its own `config` row. {@see featureMap()}
     * has the numbers.
     *
     * The measured consequence, php-fpm arm of the 2026-11-23 run:
     *
     *   azera proxy-routed vs Phalcon's plain implementation
     *     log 0.58x · events 0.89x
     *   azera plain vs Phalcon plain
     *     validation 0.40x · config 0.43x · rate-limit 0.43x
     *
     * Phalcon loses every row where neither entrant pays for a proxy, by 2.3-2.5x
     * on the plain rows, and does not win the two remaining rows either. So the
     * per-feature ranking here is dominated by each framework's request-dispatch
     * floor, not by the cache, the logger or the rate limiter being faster in one
     * of them.
     *
     * Note this is NOT the same as saying the proxy "costs" that ratio: the two
     * lists are different workloads, not the same work done with and without
     * advice. Pricing the proxy needs the identical endpoint implemented both
     * ways, which no entrant provides (see `aopStyle()`).
     *
     * @return array<string,array<string,bool>> app => endpoint => routed through advice
     */
    public static function adviceRoutedEndpoints(): array
    {
        $azera = [
            'GET /features/aop-tx'      => true,   // createItemTransactional #[Transactional]
            'GET /features/events'   => true,   // createItemTransactional #[Transactional]
            'GET /features/aop-cache' => true,  // countItems #[Cache]
            'GET /features/aop-cache-direct' => false, // hand-written lookup
            'GET /features/log'      => false,  // direct logging, no advice
            'GET /features/db-events' => false, // createItemAutocommit, plain autocommit write
            'GET /features/validation' => false,
            'GET /features/config'   => false,
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
        // Six names joined by "and" read badly as a subject ("Laravel and Symfony
        // and … ships"), so the list is comma-separated and the verb agrees with
        // whether it names one entrant or many. The sentence is printed verbatim on
        // both pages, so the grammar is part of the contract.
        $named   = implode(', ', $absent);
        $subject = $named !== '' ? $named : 'This entrant';
        $plural  = count($absent) > 1;
        $ships   = $plural ? 'ship' : 'ships';
        $it      = $plural ? 'they' : 'it';
        $its     = $plural ? 'their' : 'its';
        $isNot   = $plural ? 'are not' : 'is not';
        $cacheRow = $plural
            ? 'The cache row those entrants serve is '
            : 'The cache row this entrant serves is ';

        if ($feature === 'aop-tx') {
            return "{$subject} {$ships} no attribute-declared, proxy-generated advice layer, so "
                . 'there is no transparent AOP to race. That is a FACILITY gap, not a missing '
                . 'endpoint: every entrant answers `GET /features/aop-tx-direct` (the same write with '
                . 'the transaction written by hand) and `GET /features/aop-cache-direct` (the same '
                . 'cached COUNT with the lookup written by hand), which are the controls this row '
                . 'is read against.';
        }

        if ($feature === 'aop-cache') {
            return "{$subject} {$ships} no attribute-declared, proxy-generated advice layer, so "
                . "{$it} cannot apply caching through `#[Cache]`. {$cacheRow}"
                . '`GET /features/aop-cache-direct` — the same COUNT, the same key and the same '
                . '10-second TTL with the lookup written in application code — which every entrant '
                . 'races.';
        }

        if ($feature === 'aop-tx-explicit') {
            return "{$subject} {$ships} no dedicated interceptor abstraction (an interceptor "
                . 'interface plus a pipeline that composes it), so the explicit AOP form '
                . "{$isNot} {$its} to serve. A hand-written `begin`/`commit` is not this feature.";
        }

        return "{$subject} " . ($plural ? 'do' : 'does')
            . ' not provide the facility this benchmark measures.';
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
