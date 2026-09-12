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
require_once __DIR__ . '/scripts/report/BenchmarkConfig.php';

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
        'report',
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
                          (default: all benchmark + REST API + feature routes;
                           see the $requests array in run.php)
  --seed                  Reset & reseed the SQLite DB before running (prevents
                          POST /items from accumulating rows across runs)
  --rows=N                Row count when --seed is used (default: 1000)
  --report                Also regenerate the report (docs/benchmarks/):
                          HTML dashboard + SVG charts + Markdown fragment.
                          The dataset is passed through, so the report always
                          matches the run you just made.

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
        // -orm paths deactivated (2026-09-05) — re-enable together with the
        // routes in apps/azera/Bootstrap.php and verify.php's $expect:
        // 'GET /items-orm',
        // 'GET /items-orm/1',
        // 'POST /items-orm',
        'GET /items-qb',
        'GET /items-qb/1',
        'POST /items-qb',
        // REST API endpoints (JSON serialization category)
        'GET /api/items',
        'GET /api/items/1',
        'POST /api/items',
        // Feature demo endpoints (each framework participates only if it
        // supports the underlying feature — see $adapterFeatures)
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

// Normalize requests to [method, uri] pairs
$requests = array_map(function (string $r): array {
    $parts = explode(' ', $r, 2);
    return [strtoupper(trim($parts[0])), trim($parts[1] ?? '/')];
}, $requests);

// --- Feature categories ----------------------------------------------------
// The maps live in scripts/report/BenchmarkConfig.php so the harness and the
// report generator share a single source of truth and cannot drift apart.
$featureMap      = \AzeraCompetition\Report\BenchmarkConfig::featureMap();
$adapterFeatures = \AzeraCompetition\Report\BenchmarkConfig::adapterFeatures();

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

function envInfo(): array
{
    return [
        'php_version'         => PHP_VERSION,
        'os'                  => PHP_OS . ' ' . php_uname('r'),
        'opcache'             => (bool) ini_get('opcache.enable_cli'),
        'sapi'                => PHP_SAPI,
        'timestamp'           => date('c'),
        'azera_framework_ref' => azeraFrameworkRef(),
    ];
}

/**
 * Git ref of the local azera-framework path repository. Azera is not published
 * on Packagist yet, so pinning the ref makes results reproducible.
 */
