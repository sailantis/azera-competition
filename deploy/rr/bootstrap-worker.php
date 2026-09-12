<?php

/**
 * Adapter registry + factory for the RoadRunner worker (and any other
 * out-of-process deployment). Mirrors run.php's $adapterClasses map — the
 * single source of truth stays there; this copy exists because the worker
 * is a separate process that must not include run.php.
 */

function benchAdapterClasses(): array
{
    return [
        'azera'       => 'AzeraAdapter',
        'laravel'     => 'LaravelAdapter',
        'symfony'     => 'SymfonyAdapter',
        'spiral'      => 'SpiralAdapter',
        'codeigniter' => 'CodeIgniterAdapter',
        'cakephp'     => 'CakePhpAdapter',
    ];
}

function createAdapter(string $appKey): WebAppAdapter
{
    $classes = benchAdapterClasses();
    if (!isset($classes[$appKey])) {
        fwrite(STDERR, "[worker] Unknown BENCH_APP '{$appKey}'\n");
        exit(1);
    }

    $class = $classes[$appKey];
    // Adapter classes are plain global classes in adapters/*.php — not in
    // composer's autoload and not covered by BenchmarkAutoloader (which maps
    // the app namespaces only). Same pattern as run-app.php/smoke-app.php.
    $root = dirname(__DIR__, 2);
    require_once "{$root}/adapters/{$class}.php";

    return new $class();
}

// Guard: Laravel's helpers.php declares a global env() as well — the adapter
// bootstraps the framework inside the same worker process, so only declare
// ours when it doesn't already exist.
if (!function_exists('env')) {
    function env(string $key): ?string
    {
        $v = getenv($key);
        return $v === false ? null : $v;
    }
}