<?php

declare(strict_types=1);

/**
 * Attribute the per-request cost of `/features/aop-direct` — ON THE BENCH VM.
 *
 * WHY THIS EXISTS. The php-fpm row for `GET /features/aop-direct` is ~1.14 ms
 * slower than Phalcon's for the same upsert inside the same transaction, and
 * three explanations have been written into BenchmarkConfig.php and then
 * retracted:
 *
 *   1. "per-request cold boot"        — refuted by arithmetic: the row is
 *      1000 iterations x 10 runs with fpm_max_requests = 0, so a run pays for
 *      at most ONE cold request; explaining +1.8 ms/request needs ~1800 ms of
 *      one-off work against a measured boot_probe of 0.27 ms.
 *   2. "the DB-event listener graph"  — measured at 0.0248 ms/request with warm
 *      classes, i.e. 70x too small.
 *   3. "the AOP proxy rebuild"        — real defect, but on Linux it is 0.28 ms
 *      against 0.14 ms, and with classes loaded the whole proxy path is <0.03 ms.
 *
 * Every one of those was inferred from microbenchmarks on a Windows dev box
 * where a single SQLite write through a fresh connection measures 5-12 ms and a
 * bare `new PDO` touch costs ~1.2 ms. That floor is 4-5x the effect being
 * explained, so it cannot attribute anything. This script is the measurement that
 * can: it runs on the VM, exercises the REAL adapter, and reports the per-layer
 * split so the answer comes from the environment the numbers are published from.
 *
 * WHAT IT REPORTS (all medians, fresh container per sample — the php-fpm shape):
 *
 *   rewire        Bootstrap::forget() + boot()   container rebuilt, classes warm
 *   connect       first DB touch on the new container (PRAGMAs included)
 *   write         begin(true) + upsert + commit, connection already open
 *   event         ItemCreated dispatch
 *   controller    the whole /features/aop-direct dispatch
 *   floor         /echo, the same container cost with no DB at all
 *
 * `connect` is the number to watch. If it carries the gap, the cost is connection
 * setup (PRAGMA round-trips, WAL recovery) rather than the write, the event or the
 * proxy — and the fix belongs in the DB factory, not in the AOP layer.
 *
 * USAGE (on the bench VM, from the repository root):
 *   php scripts/aop-direct-attribution.php
 *   php scripts/aop-direct-attribution.php --samples=200 --app=/path/to/app
 *
 * Exit code is 0 when the report was produced. It does not assert a threshold:
 * the point is to capture the split for a human to read, and a hard-coded budget
 * here would be one more number to go stale.
 */

$opts    = getopt('', ['samples::', 'app::', 'json::', 'http-port::']);
$samples = max(20, (int) ($opts['samples'] ?? 100));
$root    = $opts['app'] ?? dirname(__DIR__);
$jsonOut = $opts['json'] ?? null;

require_once $root . '/vendor/autoload.php';
require_once $root . '/WebAppAdapter.php';
require_once $root . '/adapters/BenchmarkAutoloader.php';
require_once $root . '/adapters/AzeraAdapter.php';

BenchmarkAutoloader::map('App\\Azera\\', $root . '/apps/azera');

$dbPath = $root . '/data/bench.sqlite';

if (!is_file($dbPath)) {
    fwrite(STDERR, "  database not found: {$dbPath}\n  run: php seed.php\n");
    exit(1);
}

// The in-process layers below are necessary but NOT sufficient: measured on the
// bench VM (2026-11-23) the entire Azera write chain costs 0.181 ms in-process
// against 0.027 ms for a no-DB route — a delta of 0.15 ms — while the same pair
// over nginx+FPM differs by 1.76 ms. Over 90% of the effect lives in the HTTP
// path, so this script reports both and does not pretend the in-process split is
// the answer.
$httpPort = (int) ($opts['http-port'] ?? 0);
$httpUris = [];

$boot = 'App\Azera\Bootstrap';

/**
 * Median of $fn over $samples runs, in ms.
 *
 * @return array{p50: float, p95: float, min: float}
 */
$measure = static function (callable $fn) use ($samples): array {
    $s = [];

    for ($i = 0; $i < $samples; $i++) {
        $t0 = hrtime(true);
        $fn();
        $s[] = (hrtime(true) - $t0) / 1e6;
    }

    sort($s);

    return [
        'p50' => $s[intdiv(count($s), 2)],
        'p95' => $s[min(count($s) - 1, (int) (count($s) * 0.95))],
        'min' => $s[0],
    ];
};

