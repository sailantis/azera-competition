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

// Cold mode boots the framework on EVERY timed iteration (boot inside the
// request clock), so a 1000×30 block would run 30,000 full boots in one
// process — memory ratchets (allocator + static residue never fully return
// to the OS) until a small VM swaps to death (2026-09-13). Cap cold at
// 50×30 = 1,500 samples unless the caller explicitly raised iterations.
// Mirrors the same cap in run.php; one of the two is authoritative per
// spawn path, and both must agree so parent and child never disagree.
$coldItersCap = 50;
if ($modeName === 'cold' && !isset($opts['iterations-per-run'])) {
    $itersPerRun = $coldItersCap;
}

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
        // Memory limit per mode (mirrors run.php's spawn): cold re-boots the
        // framework per iteration in one process — boot residue accumulates
        // ~0.3 MB per boot and OOM'd a 512 M child at the 50×30 cap
        // (2026-09-13) — so cold blocks get 1 G, warm stays at 512 M.
        $memLimit = $modeName === 'cold' ? '1024M' : '512M';
        $tmpJson = tempnam(sys_get_temp_dir(), 'bench-block-') . '.json';
        $cmd     = sprintf(
            '%s -d memory_limit=%s %s --app=%s --mode=%s --iterations-per-run=%d --runs=%d --requests=%s --out-json=%s%s',
            escapeshellarg(PHP_BINARY),
            $memLimit,
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

// Cold mode runs one fresh boot per timed iteration. Two implementations:
//
//   fork (default on Linux, pcntl available): pcntl_fork() per iteration —
//     the child boots, dispatches, cleans up, exits. The forked child
//     inherits the parent's loaded classes + shared opcache copy-on-write,
//     exactly like a real FPM master→worker fork. No accumulation: every
//     iteration is a genuinely fresh process, so memory and fds can never
//     ratchet (2026-09-13: in-process cold OOM'd at 512 MB after ~1,500
//     Laravel boots and then leaked SQLite fds past the 1,024 ulimit).
//     peak_mem becomes the honest per-request footprint (one boot + one
//     request, then the process dies). Fork cost (~0.1-0.3 ms) is INCLUDED
//     in the measured time — real FPM pays it per recycled worker too.
//
//   in-process (Windows / no pcntl fallback): the legacy loop that re-boots
//     the SAME adapter 50×30 times. Boot residue accumulates ~0.3 MB per
//     Laravel boot — fine for local smoke runs, never run full cold blocks
//     with it (raise --iterations-per-run only on Linux).
//
// The forked child reports its three phase times (boot/handle/cleanup) and
// its own peak memory over a socketpair; the parent folds them into the
// per-run statistics exactly like the in-process loop's arrays.
function benchRequestForked(WebAppAdapter $adapter, array $request, int $itersPerRun, int $runs): array
{
    [$method, $uri] = $request;
    $reqLabel = "{$method} {$uri}";

    echo "  [cold/fork] {$reqLabel} — {$itersPerRun}×{$runs}\n";

    // One untimed priming boot in the PARENT: warms opcache + class tables
    // so the forked children inherit compiled bytecode (the FPM master
    // preloads nothing, but opcache sharing via fork is exactly what real
    // FPM workers get). Without priming, every child would pay one-time
    // class-load costs that a real worker never pays after the first
    // request — same rationale as the in-process priming cycle.
    $adapter->bootstrap();
    $adapter->dispatch('GET', '/');
    $adapter->cleanup();

    $runMeans     = [];
    $handleMeans  = [];
    $cleanupMeans = [];
    $bootMeans    = [];
    $allTimes     = [];
    $peakMem      = 0;

    for ($r = 0; $r < $runs; $r++) {
        $times        = [];
        $handleTimes  = [];
        $cleanupTimes = [];
        $bootTimes    = [];

        for ($i = 0; $i < $itersPerRun; $i++) {
            // socketpair: the child writes [boot_ms, handle_ms, cleanup_ms,
            // peak_mem_bytes] as a 4x8-byte little-endian payload + one
            // status byte (0 = ok, 1 = error body).
            $socks = [];
            if (!@socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $socks)) {
                fwrite(STDERR, "\n[ABORT] socket_create_pair failed: " . socket_strerror(socket_last_error()) . "\n");
                exit(1);
            }
            [$pairParent, $pairChild] = $socks;

            $t0 = hrtime(true);
            $pid = pcntl_fork();
            if ($pid === -1) {
                fwrite(STDERR, "\n[ABORT] pcntl_fork failed\n");
                exit(1);
            }

            if ($pid === 0) {
                // ---- child: one fresh request lifecycle, then die -------
                socket_close($pairParent);
                memory_reset_peak_usage();
                $cb = []; $ch = []; $cc = []; $status = 0;
                try {
                    $tb0 = hrtime(true);
                    $adapter->bootstrap();
                    $tb1 = hrtime(true);
                    $body = $adapter->dispatch($method, $uri);
                    $tb2 = hrtime(true);
                    $adapter->cleanup();
                    $tb3 = hrtime(true);
                    if (str_starts_with((string) $body, 'Not Found') || str_starts_with((string) $body, '500 ')) {
                        $status = 1;
                    }
                    $cb[] = ($tb1 - $tb0) / 1e6;
                    $ch[] = ($tb2 - $tb1) / 1e6;
                    $cc[] = ($tb3 - $tb2) / 1e6;
                } catch (\Throwable $e) {
                    $status = 1;
                }
                // 4 float64 + 1 byte = 33 bytes, one write, then exit.
                @socket_write($pairChild, pack('E3C', $cb[0] ?? 0, $ch[0] ?? 0, $cc[0] ?? 0, $status), 25);
                socket_close($pairChild);
                // A clean exit releases EVERYTHING this iteration touched:
                // memory, fds, the framework instance. exit() inside the
                // forked child must never run destructors of parent state.
                posix_kill(posix_getpid(), SIGKILL);
            }

            // ---- parent: wait, read the child's report -------------------
            socket_close($pairChild);
            $status = 0;
            pcntl_waitpid($pid, $status);
            $raw = '';
            // Read until EOF (child closes after its single write).
            while (($chunk = @socket_read($pairParent, 64)) !== false && $chunk !== '') {
                $raw .= $chunk;
            }
            socket_close($pairParent);
            $t3 = hrtime(true);

            if (strlen($raw) !== 25 || exitcode($status) !== 0) {
                fwrite(STDERR, "\n[ABORT] {$reqLabel} fork child failed (exit " . pcntl_wexitstatus($status) . ", raw " . strlen($raw) . " bytes)\n");
                exit(1);
            }
            $msg = unpack('Eboot/Ehandle/Ecleanup/Cstatus', $raw);
            if ($msg['status'] === 1) {
                fwrite(STDERR, "\n[ABORT] {$reqLabel} returned an error response (fork child reported status 1)\n");
                exit(1);
            }

            $bootTimes[]    = $msg['boot'];
            $handleTimes[]  = $msg['handle'] + (($t3 - $t0) / 1e6 - $msg['boot'] - $msg['handle'] - $msg['cleanup']);
            $cleanupTimes[] = $msg['cleanup'];
            $times[]        = ($t3 - $t0) / 1e6;
            $peakMem        = max($peakMem, memory_get_peak_usage(true));
        }

        $s = stats($times);
        $runMeans[]    = $s['mean'];
        $handleMeans[] = stats($handleTimes)['mean'];
        $cleanupMeans[] = stats($cleanupTimes)['mean'];
        $bootMeans[]   = stats($bootTimes)['mean'];
        $allTimes      = array_merge($allTimes, $times);

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
        'boot_ms'            => trimmedMean($bootMeans),
        'fork_mode'          => true,
    ];
}

