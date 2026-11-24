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
    'floors-only',
    'mem-repeats::',
    'rr-binary::',
    'php-fpm::',
    'php-cli::',
    'php-cgi::',
    'volucer-binary::',
    'bench-user::',
]);

$apps        = isset($opts['apps']) ? explode(',', (string) $opts['apps']) : ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp', 'phalcon'];
$itersPerRun = (int) ($opts['iterations-per-run'] ?? 1000);
$runs        = (int) ($opts['runs'] ?? 10);
$rows        = (int) ($opts['rows'] ?? 1000);
$outPrefix   = $opts['out'] ?? 'results/real-deployments';

// deploy-lib.php is required HERE rather than further down, because the interpreter
// path is resolved from functions it owns (phpCliForFpm / phpSeriesFromFpmBin). When
// the require sat after this block the first use was an undefined function, which is a
// fatal before any config is even parsed.
require_once __DIR__ . '/deploy-lib.php';
require_once __DIR__ . '/version-lib.php';
// budgetsByAppMode()/assertUniformBudget(), which deploy-lib.php only pulls in from
// inside waitForServer(). Loaded here as well so this orchestrator's own budget
// reporting does not depend on a function having been called first.
require_once __DIR__ . '/bench-lib.php';

// RR binary lives OUTSIDE vendor/ on the bench VM: composer install prunes
// vendor/bin/rr because it is not owned by any installed package (only the
// roadrunner-cli proxy knows it). Default order: explicit --rr-binary wins,
// then ~/bin/rr (stable home on the VM), then the legacy vendor path.
// NOTE: getenv('HOME') — $_SERVER['HOME'] is NOT populated in PHP CLI on
// Ubuntu 24.04, the fallback below silently picked the dead vendor path.
$homeBinRr = rtrim((string) (getenv('HOME') ?: ($_SERVER['USERPROFILE'] ?? '')), "/\\") . '/bin/rr';
$rrBinary  = $opts['rr-binary'] ?? (is_file($homeBinRr) ? $homeBinRr : (__DIR__ . '/../vendor/bin/rr'));
$phpFpmBin = $opts['php-fpm'] ?? 'php-fpm8.5';
// The CLI interpreter the RoadRunner workers (and every helper this script
// shells out to) run under. Defaults to the sibling of $phpFpmBin, so the two
// servers share ONE PHP installation instead of the FPM half being pinned by
// this script while the worker half silently follows PATH.
$phpCli    = $opts['php-cli'] ?? phpCliForFpm($phpFpmBin);
// The php-cgi binary the volucer `spawn_cgi` (cold) arm runs. Derived from the same
// series as the other two so all three volucer-backed PHP processes share one install;
// php-cgi is a separate package from php-fpm and php-cli, so a host can easily have one
// and not the other — the block fails loudly on start rather than measuring nothing.
$phpCgiBin = $opts['php-cgi'] ?? ('php-cgi' . (phpSeriesFromFpmBin($phpFpmBin) ?? '8.5'));
// The volucer binary. Resolution order mirrors $rrBinary's, with one extra candidate:
// the Rust build tree. Unlike RoadRunner there is NO vendor/bin/volucer to fall back
// on — volucer is not a composer package — and cargo is not installed on the bench VM,
// so the binary has to be one that was already built there.
$homeBinVolucer = rtrim((string) (getenv('HOME') ?: ''), "/\\") . '/bin/volucer';
$volucerCandidates = array_filter([
    $homeBinVolucer,
    // `cargo build --release` in a checkout of the volucer repo. The bench VM keeps one
    // at ~/volucer-bench, and this is the only location a from-source build ever lands.
    rtrim((string) (getenv('HOME') ?: ''), "/\\") . '/volucer-bench/target/release/volucer',
]);
$volucerBin = $opts['volucer-binary'] ?? 'volucer';
if (!isset($opts['volucer-binary'])) {
    foreach ($volucerCandidates as $candidate) {
        if (is_file($candidate)) {
            $volucerBin = $candidate;
            break;
        }
    }
}
$benchUser = $opts['bench-user'] ?? getenv('BENCH_USER') ?: get_current_user();