// Warm every class the endpoints touch, so nothing below is class-loading. This is
// the opcache-warm worker FPM actually is after the first request; measuring without
// it is what produced the bogus "listener graph costs 1.78 ms" result.
foreach (['/echo', '/features/aop-direct', '/features/config', '/features/db-events'] as $uri) {
    $boot::forget();
    $warm = new AzeraAdapter();
    $warm->bootstrap();
    $warm->dispatch('GET', $uri);
    $warm->cleanup();
}

$results = [];

$results['floor'] = $measure(static function () use ($boot, $dbPath): void {
    $boot::forget();
    $ctx = $boot::boot($dbPath);
    $ctx->get(\Azera\Core\Dispatcher::class);
});

$results['rewire'] = $measure(static function () use ($boot, $dbPath): void {
    $boot::forget();
    $boot::boot($dbPath);
});

$results['connect'] = $measure(static function () use ($boot, $dbPath): void {
    $boot::forget();
    $ctx = $boot::boot($dbPath);
    $ctx->dbManager()->getOrDefault('default');
});

$results['write'] = $measure(static function () use ($boot, $dbPath): void {
    $boot::forget();
    $ctx = $boot::boot($dbPath);
    $db = $ctx->dbManager()->getOrDefault('default');

    $db->begin(true);
    $db->query(
        'INSERT INTO items (id, title, created_at) VALUES (?, ?, ?)'
            . ' ON CONFLICT(id) DO UPDATE SET title = excluded.title, created_at = excluded.created_at',
        [888800, 'attribution ' . mt_rand(1, 999999), date('Y-m-d H:i:s')],
    );
    $db->commit();
});

$results['event'] = $measure(static function () use ($boot, $dbPath): void {
    $boot::forget();
    $ctx = $boot::boot($dbPath);
    $ctx->dbManager()->getOrDefault('default');
    $ctx->events()->dispatch(new \App\Azera\Events\ItemCreated(888800, 'attribution'));
});

$results['controller'] = $measure(static function () use ($boot): void {
    $boot::forget();
    $o = new AzeraAdapter();
    $o->bootstrap();
    $o->dispatch('GET', '/features/aop-direct');
    $o->cleanup();
});

// The same container cost with no DB work, so `connect` and `write` can be read as
// deltas against a baseline rather than absolutes.
$results['echo'] = $measure(static function () use ($boot): void {
    $boot::forget();
    $o = new AzeraAdapter();
    $o->bootstrap();
    $o->dispatch('GET', '/echo');
    $o->cleanup();
});

printf("aop-direct attribution (php-fpm shape: fresh container, warm classes)\n");
printf("samples=%d  php=%s  os=%s\n\n", $samples, PHP_VERSION, PHP_OS_FAMILY);
printf("%-12s %9s %9s %9s\n", 'layer', 'p50', 'p95', 'min');
printf("%s\n", str_repeat('-', 42));

foreach ($results as $name => $r) {
    printf("%-12s %9.4f %9.4f %9.4f\n", $name . ' ms', $r['p50'], $r['p95'], $r['min']);
}

printf("\n");
printf("connect above echo      = %+8.4f ms  (connection setup, PRAGMAs included)\n", $results['connect']['p50'] - $results['echo']['p50']);
printf("write above connect     = %+8.4f ms  (the upsert itself)\n", $results['write']['p50'] - $results['connect']['p50']);
printf("event above write       = %+8.4f ms  (ItemCreated dispatch)\n", $results['event']['p50'] - $results['write']['p50']);
printf("controller above write  = %+8.4f ms  (routing, render, proxy, remaining)\n", $results['controller']['p50'] - $results['write']['p50']);

// OVER HTTP — where the published numbers come from and where the delta lives.
// Skipped silently when no port is given, because guessing a port would measure
// something other than the deployment under test.
$http = [];

