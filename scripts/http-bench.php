<?php

declare(strict_types=1);

/**
 * HTTP latency benchmark client (curl_multi over loopback).
 *
 * Measures REAL deployed endpoints (volucer `attach` resident worker = mode
 * "volucer-attach"; nginx + php-fpm with pm.max_requests=0 = mode "php-fpm")
 * with the same stats machinery and JSON field names as run.php's
 * benchRequest(), so the real-deployment dataset drops straight into the
 * existing report generator.
 *
 * Called by scripts/run-http.php — one invocation per (server, app) block:
 *
 *   php scripts/http-bench.php \
 *       --server=fpm --app=azera --base-url=http://127.0.0.1:8883 \
 *       --iterations-per-run=1000 --runs=10 \
 *       --requests="GET /,GET /items,POST /items" \
 *       --out-json=temp/bench-fpm-azera.json
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
    'boot-only',
    'mem-only',
    'mem-repeats::',
]);

$server      = $opts['server'] ?? 'fpm';
$appKey      = $opts['app'] ?? 'azera';
$baseUrl     = rtrim((string) ($opts['base-url'] ?? ''), '/');
$itersPerRun = (int) ($opts['iterations-per-run'] ?? 1000);
$runs        = (int) ($opts['runs'] ?? 30);
$outJsonPath = $opts['out-json'] ?? null;
// Boot-only: skip the latency loop entirely and just collect boot samples.
// The boot is a ONE-SHOT measurement — it does not depend on 1000x10 of
// latency samples — so a boot-only pass takes minutes instead of hours, and
// its output is merged into an existing dataset rather than replacing it.
$bootOnly = isset($opts['boot-only']);
// Mem-only: the same idea for the worker-memory probe, and needed for the
// same reason. The memory probe does not depend on 1000x10 latency samples, so
// a dedicated pass takes seconds instead of hours and writes a payload that
// merge-mem.php grafts onto an existing dataset, exactly as --boot-only and
// merge-boot.php do for the boot.
//
// How many probes per endpoint. The probe reports a per-REQUEST peak, which
// varies run to run, so one probe per endpoint would leave the report unable
// to tell a heavy endpoint from a noisy one. N probes are taken and reduced to
// one median per endpoint (memProbeAggregateEndpoint), then min/median/max are
// taken across the 21 endpoint medians — so every mark the report draws is an
// endpoint that was really served, and the report can NAME the worst one.
// 10 keeps the pass well under a second per app against a warm pm=static pool
// while making a single outlier unable to move a median.
$memOnly    = isset($opts['mem-only']);
$memRepeats = max(1, (int) ($opts['mem-repeats'] ?? 10));

// --- Shared stats helpers (kept in sync with run.php / run-app.php) --------
require_once __DIR__ . '/bench-lib.php';

$requests = parseRequestsArg($opts['requests'] ?? null);

if ($baseUrl === '') {
    fwrite(STDERR, "[http-bench] --base-url is required\n");
    exit(1);
}

echo "=== http-bench: {$server} / {$appKey} @ {$baseUrl} ({$itersPerRun}x{$runs})\n";

// The report mode name for this block. Must match scripts/run-http.php's modeNameFor()
// — the orchestrator looks the child's output up by that key, so a mismatch reads as
// "No results from http-bench", not as a naming quirk.
//
// The two arms are different deployment models: `volucer-attach` is resident (the
// framework boots once and every connection reuses it) while `fpm` re-runs the entry
// script per request. Bucketing them together would average a resident arm with a cold
// one.
$modeName = match ($server) {
    'fpm'   => 'php-fpm',
    default => $server,
};

// The probe-file kind for this block. Same match as scripts/run-http.php's
// probeKindFor(): the harness CLEARS these files before the block and reads them after,
// so both sides must name the same one.
//
// This must agree with scripts/run-http.php's probeKindFor(): the harness CLEARS these
// files before the block and this side READS them after, so both must name the same
// one. The kinds are historical strings and are NOT renamed with the arms, because
// datasets written before this pair existed store their samples under them.
$probeKind = match ($server) {
    'fpm'            => 'fpm',
    'volucer-attach' => 'volucer',
    default          => throw new RuntimeException("http-bench: no probe kind for server '{$server}'"),
};

// Where a per-request entry script appends its memory samples (see mem_probe_arm()
// in boot-probe.php). Same temp/ rule as the boot-probe files: gitignored and
// excluded from the sync, so samples never leak between machines.
$memFile = dirname(__DIR__) . "/temp/mem-{$probeKind}-{$appKey}.jsonl";

// --mem-only: fresh sample file, so the pass measures this run rather than
// appending to whatever a previous run left behind. (The boot probe has the
// same hazard and solves it by truncating temp/boot-* before the run; do the
// same here.)
if ($memOnly) {
    @unlink($memFile);
}

$measured = [];
$peakMem  = 0;

// --boot-only: skip the latency loop. The boot probe below then runs against
// a server that has already served the warm-up requests it needs to be ready.
if ($bootOnly) {
    echo "  [{$modeName}] boot-only pass — skipping the latency loop\n";
    for ($i = 0; $i < 5; $i++) {
        try {
            httpRequest($baseUrl, 'GET', '/');
        } catch (RuntimeException $e) {
            break;
        }
    }
}

// --- Memory-only pass --------------------------------------------------------
// N probe requests per endpoint, in the harness's canonical order, with no
// timed loop in between.
//
// WHY ORDER STILL MATTERS, and why it did not stop mattering when the probe
// changed: `heap` is still the resident heap, so it is cumulative and only
// meaningful in the order the endpoints were served (residentTrajectory()
// documents this). `peak` is per-request and therefore order-INDEPENDENT —
// which is exactly the property that lets it carry a ranking. The two live in
// one row because the report draws them as two different things.
//
// The rows are NOT sorted: sorting would make `heap` a sequence the probe
// never measured while `peak` gains nothing from it.
if ($memOnly) {
    echo "  [{$modeName}] mem-only pass — {$memRepeats} probes per endpoint, no timing\n";
    $memRows = [];
    foreach ($requests as [$method, $uri]) {
        $reqLabel = "{$method} {$uri}";
        try {
            // The endpoint's own work, plus the same abort guard the timed path
            // uses. Without it a 404 here would produce a memory row for an
            // error page, which merge-mem.php would then graft onto the dataset.
            abortIfBroken($server, $appKey, $reqLabel, httpRequest($baseUrl, $method, $uri));
            $samples = memProbeRepeated(
                $baseUrl,
                $method,
                $uri,
                $server,
                $memFile,
                $memRepeats
            );
        } catch (RuntimeException $e) {
            fwrite(STDERR, "  mem-only: request failed at {$reqLabel}: {$e->getMessage()}\n");
            break;
        }

        $agg = memProbeAggregateEndpoint($samples);
        if ($agg === null) {
            fwrite(STDERR, "  mem-only: no sample landed at {$reqLabel}\n");
            continue;
        }

        $memRows[$reqLabel] = [
            'mem_boot_heap' => $agg['boot'],
            'mem_peak_heap' => $agg['peak'],
            'mem_heap'      => $agg['heap'],
            'mem_rss'       => $agg['rss'],
            'mem_hwm'       => $agg['hwm'],
            'mem_samples'   => $agg['samples'],
        ];
        echo sprintf(
            "    %-28s boot %s  peak %s  (%d/%d)\n",
            $reqLabel,
            $agg['boot'] > 0 ? number_format($agg['boot'] / 1048576, 3) . ' MB' : '(none)',
            $agg['peak'] > 0 ? number_format($agg['peak'] / 1048576, 3) . ' MB' : '(none)',
            $agg['samples'],
            $memRepeats
        );
    }

    $payload = [
        'app'         => $appKey,
        'server'      => $server,
        'mem_only'    => true,
        'mem_repeats' => $memRepeats,
        'mem_rows'    => $memRows,
    ];
    file_put_contents($outJsonPath, json_encode($payload, JSON_PRETTY_PRINT));
    echo "  wrote {$outJsonPath} (mem only, " . count($memRows) . " endpoints, "
        . $memRepeats . " probes each)\n";
    exit($memRows === [] ? 1 : 0);
}

foreach ($bootOnly ? [] : $requests as [$method, $uri]) {
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

    // --- Memory probe (every server) -----------------------------------------
    // One extra request AFTER the timed loop answers "after N requests the
    // worker holds X". Kept off the hot path so latency stays comparable (see
    // deploy/volucer/app.php and boot-probe.php for what
    // each field means).
    //
    // The transport is chosen by whether the arm holds a WORKER LOOP that can be
    // asked at any time:
    //
    //   resident (volucer, volucer-attach)
    //                — the worker is alive and answers in RESPONSE HEADERS
    //                (X-Bench-Boot/Peak/Heap/Rss/Hwm). It can be asked any time.
    //   per-request (php-fpm)
    //                — there is no worker loop to ask: the entry script runs, serves
    //                this one request and is torn down. It appends to
    //                temp/mem-<kind>-<app>.jsonl from a shutdown hook, and the newest
    //                line is the sample for the endpoint just measured.
    //
    // The criterion is the DEPLOYMENT MODEL, not the server's name: volucer's spawn_cgi
    // arm re-runs public/index-<app>.php exactly as FPM does, so it must use the file
    // transport or its rows would carry no memory data at all.
    //
    // Both transports report the SAME five quantities from the same PHP calls —
    // boot/heap/peak from memory_get_usage(false) and memory_get_peak_usage(false),
    // rss/hwm from /proc/self/status — so the figures are comparable across the
    // deployment models. That comparability is the whole point of giving the
    // per-request arms a probe at all.
    $mem = ['boot' => 0, 'peak' => 0, 'heap' => 0, 'rss' => 0, 'hwm' => 0];
    if (probeUsesHeaders($probeKind)) {
        $probe = httpProbeRequest($baseUrl, $method, $uri);
        $mem   = [
            'boot' => (int) ($probe['x-bench-boot'] ?? 0),
            'peak' => (int) ($probe['x-bench-peak'] ?? 0),
            'heap' => (int) ($probe['x-bench-heap'] ?? 0),
            'rss'  => (int) ($probe['x-bench-rss'] ?? 0),
            'hwm'  => (int) ($probe['x-bench-hwm'] ?? 0),
        ];
    } else {
        // Trigger the shutdown-hook write, then WAIT for the sample to land.
        // PHP runs shutdown functions after the response is flushed, so a
        // straight read races the write and records a zero row — which is
        // exactly what happened on the first endpoint of laravel, cakephp and
        // symfony in the 2026-09-17 VM run.
        $before = memProbeLineCount($memFile);
        try {
            httpHeaderProbe($baseUrl, $method, $uri, 'X-Mem-Probe: 1');
        } catch (RuntimeException $e) {}
        $sample = memProbeWaitForNewSample($memFile, $before);
        if ($sample !== null) {
            // Same five fields, same names as the resident branch above: the two
            // probes report the same quantities, only the transport differs.
            $mem = [
                'boot' => $sample['boot'],
                'peak' => $sample['peak'],
                'heap' => $sample['heap'],
                'rss'  => $sample['rss'],
                'hwm'  => $sample['hwm'],
            ];
        }
    }

    // --- Resident-worker handle/cleanup split (opt-in, resident arms) ---------
    // The timed loop measures the whole HTTP round-trip, so the work a worker
    // does BETWEEN requests (request-scoped teardown, ORM/heap resets, driver
    // disconnects) is invisible inside it. One short gated probe splits it, so
    // the report can say how much of a resident request is actually framework
    // teardown. Kept off the hot path: the header is what turns the worker's
    // hrtime() calls on, and no timed request carries it.
    $split = ['handle' => 0.0, 'cleanup' => 0.0, 'samples' => 0];
    if (probeUsesHeaders($probeKind)) {
        $samples = httpSplitProbe($baseUrl, $method, $uri);
        if ($samples !== []) {
            $h     = array_column($samples, 'handle');
            $c     = array_column($samples, 'cleanup');
            $split = [
                'handle'  => stats($h)['median'],
                'cleanup' => stats($c)['median'],
                'samples' => count($samples),
            ];
        }
    }

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
        // Worker memory. Populated for BOTH servers since 2026-09-17. It used
        // to be resident-only on the reasoning that "a php-fpm worker is
        // per-request, so there is no resident heap to report" — but the FPM
        // pool is pm=static/max_children=1/max_requests=0, so one worker IS
        // resident for the whole block and DOES retain what it built. The
        // numbers come from different transports (headers vs a shutdown-hook
        // file) but the same PHP calls, so they are comparable.
        //
        // THE TWO ARE DIFFERENT QUESTIONS, and a reader must not mix them:
        //   mem_peak_heap — how much memory THIS request needed (per-request,
        //                   order-independent, the comparable one).
        //   mem_heap      — what the worker still held at that point
        //                   (cumulative, endpoint-order dependent; for the resident arm this
        //                   is the leak trajectory boot -> ... -> last).
        // mem_boot_heap is the endpoint-independent footprint. See
        // memProbeRepeated()/ResultStore::residentPeakSeries().
        'mem_boot_heap' => $mem['boot'],
        'mem_peak_heap' => $mem['peak'],
        'mem_heap'      => $mem['heap'],
        'mem_rss'       => $mem['rss'],
        'mem_hwm'       => $mem['hwm'],
        // How many probes this row's numbers came from (1 on the full-run
        // path, N on the --mem-only path). Recorded so the report can state
        // the real figure instead of assuming today's default.
        'mem_samples' => $mem['samples'] ?? 1,
    ];

    // The resident-worker teardown split is recorded under its OWN keys, NOT
    // handle_ms/cleanup_ms. Those two have an established contract: they are
    // the framework-side terms that SUM to the headline (`boot + handle +
    // cleanup`), which holds because a fork-mode row times them in the same
    // clock as the request. These probe numbers cannot: the headline is the
    // client's HTTP round-trip (socket write + supervisor IPC + the request
    // object + dispatch + teardown), so a framework-side handle of 0.026 ms
    // against a 0.267 ms headline would leave a 0.24 ms residual that
    // Tables::splitLabel() parks on the largest term — printing a sub-line
    // that claims handle is the whole request. Separate keys keep the report
    // honest and the measurement available.
    if ($split['samples'] > 0) {
        $measured[$reqLabel]['worker_handle_ms']  = $split['handle'];
        $measured[$reqLabel]['worker_cleanup_ms'] = $split['cleanup'];
        $measured[$reqLabel]['split_samples']     = $split['samples'];
    }

    // Reset peak mem tracking per label is not possible in PHP; the value is
    // the high-water mark of this process — acceptable because the real
    // deployment's memory is the SERVER's business, not the client's. The
    // report must not present it as framework memory; run-http.php therefore
    // records peak_mem as 0 for real deployments.
    $measured[$reqLabel]['peak_mem'] = 0;
}

// --- Boot probe ---------------------------------------------------------------
// N dedicated requests that ask the entry script (or worker) to append one
// boot sample each. Runs AFTER the timed loop so the probe traffic never
// shares a sample with the latency measurement, and the entry scripts only
// write when this header is present (see boot-probe.php for the cost model:
// an ungated 3.1 us append would charge the fastest framework 0.39% and the
// slowest 0.016% — a systematic bias, not noise).
//
// The two servers record DIFFERENT kinds of boot, deliberately:
//
//   php-fpm    -> 'fpm': the entry script re-runs per request, so each probe
//                 request appends the boot that request waited for.
//   volucer    -> 'volucer': the supervisor already appended its boot samples
//                 when it started (deploy/volucer/app.php), because the probe
//                 requests below cannot force a re-boot — the worker serves
//                 them from the state it is holding. The probe requests are
//                 therefore only a readiness ping for the resident arm; the
//                 samples come from the supervisor's own startup.
//
// 50 samples is well past the point where the median is stable, and costs
// ~35 ms of server time per block.
$bootSamples = [];
$bootKind    = $probeKind;
$bootFile    = dirname(__DIR__) . "/temp/boot-{$bootKind}-{$appKey}.jsonl";
$haveBoot    = false;
for ($i = 0; $i < 50; $i++) {
    try {
        httpRequest($baseUrl, 'GET', '/', ['X-Boot-Probe: 1']);
        $haveBoot = true;
    } catch (RuntimeException $e) {
        break; // server gone — report what we have (or nothing)
    }
}
$bootSamples = bootProbeRead($bootFile);

// The row-level boot_ms is only meaningful for php-fpm: there the entry
// script re-runs per request, so the boot IS part of every timed row. On a
// resident arm the worker boots once before serving anything, so the boot is
// NOT in the timed rows (adding it to a row would double-count).
$bootMs    = $bootSamples === [] ? null : bootProbeStat($bootSamples)['median'];
$bootStats = $bootSamples === [] ? null : bootProbeStat($bootSamples);
if ($bootKind === 'fpm' && $bootMs !== null) {
    foreach ($measured as $label => $row) {
        $measured[$label]['boot_ms'] = $bootMs;
    }
}
echo $bootSamples === []
    ? "  boot probe: no samples" . ($haveBoot ? " (file {$bootFile} missing)" : " (probe requests failed)") . "\n"
    : sprintf(
        "  boot probe: n=%d median=%.3f ms min=%.3f p95=%.3f\n",
        $bootStats['count'],
        $bootStats['median'],
        $bootStats['min'],
        $bootStats['p95']
    );

// Boot-only: write JUST the boot block. run-http.php (or merge-boot.php)
// merges it into the existing dataset, so the 1000x10 latency numbers already
// measured stay valid — re-measuring them would cost hours and change nothing.
if ($bootOnly) {
    $payload = [
        'app'       => $appKey,
        'server'    => $server,
        'boot_only' => true,
    ];
    if ($bootStats !== null) {
        $median = (float) $bootStats['median'];
        $payload['boot']         = ['cold_ms' => $median, 'warm_ms' => $median];
        $payload['boot_samples'] = $bootStats;
        $payload['boot_kind']    = probeUsesHeaders($probeKind) ? 'warm_recycle' : 'per_request_boot';
    }
    file_put_contents($outJsonPath, json_encode($payload, JSON_PRETTY_PRINT));
    echo "  wrote {$outJsonPath} (boot only)\n";
    exit($bootStats === null ? 1 : 0);
}

writeHttpResults($outJsonPath, $server, $appKey, $modeName, $itersPerRun, $runs, $measured, $bootStats, $probeKind);

echo "  wrote {$outJsonPath}\n";
exit(0);