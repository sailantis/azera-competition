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
            'GET /'                        => 'routing',
            'GET /items'                   => 'orm',
            'GET /items/1'                 => 'orm',
            'POST /items'                  => 'orm',
            'GET /items-qb'                => 'query-builder',
            'GET /items-qb/1'              => 'query-builder',
            'POST /items-qb'               => 'query-builder',
            'GET /api/items'               => 'rest-api',
            'GET /api/items/1'             => 'rest-api',
            'POST /api/items'              => 'rest-api',
            'GET /features/aop'            => 'aop',
            'GET /features/cache'          => 'cache',
            'GET /features/log'            => 'aop',
            'GET /features/retry'          => 'aop',
            'GET /features/pipeline'       => 'aop',
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
            'azera'       => ['routing', 'orm', 'query-builder', 'rest-api', 'aop', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'laravel'     => ['routing', 'orm', 'query-builder', 'rest-api', 'aop', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'symfony'     => ['routing', 'orm', 'query-builder', 'rest-api', 'aop', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'spiral'      => ['routing', 'orm', 'query-builder', 'rest-api', 'aop', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'codeigniter' => ['routing', 'orm', 'query-builder', 'rest-api', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
            'cakephp'     => ['routing', 'orm', 'query-builder', 'rest-api', 'cache', 'db-events', 'events', 'validation', 'config', 'request-scoped', 'rate-limiter'],
        ];
    }

    /**
     * Canonical display order for frameworks.
     *
     * @return list<string>
     */
    public static function appOrder(): array
    {
        return ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
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
        ];
    }

    /**
     * Human label per feature key.
     */
    public static function featureLabel(string $feature): string
    {
        return [
            'routing'        => 'Routing',
            'orm'            => 'ORM / Active Record',
            'query-builder'  => 'Query Builder',
            'rest-api'       => 'REST API (JSON)',
            'aop'            => 'AOP (Aspect-Oriented)',
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
            'GET /items'                   => '20 of 1000 items (page 1, + COUNT)',
            'GET /items/1'                 => '1 item by id',
            'POST /items'                  => '1 row upserted (sentinel #999999)',
            'GET /items-qb'                => '20 of 1000 items (page 1, + COUNT)',
            'GET /items-qb/1'              => '1 item by id',
            'POST /items-qb'               => '1 row upserted (sentinel #999997)',
            'GET /api/items'               => '20 of 1000 items as JSON',
            'GET /api/items/1'             => '1 item by id as JSON',
            'POST /api/items'              => '1 row upserted (sentinel #999998)',
            'GET /features/aop'            => 'no DB — interceptor pipeline',
            'GET /features/cache'          => 'COUNT(*) of 1000 rows, cached 10s (miss = query)',
            'GET /features/log'            => 'no DB — buffered log handlers',
            'GET /features/retry'          => 'no DB — retry policy',
            'GET /features/pipeline'       => 'no DB — middleware pipeline',
            'GET /features/db-events'      => '1 event row INSERTed per request',
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
            'routing'        => 'dispatches a plain request through the router and returns a rendered '
                . 'template — no database access.',
            'orm'            => 'loads one page of the 1,000 seeded rows through each framework\'s '
                . 'ORM / Active Record layer: 20 items plus a COUNT for the pagination total.',
            'query-builder'  => 'builds the same page of 20 items with each framework\'s query builder '
                . 'instead of its ORM, so the two data-access styles can be compared directly.',
            'rest-api'       => 'serves the same page of items as a JSON response rather than HTML, '
                . 'which adds serialization to the ORM work.',
            'aop'            => 'runs a request through an interceptor pipeline — logging, retry and '
                . 'middleware aspects wrapped around the handler. Only frameworks with an AOP layer '
                . 'take part.',
            'cache'          => 'reads a COUNT(*) over the 1,000 rows through the framework\'s cache '
                . 'with a 10-second TTL, so a hit costs no database work and a miss runs the query.',
            'db-events'      => 'inserts one event row per request and lets the framework\'s database '
                . 'events fire around that write.',
            'events'         => 'dispatches an in-process event to registered listeners.',
            'validation'     => 'validates a payload with the framework\'s own validator.',
            'config'         => 'resolves a value from the framework\'s config repository.',
            'request-scoped' => 'resolves a service scoped to the request from the container.',
            'rate-limiter'   => 'checks a cache-backed rate limiter.',
        ];
    }

    /**
     * One-line description of a feature; '' when unmapped.
     */
    public static function featureDescriptionFor(string $feature): string
    {
        return self::featureDescription()[$feature] ?? '';
    }
}