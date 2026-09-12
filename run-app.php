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
 * Endpoint-block isolation: memory_get_peak_usage() is a process-lifetime
 * high-water mark and frameworks accumulate state across re-boots (service
 * containers, static caches, opcache bookkeeping). Measuring all endpoint
 * blocks of one app x mode in a single process therefore made peak_mem
 * staircase with the block ORDER instead of reflecting the per-endpoint
 * footprint (2026-09-12: Laravel cold climbed 32 MB -> 502 MB across 21
 * endpoints). When more than one request is given, this process thus acts
 * as a pure orchestrator and re-spawns ITSELF once per request — every
 * (app, mode, request) block runs in a fresh PHP process with a clean
 * high-water mark. Only the first block measures boot cost (--skip-boot is
 * passed to the rest); --seed is applied once, before the blocks.
 *
 * Everything after the shared preamble mirrors run.php's per-app loop.
 */

$opts = getopt('', ['app::', 'mode::', 'iterations-per-run::', 'runs::', 'requests::', 'seed', 'rows::', 'out-json::', 'skip-boot']);

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
require_once __DIR__ . '/adapters/BenchmarkAutoloader.php';
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
        'min'    => $values[0],
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

// --- Endpoint-block isolation (orchestrator mode) ----------------------------
//
// With more than one request we do NOT measure in this process. Instead we
// spawn one fresh child per request (this same script with a single request)
// and merge the per-block results. Rationale: see the header comment — a
// fresh process per block is the only way to get an honest per-endpoint
// peak_mem, because the frameworks' state accumulates over process lifetime.
// The recursion is self-limiting: each child receives exactly one request
// and therefore takes the normal measuring path below.

if (count($requests) > 1) {
    echo " -- mode: {$modeName} — " . count($requests) . " endpoint blocks, one fresh process each\n";

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

    $merged = ['app' => $appKey, 'modes' => [$modeName => ['requests' => []]], 'boot' => null];

    foreach ($requests as $blockIdx => $request) {
        $label   = "{$request[0]} {$request[1]}";
        $tmpJson = tempnam(sys_get_temp_dir(), 'bench-block-') . '.json';
        $cmd     = sprintf(
            '%s %s --app=%s --mode=%s --iterations-per-run=%d --runs=%d --requests=%s --out-json=%s%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__FILE__),
            escapeshellarg($appKey),
            escapeshellarg($modeName),
            $itersPerRun,
            $runs,
            escapeshellarg($label),
            escapeshellarg($tmpJson),
            $blockIdx === 0 ? '' : ' --skip-boot'
        );

        passthru($cmd, $blockExit);
        if ($blockExit !== 0 || !is_file($tmpJson)) {
            fwrite(STDERR, "Block benchmark for {$label} failed (exit {$blockExit}), aborting.\n");
            exit(1);
        }

        $block = json_decode((string) file_get_contents($tmpJson), true);
        unlink($tmpJson);
        if (!is_array($block) || !isset($block['modes'][$modeName]['requests'][0])) {
            fwrite(STDERR, "Block benchmark for {$label} returned no results, aborting.\n");
            exit(1);
        }

        $merged['modes'][$modeName]['requests'][] = $block['modes'][$modeName]['requests'][0];
        if ($merged['boot'] === null && isset($block['boot'])) {
            $merged['boot'] = $block['boot'];
        }
    }

    $json = json_encode($merged);
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
    exit(0);
}

$class = $adapterClasses[$appKey];
require_once __DIR__ . '/adapters/' . $class . '.php';

/** @var WebAppAdapter $adapter */
$adapter = new $class();

// --- Framework boot cost -----------------------------------------------------

/**
 * Time consecutive bootstrap() calls: the framework's real load cost
 * (autoloader + kernel/container build + routes + DB connect), with
 * GET / as the first request inside each boot. This is the number a
 * RoadRunner-style worker pays per cold start and per worker recycle.
 *
 * Two numbers go into the result JSON as "boot": { cold_ms, warm_ms }.
 *   cold_ms = the very first bootstrap in the process (fresh PHP state)
 *   warm_ms = trimmed mean of N further re-boots (kernel re-created,
 *             classes/opcache already loaded)
 */
