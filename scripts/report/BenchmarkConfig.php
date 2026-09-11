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
}