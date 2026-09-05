<?php

/**
 * Per-app benchmark runner (child process of run.php).
 *
 * Full-stack PHP frameworks define colliding global helper functions
 * (config(), view(), env(), ...) that are all function_exists-guarded —
 * which framework's helper wins is decided purely by load order, so two
 * of them cannot share one PHP process. The harness therefore benchmarks
 * each app in its own fresh process: run.php spawns `php run-app.php
 * <key> [options]` per app and merges the JSON results.
 *
 * Everything after the shared preamble mirrors run.php's per-app loop.
 */

$opts = getopt('', ['app::', 'mode::', 'iterations-per-run::', 'runs::', 'requests::', 'seed', 'rows::', 'out-json::']);

$appKey = $opts['app'] ?? 'azera';

// CI4's global helpers (config(), view(), env(), ...) are function_exists-
// guarded and collide with Laravel's, which composer's `files` autoload
// includes eagerly on vendor/autoload.php. In this child process only ONE
// framework runs — for codeigniter, pre-load CI4's Common.php BEFORE the
// composer autoloader so its helpers win the race and Laravel's guarded
// definitions simply skip the taken names. (Common.php is pure function
// definitions — no top-level side effects — so the early include is safe.)
if ($appKey === 'codeigniter') {
    require_once __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WebAppAdapter.php';
$itersPerRun = isset($opts['iterations-per-run']) ? (int) $opts['iterations-per-run'] : 1000;
$runs        = isset($opts['runs']) ? (int) $opts['runs'] : 30;
$modeName    = $opts['mode'] ?? 'warm';
$doSeed      = isset($opts['seed']);
$seedRows    = isset($opts['rows']) ? (int) $opts['rows'] : 1000;
$outJsonPath = $opts['out-json'] ?? null;

// Requests: same default list as run.php (duplicated here because the child
// process re-parses options from scratch).
$requests = isset($opts['requests'])
    ? array_map('trim', preg_split('/[,\n]/', $opts['requests']))
    : [
        // HTML benchmark endpoints
        'GET /',
        'GET /items',
        'GET /items/1',
        'POST /items',
        'GET /items-qb',
        'GET /items-qb/1',
        'POST /items-qb',
        // REST API endpoints (JSON serialization category)
        'GET /api/items',
        'GET /api/items/1',
        'POST /api/items',
        // Feature demo endpoints (each framework participates only if it
        // supports the underlying feature — see run.php $adapterFeatures)
        'GET /features/aop',
        'GET /features/cache',
        'GET /features/log',
        'GET /features/retry',
        'GET /features/pipeline',
        'GET /features/db-events',
        'GET /features/events',
        'GET /features/validation',
        'GET /features/config',
        'GET /features/request-scoped',
        'GET /features/rate-limit',
    ];

$requests = array_map(function (string $r): array {
    $parts = explode(' ', $r, 2);
    return [strtoupper(trim($parts[0])), trim($parts[1] ?? '/')];
}, $requests);

// --- Shared stats helpers (kept in sync with run.php) -----------------------

function stats(array $values): array
{
    sort($values);
    $count  = count($values);
    $sum    = array_sum($values);
    $mean   = $sum / $count;
    $median = $values[(int) floor(($count - 1) / 2)];
    $p95Idx = max(0, min($count - 1, (int) floor($count * 0.95) - 1));
    return [
        'count'  => $count,
        'mean'   => $mean,
        'median' => $median,
        'p95'    => $values[$p95Idx],
    ];
}

function trimmedMean(array $values): float
{
    sort($values);
    $drop = max(1, (int) round(count($values) * 0.1));
    $kept = array_slice($values, $drop, count($values) - 2 * $drop);
    if (count($kept) === 0) {
        $kept = $values;
    }
    return array_sum($kept) / count($kept);
}

// --- Adapter registry (same mapping as run.php) ------------------------------

$adapterClasses = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
];

if (!isset($adapterClasses[$appKey])) {
    fwrite(STDERR, "Unknown adapter: {$appKey}\n");
    exit(1);
}

$class = $adapterClasses[$appKey];
require_once __DIR__ . '/adapters/' . $class . '.php';

/** @var WebAppAdapter $adapter */
$adapter = new $class();

// --- Optional reseed (same rationale as run.php) -----------------------------

if ($doSeed) {
    echo "    reseeding database ({$seedRows} rows)...\n";
    $seedScript  = escapeshellarg(__DIR__ . '/seed.php');
    $seedRowsArg = escapeshellarg((string) $seedRows);
    passthru("php {$seedScript} --rows={$seedRowsArg}", $seedExit);
    if ($seedExit !== 0) {
        echo "Seed failed (exit {$seedExit}), aborting.\n";
        exit(1);
    }
}

// --- Benchmark loop (mirrors run.php benchRequest) ---------------------------

/**
 * Time a single request combination.
 */
function benchRequest(WebAppAdapter $adapter, string $mode, array $request, int $itersPerRun, int $runs): array
{
    [$method, $uri] = $request;
    $reqLabel = "{$method} {$uri}";

    echo "  [{$mode}] {$reqLabel} — {$itersPerRun}×{$runs}\n";

    if ($mode === 'warm') {
        $adapter->bootstrap();
        // one untimed warm-up dispatch
        $adapter->dispatch($method, $uri);
    }

    $runMeans = [];
    $allTimes = [];
    $peakMem  = 0;

    for ($r = 0; $r < $runs; $r++) {
        if ($mode === 'cold') {
            $adapter->bootstrap();
        }

        $times = [];
        for ($i = 0; $i < $itersPerRun; $i++) {
            $t0 = hrtime(true);
            $adapter->dispatch($method, $uri);
            $t1 = hrtime(true);
            $times[] = ($t1 - $t0) / 1e6;
        }

        $s = stats($times);
        $runMeans[] = $s['mean'];
        $allTimes = array_merge($allTimes, $times);
        $peakMem  = max($peakMem, memory_get_peak_usage(true));

        echo sprintf(
            "    run %2d/%d — mean %.4f ms, median %.4f ms\n",
            $r + 1,
            $runs,
            $s['mean'],
            $s['median']
        );
    }

    $sAll  = stats($allTimes);
    $tMean = trimmedMean($runMeans);

    return [
        'request'            => $reqLabel,
        'iterations_per_run' => $itersPerRun,
        'runs'               => $runs,
        'trimmed_mean_ms'    => $tMean,
        'mean_ms'            => $sAll['mean'],
        'median_ms'          => $sAll['median'],
        'p95_ms'             => $sAll['p95'],
        'peak_mem'           => $peakMem,
    ];
}

$appResult = ['app' => $appKey, 'modes' => []];

echo " -- mode: {$modeName}\n";
$modeResult = ['requests' => []];
foreach ($requests as $request) {
    $modeResult['requests'][] = benchRequest(
        $adapter,
        $modeName,
        $request,
        $itersPerRun,
        $runs
    );
}
$appResult['modes'][$modeName] = $modeResult;

// Emit this app's result as JSON — to the file given via --out-json when
// provided (run.php captures it there), else to stdout.
$json = json_encode($appResult);
if (is_string($outJsonPath) && $outJsonPath !== '') {
    if (file_put_contents($outJsonPath, $json) === false) {
        fwrite(STDERR, "Failed to write results to {$outJsonPath}\n");
        exit(1);
    }
    echo "Results written to {$outJsonPath}\n";
} else {
    echo "---RESULTS---\n";
    echo $json . "\n";
}