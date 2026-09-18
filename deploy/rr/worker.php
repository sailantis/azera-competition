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
require $benchRoot . '/boot-probe.php';

// --- Resolve the app under test ---------------------------------------------

$adapter = createAdapter($benchApp);

// --- Boot probe: cold worker start + warm recycle cycles ---------------------
//
// TWO different costs live here, and conflating them is what made the first
// version of this probe misleading:
//
//   cold  — process start → framework ready. Pays composer's autoloader and
//           every first-touch class load. This is what RoadRunner's supervisor
//           pays when it recycles a worker PROCESS (max_jobs, memory limit).
//   warm  — the framework re-initialised in an ALREADY-warm process: opcache
//           holds the bytecode, every class is loaded, so only the
//           kernel/container graphs are rebuilt. This is the "cycle reset"
//           cost, and the one comparable to the in-process harness's warm_ms
//           (run-app.php measureBoot()) and to the FPM band's per-request boot.
//
// The report's RoadRunner band draws the WARM number. A single cold sample
// cannot produce a median — the spread across passes was large (symfony 455 ms
// in one pass) — so a one-sample band is a coin flip rather than a measurement.
// N warm cycles give a real distribution.
//
// How many cycles. Each one re-boots the framework in this process, so even
// the slowest framework finishes 20 in a few seconds; it runs once per worker,
// never per request. Overridable so a run can widen the sample if needed.
$bootCycles = (int) (getenv('BENCH_BOOT_CYCLES') ?: 20);
if ($bootCycles < 1) {
    $bootCycles = 1;
}

// Clock started HERE rather than at the top of the script: the worker is
// started by the server, so "PHP start" means "worker process start", and
// everything before this line is the generic worker bootstrap (composer +
// adapter selection + helper preload) that every framework pays identically.
boot_probe_start();

/**
 * First framework boot in this process — cold (fresh PHP state).
 */
$adapter->bootstrap();

// Forced writes: the worker boots BEFORE it can see any request, so there is
// no header to gate on. Kind 'rr-cold' is kept separate from the cycle samples
// so a one-time autoload cost cannot contaminate the cycle distribution.
boot_probe_record('rr-cold', $benchApp, true);

/**
 * Warm recycle cycles — the number the RoadRunner band reports.
 *
 * Each iteration re-boots the framework in this already-warm process, which is
 * exactly what a worker recycle does under a real `max_jobs` setting (minus
 * the process spawn, which the server pays, not the framework). Recorded as
 * kind 'rr' with N samples so the report can take a median instead of trusting
 * one observation.
 *
 * One untimed warm-up cycle first: the very first re-boot still pays some
 * lazy init (route compilation, template cache) that every later cycle skips.
 * Including it would skew the distribution the same way timing a cold boot
 * would.
 *
 * A re-boot that throws must not kill the worker: the failure is reported once
 * on stderr and the loop stops, leaving whatever samples landed — a partial
 * distribution beats a dead worker, and the harness reads the file rather than
 * assuming a sample count.
 */
if ($bootCycles > 1) {
    try {
        $adapter->bootstrap();
        $adapter->cleanup();
    } catch (Throwable $e) {
        fwrite(STDERR, "[worker] warm-up cycle failed: {$e->getMessage()}\n");
    }
}

for ($cycle = 0; $cycle < $bootCycles; $cycle++) {
    try {
        boot_probe_start();
        $adapter->bootstrap();
        boot_probe_record('rr', $benchApp, true);
        $adapter->cleanup();
    } catch (Throwable $e) {
        fwrite(STDERR, "[worker] boot cycle {$cycle} failed: {$e->getMessage()}\n");
        break;
    }
}

// The worker must be serving with a live framework after the cycles: the loop
// above re-booted it repeatedly, so one final bootstrap guarantees the adapter
// holds a working kernel before the first request arrives.
try {
    $adapter->bootstrap();
} catch (Throwable $e) {
    fwrite(STDERR, "[worker] final bootstrap failed: {$e->getMessage()}\n");
    exit(1);
}

