<?php

declare(strict_types=1);

/**
 * The benchmark application behind a volucer backend, as a handler.
 *
 * Both volucer entries load this file and serve the closure it returns:
 *
 *   attach.php   `attach` backend — this process is the supervisor; it LISTENS on
 *                the relay and forks a child per accepted connection, and volucer
 *                dials it (`Volucer\Supervisor::serve($relay, $handler)`). This is
 *                the resident arm the dataset measures.
 *   worker.php   `worker` backend — volucer starts this process and hands it ONE
 *                connection (`Volucer\Worker::create()->run($handler)`). Not in the
 *                dataset (see worker.php), but still runnable by hand.
 *
 * They are the same handler on purpose: the only difference between them is who owns
 * the process lifetime, so keeping the request handling in one file is what stops the
 * pair from drifting into two subtly different benchmarks.
 *
 * WHERE THE BOOT HAPPENS, AND WHY IT IS HERE. This file does the framework boot
 * before returning, which means:
 *
 *   attach — booted once in the MASTER, before the first fork. Every child inherits
 *            the initialized class table and the built container, so no child pays
 *            the boot either. This is the position the SDK documentation calls out:
 *            "an application that builds a container, compiles templates or warms a
 *            router does it once, here, before the first fork."
 *   worker — booted once in the process volucer started, before the first request.
 *
 * So both are resident-worker arms and neither pays a per-request boot. The arm that
 * DOES pay it is `spawn_cgi`, which runs public/index-<app>.php per request through
 * php-cgi — the same shape as nginx + php-fpm.
 *
 * WHAT MUST NOT HAPPEN BEFORE A FORK. An open network connection, because every
 * child would inherit the socket and they would take turns reading from it. SQLite
 * is the exception this benchmark relies on and it is safe to inherit: it is a file
 * handle to a local database, and the arms hold `children = 1` / `workers = 1` so
 * exactly one process is ever reading or writing it — the same single-writer rule
 * that makes the FPM pool `pm.max_children = 1` and RoadRunner `num_workers = 1`.
 */

// Shared with the RoadRunner worker rather than copied: it owns the adapter map and
// the framework-helper preload order, both of which are subtle (see its comments on
// the Laravel `env()` and CodeIgniter `Common.php` races). A second copy would drift.
require __DIR__ . '/../rr/bootstrap-worker.php';

// BENCH_APP is read with getenv() BEFORE composer loads, for the same reason the
// RoadRunner worker does it: a helper defined here would shadow Laravel's own env().
$benchApp  = (string) (getenv('BENCH_APP') ?: '');
$benchRoot = dirname(__DIR__, 2);

// The SDK's COMPOSER PACKAGE NAME is `sailantis/volucer-worker`, even though the
// repository is called volucer-php-sdk: composer installs it under the package name,
// so `vendor/sailantis/volucer-php-sdk` never exists. That mismatch made the worker
// exit 1 with "not found" on a tree where composer had installed the SDK correctly.
$sdk = (string) (getenv('VOLUCER_SDK') ?: $benchRoot . '/vendor/sailantis/volucer-worker');
if (!is_file($sdk . '/autoload.php')) {
    fwrite(STDERR, "[volucer] volucer-worker SDK not found at {$sdk} — set VOLUCER_SDK\n");
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

// The clock starts immediately before bootstrap() rather than at the top of the file:
// everything above is the generic worker bootstrap (composer, adapter selection,
// helper preload) that every arm pays identically, and charging it to the framework
// would make this band measure the harness.
boot_probe_start();
$adapter->bootstrap();
boot_probe_record('volucer', $benchApp, true);

// The endpoint-independent footprint every probe response carries. Read here, once,
// because it must be the framework-built / no-request-served heap — the same number
// deploy/rr/worker.php captures at its own boot. Reading it per request would report
// whatever the previous request left behind.
//
// For the attach arm this is read in the MASTER, so every child inherits the same
// figure, which is the truth: they inherited the same heap.
$bootHeap = memory_get_usage(false);

return static function (Volucer\Request $request) use ($adapter, $bootHeap): Volucer\Response {
    // A URI the application can route on: path plus query, exactly as the adapter
    // expects from run.php, from RoadRunner and from the FPM entry scripts.
    $uri = (string) parse_url($request->uri, PHP_URL_PATH);
    $query = (string) parse_url($request->uri, PHP_URL_QUERY);
    if ($query !== '') {
        $uri .= '?' . $query;
    }

    // --- Opt-in memory probe (X-Mem-Probe: 1) --------------------------------
    //
    // Same header, same five values, same meanings as deploy/rr/worker.php — the
    // report compares this band with the RoadRunner band through ONE code path, so
    // the field sets have to stay identical.
    //
    // Gated on the header because no timed request may carry it: the probe costs a
    // /proc read plus a header write, which is a systematic bias on the fast rows
    // rather than noise. The harness sends it on extra requests per endpoint AFTER
    // the timed loop.
    //
    // The peak is reset BEFORE dispatch, so it covers this request only, and it is
    // anchored at the boot heap — the identical construction the FPM shutdown hook
    // uses, which is what makes peak an absolute number comparable across arms.
    $memProbe = $request->header('X-Mem-Probe') === '1';
    if ($memProbe && function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $body = $adapter->dispatch($request->method, $uri);
    // Per-request teardown, the resident-worker contract: without it the
    // request-scoped heap and any service resolved from AppContext survive into
    // the next request.
    $adapter->cleanup();

    $response = new Volucer\Response(
        str_starts_with($body, '500 ') ? 500 : 200,
        [['Content-Type', 'text/html; charset=utf-8']],
        $body,
    );

    if ($memProbe) {
        [$rss, $hwm] = mem_probe_proc_mem();
        // Heap is read AFTER cleanup() so it reports what the request RETAINED,
        // not its transient working set; peak is a high-water mark, so cleanup
        // cannot lower it. Reading both at one instant keeps them consistent with
        // the FPM probe, which also reports the pair from its shutdown hook.
        $peak = memory_get_peak_usage(false);
        if ($peak < $bootHeap) {
            $peak = $bootHeap;
        }

        $response = $response
            ->withHeader('X-Bench-Boot', (string) $bootHeap)
            ->withHeader('X-Bench-Peak', (string) $peak)
            ->withHeader('X-Bench-Heap', (string) memory_get_usage(false))
            ->withHeader('X-Bench-Rss', (string) $rss)
            ->withHeader('X-Bench-Hwm', (string) $hwm);
    }

    return $response;
};
