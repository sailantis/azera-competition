<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Filters config.
 *
 * Required filters trimmed to the minimum for in-process dispatch:
 * `forcehttps` and the debug toolbar are irrelevant for synthetic requests
 * and would only add overhead no real deployment forces globally on plain
 * HTTP benchmarks.  csrf/toolbars stay available per-route.
 */

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;

class Filters extends BaseFilters
{
    /**
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => \CodeIgniter\Filters\CSRF::class,
        'toolbar'       => \CodeIgniter\Filters\DebugToolbar::class,
        'honeypot'      => \CodeIgniter\Filters\Honeypot::class,
        'invalidchars'  => \CodeIgniter\Filters\InvalidChars::class,
        'secureheaders' => \CodeIgniter\Filters\SecureHeaders::class,
        'cors'          => \CodeIgniter\Filters\Cors::class,
        'forcehttps'    => \CodeIgniter\Filters\ForceHTTPS::class,
        'pagecache'     => \CodeIgniter\Filters\PageCache::class,
        'performance'   => \CodeIgniter\Filters\PerformanceMetrics::class,
    ];

    /**
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [],
        'after'  => [],
    ];

    /**
     * @var array{before: array<string, array{except: list<string>|string}>|list<string>, after: array<string, array{except: list<string>|string}>|list<string>}
     */
    public array $globals = [
        'before' => [],
        'after'  => [],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * @var array<string, mixed>
     */
    public array $filters = [];
}