function azeraFrameworkRef(): ?string
{
    static $ref = false;
    if ($ref !== false) {
        return $ref;
    }

    $ref  = null;
    $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    $dirs = [
        __DIR__ . '/vendor/sailantis/azera-framework',
        dirname(__DIR__) . '/azera-framework',
    ];
    foreach ($dirs as $dir) {
        if (!is_dir($dir . '/.git') && !is_file($dir . '/.git')) {
            continue;
        }
        $out = [];
        $rc  = 0;
        @exec(sprintf('git -C %s rev-parse --short HEAD %s', escapeshellarg($dir), $null), $out, $rc);
        if ($rc === 0 && isset($out[0]) && $out[0] !== '') {
            $ref = trim((string) $out[0]);
            break;
        }
    }

    return $ref;
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
        'handle_ms',
        'cleanup_ms',
        'min_ms',
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
                    $req['handle_ms'] ?? '',
                    $req['cleanup_ms'] ?? '',
                    $req['min_ms'],
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

function writeReport(string $prefix, array $results, array $featureMap, array $adapterFeatures): void
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
        $lines[] = '| Mode | Request | Iter/Run | Runs | Trimmed Mean (ms) | Handle (ms) | Cleanup (ms) | Min (ms) | Mean (ms) | Median (ms) | p95 (ms) | Peak mem |';
        $lines[] = '|---|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|';
        foreach ($app['modes'] as $modeName => $mode) {
            foreach ($mode['requests'] as $req) {
                $lines[] = sprintf(
                    '| %s | %s | %d | %d | %.4f | %s | %s | %.4f | %.4f | %.4f | %.4f | %s |',
                    $modeName,
                    $req['request'],
                    $req['iterations_per_run'],
                    $req['runs'],
                    $req['trimmed_mean_ms'],
                    isset($req['handle_ms']) ? sprintf('%.4f', $req['handle_ms']) : '',
                    isset($req['cleanup_ms']) ? sprintf('%.4f', $req['cleanup_ms']) : '',
                    $req['min_ms'],
                    $req['mean_ms'],
                    $req['median_ms'],
                    $req['p95_ms'],
                    number_format($req['peak_mem'])
                );
            }
        }
        $lines[] = '';
    }

    // --- Per-feature winners table ----------------------------------------
    // For each feature, compare only the frameworks that support it and
    // declare a winner per request (lowest trimmed mean).  A framework
    // without the feature simply doesn't take part.
    $lines[] = '## Winners by Feature';
    $lines[] = '';
    $lines[] = 'For each feature, only frameworks that support it are compared.';
    $lines[] = 'Winner = lowest trimmed mean (ms) for that request.';
    $lines[] = '';

    // Build a lookup: app => mode => request => trimmed_mean_ms
    $byApp = [];
    foreach ($results['apps'] as $app) {
        foreach ($app['modes'] as $modeName => $mode) {
            foreach ($mode['requests'] as $req) {
                $byApp[$app['app']][$modeName][$req['request']] = $req['trimmed_mean_ms'];
            }
        }
    }

    // Determine which modes were actually measured.
    $modes = [];
    foreach ($results['apps'] as $app) {
        foreach (array_keys($app['modes']) as $m) {
            $modes[$m] = true;
        }
    }
    $modes = array_keys($modes);

    // Group requests by feature.
    $features = [];
    foreach ($featureMap as $reqLabel => $feature) {
        $features[$feature][] = $reqLabel;
    }

    foreach ($features as $feature => $reqLabels) {
        $lines[] = "### {$feature}";
        $lines[] = '';
        $lines[] = '| Request | Winner | Trimmed Mean (ms) | Runner-up | Trimmed Mean (ms) | Margin (ms) | Speed-up |';
        $lines[] = '|---|---|---:|---|---:|---:|---:|';

        foreach ($reqLabels as $reqLabel) {
            foreach ($modes as $modeName) {
                // Collect participants that support this feature and have a measurement.
                $participants = [];
                foreach ($results['apps'] as $app) {
                    $appKey = $app['app'];
                    if (!in_array($feature, $adapterFeatures[$appKey] ?? [], true)) {
                        continue;
                    }
                    if (!isset($byApp[$appKey][$modeName][$reqLabel])) {
                        continue;
                    }
                    $participants[$appKey] = $byApp[$appKey][$modeName][$reqLabel];
                }

                if (count($participants) < 2) {
                    // Not enough competitors for a meaningful race.
                    $lines[] = "| {$reqLabel} ({$modeName}) | — | — | — | — | — | — |";
                    continue;
                }

                asort($participants);
                $ranked = array_keys($participants);
                $winner = $ranked[0];
                $runner = $ranked[1] ?? null;
                $margin = $runner !== null
                    ? $participants[$runner] - $participants[$winner]
                    : 0.0;
                // Speed-up: how many times faster the winner is than the
                // runner-up (runner-up trimmed mean / winner trimmed mean).
                $speedup = $runner !== null
                    ? sprintf('%.1fx', $participants[$runner] / $participants[$winner])
                    : '—';

                $lines[] = sprintf(
                    '| %s (%s) | **%s** | %.4f | %s | %.4f | %.4f | %s |',
                    $reqLabel,
                    $modeName,
                    $winner,
                    $participants[$winner],
                    $runner ?? '—',
                    $runner !== null ? $participants[$runner] : 0.0,
                    $margin,
                    $speedup
                );
            }
        }
        $lines[] = '';
    }

    // --- Win count summary -------------------------------------------------
    $lines[] = '## Win Count';
    $lines[] = '';
    $lines[] = 'Number of requests each framework won (lowest trimmed mean), per mode.';
    $lines[] = '';
    $lines[] = '| Framework | ' . implode(' | ', $modes) . ' | Total |';
    $lines[] = '|---|' . str_repeat('---:|', count($modes)) . '---:|';

    $winCounts = [];
    foreach ($results['apps'] as $app) {
        $winCounts[$app['app']] = array_fill_keys($modes, 0);
    }

    foreach ($features as $feature => $reqLabels) {
        foreach ($reqLabels as $reqLabel) {
            foreach ($modes as $modeName) {
                $participants = [];
                foreach ($results['apps'] as $app) {
                    $appKey = $app['app'];
                    if (!in_array($feature, $adapterFeatures[$appKey] ?? [], true)) {
                        continue;
                    }
                    if (!isset($byApp[$appKey][$modeName][$reqLabel])) {
                        continue;
                    }
                    $participants[$appKey] = $byApp[$appKey][$modeName][$reqLabel];
                }
                if (count($participants) < 2) {
                    continue;
                }
                asort($participants);
                $winner = array_key_first($participants);
                $winCounts[$winner][$modeName]++;
            }
        }
    }

    foreach ($winCounts as $appKey => $counts) {
        $total = array_sum($counts);
        $lines[] = '| ' . $appKey . ' | ' . implode(' | ', $counts) . ' | ' . $total . ' |';
    }
    $lines[] = '';

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
        // one untimed warm-up dispatch (paired with its cleanup)
        $adapter->dispatch($method, $uri);
        $adapter->cleanup();
    }

    $runMeans     = [];
    $handleMeans  = [];
    $cleanupMeans = [];
    $allTimes     = [];
    $peakMem      = 0;

    for ($r = 0; $r < $runs; $r++) {
        if ($mode === 'cold') {
            $adapter->bootstrap();
        }

        $times        = [];
        $handleTimes  = [];
        $cleanupTimes = [];
        for ($i = 0; $i < $itersPerRun; $i++) {
            $t0 = hrtime(true);
            $adapter->dispatch($method, $uri);
            $t1 = hrtime(true);
            $adapter->cleanup();
            $t2 = hrtime(true);
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
        'handle_ms'          => trimmedMean($handleMeans),
        'cleanup_ms'         => trimmedMean($cleanupMeans),
    ];
}

