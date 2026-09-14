<?php

declare(strict_types=1);

/**
 * HTTP latency benchmark client (curl_multi over loopback).
 *
 * Measures REAL deployed endpoints (RoadRunner resident worker = mode
 * "roadrunner"; nginx + php-fpm with pm.max_requests=1 = mode "php-fpm")
 * with the same stats machinery and JSON field names as run.php's
 * benchRequest(), so the real-deployment dataset drops straight into the
 * existing report generator.
 *
 * Called by scripts/run-http.php — one invocation per (server, app) block:
 *
 *   php scripts/http-bench.php \
 *       --server=rr --app=azera --base-url=http://127.0.0.1:8988 \
 *       --iterations-per-run=1000 --runs=10 \
 *       --requests="GET /,GET /items,POST /items" \
 *       --out-json=temp/bench-rr-azera.json
 *
 * Measurement model (matches run.php):
 *  - hrtime() around each sequential request; cleanup cost is part of the
 *    request (dispatch+cleanup are summed in-process too).
 *  - Per-run means -> headline = trimmed_mean_ms (10% trimmed), plus
 *    min/mean/median/p95 over all samples.
 *  - Untimed warm-up request per label before each timed run (a real server
 *    lazily compiles routes on first hit).
 *  - Abort guard identical to benchRequest(): a body of "Not Found" or one
 *    starting with "500 " aborts with exit 1 — a benchmark against a broken
 *    deployment must never produce numbers.
 */

$opts = getopt('', [
    'server::',
    'app::',
    'base-url::',
    'iterations-per-run::',
    'runs::',
    'requests::',
    'out-json::',
]);

$server      = $opts['server'] ?? 'rr';
$appKey      = $opts['app'] ?? 'azera';
$baseUrl     = rtrim((string) ($opts['base-url'] ?? ''), '/');
$itersPerRun = (int) ($opts['iterations-per-run'] ?? 1000);
$runs        = (int) ($opts['runs'] ?? 30);
$outJsonPath = $opts['out-json'] ?? null;

// --- Shared stats helpers (kept in sync with run.php / run-app.php) --------

require_once __DIR__ . '/bench-lib.php';

$requests = parseRequestsArg($opts['requests'] ?? null);

if ($baseUrl === '') {
    fwrite(STDERR, "[http-bench] --base-url is required\n");
    exit(1);
}

echo "=== http-bench: {$server} / {$appKey} @ {$baseUrl} ({$itersPerRun}x{$runs})\n";

$modeName = ($server === 'rr') ? 'roadrunner' : 'php-fpm';

$measured = [];
$peakMem  = 0;

foreach ($requests as [$method, $uri]) {
    $reqLabel = "{$method} {$uri}";
    echo "  [{$modeName}] {$reqLabel} — {$itersPerRun}x{$runs}\n";

    // Warm-up: real deployments compile routes/templates on first hit —
    // untimed, and more of it than the in-process harness needs (1 request
    // there; 5 here because the server stack also warms buffers/sockets).
    for ($i = 0; $i < 5; $i++) {
        $body = httpRequest($baseUrl, $method, $uri);
        abortIfBroken($server, $appKey, $reqLabel, $body);
    }

    $runMeans   = [];
    $allTimes   = [];
    $connectAvg = 0.0;

    for ($r = 0; $r < $runs; $r++) {
        $times    = [];
        $connects = [];
        $connect  = 0.0;

        for ($i = 0; $i < $itersPerRun; $i++) {
            $t = httpTimedRequest($baseUrl, $method, $uri, $connect);
            $times[] = $t;
            $connects[] = $connect;
            abortIfBroken($server, $appKey, $reqLabel, $t['body'], $t['status']);
            $times[count($times) - 1] = $t['total_ms'];
        }

        $s = stats($times);
        $runMeans[] = $s['mean'];
        // $times entries were replaced with their total_ms floats above, so
        // merge them directly (array_column() here would yield [] → stats()
        // divide-by-zero on $allTimes).
        $allTimes   = array_merge($allTimes, $times);
        $connectAvg = max($connectAvg, stats($connects)['mean']);
        $peakMem    = max($peakMem, memory_get_peak_usage(true));

        echo sprintf(
            "    run %2d/%d — mean %.4f ms, median %.4f ms\n",
            $r + 1,
            $runs,
            $s['mean'],
            $s['median']
        );
    }

    $sAll = stats($allTimes);

    $measured[$reqLabel] = [
        'request'            => $reqLabel,
        'iterations_per_run' => $itersPerRun,
        'runs'               => $runs,
        'trimmed_mean_ms'    => trimmedMean($runMeans),
        'min_ms'             => $sAll['min'],
        'mean_ms'            => $sAll['mean'],
        'median_ms'          => $sAll['median'],
        'p95_ms'             => $sAll['p95'],
        'peak_mem'           => $peakMem,
        'connect_ms'         => $connectAvg,
    ];

    // Reset peak mem tracking per label is not possible in PHP; the value is
    // the high-water mark of this process — acceptable because the real
    // deployment's memory is the SERVER's business, not the client's. The
    // report must not present it as framework memory; run-http.php therefore
    // records peak_mem as 0 for real deployments.
    $measured[$reqLabel]['peak_mem'] = 0;
}

writeHttpResults($outJsonPath, $server, $appKey, $modeName, $itersPerRun, $runs, $measured);

echo "  wrote {$outJsonPath}\n";
exit(0);