// The servers this run measures. A list, not a set of booleans: four of them can be
// asked for now, the report has two modes today, and the loop below must not carry a
// branch per server name.
//
//   rr              RoadRunner resident worker            -> mode roadrunner
//   fpm             nginx + php-fpm pool                   -> mode php-fpm
//   volucer-attach  volucer `attach`, deployment owns them  -> mode volucer-attach
//   volucer-cgi     volucer `spawn_cgi`, php-cgi per request-> mode volucer-cgi
//
// The two volucer arms are SEPARATE modes rather than one "volucer" bucket because
// they are different deployment models: `attach` is resident (the framework boots
// once and every connection reuses it) while `spawn_cgi` boots per request like FPM.
// Collapsing them would make the report average a resident arm with a cold one.
//
// volucer's `worker` backend is deliberately NOT one of them. It is resident exactly
// like `attach` and boots the same shared handler (deploy/volucer/app.php), so it
// measures the same thing and differs only in who owns the process - two rows that
// must agree, which is a sanity check, not a measurement. It is still runnable by
// hand; only the dataset dropped it.
$validServers = ['rr', 'fpm', 'volucer-attach', 'volucer-cgi'];
// Split on comma OR whitespace: a launcher that hands an array to a string parameter
// gets a space-joined value ("rr fpm volucer"), and treating that as one unknown server
// would reject a list the operator clearly intended. Both forms mean the same thing.
$serversArg = array_values(array_filter(
    preg_split('/[,\s]+/', (string) ($opts['servers'] ?? 'rr,fpm')) ?: [],
    static fn(string $s): bool => $s !== ''
));

$unknown = array_diff($serversArg, $validServers);
if ($unknown !== []) {
    fwrite(STDERR, '[run-http] unknown server(s): ' . implode(', ', $unknown)
        . '. Valid: ' . implode(', ', $validServers) . "\n");
    exit(1);
}
if ($serversArg === []) {
    fwrite(STDERR, "[run-http] --servers selected nothing.\n");
    exit(1);
}

$servers = $serversArg;

/**
 * The report mode name for one server key.
 *
 * The report's mode vocabulary is its own — 'roadrunner' and 'php-fpm' are already
 * spelled that way in six files — so this is the ONE place that translates a CLI
 * server name into it. Both the block loop and http-bench.php call it, which is what
 * keeps the dataset's mode keys and the report's lookups from drifting.
 */
function modeNameFor(string $server): string
{
    return match ($server) {
        'rr'             => 'roadrunner',
        'fpm'            => 'php-fpm',
        // Already mode-shaped: the arm name IS the deployment model, and a reader of
        // the dataset should see `volucer-attach` rather than a second translation.
        default          => $server,
    };
}

/**
 * The probe-file kind one server's processes write.
 *
 * Must agree with the code that WRITES the sample, on both sides:
 *
 *   rr              hardcoded 'rr'           in deploy/rr/worker.php
 *   fpm             hardcoded 'fpm'          in the public/index-*.php entry scripts
 *   volucer-attach  hardcoded 'volucer'      in deploy/volucer/app.php
 *   volucer-cgi     'volucer-cgi' via BENCH_PROBE_KIND, because it re-runs the FPM
 *                   entry scripts and they must not write into FPM's files
 *
 * Only the spawn_cgi arm needs the environment variable, and it needs it because it
 * shares the entry scripts with FPM. The resident arm keeps the fixed kind 'volucer',
 * which is also what the hand-runnable `worker` backend writes — so if that one is
 * ever added back to $validServers it needs no new kind, and its samples land beside
 * attach's exactly as they should.
 */
function probeKindFor(string $server): string
{
    return match ($server) {
        'rr'             => 'rr',
        'fpm'            => 'fpm',
        'volucer-attach' => 'volucer',
        'volucer-cgi'    => 'volucer-cgi',
        default          => throw new RuntimeException("no probe kind for server '{$server}'"),
    };
}

$useRr  = in_array('rr', $servers, true);
$useFpm = in_array('fpm', $servers, true);
$volucerArms = array_values(array_filter($servers, static fn(string $s): bool => str_starts_with($s, 'volucer')));

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

// Floors-only: measure just the constant server overhead and write it on its own,
// for splicing into a dataset that was produced before a floor existed. Floors are
// the one block that is APP-INDEPENDENT (each probe is a GET / to a server with no
// framework in it), so unlike a boot or memory probe they can come from a later run.
$floorsOnly = isset($opts['floors-only']);

