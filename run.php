<?php
/**
 * azera-competition — main benchmark harness.
 *
 * Times each framework adapter's dispatch() across multiple runs of N
 * iterations, in cold and/or warm modes, and emits JSON + CSV + Markdown
 * report.
 *
 * Usage:
 *   php -d opcache.enable_cli=1 run.php
 *       --apps=azera,laravel,symfony,spiral,codeigniter,cakephp
 *       --iterations-per-run=1000
 *       --runs=30
 *       --warm
 *       --out=results/2026-08-05-120000
 *
 * Run "php run.php --help" for all options.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WebAppAdapter.php';

// --- CLI options -----------------------------------------------------------

$opts = getopt(
    '',
    [
        'apps::',
        'iterations-per-run::',
        'runs::',
        'warm',
        'cold',
        'out::',
        'help',
        'clear-cache',
        'requests::',
        'seed::',
        'rows::',
    ]
);

if (isset($opts['help'])) {
    echo <<<TXT
azera-competition benchmark harness

Usage:
  php -d opcache.enable_cli=1 run.php [options]

Options:
  --apps=<csv>            Comma-separated adapter keys (default: azera)
  --iterations-per-run=N  Iterations per run (default: 1000)
  --runs=N                Number of runs (default: 30)
  --warm                  Measure warm mode (bootstrap once, reuse) [default]
  --cold                  Measure cold mode (fresh bootstrap per iteration)
  --out=<prefix>          Write JSON + CSV + Markdown report with this prefix
  --clear-cache           Clear template caches before running
  --requests=<csv>        Comma-separated "METHOD URI" requests to benchmark
                          (default: "GET /","GET /items","GET /items/1",
                           "POST /items",
                           "GET /items-qb","GET /items-qb/1","POST /items-qb",
                           "GET /api/items","GET /api/items/1","POST /api/items")
  --seed                  Reset & reseed the SQLite DB before running (prevents
                          POST /items from accumulating rows across runs)
  --rows=N                Row count when --seed is used (default: 1000)

Examples:
  php -d opcache.enable_cli=1 run.php --apps=azera --warm --iterations-per-run=1000 --runs=10
  php -d opcache.enable_cli=1 run.php --apps=azera,laravel --warm --cold --out=results/run1

TXT;
    exit(0);
}

$apps        = isset($opts['apps']) ? explode(',', $opts['apps']) : ['azera'];
$itersPerRun = isset($opts['iterations-per-run']) ? (int) $opts['iterations-per-run'] : 1000;
$runs        = isset($opts['runs']) ? (int) $opts['runs'] : 30;
$doWarm      = isset($opts['warm']) || (!isset($opts['cold']));
$doCold      = isset($opts['cold']);
$outPrefix   = $opts['out'] ?? null;
$clearCache  = isset($opts['clear-cache']);
$doSeed      = isset($opts['seed']);
$seedRows    = isset($opts['rows']) ? (int) $opts['rows'] : 1000;
$requests    = isset($opts['requests'])
    ? array_map('trim', explode(',', $opts['requests']))
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
    ];

// Normalize requests to [method, uri] pairs
$requests = array_map(function (string $r): array {
    $parts = explode(' ', $r, 2);
    return [strtoupper(trim($parts[0])), trim($parts[1] ?? '/')];
}, $requests);

// --- Adapter registry ------------------------------------------------------

$adapterClasses = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
];

// --- Helpers ---------------------------------------------------------------

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

function envInfo(): array
{
    return [
        'php_version' => PHP_VERSION,
        'os'          => PHP_OS . ' ' . php_uname('r'),
        'opcache'     => (bool) ini_get('opcache.enable_cli'),
        'sapi'        => PHP_SAPI,
        'timestamp'   => date('c'),
    ];
}

function writeResults(string $prefix, array $results): void
{
    $dir = dirname($prefix);
    if ($dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($prefix . '.json', json_encode($results, JSON_PRETTY_PRINT));

    $fp = fopen($prefix . '.csv', 'w');
    fputcsv($fp, [
        'app',
        'mode',
        'request',
        'iterations_per_run',
        'runs',
        'trimmed_mean_ms',
        'mean_ms',
        'median_ms',
        'p95_ms',
        'peak_mem',
    ]);
    foreach ($results['apps'] as $app) {
        foreach ($app['modes'] as $modeName => $mode) {
            foreach ($mode['requests'] as $req) {
                fputcsv($fp, [
                    $app['app'],
                    $modeName,
                    $req['request'],
                    $req['iterations_per_run'],
                    $req['runs'],
                    $req['trimmed_mean_ms'],
                    $req['mean_ms'],
                    $req['median_ms'],
                    $req['p95_ms'],
                    $req['peak_mem'],
                ]);
            }
        }
    }
    fclose($fp);
}

function writeReport(string $prefix, array $results): void
{
    $lines = [
        "# Benchmark report — {$results['env']['timestamp']}",
        '',
        '## Environment',
        '',
        '- PHP: ' . $results['env']['php_version'],
        '- OS: ' . $results['env']['os'],
        '- OPcache (CLI): ' . ($results['env']['opcache'] ? 'yes' : 'no'),
        '- SAPI: ' . $results['env']['sapi'],
        '',
        '## Summary',
        '',
    ];

    foreach ($results['apps'] as $app) {
        $lines[] = "### {$app['app']}";
        $lines[] = '';
        $lines[] = '| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |';
        $lines[] = '|---|---|---:|---:|---:|---:|---:|---:|---:|';
        foreach ($app['modes'] as $modeName => $mode) {
            foreach ($mode['requests'] as $req) {
                $lines[] = sprintf(
                    '| %s | %s | %d | %d | %.4f | %.4f | %.4f | %.4f | %s |',
                    $modeName,
                    $req['request'],
                    $req['iterations_per_run'],
                    $req['runs'],
                    $req['trimmed_mean_ms'],
                    $req['mean_ms'],
                    $req['median_ms'],
                    $req['p95_ms'],
                    number_format($req['peak_mem'])
                );
            }
        }
        $lines[] = '';
    }

    file_put_contents($prefix . '.md', implode("\n", $lines));
}

// --- Benchmark loop --------------------------------------------------------

/**
 * Time a single (app, mode, request) combination.
 */
