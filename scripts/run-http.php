<?php

declare(strict_types=1);

/**
 * Real-deployment benchmark orchestrator — RUNS ON THE BENCHMARK VM.
 *
 * Benchmarks every app under real servers, one block per (server, app):
 *
 *   server rr  → RoadRunner resident worker (deploy/rr/worker.php,
 *                BENCH_APP=<app>, config stamped from deploy/rr/) → warm model
 *   server fpm → nginx vhost + php-fpm pool (pm.max_requests=0, i.e. the
 *                worker is NOT recycled; the app's entry script still runs
 *                per request, so its boot stays inside the clock)
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
    'boot-only',
    'mem-only',
    'mem-repeats::',
    'rr-binary::',
    'php-fpm::',
    'bench-user::',
]);

$apps        = isset($opts['apps']) ? explode(',', (string) $opts['apps']) : ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
$itersPerRun = (int) ($opts['iterations-per-run'] ?? 1000);
$runs        = (int) ($opts['runs'] ?? 10);
$rows        = (int) ($opts['rows'] ?? 1000);
$outPrefix   = $opts['out'] ?? 'results/real-deployments';
// RR binary lives OUTSIDE vendor/ on the bench VM: composer install prunes
// vendor/bin/rr because it is not owned by any installed package (only the
// roadrunner-cli proxy knows it). Default order: explicit --rr-binary wins,
// then ~/bin/rr (stable home on the VM), then the legacy vendor path.
// NOTE: getenv('HOME') — $_SERVER['HOME'] is NOT populated in PHP CLI on
// Ubuntu 24.04, the fallback below silently picked the dead vendor path.
$homeBinRr = rtrim((string) (getenv('HOME') ?: ($_SERVER['USERPROFILE'] ?? '')), "/\\") . '/bin/rr';
$rrBinary  = $opts['rr-binary'] ?? (is_file($homeBinRr) ? $homeBinRr : (__DIR__ . '/../vendor/bin/rr'));
$phpFpmBin = $opts['php-fpm'] ?? 'php-fpm8.3';
$benchUser = $opts['bench-user'] ?? getenv('BENCH_USER') ?: get_current_user();
$useRr     = true;
$useFpm    = true;

$serversArg = array_map('trim', explode(',', (string) ($opts['servers'] ?? 'rr,fpm')));
$useRr      = in_array('rr', $serversArg, true);
$useFpm     = in_array('fpm', $serversArg, true);

$quick = isset($opts['quick']);
if ($quick) {
    $itersPerRun = min($itersPerRun, 100);
    $runs        = min($runs, 3);
}

// Boot-only: measure the boot probe and nothing else. The boot is a one-shot
// measurement, so this takes minutes instead of the full run's hours, and its
// output is merged into an EXISTING dataset by scripts/merge-boot.php — the
// latency numbers already measured stay valid and are not re-measured.
$bootOnly = isset($opts['boot-only']);

// Mem-only: the same treatment for the worker-memory probe. Output is merged
// by scripts/merge-mem.php.
//
// Mem-repeats: how many probes each endpoint gets. Since the probe reports a
// per-REQUEST peak (2026-09-17) — a measurement that varies run to run — one
// probe per endpoint could not tell a heavy endpoint from a noisy one. The
// harness reduces each endpoint to its median, then the report takes the
// extremes across endpoints. The count is forwarded to http-bench.php AND
// recorded per row (mem_samples), so a dataset always states the figure it
// was measured with rather than assuming today's default.
$memOnly    = isset($opts['mem-only']);
$memRepeats = max(1, (int) ($opts['mem-repeats'] ?? 10));

// ONE budget for every server. RoadRunner and FPM are two ways of running the
// same request, so the only meaningful comparison measures both with the same
// sample; an asymmetry (the historical RR 1000x10 vs FPM 300x5) makes every
// cross-model caption branch on the mismatch and leaves the reader comparing
// rows with different confidence. There is deliberately no per-server
// override: a budget knob that only one server honours is the bug, not the
// feature. assemble-real.php re-checks this across per-app invocations.
$budget = "{$itersPerRun}x{$runs}";

require_once __DIR__ . '/deploy-lib.php';

$root = dirname(__DIR__);

echo "=== real-deployment benchmark (run-http.php) ===\n";
echo "Root: {$root}\n";
echo "Apps: " . implode(', ', $apps) . "\n";
echo "Servers: " . implode(', ', array_keys(array_filter(['rr' => $useRr, 'fpm' => $useFpm]))) . "\n";
echo "Iterations/run: {$itersPerRun}, Runs: {$runs}\n";
echo "Budget (all servers): {$budget}\n";
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
        // The FPM deployment model as actually configured. The report reads
        // this to describe what it measured instead of assuming a value.
        'fpm_max_requests' => fpmMaxRequestsFromTemplate($root),
        // The RoadRunner pool's recycle setting, from the same template that
        // generated the .rr-<app>.yaml files. The report divides the worker's
        // boot by this to turn "recycle cost" into "boot share a request
        // carries": at 0 (no limit — the value benchmarked here) the worker is
        // never recycled, so no request pays a boot at all.
        'rr_max_jobs' => rrMaxJobsFromTemplate($root),
        // The sample BOTH servers were measured with ("1000x10"). Stamped so
        // the report can state the budget from the dataset rather than from a
        // constant, and so a mixed-budget dataset is detectable.
        'budget' => $budget,
        // Boot probe: real "PHP start -> framework ready" samples, collected
        // by dedicated probe requests rather than inside the timed loop (see
        // boot-probe.php for the cost model that forced that split). Present
        // so the report can tell a probed dataset from a legacy one and stop
        // rendering the warm-GET-/ startup proxy.
        'boot_probe' => 50,
        'servers'    => [
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
// running; per-block the orchestrator only starts/stops RoadRunner. The
// pools never recycle (pm.max_requests=0), so one long-lived pool serves the
// whole run — no per-block start/stop is needed or wanted.
stampAllDeployConfigs($root, $deployDir, $apps, $fpmPort, $rrPort, $ports, $benchUser);

// A benchmark RoadRunner left behind by an earlier invocation would hold its
// port, silently absorb the new server's bind failure, and get benchmarked
// under the wrong app's name (2026-09-14). Clear them before the first block.
killAllBenchRoadRunners($deployDir);

foreach ($apps as $appKey) {
    $appKey = trim($appKey);
    echo "\n=== App: {$appKey}\n";

    $appResult = ['app' => $appKey, 'modes' => []];

    foreach (array_keys(array_filter(['rr' => $useRr, 'fpm' => $useFpm])) as $server) {
        $port    = $ports[$server][$appKey];
        $baseUrl = "http://127.0.0.1:{$port}";

        // Fresh DB per block — same rationale as run.php's per-app re-seed.
        seedDatabase($root, $rows);

        // Start the boot probe from an EMPTY file, and do it BEFORE the
        // server starts. A RoadRunner worker records its samples while
        // `rr serve` comes up (deploy/rr/worker.php records once, before the
        // request loop), so clearing the file after waitForServer() deleted
        // the very samples this block had just produced — every run reported
        // "boot probe: no samples ... missing" for rr while fpm worked
        // (2026-09-16). Clearing first also keeps a file left by an earlier
        // block from contributing samples to this one.
        $bootFile = "{$root}/temp/boot-" . ($server === 'rr' ? 'rr' : 'fpm') . "-{$appKey}.jsonl";
        @unlink($bootFile);
        // The RR worker also records its COLD process-start boot under a
        // separate kind (one sample, not the reported number) — clear it too,
        // or it accumulates across runs and confuses the next reader.
        @unlink("{$root}/temp/boot-rr-cold-{$appKey}.jsonl");

        $proc = null;
        if ($server === 'rr') {
            $proc = startRoadRunner($root, $deployDir, $appKey, $rrBinary, $port);
        } else {
            $proc = ensureFpmRunning($root, $phpFpmBin, $deployDir);
        }

        $failure = null;
        try {
            waitForServer($baseUrl, $server, $appKey, $proc['log'] ?? null);

            // http-bench.php aborts (exit 1) on broken bodies — its output is
            // trusted only when the child exits 0.
            $tmpJson = "{$root}/temp/bench-{$server}-{$appKey}.json";
            if ($bootOnly || $memOnly) {
                // One file per (server, app) so merge-boot.php / merge-mem.php
                // can graft each block onto the dataset's matching mode.
                $prefix = $bootOnly ? 'bootonly' : 'memonly';
                // The per-endpoint probe count is forwarded explicitly rather
                // than left to http-bench's default, so the number the dataset
                // ends up describing is the number this run actually used.
                $flag = $bootOnly
                    ? '--boot-only'
                    : '--mem-only --mem-repeats=' . $memRepeats;
                $tmpJson = "{$root}/temp/{$prefix}-{$server}-{$appKey}.json";
                @unlink($tmpJson);
                runHttpBench($root, $server, $appKey, $baseUrl, $itersPerRun, $runs, $tmpJson, $flag);
                echo "  {$prefix} written: temp/{$prefix}-{$server}-{$appKey}.json\n";
                continue;
            }
            runHttpBench($root, $server, $appKey, $baseUrl, $itersPerRun, $runs, $tmpJson);

            $modeData = json_decode((string) file_get_contents($tmpJson), true);
            if (!is_array($modeData) || !isset($modeData['modes'][$modeOf($server)])) {
                throw new RuntimeException("No results from http-bench for {$server}/{$appKey}.");
            }
            $appResult['modes'] += $modeData['modes'];

            // Boot probe. Keyed BY MODE, not stored once per app: this loop
            // runs once per server for the same app, so a single
            // $appResult['boot'] would be silently overwritten by the second
            // server — and the report would then show RoadRunner's worker boot
            // in the PHP-FPM view (or vice versa) with no error anywhere.
            // The semantics differ per mode, which is exactly why they cannot
            // share one field:
            //   php-fpm    -> the entry script re-runs per request, so this
            //                 boot IS inside every timed row (boot_ms too).
            //   roadrunner -> the worker boots once before serving anything,
            //                 so it is NOT inside the timed rows.
            //
            // The legacy scalar is emitted BESIDE the map for old readers, and
            // deliberately NOT overwritten per server: with the server loop
            // [rr, fpm] an unconditional assignment left it holding the
            // php-fpm boot, and any reader that looked at the scalar charged
            // that to the RoadRunner view (2026-09-17).
            if (isset($modeData['boot'])) {
                $modeOfServer = $modeOf($server);
                $appResult['boot_by_mode'][$modeOfServer] = $modeData['boot'];
                if (!isset($appResult['boot']) || $modeOfServer === 'roadrunner') {
                    $appResult['boot'] = $modeData['boot']; // legacy shape
                }
            }
            if (isset($modeData['boot_samples'])) {
                $modeOfServer = $modeOf($server);
                $appResult['boot_samples_by_mode'][$modeOfServer] = $modeData['boot_samples'];
                if (!isset($appResult['boot_samples']) || $modeOfServer === 'roadrunner') {
                    $appResult['boot_samples'] = $modeData['boot_samples'];
                }
            }
            unlink($tmpJson);
        } catch (RuntimeException $e) {
            // The finally below tears the server down BEFORE we abort —
            // aborting inside the try skipped teardown and leaked the RR
            // worker still holding the port (2026-09-14).
            $failure = $e;
        } finally {
            if ($server === 'rr') {
                stopRoadRunner($proc);
            }
        }

        if ($failure !== null) {
            fwrite(STDERR, "[run-http] {$server}/{$appKey} failed: {$failure->getMessage()}\n");
            exit(1);
        }
    }

    $results['apps'][] = $appResult;
}

// Boot-only / mem-only stop here: floors and the dataset writer are for a full
// latency run, and writing real-deployments.json from a probe-only pass would
// REPLACE the measured latency numbers with an empty dataset. The per-block
// files in temp/ are merged into the existing dataset by merge-boot.php /
// merge-mem.php.
if ($bootOnly || $memOnly) {
    $prefix = $bootOnly ? 'bootonly' : 'memonly';
    $merge  = $bootOnly ? 'merge-boot' : 'merge-mem';
    echo "\n=== {$prefix} pass complete ===\n";
    echo "Per-block files: temp/{$prefix}-<server>-<app>.json\n";
    echo "Merge with:      php scripts/{$merge}.php results/real-deployments.json temp/{$prefix}-*.json\n";
    exit(0);
}

echo "\n=== Floors (webserver overhead, measured once per server) ===\n";
$results['floors'] = measureFloors($root, $deployDir, $useRr, $useFpm, $rrPort, $fpmPort, $itersPerRun, $runs, $rrBinary, $phpFpmBin, $benchUser);

writeRealResults($outPrefix, $results);

echo "\nWrote: {$outPrefix}.json\n";
exit(0);