// ONE budget for every server. RoadRunner and FPM are two ways of running the
// same request, so the only meaningful comparison measures both with the same
// sample; an asymmetry (the historical RR 1000x10 vs FPM 300x5) makes every
// cross-model caption branch on the mismatch and leaves the reader comparing
// rows with different confidence. There is deliberately no per-server
// override: a budget knob that only one server honours is the bug, not the
// feature. assemble-real.php re-checks this across per-app invocations.
$budget = "{$itersPerRun}x{$runs}";

$root = dirname(__DIR__);

echo "=== real-deployment benchmark (run-http.php) ===\n";
echo "Root: {$root}\n";
echo "Apps: " . implode(', ', $apps) . "\n";
echo "Servers: " . implode(', ', $servers) . "\n";
echo "Iterations/run: {$itersPerRun}, Runs: {$runs}\n";
echo "Budget (all servers): {$budget}\n";
echo "Bench user (FPM pool owner): {$benchUser}\n";
echo "PHP CLI (RoadRunner worker + harness): {$phpCli}\n";
echo "PHP FPM:                              {$phpFpmBin}\n\n";

// --- Interpreter coherence ------------------------------------------------------
//
// The dataset carries ONE `env.php_version`, so the two servers must run the same
// PHP. Nothing in the harness links them: nginx talks to the packaged
// `php-fpm<X.Y>` binary while RoadRunner spawns the CLI interpreter. On
// 2026-10-19 the bench VM's default `php` was moved to 8.5 while its FPM pools
// stayed under /etc/php/8.3, and the pre-existing code — hardcoded `php-fpm8.3`,
// bare `php` for the worker — would have produced a dataset of 8.5.11 RoadRunner
// numbers beside 8.3.35 FPM numbers under a single 8.5.11 stamp.
//
// This is checked rather than assumed because a mismatch is SILENT: the run
// completes, the file looks normal, and every cross-server ratio in it compares
// two runtimes. Refusing beats guessing, so a mismatch aborts.
$cliVersion = trim((string) shellProcessOutput(escapeshellarg($phpCli) . ' -r "echo PHP_VERSION;"'));
$fpmVersion = '';
if ($useFpm) {
    // `php-fpm<X.Y> -v` prints the version on its first line; a missing binary
    // exits non-zero and is reported as an empty string here so the check below
    // says which side is unreadable instead of silently passing.
    $fpmOut = trim((string) shellProcessOutput("{$phpFpmBin} -v 2>&1"));
    if (preg_match('/PHP\s+(\d+\.\d+\.\d+)/', $fpmOut, $vm) === 1) {
        $fpmVersion = $vm[1];
    }
}

if ($cliVersion === '') {
    fwrite(STDERR, "[run-http] cannot execute the PHP CLI binary '{$phpCli}'.\n");
    exit(1);
}
if ($useFpm && $fpmVersion === '') {
    fwrite(STDERR, "[run-http] cannot read a version from the FPM binary '{$phpFpmBin}'.\n");
    exit(1);
}

// Compare MAJOR.MINOR, not the patch: php8.5-fpm and php8.5-cli are upgraded in
// lockstep by the distro, but the two packages can transiently hold different
// patch releases mid-upgrade, and a patch skew does not change what is measured.
$cliSeries = implode('.', array_slice(explode('.', $cliVersion), 0, 2));
$fpmSeries = $fpmVersion === '' ? $cliSeries : implode('.', array_slice(explode('.', $fpmVersion), 0, 2));

if ($cliSeries !== $fpmSeries) {
    fwrite(STDERR, <<<MSG
[run-http] REFUSING TO RUN: the two servers use different PHP versions.

    RoadRunner worker : {$phpCli}  -> {$cliVersion}
    nginx -> php-fpm  : {$phpFpmBin}  -> {$fpmVersion}

The dataset records one env.php_version, so this run would publish one runtime's
numbers under the other's name, and every RoadRunner-vs-FPM ratio would compare
two different engines. Pass a matching pair, e.g.:

    --php-cli=php8.5 --php-fpm=php-fpm8.5

MSG);
    exit(1);
}

echo "PHP coherence: CLI {$cliVersion} / FPM " . ($fpmVersion === '' ? '(not measured)' : $fpmVersion) . " (series {$cliSeries})\n\n";