if ($httpPort > 0) {
    $get = static function (string $uri, int $port, int $n): array {
        $ch = curl_init();

        for ($i = 0; $i < 30; $i++) {
            curl_setopt_array($ch, [
                CURLOPT_URL            => "http://127.0.0.1:{$port}{$uri}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            curl_exec($ch);
        }

        $s = [];
        $body = '';

        for ($i = 0; $i < $n; $i++) {
            curl_setopt_array($ch, [
                CURLOPT_URL            => "http://127.0.0.1:{$port}{$uri}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $t0   = hrtime(true);
            $body = curl_exec($ch);
            $s[]  = (hrtime(true) - $t0) / 1e6;
        }

        curl_close($ch);
        sort($s);

        // Trimmed mean, matching the harness: drop 10% each side.
        $trim = array_slice($s, (int) ($n * 0.1), (int) ($n * 0.8));

        return ['trim' => array_sum($trim) / count($trim), 'body' => strlen((string) $body)];
    };

    $http['/echo']            = $get('/echo', $httpPort, $samples);
    $http['/features/config'] = $get('/features/config', $httpPort, $samples);
    $http['/items/1']         = $get('/items/1', $httpPort, $samples);
    $http['/features/aop-direct'] = $get('/features/aop-direct', $httpPort, $samples);

    printf("\nOVER HTTP (nginx + php-fpm, port %d, trimmed mean of %d):\n", $httpPort, $samples);
    printf("%-26s %9s %8s\n", 'uri', 'trimmed', 'body');

    foreach ($http as $uri => $r) {
        printf("%-26s %9.3f %8d\n", $uri, $r['trim'], $r['body']);
    }

    $writeDelta = $http['/features/aop-direct']['trim'] - $http['/echo']['trim'];
    $readDelta  = $http['/items/1']['trim'] - $http['/echo']['trim'];
    $inprocDelta = $results['controller']['p50'] - $results['echo']['p50'];

    printf("\nwrite - noDB  over HTTP : %+8.3f ms\n", $writeDelta);
    printf("read  - noDB  over HTTP : %+8.3f ms\n", $readDelta);
    printf("write - noDB  in-process: %+8.3f ms\n", $inprocDelta);
    printf("\nHTTP accounts for %+.3f ms of the write route that in-process does not.\n",
        $writeDelta - $inprocDelta);
}

// Compare against the published rows so the residual being chased is explicit.
// Deliberately read from the dataset rather than measured here: a phalcon
// measurement on a dev box is no more trustworthy than a local azera one, and
// the whole point of this script is to attribute a PUBLISHED difference.
$publishedAzera    = null;
$publishedPhalcon  = null;
$datasetPath       = $root . '/results/real-advice-pair.json';

if (is_file($datasetPath)) {
    $dataset = json_decode((string) file_get_contents($datasetPath), true);

    foreach (($dataset['apps'] ?? []) as $app) {
        $name = $app['app'] ?? '';
        if (!in_array($name, ['azera', 'phalcon'], true)) {
            continue;
        }

        foreach (($app['modes']['php-fpm']['requests'] ?? []) as $row) {
            if (($row['request'] ?? '') !== 'GET /features/aop-direct') {
                continue;
            }

            if ($name === 'azera') {
                $publishedAzera = (float) $row['mean_ms'];
            } else {
                $publishedPhalcon = (float) $row['mean_ms'];
            }
        }
    }
}

if ($publishedAzera !== null && $publishedPhalcon !== null) {
    printf("\npublished php-fpm rows for GET /features/aop-direct:\n");
    printf("  azera   %.4f ms\n", $publishedAzera);
    printf("  phalcon %.4f ms\n", $publishedPhalcon);
    printf("  delta   %+.4f ms  <- the number this script exists to attribute\n", $publishedAzera - $publishedPhalcon);
    printf("\nthis run measured the azera controller at %.4f ms (local environment:\n", $results['controller']['p50']);
    printf("absolute values are not comparable to the dataset above; only the LAYER\n");
    printf("split is meaningful, and only when taken on the bench VM).\n");
} else {
    printf("\n(results/real-advice-pair.json not found or incomplete; skipping the\n");
    printf(" published-row comparison)\n");
}

if ($jsonOut !== null) {
    file_put_contents($jsonOut, json_encode([
        'samples'   => $samples,
        'php'       => PHP_VERSION,
        'os'        => PHP_OS_FAMILY,
        'layers'    => $results,
        'http'      => $http,
        'published' => [
            'dataset' => $datasetPath,
            'azera'   => $publishedAzera,
            'phalcon' => $publishedPhalcon,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
}

exit(0);