function exitcode(int $status): int
{
    return pcntl_wexitstatus($status);
}

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
    $bootMeans    = [];
    $allTimes     = [];
    $peakMem      = 0;

    for ($r = 0; $r < $runs; $r++) {
        // Per-run memory window: memory_get_peak_usage() is a PROCESS-LIFETIME
        // high-water mark — without this reset every endpoint would report
        // max(all previous endpoints, itself) and the memory chart would
        // plot benchmark ORDER, not per-endpoint footprint (2026-09-12 bug).
        memory_reset_peak_usage();

        if ($mode === 'cold') {
            // One untimed priming cycle: the very first boot of a PHP process
            // pays one-time costs (opcache bookkeeping, FS cache cold, static
            // map init) that later boots never pay again. Without priming,
            // every timed boot would double-count those one-time costs.
            $adapter->bootstrap();
        }

        $times        = [];
        $handleTimes  = [];
        $cleanupTimes = [];
        $bootTimes    = [];
        for ($i = 0; $i < $itersPerRun; $i++) {
            // Cold mode times the FULL request lifecycle: a fresh boot is
            // part of the work the request must wait for, exactly like a
            // real PHP-FPM worker building the app before serving.
            $t0 = hrtime(true);
            if ($mode === 'cold') {
                $adapter->bootstrap();
            }
            $t1   = hrtime(true);
            $body = $adapter->dispatch($method, $uri);
            $t2   = hrtime(true);
            $adapter->cleanup();
            $t3 = hrtime(true);
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
            $bootTimes[] = ($t1 - $t0) / 1e6;
            $handleTimes[] = ($t2 - $t1) / 1e6;
            $cleanupTimes[] = ($t3 - $t2) / 1e6;
            $times[] = ($t3 - $t0) / 1e6;
        }

        $s = stats($times);
        $runMeans[] = $s['mean'];
        $handleMeans[] = stats($handleTimes)['mean'];
        $cleanupMeans[] = stats($cleanupTimes)['mean'];
        if ($bootTimes !== []) {
            $bootMeans[] = stats($bootTimes)['mean'];
        }
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
    // convention as the headline number). In cold mode handle includes the
    // fresh boot — boot_ms reports it separately so the report can state
    // the boot share explicitly instead of leaving it buried.
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
        'boot_ms'            => $bootTimes !== [] ? trimmedMean($bootMeans) : null,
    ];
}

$appResult = ['app' => $appKey, 'modes' => [], 'boot' => $boot, 'cold_boot_included' => $modeName === 'cold'];

echo " -- mode: {$modeName}\n";
$modeResult = ['requests' => []];
foreach ($requests as $request) {
    if ($modeName === 'cold' && function_exists('pcntl_fork')) {
        // Linux: fork-per-iteration cold — each iteration is a genuinely
        // fresh process (real FPM worker model), no boot-ratchet artifacts.
        $modeResult['requests'][] = benchRequestForked(
            $adapter,
            $request,
            $itersPerRun,
            $runs
        );
        continue;
    }
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