// --- JIT, installed before anything is measured ---------------------------------
//
// `opcache.jit` is PHP_INI_SYSTEM: it cannot be set per FPM pool, so FPM needs it in
// an ini drop-in at startup. Installing it HERE rather than only in the Windows
// launcher means a direct `php scripts/run-http.php` invocation cannot skip it —
// and skipping it would not be visible, because the CLI probe below would still
// report whatever the CLI SAPI happened to have, while the FPM workers compiled
// nothing. scripts/run-remote.ps1 does the same thing before launching; the write
// is idempotent, so doing it twice costs one `cp` and one reload.
$jitInstalled = installJitIni($phpFpmBin, static fn(string $cmd): string => shellProcessOutput($cmd));
if ($jitInstalled === []) {
    echo "JIT ini:                             could not establish the PHP series; JIT not installed\n";
} else {
    shellProcessOutput('sudo -n systemctl reload-or-restart ' . escapeshellarg('php' . $cliSeries . '-fpm') . ' 2>&1');
    echo "JIT ini:                             " . implode(', ', $jitInstalled) . "\n\n";
}

// Mode name per server — must match the report views' mode filters.
$modeOf = 'modeNameFor';

// --- Results skeleton ---------------------------------------------------------

$results = [
    'env' => [
        // The version BOTH servers run, established above rather than assumed:
        // PHP_VERSION is the orchestrator's own interpreter, which is a third
        // process and need not be either server's. After the coherence check the
        // two agree in series, so the CLI one speaks for both.
        'php_version' => $cliVersion,
        'os'          => PHP_OS . ' ' . php_uname('r'),
        // NOTE: there is deliberately NO `sapi` and NO `opcache` key here.
        //
        // Both are CLI-harness fields. `sapi` would record THIS process's SAPI
        // ('cli'), which is the orchestrator — neither of the servers whose
        // numbers are in the file. And `opcache` (= opcache.enable_cli) is the
        // switch only the CLI SAPI reads; php-fpm runs as fpm-fcgi and reads
        // `opcache.enable` instead, so the field answers a question the FPM
        // rows cannot use. Stamping either produces a dataset that describes
        // the client rather than the deployment.
        //
        // The per-server truth is `opcache_by_mode` below. It used to be
        // MISSING entirely, and the renderers' `!empty($env['opcache'])`
        // turned that absence into an affirmative "OPcache (CLI): no" on every
        // real-deployment page — contradicting the same dataset's own
        // servers.php_fpm string ("with Zend OPcache v8.3.33") and the pool
        // template's `php_admin_value[opcache.enable] = 1`.
        'timestamp'  => date('c'),
        'deployment' => 'real',
        // What each server's requests actually ran under, per mode. Absent
        // keys mean "not establishable", never "off" — see deploymentOpcache().
        'opcache_by_mode' => deploymentOpcache($root, static fn(string $cmd): string => shellProcessOutput($cmd), $phpCli),
        // The JIT settings the measured deployments ran under. Stated rather than
        // assumed in EITHER direction: PHP 8.4+ ships a 64M
        // `opcache.jit_buffer_size` by DEFAULT with `opcache.jit=disable`, so
        // `php -i` shows a buffer beside a disabled engine and a run can be (and
        // once was) reported as having gained from JIT when it had none. This host
        // states neither directive, so nothing is inherited: the flags in
        // deploy/fpm/pool.conf.template and the -d list on every spawned
        // interpreter are what make it true. See deploymentJit().
        //
        // Spread as its own element: PHP has no unary `+` for arrays, so
        // `'opcache_by_mode' => ... + deploymentJit(...)` is a fatal TypeError
        // rather than a merge. A spread also means an empty result (no probe
        // possible) adds no keys at all, instead of adding nulls that a renderer
        // would read as an established value.
        ...deploymentJit(static fn(string $cmd): string => shellProcessOutput($cmd), $phpCli),
        // The FPM deployment model as actually configured. The report reads
        // this to describe what it measured instead of assuming a value.
        'fpm_max_requests' => fpmMaxRequestsFromTemplate($root),
        // The RoadRunner pool's recycle setting, from the same template that
        // generated the .rr-<app>.yaml files. The report divides the worker's
        // boot by this to turn "recycle cost" into "boot share a request
        // carries": at 0 (no limit — the value benchmarked here) the worker is
        // never recycled, so no request pays a boot at all.
        'rr_max_jobs' => rrMaxJobsFromTemplate($root),
        // Volucer's equivalent knobs, per arm. Only stamped when that arm is
        // measured, and null means "this arm does not have that setting" rather
        // than "unknown".
        //
        // Why they must be stamped rather than inferred: the report describes what
        // a request's boot contains from the POOL SETTING, and it used to read the
        // RoadRunner value for any resident arm. On a Volucer page that was a claim
        // about a knob Volucer does not read (max_jobs) to describe recycling
        // Volucer performed by a different mechanism (`max_requests`, and for
        // `attach` the supervisor's fork-per-connection). The numbers happened to
        // agree only because both were 0.
        // Only the RESIDENT arms get this stamp: on a per-request arm the question
        // ("how often is the worker recycled") has no meaning, and recording an
        // answer for it would invite a future reader to act on a fact about a
        // mechanism that arm does not use. Volucer's resident arm is the one whose
        // probe kind is 'volucer' — the same test the loop above uses.
        //
        // What it records: attach.php boots the framework ONCE in the master,
        // before the first fork; children share the built container. The supervisor
        // forks one child per connection, and volucer holds its configured `workers`
        // connections (1 here) for the life of the block, so the framework is built
        // once for the whole run.
        ...(array_filter($volucerArms, static fn(string $a): bool => probeKindFor($a) === 'volucer') === []
            ? []
            : ['volucer_recycle_by_mode' => array_fill_keys(
                array_values(array_filter($volucerArms, static fn(string $a): bool => probeKindFor($a) === 'volucer')),
                'never'
            )]),
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
        // Every entrant's version, as THIS run saw it. The real-deployment
        // dataset recorded the servers (rr/nginx/php-fpm) and the PHP version
        // but no framework version at all, so the published pages described an
        // environment in which none of the six measured things had an identity.
        // Also stamped on the CLI side (run.php) through the same helper.
        'frameworks' => frameworkVersions($root),
        'servers'    => [
            'roadrunner' => trim((string) shellProcessOutput("{$rrBinary} --version")),
            'nginx'      => trim((string) shellProcessOutput('nginx -v 2>&1')),
            'php_fpm'    => trim((string) shellProcessOutput("{$phpFpmBin} -v")),
            // Only when a volucer arm is actually measured: a stamp for a server that
            // did not run is a claim the dataset cannot support, and `--servers rr,fpm`
            // should keep producing exactly the bytes it produced before.
            //
            // The Rust binary and the PHP worker SDK are TWO builds with two versions,
            // and both matter: the SDK is the request handler, the binary schedules it.
            // Naming only one would let a future reader attribute a change in numbers to
            // the wrong half — the same trap the framework-version stamp exists for.
            ...($volucerArms === [] ? [] : [
                'volucer' => trim((string) shellProcessOutput("{$volucerBin} version 2>&1")),
                'volucer_php_worker' => volucerWorkerVersion($root),
            ]),
        ],
    ],
    'apps' => [],
];

