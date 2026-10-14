<?php

declare(strict_types=1);

/**
 * volucer worker for ALL benchmark apps.
 *
 * The third deployment model, beside `deploy/rr/worker.php` (RoadRunner) and the
 * nginx + php-fpm vhosts. It exists to answer one question the other two cannot:
 * **how much of the warm-model advantage is the framework boot?** FPM re-runs the
 * entry script per request, so `Bootstrap::boot()` sits inside every row; a resident
 * worker pays it once. Measuring that difference requires the same application
 * behind both models, which is why this file drives the same `WebAppAdapter` the
 * in-process harness and the RoadRunner worker use.
 *
 * It is deliberately the smallest of the three workers. RoadRunner's worker has to
 * build a PSR-7 request from the wire and marshal telemetry by hand; volucer's SDK
 * owns the framing, and `Request` here already carries the method and URI, so the
 * loop is the three lines it looks like.
 *
 * Contract notes, identical to the RoadRunner worker's because they are properties
 * of the application and not of the server:
 *  - Adapters return ONLY the response body (never echo/header/exit).
 *  - A body starting with "500 " marks a handler error (the harness's abort guard
 *    convention) — surfaced as HTTP 500.
 *  - `cleanup()` runs after every request: a resident worker must release its
 *    per-request scope or the next request inherits it.
 *
 * Usage (wired by `scripts/run-http.php --servers=volucer`, or by hand):
 *
 *     BENCH_APP=azera VOLUCER_SDK=/path/to/volucer-php-sdk \
 *         php deploy/volucer/worker.php
 */

// Shared with the RoadRunner worker rather than copied: it owns the adapter map and
// the framework-helper preload order, both of which are subtle (see its comments on
// the Laravel `env()` and CodeIgniter `Common.php` races). A second copy would drift.
require __DIR__ . '/../rr/bootstrap-worker.php';

// BENCH_APP is read with getenv() BEFORE composer loads, for the same reason the
// RoadRunner worker does it: a helper defined here would shadow Laravel's own env().
$benchApp  = (string) (getenv('BENCH_APP') ?: '');
$benchRoot = dirname(__DIR__, 2);

$sdk = (string) (getenv('VOLUCER_SDK') ?: $benchRoot . '/vendor/sailantis/volucer-php-sdk');
if (!is_file($sdk . '/autoload.php')) {
    fwrite(STDERR, "[volucer] volucer-php-sdk not found at {$sdk} — set VOLUCER_SDK\n");
    exit(1);
}
require $sdk . '/autoload.php';

// Framework helpers must win their function_exists race BEFORE composer's `files`
// autoload pulls Laravel's in (see preloadFrameworkHelpers()).
preloadFrameworkHelpers($benchApp, $benchRoot);

require $benchRoot . '/vendor/autoload.php';
require $benchRoot . '/WebAppAdapter.php';
require $benchRoot . '/adapters/BenchmarkAutoloader.php';
require $benchRoot . '/boot-probe.php';

$adapter = createAdapter($benchApp);

// The framework boots ONCE here, before the request loop, and that one boot is the
// whole point of this deployment model: it is what the FPM arm pays per request.
$adapter->bootstrap();
boot_probe_record('volucer', $benchApp, true);

$worker = Volucer\Worker::create();

// Response bodies are returned as text/html with the same status convention the
// RoadRunner worker uses, so a row measured here and a row measured there describe
// the same application behaviour and differ only in the server.
$worker->run(static function (Volucer\Request $request, Volucer\Worker $worker) use ($adapter): Volucer\Response {
    // A URI the application can route on: path plus query, exactly as the adapter
    // expects from run.php and from RoadRunner.
    $uri = (string) parse_url($request->uri, PHP_URL_PATH);
    $query = (string) parse_url($request->uri, PHP_URL_QUERY);
    if ($query !== '') {
        $uri .= '?' . $query;
    }

    $body = $adapter->dispatch($request->method, $uri);
    // Per-request teardown, the resident-worker contract: without it the
    // request-scoped heap and any service resolved from AppContext survive into
    // the next request.
    $adapter->cleanup();

    return new Volucer\Response(
        str_starts_with($body, '500 ') ? 500 : 200,
        [['Content-Type', 'text/html; charset=utf-8']],
        $body,
    );
});
