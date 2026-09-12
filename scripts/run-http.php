<?php

declare(strict_types=1);

/**
 * Real-deployment benchmark orchestrator — RUNS ON THE BENCHMARK VM.
 *
 * Benchmarks every app under real servers, one block per (server, app):
 *
 *   server rr  → RoadRunner resident worker (deploy/rr/worker.php,
 *                BENCH_APP=<app>, config stamped from deploy/rr/) → warm model
 *   server fpm → nginx vhost + php-fpm pool (pm.max_requests=1, i.e. the
 *                worker is recycled after every request) → cold model
 *
 * Per block: seed SQLite → stamp configs → start server → readiness poll →
 * run scripts/http-bench.php → stop server. Floor pseudo-apps (static file
 * through nginx, hello-world through FPM) are measured once per server so
 * the constant webserver overhead lands in the dataset as context.
 *
 * Output: results/real-deployments.json — same JSON shape as run.php's
 * dataset (env + apps[].modes.<mode>.requests[]), mode names already
 * "roadrunner"/"php-fpm", so report.php consumes it unchanged.
 *
 * Usage:
 *   php scripts/run-http.php --servers=rr,fpm --out=results/real-deployments
 *   php scripts/run-http.php --apps=azera --servers=rr --quick --out=temp/smoke
 */

$opts = getopt('', [
    'apps::',
    'servers::',
    'iterations-per-run::',
    'runs::',
    'rows::',
    'out::',
    'quick',
    'rr-binary::',
    'php-fpm::',
    'bench-user::',
]);

$apps        = isset($opts['apps']) ? explode(',', (string) $opts['apps']) : ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
$itersPerRun = (int) ($opts['iterations-per-run'] ?? 1000);
$runs        = (int) ($opts['runs'] ?? 10);
$rows        = (int) ($opts['rows'] ?? 1000);
$outPrefix   = $opts['out'] ?? 'results/real-deployments';
$rrBinary    = $opts['rr-binary'] ?? (__DIR__ . '/../vendor/bin/rr');
$phpFpmBin   = $opts['php-fpm'] ?? 'php-fpm8.3';
$benchUser   = $opts['bench-user'] ?? getenv('BENCH_USER') ?: get_current_user();
$useRr       = true;
$useFpm      = true;

$serversArg = array_map('trim', explode(',', (string) ($opts['servers'] ?? 'rr,fpm')));
$useRr      = in_array('rr', $serversArg, true);
$useFpm     = in_array('fpm', $serversArg, true);

$quick = isset($opts['quick']);
if ($quick) {
    $itersPerRun = min($itersPerRun, 100);
    $runs        = min($runs, 3);
}

require_once __DIR__ . '/deploy-lib.php';

$root = dirname(__DIR__);

echo "=== real-deployment benchmark (run-http.php) ===\n";
echo "Root: {$root}\n";
echo "Apps: " . implode(', ', $apps) . "\n";
echo "Servers: " . implode(', ', array_keys(array_filter(['rr' => $useRr, 'fpm' => $useFpm]))) . "\n";
echo "Iterations/run: {$itersPerRun}, Runs: {$runs}\n";
echo "Bench user (FPM pool owner): {$benchUser}\n\n";

// Mode name per server — must match the report views' mode filters.
$modeOf = static fn(string $server): string => $server === 'rr' ? 'roadrunner' : 'php-fpm';

// --- Results skeleton ---------------------------------------------------------

$results = [
    'env' => [
        'php_version' => PHP_VERSION,
        'os'          => PHP_OS . ' ' . php_uname('r'),
        'sapi'        => PHP_SAPI,
        'timestamp'   => date('c'),
        'deployment'  => 'real',
        'servers'     => [
            'roadrunner' => trim((string) shellProcessOutput("{$rrBinary} --version")),
            'nginx'      => trim((string) shellProcessOutput('nginx -v 2>&1')),
            'php_fpm'    => trim((string) shellProcessOutput("{$phpFpmBin} -v")),
        ],
    ],
    'apps' => [],
];

// --- Server + seed setup -------------------------------------------------------

$rrPort    = 8983;
$fpmPort   = 8883;
$deployDir = "{$root}/temp/deploy";
$ports     = [];

// FPM pools + nginx vhosts are stamped ONCE per app up front and kept
// running; per-block the orchestrator only starts/stops RoadRunner and, for
// FPM, relies on pm.max_requests=1 recycling (a running pool IS the cold
// model — no per-block start/stop needed).
stampAllDeployConfigs($root, $deployDir, $apps, $fpmPort, $rrPort, $ports, $benchUser);

foreach ($apps as $appKey) {
    $appKey = trim($appKey);
    echo "\n=== App: {$appKey}\n";

    $appResult = ['app' => $appKey, 'modes' => []];

    foreach (array_keys(array_filter(['rr' => $useRr, 'fpm' => $useFpm])) as $server) {
        $port    = $ports[$server][$appKey];
        $baseUrl = "http://127.0.0.1:{$port}";

        // Fresh DB per block — same rationale as run.php's per-app re-seed.
        seedDatabase($root, $rows);

        $proc = null;
        if ($server === 'rr') {
            $proc = startRoadRunner($root, $deployDir, $appKey, $rrBinary, $port);
        } else {
            $proc = ensureFpmRunning($root, $phpFpmBin, $deployDir);
        }

        try {
            waitForServer($baseUrl, $server, $appKey);

            // http-bench.php aborts (exit 1) on broken bodies — its output is
            // trusted only when the child exits 0.
            $tmpJson = "{$root}/temp/bench-{$server}-{$appKey}.json";
            runHttpBench($root, $server, $appKey, $baseUrl, $itersPerRun, $runs, $tmpJson);

            $modeData = json_decode((string) file_get_contents($tmpJson), true);
            if (!is_array($modeData) || !isset($modeData['modes'][$modeOf($server)])) {
                fwrite(STDERR, "[run-http] No results from http-bench for {$server}/{$appKey}, aborting.\n");
                exit(1);
            }
            $appResult['modes'] += $modeData['modes'];
            unlink($tmpJson);
        } finally {
            if ($server === 'rr') {
                stopRoadRunner($proc);
            }
        }
    }

    $results['apps'][] = $appResult;
}

echo "\n=== Floors (webserver overhead, measured once per server) ===\n";
$results['floors'] = measureFloors($root, $deployDir, $useRr, $useFpm, $rrPort, $fpmPort, $itersPerRun, $runs, $rrBinary, $phpFpmBin, $benchUser);

writeRealResults($outPrefix, $results);

echo "\nWrote: {$outPrefix}.json\n";
exit(0);