// --- Server + seed setup -------------------------------------------------------

$rrPort    = 8983;
$fpmPort   = 8883;
// A third bank, well clear of the other two: an overlap here would put two servers on
// one port and the harness would benchmark whichever bound first (the failure
// stampPort() documents for apps).
$volucerPortBase = 8800;
$deployDir = "{$root}/temp/deploy";
$ports     = [];

// FPM pools + nginx vhosts are stamped ONCE per app up front and kept
// running; per-block the orchestrator only starts/stops RoadRunner. The
// pools never recycle (pm.max_requests=0), so one long-lived pool serves the
// whole run — no per-block start/stop is needed or wanted.
stampAllDeployConfigs($root, $deployDir, $apps, $fpmPort, $rrPort, $ports, $benchUser, $phpCli);

// Volucer configs are stamped separately (see stampVolucerConfigs) because each arm
// needs a different placeholder set.
stampVolucerConfigs($root, $deployDir, $apps, $volucerArms, $phpCli, $phpCgiBin, $volucerPortBase, $ports);

// A benchmark RoadRunner left behind by an earlier invocation would hold its
// port, silently absorb the new server's bind failure, and get benchmarked
// under the wrong app's name (2026-09-14). Clear them before the first block.
killAllBenchRoadRunners($deployDir);
// The same hazard for volucer, and worse for the attach arm: an orphaned supervisor
// still holds its relay, so the next block's volucer would connect to the PREVIOUS
// app's worker and serve that app's pages under this app's name.
killAllBenchVolucers();