function benchRequest(
    WebAppAdapter $adapter,
    string $mode,
    array $request,
    int $itersPerRun,
    int $runs
): array {
    [$method, $uri] = $request;
    $reqLabel = "{$method} {$uri}";

    echo "  [{$mode}] {$reqLabel} — {$itersPerRun}×{$runs}\n";

    // Warm-up / bootstrap
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

// --- Main ------------------------------------------------------------------

// --- Optional DB reseed ---------------------------------------------------
if ($doSeed) {
    echo "Reseeding database ({$seedRows} rows)...\n";
    $seedScript  = escapeshellarg(__DIR__ . '/seed.php');
    $seedRowsArg = escapeshellarg((string) $seedRows);
    passthru("php {$seedScript} --rows={$seedRowsArg}", $seedExit);
    if ($seedExit !== 0) {
        echo "Seed failed (exit {$seedExit}), aborting.\n";
        exit(1);
    }
}

echo "=== azera-competition benchmark ===\n";
echo "Apps: " . implode(', ', $apps) . "\n";
echo "Iterations/run: {$itersPerRun}, Runs: {$runs}\n";
echo "Modes: " . implode(', ', array_filter(['warm' => $doWarm, 'cold' => $doCold])) . "\n";
echo "Requests: " . implode(', ', array_map(fn($r) => "{$r[0]} {$r[1]}", $requests)) . "\n\n";

$results = [
    'env'  => envInfo(),
    'apps' => [],
];

foreach ($apps as $key) {
    $key = trim($key);
    if (!isset($adapterClasses[$key])) {
        echo "Unknown adapter: {$key} (skipping)\n";
        continue;
    }

    $class = $adapterClasses[$key];
    require_once __DIR__ . "/adapters/{$class}.php";

    echo "\n=== App: {$key}\n";

    // Warm and cold modes share one adapter instance; bootstrap is called
    // appropriately inside benchRequest.
    /** @var WebAppAdapter $adapter */
    $adapter = new $class();

    $appResult = [
        'app'   => $key,
        'modes' => [],
    ];

    $modes = array_filter([
        'warm' => $doWarm,
        'cold' => $doCold,
    ]);

    foreach (array_keys($modes) as $modeName) {
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
    }

    $results['apps'][] = $appResult;
}

if ($outPrefix !== null) {
    writeResults($outPrefix, $results);
    writeReport($outPrefix, $results);
    echo "\nWrote: {$outPrefix}.json, {$outPrefix}.csv, {$outPrefix}.md\n";
}

echo "\nDone.\n";