// --- Memory probe ------------------------------------------------------------
// The worker is only instrumented when the client asks for it: nothing on the
// hot path pays for memory accounting. scripts/http-bench.php sets
// X-Mem-Probe: 1 on extra requests per endpoint AFTER the timed loop.
//
// TWO DIFFERENT QUESTIONS, and this worker answers both because a resident
// worker genuinely has both — unlike FPM, where only the second exists:
//
//   heap/peak — the CUMULATIVE story. The high-water mark is reset at the
//          start of each probe request and read at the end, so `peak` is what
//          THAT request needed on top of everything the worker already held.
//          (Added 2026-09-17 so the RR and FPM pages can draw one shared
//          statistic; FPM has no resident state, so its peak is the same
//          quantity measured in a process that starts fresh.)
//   heap   = exact PHP heap (memory_get_usage(false); the (true) form
//          quantizes to 2 MiB allocator chunks) read AFTER cleanup(), so it
//          reflects what the request RETAINED, not its transient working set.
//          This is the trajectory the report draws as boot -> ... -> last:
//          a resident worker accumulates, and for cakePHP it USED to grow
//          5.3 -> 40.6 MB across 21 endpoints. That growth was the finding,
//          so `heap` keeps its existing meaning and is NOT replaced by the
//          per-request peak.
//
//          ROOT-CAUSED + FIXED 2026-09-18: the growth was APP-SIDE and fixed
//          at the source, not in this probe. Cake's stock request factory
//          builds a fresh Session per call and Session::__construct() calls
//          session_register_shutdown(), which appends to a handler list PHP
//          only frees at exit (~186 B/request, linear). The CakePHP adapter now
//          reuses one Session per worker (App\Cake\Support\WorkerRequestFactory).
//
//          RE-MEASURED on the bench VM 2026-09-18 (1000x10, both servers):
//          cakePHP now reads 0.923 -> 5.96 MB end state, 8.14 MB worst
//          endpoint, growth 5.0 MB — down from 39.7 MB, and inside the
//          0.7-1.5 MB band the other five frameworks occupy. The SHAPE is the
//          proof: before the fix the series rose monotonically (+~2.4 MB per
//          endpoint, never released); after it the heap oscillates and RETURNS
//          to baseline, i.e. each endpoint's state is released rather than
//          retained. The probe is unchanged — it surfaced the bug and would
//          surface a regression the same way.
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

        // Opt-in handle/cleanup split (X-Bench-Split: 1). A resident worker
        // does real work BETWEEN requests — request-scoped service teardown,
        // ORM/heap resets, driver disconnects — and this measures it instead
        // of assuming it is free. Gated on the header so the timed loop pays
        // NOTHING: no hrtime() call is even reached on a normal request.
        $split = $request->getHeaderLine('X-Bench-Split') === '1';

        // Anchor the per-request heap high-water mark. Reset BEFORE dispatch
        // (in the same request that will report it), so the peak read below
        // covers this request only — on top of whatever the worker retains.
        $memProbe = $request->getHeaderLine('X-Mem-Probe') === '1';
        if ($memProbe && function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        if ($split) {
            $tHandle0 = hrtime(true);
            $body     = $adapter->dispatch($method, $uri);
            $tHandle1 = hrtime(true);
            $adapter->cleanup();
            $tSplit1 = hrtime(true);
        } else {
            $body = $adapter->dispatch($method, $uri);
            $adapter->cleanup();
        }

        $status  = str_starts_with($body, '500 ') ? 500 : 200;
        $headers = ['Content-Type' => 'text/html; charset=utf-8'];

        if ($split) {
            // Microseconds, so no precision is lost in the header round-trip.
            $headers += [
                'X-Bench-Handle-Us'  => (string) (int) (($tHandle1 - $tHandle0) / 1000),
                'X-Bench-Cleanup-Us' => (string) (int) (($tSplit1 - $tHandle1) / 1000),
            ];
        }

        // Opt-in probe (see the memory-probe block above the loop).
        //   Heap is read AFTER cleanup() so it reflects what the request
        //   RETAINED, not its transient working set.
        //   Peak is read after cleanup() too: it is a high-water mark, so
        //   cleanup cannot lower it, and reading both at one instant keeps
        //   them consistent with the FPM probe (which also reports the pair
        //   from its shutdown hook).
        if ($memProbe) {
            [$rss, $hwm] = readProcMem();
            $peak = memory_get_peak_usage(false);
            // Defensive: the reset anchored peak at the heap that existed at
            // dispatch. Keep it an absolute reading (>= boot) even on a runtime
            // without the reset.
            if ($peak < $bootHeap) {
                $peak = $bootHeap;
            }
            $headers += [
                'X-Bench-Boot' => (string) $bootHeap,
                'X-Bench-Peak' => (string) $peak,
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