function measureBoot(WebAppAdapter $adapter, int $warmRuns = 5): array
{
    $t0 = hrtime(true);
    $adapter->bootstrap();
    $cold = (hrtime(true) - $t0) / 1e6;
    // One untimed warm-up dispatch so route compilation/template caches land
    // before the first timed dispatch of the request loop.
    $adapter->dispatch('GET', '/');
    $adapter->cleanup();

    $warmTimes = [];
    for ($i = 0; $i < $warmRuns; $i++) {
        $t0 = hrtime(true);
        $adapter->bootstrap();
        $warmTimes[] = (hrtime(true) - $t0) / 1e6;
    }
    $adapter->dispatch('GET', '/');
    $adapter->cleanup();

    sort($warmTimes);
    // Trimmed mean, same shape as the request loop's trimmedMean(): drop
    // 10% off each end so a single slow boot (fsync, antivirus) doesn't
    // tilt the number.
    $drop = max(0, (int) round(count($warmTimes) * 0.1));
    $kept = array_slice($warmTimes, $drop, count($warmTimes) - 2 * $drop);
    if ($kept === []) {
        $kept = $warmTimes;
    }

    return [
        'cold_ms' => $cold,
        'warm_ms' => array_sum($kept) / count($kept),
    ];
}

// Non-first blocks (spawned by the orchestrator above) skip the boot
// measurement: the boot numbers come from this mode's first block, and
// skipping keeps later blocks free of extra re-boot state.
$boot = isset($opts['skip-boot']) ? null : measureBoot($adapter);
if ($boot !== null) {
    printf(
        "  boot — cold %.1f ms, warm %.1f ms\n",
        $boot['cold_ms'],
        $boot['warm_ms']
    );
}

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
        // one untimed warm-up dispatch (with its cleanup, so state matches
        // what the timed loop starts from)
        $adapter->dispatch($method, $uri);
        $adapter->cleanup();
    }

    $runMeans     = [];
    $handleMeans  = [];
    $cleanupMeans = [];
    $allTimes     = [];
    $peakMem      = 0;

    for ($r = 0; $r < $runs; $r++) {
        // Per-run memory window: memory_get_peak_usage() is a PROCESS-LIFETIME
        // high-water mark — without this reset every endpoint would report
        // max(all previous endpoints, itself) and the memory chart would
        // plot benchmark ORDER, not per-endpoint footprint (2026-09-12 bug).
        memory_reset_peak_usage();

        if ($mode === 'cold') {
            $adapter->bootstrap();
        }

        $times        = [];
        $handleTimes  = [];
        $cleanupTimes = [];
        for ($i = 0; $i < $itersPerRun; $i++) {
            $t0   = hrtime(true);
            $body = $adapter->dispatch($method, $uri);
            $t1   = hrtime(true);
            $adapter->cleanup();
            $t2 = hrtime(true);
            // Harness guard — an adapter that swallows an error (404/500)
            // returns a short error string instead of the real response.
            // Timing those would silently poison the dataset (this is how
            // the 2026-09-12 symfony /features/* rows came out flat at
            // ~0.05 ms: every request was actually a 404). Fail loudly
            // instead so the run can be fixed and re-run.
            if (str_starts_with($body, 'Not Found') || str_starts_with($body, '500 ')) {
                fwrite(STDERR, "\n[ABORT] {$mode} {$reqLabel} returned an error response\n"
                    . "  body: " . substr($body, 0, 300) . "\n"
                    . "  A dataset must never contain error responses — fix the app\n"
                    . "  (stale cache? missing route? changed signature?) and re-run.\n");
                exit(1);
            }
            $handleTimes[] = ($t1 - $t0) / 1e6;
            $cleanupTimes[] = ($t2 - $t1) / 1e6;
            $times[] = ($t2 - $t0) / 1e6;
        }

        $s = stats($times);
        $runMeans[] = $s['mean'];
        $handleMeans[] = stats($handleTimes)['mean'];
        $cleanupMeans[] = stats($cleanupTimes)['mean'];
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

    // Handle vs teardown split (trimmed mean over per-run means, same
    // convention as the headline number). handle + cleanup = trimmed_mean.
    $hMean = trimmedMean($handleMeans);
    $cMean = trimmedMean($cleanupMeans);

    return [
        'request'            => $reqLabel,
        'iterations_per_run' => $itersPerRun,
        'runs'               => $runs,
        'trimmed_mean_ms'    => $tMean,
        'min_ms'             => $sAll['min'],
        'mean_ms'            => $sAll['mean'],
        'median_ms'          => $sAll['median'],
        'p95_ms'             => $sAll['p95'],
        'peak_mem'           => $peakMem,
        'handle_ms'          => $hMean,
        'cleanup_ms'         => $cMean,
    ];
}

$appResult = ['app' => $appKey, 'modes' => [], 'boot' => $boot];

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