<?php

declare(strict_types=1);

/**
 * Generic RoadRunner worker for ALL benchmark apps.
 *
 * RoadRunner starts this script as the http pool worker and keeps the PHP
 * process resident (warm-start model: the framework boots once, then serves
 * every request — exactly what run.php's warm mode simulates in-process).
 *
 * The app under test is selected with the BENCH_APP environment variable
 * (wired in the generated .rr-<app>.yaml): the worker instantiates the same
 * WebAppAdapter the in-process harness uses, calls bootstrap() once, and
 * then loops dispatch()/cleanup() per request.
 *
 * Contract notes:
 *  - Adapters return ONLY the response body (never echo/header/exit), so the
 *    body maps 1:1 onto a PSR-7 response.
 *  - A body starting with "500 " marks a handler error (the harness's abort
 *    guard convention) — surfaced to RoadRunner as HTTP 500.
 *  - POST benchmark requests generate their own payloads inside the
 *    controllers, so an empty body on POST /items is expected and fine.
 *
 * API note: mirrors azera-roadrunner's RoadRunnerHttpWorker — the correct
 * class is Spiral\RoadRunner\Http\PSR7Worker (waitRequest() returns a PSR-7
 * ServerRequest or null on shutdown; respond() takes a PSR-7 Response).
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Spiral\Goridge\Exception\GoridgeException;
use Spiral\RoadRunner\Exception\RoadRunnerException;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;

require __DIR__ . '/bootstrap-worker.php';

// BENCH_APP is read with getenv() BEFORE composer loads: defining a helper for
// it would shadow Laravel's own env() (see the note in bootstrap-worker.php).
$benchApp  = (string) (getenv('BENCH_APP') ?: '');
$benchRoot = dirname(__DIR__, 2);

// Framework helpers must win their function_exists race BEFORE composer's
// `files` autoload pulls Laravel's in (see preloadFrameworkHelpers()).
preloadFrameworkHelpers($benchApp, $benchRoot);

require $benchRoot . '/vendor/autoload.php';
require $benchRoot . '/WebAppAdapter.php';
require $benchRoot . '/adapters/BenchmarkAutoloader.php';

// --- Resolve the app under test ---------------------------------------------

$adapter = createAdapter($benchApp);

/**
 * One-time framework boot. In the real warm model this cost is paid once per
 * worker lifetime, exactly like run.php's warm mode pays it outside the loop.
 */
$adapter->bootstrap();

// --- Memory probe ------------------------------------------------------------
// The worker is only instrumented when the client asks for it: nothing on the
// hot path pays for memory accounting. scripts/http-bench.php sets
// X-Mem-Probe: 1 on ONE extra request per endpoint AFTER its timed loop, so
// the numbers describe the RETAINED state of the resident worker.
//   heap = exact PHP heap (memory_get_usage(false); the (true) form quantizes
//          to 2 MiB allocator chunks) — this is the framework comparison.
//   rss  = process total from /proc/self/status (PHP binary + extensions +
//          opcache SHM). Mostly SHARED between workers, so it is a host
//          capacity figure, NOT a framework comparison.
//   hwm  = lifetime peak RSS. Monotonic, never resets — context only.
function readProcMem(): array
{
    $s = @file_get_contents('/proc/self/status');
    if ($s === false) {
        return [0, 0]; // non-Linux: probe reports 0, timing is unaffected
    }
    $rss = preg_match('/^VmRSS:\s+(\d+) kB/m', $s, $m) ? (int) $m[1] * 1024 : 0;
    $hwm = preg_match('/^VmHWM:\s+(\d+) kB/m', $s, $m) ? (int) $m[1] * 1024 : 0;
    return [$rss, $hwm];
}

$bootHeap = memory_get_usage(false);
[$bootRss] = readProcMem();

// --- Resident request loop ---------------------------------------------------

$psr7 = new PSR7Worker(
    Worker::create(),
    new Psr17Factory(),
    new Psr17Factory(),
    new Psr17Factory(),
);

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break; // RoadRunner signalled shutdown
        }
    } catch (GoridgeException | RoadRunnerException $e) {
        // Transport failure (RR gone / pipe closed): exiting is the only sane
        // reaction. An unguarded catch-all here would spin at 100% CPU — a
        // dead transport never starts delivering requests again.
        break;
    } catch (Throwable $e) {
        // Malformed request payload must not kill the worker.
        $psr7->respond(new Response(400, [], '400 Bad Request'));
        continue;
    }

    try {
        $uri = $request->getUri()->getPath()
            . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : '');
        $method = $request->getMethod();

        $body = $adapter->dispatch($method, $uri);
        $adapter->cleanup();

        $status  = str_starts_with($body, '500 ') ? 500 : 200;
        $headers = ['Content-Type' => 'text/html; charset=utf-8'];

        // Opt-in probe (see the memory-probe block above the loop). Read AFTER
        // cleanup() so the heap reflects what the request RETAINED, not its
        // transient working set.
        if ($request->getHeaderLine('X-Mem-Probe') === '1') {
            [$rss, $hwm] = readProcMem();
            $headers += [
                'X-Bench-Boot' => (string) $bootHeap,
                'X-Bench-Heap' => (string) memory_get_usage(false),
                'X-Bench-Rss'  => (string) $rss,
                'X-Bench-Hwm'  => (string) $hwm,
            ];
        }

        $psr7->respond(new Response($status, $headers, $body));
    } catch (Throwable $e) {
        // Send an error response and keep the worker alive.
        $psr7->respond(new Response(500, [], '500 ' . get_class($e) . ': ' . $e->getMessage()));
    }
}