foreach ($apps as $appKey) {
    $appKey = trim($appKey);

    // --floors-only measures the server, not the apps. Without this guard the flag
    // only took effect AFTER the app loop, so the run benchmarked all seven apps
    // (hours) before reaching the floors block it was asked for. That is why the
    // 2026-11-23 floor probe was produced with `--quick` instead — a 100x3 sample
    // grafted into a 1000x10 dataset, which is how Phalcon's `/echo` came to read
    // 13.9% below its own framework-free floor. See run-http.php's floors block and
    // bench-lib.php::assertRowsNotBelowFloor().
    if ($floorsOnly) {
        break;
    }

    echo "\n=== App: {$appKey}\n";

    $appResult = ['app' => $appKey, 'modes' => []];

    foreach ($servers as $server) {
        $port    = $ports[$server][$appKey];
        $baseUrl = "http://127.0.0.1:{$port}";

        // Fresh DB per block — same rationale as run.php's per-app re-seed.
        seedDatabase($root, $rows, $phpCli);

        // Generate this app's PRUNED autoloader before it is booted. The shared
        // composer.json requires all seven frameworks, so vendor/autoload.php executes
        // about 1 MB of other entrants' `files` bootstrap code on every request; the
        // pruned loader runs only this app's dependency closure. Measured through real
        // nginx + PHP-FPM: 0.283 ms -> 0.066 ms on the boot band, with the framework's
        // own work unchanged. See scripts/app-autoload.php.
        ensureAppAutoload($root, $appKey, $phpCli);

        // Warm the route cache ONCE per app, not once per (app, server) block. The
        // artifact is a file on disk and both servers read the same one, so re-warming
        // inside this loop would only rewrite an already-fresh table — and, worse, would
        // hide a real failure mode: if the cache did not survive the block transition,
        // the second server's rows would silently be measured against a rebuild.
        if ($server === $servers[0]) {
            warmRouteCaches($root, $appKey, $phpCli);
        }

        // Start the boot probe from an EMPTY file, and do it BEFORE the
        // server starts. A RoadRunner worker records its samples while
        // `rr serve` comes up (deploy/rr/worker.php records once, before the
        // request loop), so clearing the file after waitForServer() deleted
        // the very samples this block had just produced — every run reported
        // "boot probe: no samples ... missing" for rr while fpm worked
        // (2026-09-16). Clearing first also keeps a file left by an earlier
        // block from contributing samples to this one.
        //
        // THE KIND IS PER SERVER, not a two-way rr/fpm choice. It must match what the
        // arm's processes write: the FPM and RoadRunner workers hardcode their own
        // kind, the resident volucer arm writes 'volucer', and the volucer spawn_cgi
        // arm takes its kind from BENCH_PROBE_KIND in the process environment (see
        // bench_probe_kind()). Getting this wrong is silent: the second arm would read
        // the first arm's leftover samples and report them as its own.
        $bootKind = probeKindFor($server);
        $bootFile = "{$root}/temp/boot-{$bootKind}-{$appKey}.jsonl";
        @unlink($bootFile);
        // The RR worker also records its COLD process-start boot under a
        // separate kind (one sample, not the reported number) — clear it too,
        // or it accumulates across runs and confuses the next reader.
        @unlink("{$root}/temp/boot-rr-cold-{$appKey}.jsonl");
        // The memory-probe file is per kind for the same reason; clear it so a
        // previous run's samples cannot be attributed to this block.
        @unlink("{$root}/temp/mem-{$bootKind}-{$appKey}.jsonl");

        $proc = null;
        if ($server === 'rr') {
            $proc = startRoadRunner($root, $deployDir, $appKey, $rrBinary, $port);
        } elseif ($server === 'fpm') {
            $proc = ensureFpmRunning($root, $phpFpmBin, $deployDir);
        } else {
            $proc = startVolucer($root, $deployDir, $appKey, $server, $volucerBin, $phpCli, $port);
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
                runHttpBench($root, $server, $appKey, $baseUrl, $itersPerRun, $runs, $tmpJson, $flag, $phpCli);
                echo "  {$prefix} written: temp/{$prefix}-{$server}-{$appKey}.json\n";
                continue;
            }
            runHttpBench($root, $server, $appKey, $baseUrl, $itersPerRun, $runs, $tmpJson, '', $phpCli);

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
            // WHAT KIND of boot this band measured. Without it the report can only guess
            // from the field name, and once more than one resident arm exists that guess
            // has to be made from the mode string — which is exactly the coupling
            // merge-boot.php already avoids by carrying this through. 'warm_recycle'
            // means the boot is NOT inside the timed rows (resident worker);
            // 'per_request_boot' means it is.
            if (isset($modeData['boot_kind'])) {
                $appResult['boot_kind_by_mode'][$modeOf($server)] = $modeData['boot_kind'];
            }
            unlink($tmpJson);
        } catch (RuntimeException $e) {
            // The finally below tears the server down BEFORE we abort —
            // aborting inside the try skipped teardown and leaked the RR
            // worker still holding the port (2026-09-14).
            $failure = $e;
        } finally {
            // EVERY server this loop can start must be torn down here. A missing branch
            // is not a leak of one process — it is the next block silently measuring the
            // PREVIOUS app, because the survivor still holds the port and answers
            // happily. (The 2026-09-14 incident this comment block descends from was
            // exactly that, for RoadRunner.)
            if ($server === 'rr') {
                stopRoadRunner($proc);
            } elseif (str_starts_with($server, 'volucer')) {
                stopVolucer($proc);
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

// Floors-only: re-measure the constant server overhead and write JUST that block,
// so a dataset measured before a floor existed can gain one without re-timing 26
// endpoints x 7 apps. The floors are the one part of the dataset that is independent
// of the apps (each probe is one GET / to a server with no framework in it), so
// grafting them from a later run cannot mix two measurements of the same thing —
// unlike a boot or memory probe, which is per (server, app) and must come from the
// run whose latency rows it sits beside.
if ($floorsOnly) {
    echo "\n=== Floors only (webserver overhead) ===\n";
    $results['floors'] = measureFloors($root, $deployDir, $useRr, $useFpm, $rrPort, $fpmPort, $itersPerRun, $runs, $rrBinary, $phpFpmBin, $benchUser, $phpCli, $volucerArms, $volucerBin, $volucerPortBase, $phpCgiBin);

    // A floor probed at a different budget than the dataset it will be grafted into
    // is the 2026-11-23 `/echo` bug, and this is the earliest place it is visible:
    // the probe itself says what sample it ran at. merge-floors.php refuses the
    // graft if any measured row ends up below its floor, but telling the operator
    // HERE, before they run a merge, is what stops the mismatch being created.
    // `--quick` caps to 100x3, which is exactly how that probe was produced.
    $graftBudgets = budgetsByAppMode(['apps' => [], 'floors' => $results['floors']]);
    $graftBudget  = assertUniformBudget($graftBudgets);
    echo '  floor budget: ', ($graftBudget['budget'] ?? '?');
    if ($itersPerRun !== 1000 || $runs !== 10) {
        echo "  (a full run measures its floors at 1000x10; a graft from this probe must"
            . " be checked against the dataset's budget by merge-floors.php)";
    }
    echo "\n";

    writeRealResults($outPrefix, ['env' => $results['env'], 'apps' => [], 'floors' => $results['floors']]);
    // Also emit the bare floors array, which is what merge-floors.php reads: the
    // envelope above is the same shape a full run writes, so a probe can be inspected
    // exactly like a dataset.
    file_put_contents("{$outPrefix}-floors.json", json_encode($results['floors'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    echo "Floors written to: {$outPrefix}-floors.json\n";
    exit(0);
}

echo "\n=== Floors (webserver overhead, measured once per server) ===\n";
$results['floors'] = measureFloors($root, $deployDir, $useRr, $useFpm, $rrPort, $fpmPort, $itersPerRun, $runs, $rrBinary, $phpFpmBin, $benchUser, $phpCli, $volucerArms, $volucerBin, $volucerPortBase, $phpCgiBin);

// A full run produces both halves of the boot story in one pass, so they must agree
// before anything is written. See bench-lib.php::assertRowBootStampsMatchProbe().
$bootStampCheck = assertRowBootStampsMatchProbe($results);
if (!$bootStampCheck['ok']) {
    fwrite(STDERR, "Boot stamp mismatch — " . describeRowBootStampViolations($bootStampCheck));
    exit(1);
}

writeRealResults($outPrefix, $results);

echo "\nWrote: {$outPrefix}.json\n";
exit(0);