// --- Main ------------------------------------------------------------------
// Each app is benchmarked in its own fresh PHP process (run-app.php child):
// full-stack frameworks define function_exists-guarded global helpers with
// colliding names (config(), view(), env(), ...), so two of them can never
// share one process — whichever loads first shadows the other. Separate
// processes also give every framework identical opcache/jit conditions.
// The per-request benchmark loop itself lives in run-app.php.

echo "=== azera-competition benchmark ===\n";
echo "Apps: " . implode(', ', $apps) . "\n";
echo "Iterations/run: {$itersPerRun}, Runs: {$runs}\n";
echo "Modes: " . implode(', ', array_filter(['warm' => $doWarm, 'cold' => $doCold])) . "\n";
echo "Requests: " . implode(', ', array_map(fn($r) => "{$r[0]} {$r[1]}", $requests)) . "\n\n";

$results = [
    'env'  => envInfo(),
    'apps' => [],
];

$modes = array_keys(array_filter([
    'warm' => $doWarm,
    'cold' => $doCold,
]));

// Serialize the request list once for the child processes ("METHOD URI" CSV).
$requestArg = implode(',', array_map(fn($r) => "{$r[0]} {$r[1]}", $requests));

foreach ($apps as $key) {
    $key = trim($key);
    if (!isset($adapterClasses[$key])) {
        echo "Unknown adapter: {$key} (skipping)\n";
        continue;
    }

    echo "\n=== App: {$key}\n";

    $appResult = [
        'app'   => $key,
        'modes' => [],
    ];

    foreach ($modes as $modeName) {
        // Re-seed per app x mode: the feature endpoints (aop/db-events/
        // events) INSERT a row per request, so a single start-of-run seed
        // would leave the last app measuring GETs (COUNT(*) etc.) against
        // tens of thousands of accumulated rows while the first app saw
        // ~1k. Fresh 1k-row table for every measured block keeps the
        // data-layer cost identical across apps and modes.
        $seedArgs = '';
        if ($doSeed) {
            $seedArgs = ' --seed --rows=' . (int) $seedRows;
        }

        // Spawn the per-app child process. It writes this app x mode's
        // result JSON to a temp file; run.php merges it into $results.
        $tmpJson = tempnam(sys_get_temp_dir(), 'bench-') . '.json';
        $cmd     = sprintf(
            '%s %s --app=%s --mode=%s --iterations-per-run=%d --runs=%d --requests=%s --out-json=%s%s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/run-app.php'),
            escapeshellarg($key),
            escapeshellarg($modeName),
            $itersPerRun,
            $runs,
            escapeshellarg($requestArg),
            escapeshellarg($tmpJson),
            $seedArgs,
        );

        passthru($cmd, $childExit);
        if ($childExit !== 0 || !is_file($tmpJson)) {
            echo "Child benchmark for {$key} ({$modeName}) failed (exit {$childExit}), aborting.\n";
            exit(1);
        }

        $modeData = json_decode((string) file_get_contents($tmpJson), true);
        unlink($tmpJson);
        if (!is_array($modeData) || !isset($modeData['modes'][$modeName])) {
            echo "Child benchmark for {$key} ({$modeName}) returned no results, aborting.\n";
            exit(1);
        }
        $appResult['modes'][$modeName] = $modeData['modes'][$modeName]; // The child's measureBoot() result (cold_ms/warm_ms) is identical

        if (isset($modeData['boot']) && !isset($appResult['boot'])) {
            $appResult['boot'] = $modeData['boot'];
        }
    }

    $results['apps'][] = $appResult;
}

if ($outPrefix !== null) {
    writeResults($outPrefix, $results);
    writeReport($outPrefix, $results, $featureMap, $adapterFeatures);
    echo "\nWrote: {$outPrefix}.json, {$outPrefix}.csv, {$outPrefix}.md\n";
}

// Regenerate the report from the dataset we just produced. The report is a
// pure function of the results JSON, so this never depends on a separate run.
if (isset($opts['report']) && $outPrefix !== null) {
    echo "\n=== Regenerating report\n";
    passthru(sprintf(
        '%s %s --dataset=%s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/scripts/report.php'),
        escapeshellarg($outPrefix . '.json')
    ), $reportExit);
    if ($reportExit !== 0) {
        echo "Report generation failed (exit {$reportExit}).\n";
    }
}

echo "\nDone.\n";