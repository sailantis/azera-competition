<?php

declare(strict_types=1);

/**
 * Deployment + server management helpers for scripts/run-http.php.
 *
 * Owns: config stamping (FPM pool / nginx vhost / volucer toml from the deploy/
 * templates), SQLite seeding, volucer process lifecycle, FPM + nginx service
 * management (non-root user with passwordless sudo on the benchmark VM —
 * distro packages), readiness polling, and the http-bench child invocation.
 */

// benchIsFloorApp()/benchAppAutoload(), used by ensureAppAutoload(). Loaded here because
// this file only DEFINES functions at load time — requiring it has no side effects, and
// it keeps the dependency visible instead of relying on run-http.php's include order.
require_once dirname(__DIR__) . '/bench-app-autoload.php';

// --- Config stamping -----------------------------------------------------------

/**
 * Canonical app order. Ports are allocated from THIS order, never from the
 * order of the current --apps list, so `--apps=cakephp` alone puts cakephp on
 * the same port it occupies in a full six-app run. Without a fixed axis an app
 * moves port between invocations, and the stale stamps of the *other* apps in
 * the persistent deploy dir keep the ports they had — two server blocks end up
 * declaring the same listen port and nginx serves whichever it loads first
 * (2026-09-15).
 *
 * Only the real apps appear here: the floor-* probes are measured on their own
 * fixed ports (9900/9901/9902) by measureFloors() and are never stamped from
 * this table.
 */
const BENCH_APP_ORDER = ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp', 'phalcon'];

// --- Interpreter selection -------------------------------------------------------

/**
 * Directory holding one PHP installation's FPM bits, e.g. `/etc/php/8.5`.
 *
 * The harness used to spell `/etc/php/8.3` and `php8.3-fpm` out in ten places, so
 * the measured PHP was a property of the SOURCE CODE rather than of the VM: a run
 * could not measure 8.5 without editing the harness. A version is therefore stated
 * once, as the `--php-fpm` binary (`php-fpm8.5`), and everything else is derived
 * from it: the pool.d directory, the systemd unit, and — through
 * phpCliForFpm() — the CLI binary the volucer worker must use.
 *
 * `/usr/sbin/php-fpm8.5` -> `8.5`. The `cgi`/`cli`/`fpm` suffixed siblings all
 * share the prefix, so the version is whatever follows `php-fpm`.
 */
function phpSeriesFromFpmBin(string $fpmBin): ?string
{
    // basename() so an absolute override path works as well as a bare name.
    if (preg_match('/php-fpm(\d+\.\d+)$/', basename($fpmBin), $m) !== 1) {
        return null;
    }
    return $m[1];
}

/**
 * The CLI interpreter that belongs to the same PHP installation as $fpmBin.
 *
 * WHY THIS MATTERS MORE THAN IT LOOKS: the two servers run DIFFERENT binaries that
 * merely happen to share a version on a well-configured host.
 *
 *   nginx -> php-fpm   is the packaged `php-fpm<X.Y>` binary (FPM SAPI)
 *   volucer worker     is a bare `php` off PATH (CLI SAPI), spawned by volucer
 *
 * Nothing links them. On 2026-10-19 the bench VM's `/etc/alternatives/php` was
 * moved to 8.5 while its FPM pools still lived under `/etc/php/8.3`, and the
 * harness — which hardcoded `php8.3-fpm` and let the worker take `php` off PATH —
 * would have written one dataset containing 8.5.11 worker numbers next to
 * 8.3.35 FPM numbers, stamped `env.php_version = 8.5.11` over both. Every
 * cross-server ratio in that file would have compared two runtimes.
 *
 * Deriving the CLI name from the requested FPM binary makes the pairing a
 * property of the RUN rather than of the machine's PATH. `php<X.Y>` is the CLI
 * package that ships beside `php-fpm<X.Y>` (php8.5-cli / php8.5-fpm), so both
 * legs then resolve to one installation.
 *
 * Returns $fpmBin's own series when it names one; otherwise falls back to the
 * interpreter RUNNING this script, which is the only other defensible answer
 * (and is what a bare-name/path override implies).
 */
function phpCliForFpm(string $fpmBin): string
{
    $series = phpSeriesFromFpmBin($fpmBin);
    if ($series === null) {
        return PHP_BINARY;
    }

    // `php8.5` beside `/usr/sbin/php-fpm8.5` is the normal case; fall back to the
    // bare name so PATH can resolve it when the binary sits elsewhere (a distro
    // may use /usr/bin, a custom build /usr/local/bin).
    $candidate = 'php' . $series;
    foreach ([$candidate, '/usr/bin/' . $candidate, '/usr/local/bin/' . $candidate] as $path) {
        if (@is_executable($path)) {
            return $path;
        }
    }

    return $candidate;
}

/**
 * Resolve an app's port from its canonical index instead of its position in
 * the current app list.
 *
 * Falls back to the next unused port for an app outside BENCH_APP_ORDER: an
 * unknown app must not silently collide with a known one, since a wrong-port
 * collision produces numbers for the WRONG APP rather than an error.
 */
function stampPort(string $appKey, string $kind, int $base, array &$ports): int
{
    $idx = array_search($appKey, BENCH_APP_ORDER, true);
    if ($idx !== false) {
        $port = $base + (int) $idx;
    } else {
        // Unknown app: hand out the next port not already claimed in this map.
        $port = $base;
        while (in_array($port, array_values($ports[$kind] ?? []), true)) {
            $port++;
        }
    }

    // Refuse to hand the same port to two apps in one invocation.
    if (in_array($port, array_values($ports[$kind] ?? []), true)) {
        fwrite(STDERR, "[run-http] port collision: {$appKey} would share {$kind} port {$port} with "
            . (array_search($port, $ports[$kind], true) ?: '?') . " — refusing to benchmark a wrong server.\n");
        exit(1);
    }

    $ports[$kind][$appKey] = $port;

    return $port;
}

/**
 * Stamp every per-app config the benchmark needs, once, into $deployDir:
 *   bench-<app>.conf  (FPM pool, from deploy/fpm/pool.conf.template)
 *   bench-<app>.nginx (nginx vhost, from deploy/fpm/vhost.conf.template)
 * Returns the port map: ['fpm' => [app => port]].
 */
function stampAllDeployConfigs(
    string $root,
    string $deployDir,
    array $apps,
    int $fpmPortBase,
    array &$ports,
    string $benchUser,
    string $phpCli = '',
): void {
    if (!is_dir($deployDir)) {
        mkdir($deployDir, 0777, true);
    }

    // Empty means "whatever this orchestrator is", which is the pre-existing
    // behaviour for callers that do not care. run-http.php always passes the
    // resolved pairing so the two servers cannot drift apart.
    if ($phpCli === '') {
        $phpCli = PHP_BINARY;
    }

    $poolTpl    = (string) file_get_contents("{$root}/deploy/fpm/pool.conf.template");
    $vhostTpl   = (string) file_get_contents("{$root}/deploy/fpm/vhost.conf.template");

    foreach ($apps as $appKey) {
        $appKey = trim($appKey);

        // Ports used to be derived from the position in THIS app list
        // ($portBase + i), but the deploy dir persists across invocations: a
        // later single-app run (--apps=cakephp) restamped bench-cakephp with
        // the BASE port while bench-azera from an earlier multi-app run still
        // claimed the same port. Both pools/vhosts get installed, nginx binds
        // the port to whichever server block it loads first, and the app being
        // benchmarked then receives ANOTHER app's responses over it
        // (2026-09-15: the fingerprint guard caught cakephp being served
        // azera's /features/config body from port 8883). The stale-stamp sweep
        // below cannot see this: it only removes apps absent from $apps, and
        // both are present as files — only the PORTS collide. stampPort()
        // therefore derives the port from BENCH_APP_ORDER, not from this list.
        $fpmPort = stampPort($appKey, 'fpm', $fpmPortBase, $ports);

        file_put_contents("{$deployDir}/bench-{$appKey}.conf", str_replace(
            ['{{APP}}', '{{PORT}}', '{{ROOT}}', '{{USER}}'],
            [$appKey, (string) $fpmPort, $root, $benchUser],
            $poolTpl
        ));

        file_put_contents("{$deployDir}/bench-{$appKey}.nginx", str_replace(
            ['{{APP}}', '{{PORT}}', '{{ROOT}}'],
            [$appKey, (string) $fpmPort, $root],
            $vhostTpl
        ));
    }

    echo "Deploy configs stamped into {$deployDir}\n";

    // Remove STALE stamps from previous runs: ensureFpmRunning() installs
    // every bench-*.conf/.nginx in $deployDir — leftovers from runs with
    // other app lists (or older stamping bugs, e.g. empty user) would be
    // reinstalled and can poison the FPM reload for the whole run.
    foreach (glob("{$deployDir}/bench-*.conf") ?: [] as $stale) {
        $app = basename($stale, '.conf');
        $app = preg_replace('/^bench-/', '', $app);
        if (!in_array($app, $apps, true)) {
            @unlink($stale);
        }
    }
    foreach (glob("{$deployDir}/bench-*.nginx") ?: [] as $stale) {
        $app = basename($stale, '.nginx');
        $app = preg_replace('/^bench-/', '', $app);
        if (!in_array($app, $apps, true)) {
            @unlink($stale);
        }
    }
}



/**
 * Stamp one volucer config per (arm, app) into $deployDir.
 *
 * Separate from stampAllDeployConfigs() because the placeholder set is volucer-specific --
 * the relay address and the worker SDK path -- and a single str_replace list shared with
 * the nginx/FPM templates would have to carry every placeholder for every template,
 * silently leaving any name it did not know about in the rendered file. Volucer rejects
 * an unknown key, so that failure would at least be loud; it would also be at block
 * time rather than here.
 *
 * @param list<string> $arms
 */
function stampVolucerConfigs(
    string $root,
    string $deployDir,
    array $apps,
    array $arms,
    string $phpCli,
    int $volucerPortBase,
    array &$ports,
): void {
    if ($arms === []) {
        return;
    }

    $sdk = "{$root}/vendor/sailantis/volucer-worker";

    // One PORT BANK per arm. stampPort() keys its collision check on the kind, so two
    // arms sharing a base each start at the same port and the second server silently
    // binds a port the first one freed — which is how two arms once ended up on 8800 in
    // the same run. The offset is per arm, so the banks stay disjoint however many arms
    // are selected. The retired `volucer` (worker) arm keeps offset 0 reserved so
    // re-enabling it cannot collide.
    $armOffset = ['volucer-attach' => 10];

    foreach ($arms as $arm) {
        $template = match ($arm) {
            'volucer-attach' => "{$root}/deploy/volucer/attach.template.toml",
            default          => throw new RuntimeException("unknown volucer arm '{$arm}'"),
        };
        $tpl = (string) file_get_contents($template);

        foreach ($apps as $appKey) {
            $appKey = trim($appKey);
            $port   = stampPort($appKey, $arm, $volucerPortBase + $armOffset[$arm], $ports);
            $relay  = volucerRelayAddress($appKey);

            $rendered = str_replace(
                ['{{APP}}', '{{PORT}}', '{{ROOT}}', '{{SDK}}', '{{PHP_CLI}}', '{{RELAY_ADDRESS}}', '{{PROBE_KIND}}'],
                [$appKey, (string) $port, $root, $sdk, $phpCli, $relay, $arm],
                $tpl
            );

            // A surviving placeholder means this arm's template grew a name the list
            // above does not know: volucer would reject the key, or silently use a
            // literal "{{...}}" as a path. Both are worse than refusing here.
            if (preg_match('/\{\{[A-Z_]+\}\}/', $rendered, $m) === 1) {
                throw new RuntimeException(
                    "stampVolucerConfigs(): '{$m[0]}' was not substituted in {$template} — "
                    . 'add it to the str_replace lists so the arm cannot start with a literal placeholder'
                );
            }

            file_put_contents("{$deployDir}/.volucer-{$arm}-{$appKey}.toml", $rendered);
        }
    }
}

// --- SQLite seeding --------------------------------------------------------------

function seedDatabase(string $root, int $rows, string $phpCli = ''): void
{
    echo "  seeding SQLite ({$rows} rows)...\n";
    // The seed runs under the PINNED interpreter, not a bare `php`. Every other
    // deploy step does the same: the rows are written once and read by both
    // servers, so compiling the write path with a different runtime than the one
    // measured is a difference nothing in the dataset records.
    shellRun(sprintf(
        'cd %s && %s seed.php --rows=%d',
        escapeshellarg($root),
        $phpCli !== '' ? escapeshellarg($phpCli) : 'php',
        $rows
    ), 'seed.php');
}

/**
 * Warm every route cache the deploy is expected to warm, before the server starts.
 *
 * The point is FAIRNESS, not speed. A production deploy of any of these applications
 * runs one command that compiles its route table, and every server then serves from
 * that artifact; Azera (since its 0.4.0 cache), Symfony (its matcher is a disk cache)
 * and Laravel (`route:cache`) all have one — CodeIgniter and CakePHP have no persisted
 * route table at all and build from config on every request, which is a property of
 * those frameworks and is reported as such.
 *
 * Without this step the comparison measures WHO HAS BEEN WARMED rather than whose
 * router is faster: the runner that happens to have a cache file in its tree would be
 * credited with a routing win that is really a deploy-step difference.
 *
 * Each entry is `[app, command]`; an app with no route-cache command is simply absent,
 * so adding a framework here is one line rather than a branch. A command that fails is
 * reported and the block continues: a cold table is a legitimate (if slower) state, and
 * the benchmark must not abort because an optional optimisation did not run.
 */
function warmRouteCaches(string $root, string $appKey, string $phpCli = ''): void
{
    // The warmers run under the PINNED interpreter: they compile an artifact both
    // servers then require, so building it with a different runtime than the one
    // measured would put a compiler version into a file the dataset describes as
    // the measured build.
    $php = $phpCli !== '' ? escapeshellarg($phpCli) : 'php';

    // The azera route cache is warmed by the app itself: declaring the routes inside
    // `cached()` means the first read writes the table, so one CLI bootstrap compiles it
    // exactly as `azera routes:cache` would. Doing it here keeps the cost OUT of the
    // timed rows — which is where a deploy hook pays it.
    $commands = [
        'azera'   => "{$php} scripts/warm-azera-routes.php",
        // `route:cache` needs an artisan entry point, and apps/laravel has none: the bench
        // app is booted by a plain bootstrap file, not a console kernel. The equivalent is
        // the same three steps the command performs — boot, prepareForSerialization(),
        // write the compiled table — so a cache file appears exactly where
        // Application::routesAreCached() looks for it.
        'laravel' => "{$php} scripts/warm-laravel-routes.php",
    ];

    if (!isset($commands[$appKey])) {
        return;
    }

    echo "  warming route cache ({$appKey})...\n";
    $out = trim(shellProcessOutput(
        'cd ' . escapeshellarg($root) . ' && ' . $commands[$appKey] . ' 2>&1'
    ));
    if ($out !== '') {
        foreach (explode("\n", $out) as $line) {
            echo '    ', trim($line), "\n";
        }
    }
}

/**
 * Block until nothing is listening on 127.0.0.1:$port. Returns false if the
 * port is still occupied after the timeout (a stale server would silently
 * serve the benchmark).
 */
function waitForPortFree(int $port, float $timeoutSec = 10.0): bool
{
    $deadline = microtime(true) + $timeoutSec;
    while (microtime(true) < $deadline) {
        $out = [];
        exec('ss -ltn 2>/dev/null | grep -c ' . escapeshellarg(":{$port} "), $out, $rc);
        if ((int) ($out[0] ?? 0) === 0) {
            return true;
        }
        usleep(200_000);
    }

    return false;
}

// --- Volucer ----------------------------------------------------------------------
//
// Two arms, and they are two DEPLOYMENT MODELS rather than two spellings of one:
//
//   volucer-attach    `attach` backend. deploy/volucer/attach.php is the SUPERVISOR:
//                     it LISTENS on a unix relay and forks a child per connection,
//                     and volucer DIALS it. The framework boots once in the master,
//                     before the first fork, so no child pays it. volucer MUST start
//                     after the supervisor, and its `workers` must match what the
//                     supervisor can serve.
// volucer's `worker` backend (volucer SPAWNS deploy/volucer/worker.php) is not part of
// the dataset: it is resident like `attach`, shares the same handler (app.php) and the
// same probe kind, so it measures the same thing and differs only in who owns the
// process. deploy/volucer/pool.template.toml and worker.php are kept runnable for spot
// checks, but stampVolucerConfigs() no longer renders them and --servers rejects the
// name, so a dataset cannot silently gain a duplicate row.

/**
 * The unix relay PATH (no scheme) for one app.
 *
 * Kept short on purpose: `sun_path` is 108 bytes on Linux, and a long checkout path plus
 * an app name can exceed it. It lives under /run because the socket is runtime state,
 * not part of the deployment.
 *
 * Callers must decide whether they need the bare path or the `unix:`-prefixed address:
 * the CONFIG file wants `unix:<path>`, while Supervisor::serve() resolves and validates
 * an ADDRESS and rejects a bare path ("a relay is either "unix:/path" or "host:port"").
 * Handing the wrong one to either side is a startup failure, so the two are named
 * separately below rather than left to the caller to remember.
 */
function volucerRelayPath(string $appKey): string
{
    return "/run/volucer-bench/{$appKey}.sock";
}

/**
 * The relay ADDRESS volucer's config and Supervisor::serve() both speak.
 */
function volucerRelayAddress(string $appKey): string
{
    return 'unix:' . volucerRelayPath($appKey);
}

/**
 * Kill every benchmark volucer and attach supervisor (any app) before a run.
 *
 * The same discipline as any other served arm: an orphan holds its HTTP port and
 * its relay, and the next block would either fail to bind or — worse, for `attach` —
 * connect to the PREVIOUS app's supervisor and get that app's pages under this app's
 * name.
 */
function killAllBenchVolucers(): void
{
    $killed = false;
    foreach (
        [
            // Only OUR configs: an operator's own volatile deployment must survive.
            'volucer serve -c ' . dirname(__DIR__) . '/temp/deploy/.volucer-',
            'deploy/volucer/attach.php',
        ] as $pattern
    ) {
        exec('pkill -f ' . escapeshellarg($pattern) . ' 2>/dev/null', $o, $rc);
        $killed = $killed || $rc === 0;
    }
    if ($killed) {
        usleep(400_000);
        echo "  killed stale benchmark volucer process(es) before starting\n";
    }
}

/**
 * Start volucer (or its prefork supervisor) for one app.
 *
 * $arm is 'volucer-attach' (the attach backend). The config
 * must already have been stamped for ($arm, $appKey) — see stampVolucerConfigs().
 *
 * Returns the same shape the block loop expects, plus 'supervisor' for the attach arm,
 * so the block loop and stopVolucer() can treat every volucer arm alike.
 *
 * @return array{proc: resource, pipes: array, supervisor: mixed}
 */
function startVolucer(
    string $root,
    string $deployDir,
    string $appKey,
    string $arm,
    string $volucerBinary,
    string $phpCli,
    int $port,
): array {
    if (is_dir($root)) {
        chdir($root);
    }

    $cfg = "{$deployDir}/.volucer-{$arm}-{$appKey}.toml";
    if (!is_file($cfg)) {
        fwrite(STDERR, "[run-http] missing volucer config {$cfg} — stamping did not run for {$appKey}\n");
        exit(1);
    }

    if (!waitForPortFree($port)) {
        fwrite(STDERR, "[run-http] Port {$port} is still in use before starting volucer ({$arm}/{$appKey}) — refusing to benchmark a stale server.\n");
        exit(1);
    }

    // --- attach: the SUPERVISOR must be listening first ----------------------
    //
    // Volucer is the client on this backend: it dials the relay `workers` times and
    // fails the whole pool if nobody is listening ("every connection to this pool will
    // fail at startup"). So the supervisor starts here and volucer starts after it.
    $supervisor = null;
    if ($arm === 'volucer-attach') {
        $relay = volucerRelayAddress($appKey);
        @unlink(volucerRelayPath($appKey));
        $sup = startVolucerSupervisor($root, $deployDir, $appKey, volucerRelayAddress($appKey), $phpCli);
        // Wait on the PATH: file_exists() on "unix:/run/..." is always false, which read
        // as "the supervisor never started" while it was in fact listening.
        if (!waitForRelay(volucerRelayPath($appKey))) {
            stopVolucer(['proc' => $sup['proc'], 'pipes' => $sup['pipes'], 'supervisor' => null]);
            fwrite(STDERR, "[run-http] the attach supervisor never created {$relay}" . workerLogTail($sup['log']));
            exit(1);
        }
        $supervisor = $sup;
        echo "  attach supervisor listening on {$relay}\n";
    }

    echo "  starting volucer ({$arm}/{$appKey}, port {$port})...\n";

    // `exec` so the pid proc_open returns IS the server holding the port, not a shell
    // wrapper — the bug that made the pid a wrapper's rather than the server's.
    $cmd     = sprintf('exec %s serve -c %s 2>&1', escapeshellarg($volucerBinary), escapeshellarg($cfg));
    $logPath = "{$deployDir}/volucer-{$arm}-{$appKey}.log";
    $pipes   = [];
    $logFp   = fopen($logPath, 'w');
    $descr   = $logFp === false
        ? [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']]
        : [['pipe', 'r'], $logFp, $logFp];

    $proc = proc_open($cmd, $descr, $pipes);
    if (!is_resource($proc)) {
        if ($supervisor !== null) {
            stopVolucer(['proc' => $supervisor['proc'], 'pipes' => $supervisor['pipes'], 'supervisor' => null]);
        }
        fwrite(STDERR, "[run-http] Failed to start volucer for {$appKey}\n");
        exit(1);
    }

    if ($logFp === false) {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }

    return [
        'proc'       => $proc,
        'pipes'      => $pipes,
        'deployDir'  => $deployDir,
        'appKey'     => $appKey,
        'arm'        => $arm,
        'log'        => $logPath,
        'supervisor' => $supervisor,
    ];
}

/**
 * Start deploy/volucer/attach.php — the prefork supervisor the attach backend dials.
 *
 * The relay is passed through BOTH argv[1] and VOLUCER_RELAY_SOCKET: attach.php prefers
 * the argument, and volucer reads the variable when its config omits `relay`. Writing
 * both means a stamping mistake surfaces as a mismatch rather than as two processes
 * quietly using different sockets.
 *
 * @return array{proc: resource, pipes: array, log: string}
 */
function startVolucerSupervisor(string $root, string $deployDir, string $appKey, string $relay, string $phpCli): array
{
    @mkdir(dirname($relay), 0777, true);

    // A floor key has no framework to boot, and app.php exits on an unknown
    // BENCH_APP by design. So the floor gets its own supervisor — see
    // deploy/volucer/attach-floor.php for why that is a separate file rather than a
    // "no framework" branch inside the worker every measured request pays for.
    $isFloor = str_starts_with($appKey, 'floor-');
    $script  = $isFloor ? "{$root}/deploy/volucer/attach-floor.php" : "{$root}/deploy/volucer/attach.php";

    $cmd = sprintf(
        'exec env %sVOLUCER_RELAY_SOCKET=%s %s -d opcache.enable_cli=1 '
            . '-d opcache.validate_timestamps=0 -d opcache.jit=tracing '
            . '-d opcache.jit_buffer_size=64M %s 2>&1',
        $isFloor ? '' : ('BENCH_APP=' . escapeshellarg($appKey) . ' '),
        escapeshellarg($relay),
        escapeshellarg($phpCli),
        escapeshellarg($script),
    );

    $logPath = "{$deployDir}/volucer-attach-{$appKey}.supervisor.log";
    $pipes   = [];
    $logFp   = fopen($logPath, 'w');
    $descr   = $logFp === false
        ? [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']]
        : [['pipe', 'r'], $logFp, $logFp];

    $proc = proc_open($cmd, $descr, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "[run-http] Failed to start the volucer attach supervisor for {$appKey}\n");
        exit(1);
    }
    if ($logFp === false) {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }

    return ['proc' => $proc, 'pipes' => $pipes, 'log' => $logPath];
}

/**
 * Block until the relay socket exists, i.e. the supervisor is accepting connections.
 */
function waitForRelay(string $relay, float $timeoutSec = 15.0): bool
{
    $deadline = microtime(true) + $timeoutSec;
    while (microtime(true) < $deadline) {
        clearstatcache(true, $relay);
        if (file_exists($relay)) {
            return true;
        }
        usleep(50_000);
    }

    return false;
}

/**
 * Stop volucer and, for the attach arm, its supervisor.
 *
 * Order matters: volucer goes first so its connection closes cleanly before the
 * supervisor's listener disappears underneath it. Both are killed by process GROUP
 * because volucer forks (its Go runtime) and the supervisor forks a child per
 * connection — SIGTERM to the pid alone leaves those behind, holding the port.
 */
function stopVolucer(array $procRef): void
{
    foreach (['proc' => $procRef['proc'] ?? null, 'supervisor' => $procRef['supervisor']['proc'] ?? null] as $role => $proc) {
        if (!is_resource($proc)) {
            continue;
        }

        $pid = (int) (proc_get_status($proc)['pid'] ?? 0);
        if ($pid > 0) {
            @posix_kill(-$pid, 15);
            @posix_kill($pid, 15);
            for ($i = 0; $i < 30; $i++) {
                if (!proc_get_status($proc)['running']) {
                    break;
                }
                usleep(100_000);
            }
            if (proc_get_status($proc)['running']) {
                @posix_kill(-$pid, 9);
                @posix_kill($pid, 9);
                usleep(100_000);
            }
        }
        proc_close($proc);
    }

    // Hammer survivors: a volucer that ignored SIGTERM keeps its port bound, and the
    // next block would silently measure the previous app.
    if (isset($procRef['deployDir'], $procRef['appKey'])) {
        exec('pkill -f ' . escapeshellarg('volucer serve -c ' . $procRef['deployDir'] . '/.volucer-') . ' 2>/dev/null');
        exec('pkill -f ' . escapeshellarg("deploy/volucer/attach.php") . ' 2>/dev/null');
    }

    if (isset($procRef['appKey'])) {
        @unlink(volucerRelayPath($procRef['appKey']));
    }

    echo "  volucer stopped.\n";
}

// --- FPM + nginx (system services, root assumed) ------------------------------------

/**
 * The pm.max_requests the stamped pools carry, read from the template that is
 * the single source of truth. Recorded in the dataset so the report can
 * describe the deployment model it ACTUALLY measured: the same real-FPM view
 * means something different at 0 (worker persists, app boots per request —
 * what production does) than at 1 (worker destroyed per request — CGI
 * behaviour, and a process spawn on every row, ~9 ms on the bench VM).
 *
 * Without this stamp the two configurations produce datasets that are
 * indistinguishable on disk, and a config change silently re-labels
 * already-published numbers.
 */
function fpmMaxRequestsFromTemplate(string $root): ?int
{
    $tpl = @file_get_contents("{$root}/deploy/fpm/pool.conf.template");
    if ($tpl === false) {
        return null;
    }
    return preg_match('/^\s*pm\.max_requests\s*=\s*(\d+)/m', $tpl, $m) ? (int) $m[1] : null;
}



/**
 * The OPcache setting a REAL deployment's two servers actually run under, as
 * a `{mode => bool}` map — keys absent when the answer is not knowable.
 *
 * WHY THIS IS NOT THE CLI HARNESS'S `env.opcache`: that field is
 * `ini_get('opcache.enable_cli')` read by run.php under `PHP_SAPI === 'cli'`,
 * and CLI is the ONE SAPI where `opcache.enable_cli` is the relevant switch —
 * OPcache is on by default everywhere EXCEPT cli, where it is opt-in, so the
 * CLI harness must ask the CLI-specific question. A real deployment has TWO
 * servers reading TWO DIFFERENT directives:
 *
 *   nginx -> php-fpm   runs as `fpm-fcgi`, so it reads `opcache.enable` (1 by
 *                      default) and NEVER reads `opcache.enable_cli`.
 *   volucer worker     runs `deploy/volucer/app.php` under the CLI SAPI,
 *                      so it reads `opcache.enable_cli` (0 by default).
 *
 * So "OPcache (CLI)" is not merely an odd label on an FPM page — it is a
 * question that server cannot answer. run-http.php stamped no `opcache` key at
 * all, and the renderers' `!empty($env['opcache'])` turned that ABSENCE into
 * an affirmative "no". The published real-deployment pages therefore claimed
 * OPcache was disabled while the same dataset's own `servers.php_fpm` string
 * said "with Zend OPcache v8.3.33" and the pool template set
 * `php_admin_value[opcache.enable] = 1`.
 *
 * Each half is answered the way it can actually be answered:
 *
 *   php-fpm — read from the pool TEMPLATE. run-http.php copies that file into
 *             pool.d and reloads on every invocation, so the config that was
 *             installed is the config that ran. Same discipline as
 *             fpmMaxRequestsFromTemplate(): a template edit cannot silently
 *             re-label an already-published number.
 *   volucer — PROBED, with the same binary and the same `-d` overrides.
 *             `startVolucerSupervisor()` launches `deploy/volucer/attach.php``n *             with the interpreter run-http.php resolved for this run, and that
 *             command carries the same OPcache/JIT flags the pool sets for FPM --
 *             including `opcache.enable_cli=1`, because CLI is the one SAPI where
 *             that is the switch that matters. Before
 *             those flags were added the worker inherited the host php.ini alone,
 *             which on this VM means `opcache.jit=disable` beside a defaulted
 *             64M buffer: a run could be described as JIT-enabled and have
 *             compiled nothing. The probe below therefore carries the SAME flags,
 *             so it reads what the worker reads rather than what a bare `php`
 *             would have read.
 *
 *             The probe asks $phpCli, NOT a bare `php`. Those were the same binary
 *             only while PATH happened to point at the version the template
 *             hardcoded; once the VM's default `php` moved to 8.5 while the
 *             worker was pinned elsewhere, a bare-name probe would have recorded
 *             one installation's setting as another's.
 *
 * A mode whose answer cannot be established is OMITTED, never defaulted to
 * false: "not recorded" and "off" are different claims, and collapsing them is
 * the exact bug this replaces.
 *
 * @param callable(string):string $runProcess launches a command and returns
 *                                        its stdout; injected so tests can
 *                                        drive this without a VM.
 * @return array<string,bool>
 */
function deploymentOpcache(string $root, ?callable $runProcess = null, string $phpCli = ''): array
{
    $out = [];

    // The flags the attach command carries, kept in ONE place so the probe and the
    // spawned processes cannot drift. See startVolucerSupervisor() for the copy that
    // is baked into the generated command; a change here must be mirrored there.
    $flags = '-d opcache.enable_cli=1 -d opcache.validate_timestamps=0 '
        . '-d opcache.jit=tracing -d opcache.jit_buffer_size=64M';

    // --- php-fpm: the directive fpm-fcgi actually reads ----------------------
    $pool = @file_get_contents("{$root}/deploy/fpm/pool.conf.template");
    if ($pool !== false) {
        // php_admin_value[opcache.enable] = 1
        // NOTE the sibling `opcache.enable_cli` in the same file is NOT read
        // here: fpm ignores it, so it says nothing about this server.
        if (preg_match('/^\s*php_admin_value\[opcache\.enable\]\s*=\s*(\S+)/m', $pool, $m) === 1) {
            $out['php-fpm'] = iniTruthy($m[1]);
        }
    }

    // --- volucer worker: the CLI SAPI's own switch, as it sees it ------------
    if ($runProcess !== null) {
        // `-n` would SKIP php.ini and answer a different question; the worker
        // loads php.ini, so the probe must too. And it carries the SAME `-d`
        // OPcache/JIT flags the RR command carries: probing a bare interpreter
        // while the worker runs with `opcache.enable_cli=1 -d opcache.jit=tracing`
        // would record the host's defaults as the worker's configuration.
        $cli   = $phpCli !== '' ? escapeshellarg($phpCli) : 'php';
        $probe = "{$cli} {$flags} -r \"echo (int) ini_get('opcache.enable_cli');\"";
        $val   = trim($runProcess($probe));
        if ($val === '0' || $val === '1') {
            $out['volucer-attach'] = $val === '1';
        }
    }

    return $out;
}

/**
 * The JIT ini drop-in, as content. ONE definition, used by the installer below and
 * by the tests that assert the settings.
 *
 * WHY A DROP-IN AND NOT `php_admin_value` IN THE POOL. `opcache.jit` is a
 * PHP_INI_SYSTEM directive: it can only be changed at startup. FPM therefore
 * ACCEPTS `php_admin_value[opcache.jit]` without logging an error and the worker
 * still reports `disable` — verified 2026-11-11 by serving a request through the
 * pool and reading `opcache_get_status()`. The sibling `opcache.jit_buffer_size`
 * behaves the same way, so both have to live in the ini.
 *
 * Both lines are required. A mode with a 0-byte buffer selects a strategy for a
 * buffer that does not exist and compiles nothing; PHP 8.4+ ships 64M as the
 * default, which is exactly what makes "there is a buffer" read as "the JIT is
 * on".
 *
 * `tracing` is PHP's recommended mode and the one the sibling volucer-competition
 * matrix uses, so a number from either repo is described the same way. It compiles
 * HOT paths, which means it needs the counters to warm up: a cold-mode row that
 * boots once and serves one request will show almost no JIT benefit, and that is a
 * property of tracing, not evidence against the JIT.
 */
function jitIniContent(): string
{
    return "; Generated by deploy-lib.php. Installed as /etc/php/<series>/<sapi>/conf.d/99-jit.ini\n"
        . "; opcache.jit is PHP_INI_SYSTEM: it cannot be set per FPM pool, so it is\n"
        . "; stated here, at startup, for every SAPI the benchmark runs.\n"
        . "opcache.jit=tracing\n"
        . "opcache.jit_buffer_size=64M\n";
}

/**
 * Install the JIT drop-in for every SAPI that serves a measured request.
 *
 * The three SAPIs matter separately and none inherits from another:
 *
 *   fpm   nginx + PHP-FPM (mode php-fpm)
 *   cli   the attach supervisor (mode volucer-attach), which ALSO receives the pair
 *         as `-d` flags — the ini makes both routes agree rather than leaving one
 *         to a flag list
 *
 * Returns the list of installed paths (or [] when the series cannot be derived, so
 * a caller can report "not installed" instead of assuming success). Idempotent:
 * rewriting identical content and reloading is harmless.
 *
 * @return list<string>
 */
function installJitIni(string $fpmBin, ?callable $runProcess = null): array
{
    if ($runProcess === null) {
        return [];
    }

    $series = phpSeriesFromFpmBin($fpmBin);
    if ($series === null) {
        return [];
    }

    $content = jitIniContent();
    $staged  = sys_get_temp_dir() . '/bench-99-jit.ini';
    if (@file_put_contents($staged, $content) === false) {
        return [];
    }

    $installed = [];
    foreach (['fpm', 'cgi', 'cli'] as $sapi) {
        $dest = "/etc/php/{$series}/{$sapi}/conf.d/99-jit.ini";
        $runProcess(sprintf('sudo -n cp %s %s', escapeshellarg($staged), escapeshellarg($dest)));
        $installed[] = $dest;
    }
    @unlink($staged);

    return $installed;
}

/**
 * The JIT settings the measured deployments actually ran under.
 *
 * WHY THIS IS RECORDED AT ALL: because "JIT is on" and "JIT is off" are both
 * claims a reader would otherwise have to assume, and the assumption goes wrong in
 * this exact direction. PHP 8.4+ ships `opcache.jit_buffer_size=64M` by DEFAULT
 * while leaving `opcache.jit=disable`, and `-i` prints that 64M buffer next to a
 * disabled engine, so "there is a JIT buffer" reads as "JIT is on" to anyone not
 * reading both lines. The 2026-11-10 run was reported as having gained from JIT;
 * it had not, and nothing in that dataset could disprove it. Recording the pair
 * is what makes either answer checkable.
 *
 * The values are read WITH THE SAME `-d` FLAGS the measured processes carry, not
 * from the bare interpreter. That distinction is the whole test: this VM's php.ini
 * states no `opcache.jit` at all, so a bare `php -r 'ini_get(...)'` answers
 * `disable` — while every arm in the run is started with
 * `-d opcache.jit=tracing -d opcache.jit_buffer_size=64M` and compiles for real.
 * Probing the bare interpreter would stamp a dataset that describes the host's
 * defaults as the run's configuration, which is the same class of error as the
 * absent-`opcache`-key bug above: a fact about the machine published as a fact
 * about the measurement.
 *
 * Read from `$phpCli`, which is the interpreter run-http.php resolved for this
 * run and the same one probed for OPcache above — the two answers must
 * describe one installation. Absent keys mean "not establishable", never "off"
 * (see deploymentOpcache): a host without the opcache extension cannot answer,
 * and a dataset claiming JIT was off there would be inventing a measurement.
 *
 * ONE PAIR DESCRIBES EVERY ARM, which is why a single global value is honest
 * rather than a per-mode map like `opcache_by_mode`. The FPM pool states the pair
 * via `php_admin_value`, and the attach supervisor receives the identical pair as
 * `-d` flags, so both servers compile under the same configuration. If an arm's
 * JIT settings ever diverge,
 * this function must return a per-mode map instead — stamping one value would
 * then describe one arm and misdescribe the rest.
 *
 * @return array<string,string|bool>
 */
function deploymentJit(?callable $runProcess = null, string $phpCli = ''): array
{
    if ($runProcess === null) {
        return [];
    }

    // Keep in step with the flags in deploymentOpcache() and startVolucerSupervisor().
    $cli   = $phpCli !== '' ? escapeshellarg($phpCli) : 'php';
    $flags = '-d opcache.enable_cli=1 -d opcache.validate_timestamps=0 '
        . '-d opcache.jit=tracing -d opcache.jit_buffer_size=64M';
    $probe = "{$cli} {$flags} -r \"echo ini_get('opcache.jit'), '/', ini_get('opcache.jit_buffer_size');\"";
    $raw   = trim((string) $runProcess($probe));

    $parts = explode('/', $raw, 2);
    if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
        return [];
    }

    $mode   = trim($parts[0]);
    $buffer = trim($parts[1]);

    // The mode/buffer rule lives in ONE place (see jitConfigurationFrom), so a
    // dataset stamped by the deployment harness and one stamped by the
    // view-engine harness cannot disagree about what "enabled" means.
    return jitConfigurationFrom($mode, $buffer, null);
}

/**
 * The JIT facts a dataset carries, from a mode, a buffer size and an optional
 * runtime answer.
 *
 * TWO CALLERS, ONE RULE, and that is the point: `deploymentJit()` reads the pair
 * from the interpreter the benchmarked servers run, and the view-engine harness
 * reads it from a child spawned with the same flags its measured children get.
 * Both must decide "enabled" identically, or two pages can describe one
 * configuration two ways — the drift this repository has paid for repeatedly.
 *
 * `$runtimeEnabled` is the JIT's own answer (`opcache_get_status()['jit']`).
 * null means the caller could not obtain one, which is NOT "off": the key is
 * then decided from the configuration alone, exactly as it was before the
 * runtime probe existed. A `false` is a real observation and overrides the
 * configuration-derived guess, because a mode and a buffer that look correct
 * while the JIT never started is precisely the case worth publishing.
 *
 * `disable` / `off` / `0` (and the trace modes `tracing`, `function`, `1205`,
 * ...) are reported VERBATIM rather than normalised to a boolean: a trace mode
 * names WHICH compilation is in play, and collapsing it would discard the only
 * distinguishing information there is.
 *
 * @return array<string,string|bool>
 */
function jitConfigurationFrom(string $mode, string $buffer, ?bool $runtimeEnabled): array
{
    $modeOn = !in_array(strtolower($mode), ['', 'disable', 'off', '0'], true);

    // A MODE IS NOT AN ENGINE. `opcache.jit=tracing` with
    // `opcache.jit_buffer_size=0` compiles NOTHING — the mode selects a strategy
    // for a buffer that does not exist. This is not hypothetical: it is the
    // host's own state while the VM has the mirror image (`disable` + `64M`).
    // Reporting either half alone gives the wrong answer in one direction or the
    // other, so "enabled" requires BOTH and the pair is always shown.
    $bufferOn = !in_array(strtolower($buffer), ['', '0'], true);

    return [
        'opcache_jit'             => $mode,
        'opcache_jit_buffer_size' => $buffer,
        'opcache_jit_enabled'     => $runtimeEnabled ?? ($modeOn && $bufferOn),
    ];
}

/**
 * Interpret a php.ini value the way PHP does, for the handful of literals that
 * actually appear in the templates (`1`/`On`/`true` vs `0`/`Off`/`false`).
 *
 * Deliberately narrow: this reads a config FILE, and it must never turn an
 * unrecognised token into `false` — "we do not know" and "off" are different
 * answers, and conflating them is the exact bug this whole change is fixing.
 * An unparseable value returns false here only because the caller has already
 * decided the key exists; callers that cannot parse omit the key instead.
 */
function iniTruthy(string $value): bool
{
    $v = strtolower(trim($value, " \t\"'"));

    return in_array($v, ['1', 'on', 'yes', 'true'], true);
}

/**
 * Install + start the stamped FPM pool and nginx vhost for all benchmark
 * apps (idempotent: overwrites configs, then reloads services).
 *
 * The pools never recycle (pm.max_requests=0), so no per-block start/stop is
 * needed — ONE reload per orchestrator invocation is enough, and that reload
 * is what makes a template edit take effect. systemctl reload sends SIGUSR2
 * to the master, which re-reads pool.d and gracefully replaces the workers
 * WITHOUT changing ActiveEnterTimestamp — so a stale master looks perfectly
 * healthy to systemctl while still running the old max_requests.
 */
function ensureFpmRunning(string $root, string $phpFpmBin, string $deployDir): array
{
    static $installed = false;
    if ($installed) {
        return ['proc' => null, 'pipes' => []];
    }

    echo "  installing FPM pools + nginx vhosts...\n";

    // Everything FPM-side is derived from the version that $phpFpmBin names, so
    // measuring a different PHP is a flag rather than a source edit. When the
    // binary name does not carry a version (a custom build, an absolute path to
    // something else) the harness keeps its historical 8.3 layout instead of
    // guessing — a wrong pool directory would install pools no master reads, and
    // the block would then be measured against a server that never reloaded.
    $series  = phpSeriesFromFpmBin($phpFpmBin) ?? '8.3';
    $poolDir = "/etc/php/{$series}/fpm/pool.d";
    $fpmUnit = "php{$series}-fpm";

    foreach (glob("{$deployDir}/bench-*.conf") ?: [] as $pool) {
        shellRun(sprintf('sudo -n cp %s %s', escapeshellarg($pool), escapeshellarg($poolDir . '/' . basename($pool))), 'cp pool');
    }
    // nginx.conf ships with `include /etc/nginx/conf.d/*.conf;` — vhosts
    // MUST land with a .conf suffix or the glob ignores them (silent: reload
    // succeeds, but the server block never activates, nothing listens).
    foreach (glob("{$deployDir}/bench-*.nginx") ?: [] as $vhost) {
        $dst = '/etc/nginx/conf.d/' . str_replace('.nginx', '.conf', basename($vhost));
        shellRun(sprintf('sudo -n cp %s %s', escapeshellarg($vhost), escapeshellarg($dst)), 'cp vhost');
    }

    @mkdir('/run/php-fpm-bench', 0775, true);
    shellRun('sudo -n mkdir -p /run/php-fpm-bench', 'mkdir sockdir');
    shellRun('sudo -n chown www-data:www-data /run/php-fpm-bench', 'chown sockdir');

    // php-fpm --test opens the GLOBAL error_log (/var/log/php<X.Y>-fpm.log)
    // during validation — root-writable only, so the test itself must run
    // via sudo even though the benchmark user owns everything else.
    shellRun("sudo -n {$phpFpmBin} --test", 'fpm config test');
    shellRun("sudo -n systemctl reload {$fpmUnit} || sudo -n systemctl restart {$fpmUnit}", 'fpm reload');
    // nginx -t opens /run/nginx.pid (and the global error log) during
    // validation — root-only, so sudo here too.
    shellRun('sudo -n nginx -t', 'nginx config test');
    shellRun('sudo -n systemctl reload nginx || sudo -n systemctl restart nginx', 'nginx reload');

    $installed = true;
    // No process handle — FPM/nginx are system-managed; teardown happens in
    // the finally-less path via removeDeployConfigs() at script end.
    return ['proc' => null, 'pipes' => []];
}

/**
 * Remove benchmark pools/vhosts and reload services (teardown after the run).
 *
 * The pool sweep is deliberately version-AGNOSTIC: it globs every /etc/php/* on
 * the host rather than the one directory this run installed into. A pool left
 * behind by an earlier run under a DIFFERENT PHP version keeps its `listen`
 * socket and its bench-<app> name alive, and FPM fails the whole master's reload
 * when two pools claim one socket — so a leftover from the 8.3 era breaks an 8.5
 * run in a way that reads as "FPM did not reload".
 */
function removeDeployConfigs(string $deployDir): void
{
    foreach (glob("{$deployDir}/bench-*.nginx") ?: [] as $vhost) {
        // Mirror ensureFpmRunning(): installed as <name>.conf (include glob).
        @unlink('/etc/nginx/conf.d/' . str_replace('.nginx', '.conf', basename($vhost)));
    }
    shellRun('sudo -n sh -c "rm -f /etc/php/*/fpm/pool.d/bench-* /etc/nginx/conf.d/bench-*"', 'rm configs');
    // Reload every FPM master that exists, so none keeps a pool whose file is gone.
    shellRun('sudo -n sh -c ' . escapeshellarg('for u in php*-fpm; do systemctl reload "$u" 2>/dev/null || true; done'), 'fpm reload');
    shellRun('sudo -n systemctl reload nginx || true', 'nginx reload');
    echo "FPM pools + nginx vhosts removed.\n";
}

// --- Readiness + smoke ---------------------------------------------------------------

/**
 * A substring only the given app's worker can produce (all six apps return a
 * JSON /features/config payload with an app-specific description). Returned
 * apostrophe-free on purpose: json_encode() may or may not \u0027-escape the
 * quote depending on the app's encoder flags.
 */
function appFingerprint(string $appKey): ?string
{
    return [
        'azera'       => 'Dot-notation access to a nested configuration',
        'laravel'     => 'Configuration access through Laravel',
        'symfony'     => 'Configuration access through Symfony',
        'spiral'      => 'Configuration access through Spiral',
        'codeigniter' => 'Configuration access via CodeIgniter',
        'cakephp'     => 'Configuration access via a plain PHP class',
    ][$appKey] ?? null;
}

/**
 * Is this body a webserver gateway error rather than something an app served?
 *
 * nginx answers 502/503/504 itself while the upstream FastCGI worker is
 * unavailable — notably right after `systemctl reload php8.3-fpm`, which
 * replaces workers asynchronously. Such a page is generated by nginx, so it
 * carries no app fingerprint; without this distinction the readiness poll
 * mistakes the restart race for a stale server on the port and aborts the run
 * (hit on symfony, 2026-09-15).
 */
function isGatewayError(string $body): bool
{
    return str_contains($body, 'Bad Gateway')
        || str_contains($body, 'Service Unavailable')
        || str_contains($body, 'Gateway Time-out');
}

/**
 * Poll the server's GET / until it answers with a non-broken body (30 s).
 * Uses the shared abort guard semantics via curl.
 *
 * When the app has a fingerprint, /features/config MUST contain it: a server
 * that answers is not proof that the RIGHT app is behind the port. An
 * orphaned worker from a previous block answers every request happily,
 * and treating that as "ready" is exactly how the 2026-09-14 run recorded
 * azera's worker under five other frameworks' names.
 */
function waitForServer(string $baseUrl, string $server, string $appKey, ?string $logPath = null): void
{
    require_once __DIR__ . '/bench-lib.php';

    $fingerprint = appFingerprint($appKey);
    $deadline    = microtime(true) + 30;
    $lastBody    = null;

    while (microtime(true) < $deadline) {
        try {
            $body     = httpRequest($baseUrl, 'GET', '/');
            $lastBody = $body;
            $ready    = $body !== 'Not Found'
                && !str_starts_with($body, '500 ')
                && !isGatewayError($body);

            // The fingerprint check is what catches a stale server from a
            // previous block. It must only fire on a body that came from an
            // APP: nginx answers 502/503 while FPM is mid-reload (its workers
            // are replaced asynchronously and the socket is briefly
            // connectionless), and that HTML error page contains no
            // fingerprint either. Treating it as "wrong worker" aborted the
            // whole run on a 502 that resolved a moment later (symfony,
            // 2026-09-15) — a race, not a contaminated block.
            if ($ready && $fingerprint !== null && !isGatewayError($body)) {
                $probe = httpRequest($baseUrl, 'GET', '/features/config');
                if (!isGatewayError($probe) && !str_contains($probe, $fingerprint)) {
                    throw new RuntimeException(
                        "WRONG WORKER on {$baseUrl} for {$appKey}: /features/config did not contain the app fingerprint.\n"
                            . "  expected: {$fingerprint}\n"
                            . "  body head: " . substr($probe, 0, 200) . "\n"
                            . "  A stale worker from a previous block is holding the port.\n"
                            . workerLogTail($logPath)
                    );
                }
            }

            if ($ready) {
                echo "  server ready ({$server}/{$appKey})\n";
                return;
            }
        } catch (RuntimeException $e) {
            // A fingerprint mismatch is fatal (never retry it); a transport
            // failure just means "not up yet".
            if (str_contains($e->getMessage(), 'WRONG WORKER')) {
                throw $e;
            }
        }
        usleep(250_000);
    }

    throw new RuntimeException(
        "Server {$server}/{$appKey} did not become ready in 30s.\n"
            . ($lastBody !== null ? "  last body head: " . substr($lastBody, 0, 200) . "\n" : '')
            . workerLogTail($logPath)
    );
}

/**
 * Format the tail of a worker log for an error message ('' when missing).
 */
function workerLogTail(?string $logPath, int $lines = 25): string
{
    if ($logPath === null || !is_file($logPath)) {
        return '';
    }
    $all = @file($logPath, FILE_IGNORE_NEW_LINES) ?: [];
    if ($all === []) {
        return "  worker log {$logPath} is empty\n";
    }

    return "  worker log tail ({$logPath}):\n    " . implode("\n    ", array_slice($all, -$lines)) . "\n";
}

// --- http-bench child -------------------------------------------------------------------

function runHttpBench(string $root, string $server, string $appKey, string $baseUrl, int $itersPerRun, int $runs, string $tmpJson, string $extraArgs = '', string $phpCli = ''): void
{
    echo "  benchmarking {$server}/{$appKey} @ {$baseUrl}...\n";
    shellRun(sprintf(
        'cd %s && %s scripts/http-bench.php --server=%s --app=%s --base-url=%s --iterations-per-run=%d --runs=%d --out-json=%s %s',
        escapeshellarg($root),
        $phpCli !== '' ? escapeshellarg($phpCli) : 'php',
        escapeshellarg($server),
        escapeshellarg($appKey),
        escapeshellarg($baseUrl),
        $itersPerRun,
        $runs,
        escapeshellarg($tmpJson),
        $extraArgs
    ), "http-bench {$server}/{$appKey}");
}

// --- Floors --------------------------------------------------------------------------------

/**
 * Measure the constant webserver overhead once per server:
 *   floor-http → nginx serving a static file (pure webserver cost)
 *   floor-php  → hello-world PHP through FPM (webserver + FPM + bare PHP)
 * Returns entries shaped like app results ('app' => 'floor-*') so they can
 * ride in the dataset's apps[] array without special-casing in the report.
 */
function measureFloors(
    string $root,
    string $deployDir,
    bool $useFpm,
    int $fpmPort,
    int $itersPerRun,
    int $runs,
    string $phpFpmBin,
    string $benchUser,
    string $phpCli = '',
    array $volucerArms = [],
    string $volucerBinary = '',
    int $volucerPortBase = 8800,
): array {
    // Same defaulting rule as stampAllDeployConfigs(): empty means "this
    // orchestrator", so the floor probe runs on the interpreter the worker uses.
    if ($phpCli === '') {
        $phpCli = PHP_BINARY;
    }
    $floors = [];

    // floor-http: static file through nginx (port 9900, separate vhost).
    if ($useFpm) {
        $staticDir = "{$root}/temp/floor-static";
        @mkdir($staticDir, 0777, true);
        file_put_contents("{$staticDir}/index.html", "floor-http\n");

        $vhost = <<<NGINX
        server {
            listen 127.0.0.1:9900;
            root {$staticDir};
            index index.html;
            access_log off;
        }
        NGINX;
        file_put_contents("{$deployDir}/bench-floor-http.nginx", $vhost);
        // Include glob is conf.d/*.conf — .nginx names are ignored (and any
        // stale misnamed copies from earlier runs must go first).
        shellRun('sudo -n sh -c "rm -f /etc/nginx/conf.d/bench-floor-*"', 'rm stale floor vhosts');
        shellRun(sprintf('sudo -n cp %s /etc/nginx/conf.d/%s', escapeshellarg("{$deployDir}/bench-floor-http.nginx"), escapeshellarg('bench-floor-http.conf')), 'cp floor vhost');
        shellRun('sudo -n systemctl reload nginx || sudo -n systemctl restart nginx', 'nginx reload');

        $floors[] = measureFloorApp($root, 'floor-http', 'php-fpm', 'http://127.0.0.1:9900', $itersPerRun, $runs, $phpCli);

        // floor-php: hello-world through FPM (real PHP cost, no framework).
        $vhost = <<<NGINX
        server {
            listen 127.0.0.1:9901;
            root {$staticDir};
            index hello.php;
            access_log off;
            location ~ \.php$ {
                include fastcgi_params;
                fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
                fastcgi_pass unix:/run/php-fpm-bench/floor-php.sock;
            }
        }
        NGINX;
        file_put_contents("{$deployDir}/bench-floor-php.nginx", $vhost);
        file_put_contents("{$deployDir}/bench-floor-php.conf", str_replace(
            ['{{APP}}', '{{PORT}}', '{{ROOT}}', '{{USER}}'],
            ['floor-php', '9901', $root, $benchUser],
            (string) file_get_contents("{$root}/deploy/fpm/pool.conf.template")
        ));
        // floor-php serves a plain PHP file (no framework).
        file_put_contents("{$staticDir}/hello.php", "<?php echo 'floor-php';\n");

        shellRun(sprintf('sudo -n cp %s /etc/nginx/conf.d/%s', escapeshellarg("{$deployDir}/bench-floor-php.nginx"), escapeshellarg('bench-floor-php.conf')), 'cp floor vhost');
        $floorSeries = phpSeriesFromFpmBin($phpFpmBin) ?? '8.3';
        shellRun(sprintf('sudo -n cp %s %s', escapeshellarg("{$deployDir}/bench-floor-php.conf"), escapeshellarg("/etc/php/{$floorSeries}/fpm/pool.d/bench-floor-php.conf")), 'cp floor pool');
        // The floor probe must run on the SAME master that serves the measured
        // apps, or its "constant webserver overhead" baseline is a number from a
        // different runtime than the cells it is subtracted from / compared with.
        shellRun("sudo -n systemctl reload php{$floorSeries}-fpm || sudo -n systemctl restart php{$floorSeries}-fpm", 'fpm reload');
        shellRun('sudo -n systemctl reload nginx || sudo -n systemctl restart nginx', 'nginx reload');

        $floors[] = measureFloorApp($root, 'floor-php', 'php-fpm', 'http://127.0.0.1:9901', $itersPerRun, $runs, $phpCli);
    }

    // --- Volucer floors ------------------------------------------------------
    //
    // WHY THESE EXIST. The other three servers get a floor probe so the constant
    // webserver overhead is visible in the dataset. The two Volucer arms had none,
    // which made their rows UNREADABLE in exactly the case where the floor matters
    // most: `/echo` on volucer-attach is 149 us and the framework part of it is 4 us,
    // so a reader comparing 149 against 160 on another server is comparing two
    // floors and calling it a framework difference. That is the trap the
    // 2026-11-24 `/echo` investigation fell into.
    //
    // WHAT THEY MEASURE. The floor app is a real entry script — a front controller
    // that echoes `floor-volucer` — served by the same arm, the same config, the
    // same one-connection rule and the same probe-kind env as a measured app. Only
    // the framework is absent. So `floor − app` is the framework, and `floor` alone
    // is the arm's constant overhead.
    //
    // floor-volucer-attach is 2.3 ms, not 150 us: that arm's supervisor FORKS A
    // CHILD PER CONNECTION, and with `workers = 1` volucer holds one connection for
    // the life of the slot, so the child is forked once per run and never again.
    // The per-request cost of that arm is a unix-socket handshake, which is what the
    // floor reports.
    //
    // Ports are pinned ABOVE every real app's bank (attach starts at 8810, seven apps,
    // so the highest in use is 8817) rather than derived from
    // BENCH_APP_ORDER, because a floor is not an app and must not shift an app's
    // port when it is added. The base is passed so the pinning is stated on the
    // SAME scale as the bank it sits above, and the returned map is checked
    // against the pins — stampPort() is the authority on what was written.
    if ($volucerArms !== []) {
        $floorApps = [
            'floor-volucer-attach' => 'volucer-attach',
        ];

        // Stamped by the SAME function that stamps the real apps, so a floor cannot
        // drift from the arm it is a floor FOR (templates, placeholders, env).
        $floorPortMap = [];
        stampVolucerConfigs(
            $root,
            $deployDir,
            array_keys($floorApps),
            array_values($floorApps),
            $phpCli,
            $volucerPortBase,
            $floorPortMap,
        );

        $floorPorts = [];
        foreach ($floorApps as $floorKey => $arm) {
            $stamped = $floorPortMap[$arm][$floorKey] ?? null;
            if (!is_int($stamped)) {
                fwrite(STDERR, "[run-http] the {$floorKey} floor was not stamped a port — refusing to guess one.\n");
                exit(1);
            }
            $floorPorts[$arm] = $stamped;
        }

        foreach ($floorApps as $floorKey => $arm) {
            $port = $floorPorts[$arm];

            if (!waitForPortFree($port)) {
                fwrite(STDERR, "[run-http] Port {$port} is in use before the {$floorKey} floor — refusing to benchmark a stale server.\n");
                exit(1);
            }

            killAllBenchVolucers();
            $proc = startVolucer($root, $deployDir, $floorKey, $arm, $volucerBinary, $phpCli, $port);
            try {
                waitForServer("http://127.0.0.1:{$port}", $arm, $floorKey, null);
                $floors[] = measureFloorApp($root, $floorKey, $arm, "http://127.0.0.1:{$port}", $itersPerRun, $runs, $phpCli);
            } finally {
                stopVolucer($proc);
                @unlink(volucerRelayPath($floorKey));
            }
        }
    }

    return $floors;
}

/**
 * Measure one floor pseudo-app (single request label GET /) via http-bench
 * and return an app entry shaped like the real apps.
 */
function measureFloorApp(string $root, string $name, string $mode, string $baseUrl, int $itersPerRun, int $runs, string $phpCli = ''): array
{
    $tmpJson = "{$root}/temp/bench-floor-{$name}.json";

    // `mode` here is the ARM name ('roadrunner', 'php-fpm', 'volucer-attach', ...).
    // http-bench.php keys its output by modeNameFor($server), and the two Volucer
    // arms previously fell through the 'not rr => fpm' shorthand below and came back
    // labelled 'php-fpm' — a floor for the wrong server, in a dataset whose whole
    // purpose is to keep server overhead apart.
    $server = match ($mode) {
        'roadrunner' => 'rr',
        'php-fpm'    => 'fpm',
        default      => $mode,
    };

    // Floors are single-endpoint probes (GET / only) — never the full label
    // list; the report only reads the GET / entry.
    //
    // WAIT FOR IT FIRST. The app path always waits for its server, but this one did
    // not, and `measureFloors()` reloads FPM immediately before calling it: a reload
    // replaces workers ASYNCHRONOUSLY, so the new pool's socket exists while still
    // being connectionless and nginx answers 502. On 2026-11-24 that aborted a
    // completed 4-hour run at its last step (`floor-php`), discarding the latency
    // measurements of every app on every arm — abortIfBroken() treats a 502 as a
    // broken body, and it is right to, because a 502 in the middle of a timed block
    // means the number describes a restart rather than a request.
    //
    // The floor keys are not app keys, so the fingerprint check inside waitForServer()
    // is skipped (there is no `/features/config` behind a floor) — it probes until the
    // body stops being a gateway error, which is exactly the condition that failed.
    waitForServer($baseUrl, $server, $name);
    runHttpBench($root, $server, $name, $baseUrl, $itersPerRun, $runs, $tmpJson, '--requests="GET /"', $phpCli);

    $data = json_decode((string) file_get_contents($tmpJson), true);
    unlink($tmpJson);

    return [
        'app'   => $name,
        'modes' => $data['modes'] ?? [],
    ];
}

/**
 * Make sure this app's pruned autoloader exists, generating it if it does not.
 *
 * WHY THE HARNESS DOES THIS. The generated loader under `vendor/app-autoload/<app>/`
 * is a build artifact, not a committed file: it is a pure function of `composer.lock`,
 * so committing it would mean committing a stale copy the moment a dependency moves.
 * But every measured process requires it (see `benchRequireAutoload()`), so a run that
 * started without it would silently measure the SHARED autoloader — and the shared one
 * executes all seven frameworks' bootstrap files, which is the 4x boot skew this exists
 * to remove.
 *
 * Idempotent and cheap: the generator is skipped when the file is already newer than
 * `composer.lock`, so a run pays one `stat()` per app after the first.
 *
 * A missing generator is NOT fatal here: `benchRequireAutoload()` falls back to
 * `vendor/autoload.php`, which is exactly the behaviour every published dataset had.
 * The run then measures the shared chain — degraded, not broken — and
 * `tests/AppAutoloadTest.php` is what makes the omission visible in CI.
 */
function ensureAppAutoload(string $root, string $appKey, string $phpCli = ''): void
{
    if (benchIsFloorApp($appKey)) {
        return; // a floor has no framework closure to prune against
    }

    $generated = "{$root}/vendor/app-autoload/{$appKey}/autoload.php";
    $lock      = "{$root}/composer.lock";

    if (is_file($generated) && (!is_file($lock) || filemtime($generated) >= filemtime($lock))) {
        return;
    }

    $generator = "{$root}/scripts/app-autoload.php";
    if (!is_file($generator)) {
        return;
    }

    $php = $phpCli !== '' ? $phpCli : PHP_BINARY;

    exec(
        escapeshellcmd($php) . ' ' . escapeshellarg($generator)
            . ' --apps=' . escapeshellarg($appKey) . ' 2>&1',
        $out,
        $rc,
    );

    if ($rc !== 0) {
        fwrite(STDERR, "[run-http] could not generate the pruned autoloader for {$appKey}"
            . " (exit {$rc}); this block measures the SHARED autoloader instead:\n"
            . '  ' . implode("\n  ", array_slice($out, -5)) . "\n");
    }
}



// --- Shell helpers -------------------------------------------------------------------------

/**
 * Run a shell command; abort the orchestrator on non-zero exit.
 */
function shellRun(string $cmd, string $what): void
{
    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0) {
        fwrite(STDERR, "[run-http] {$what} failed (exit {$rc}):\n" . implode("\n", array_slice($out, -20)) . "\n");
        exit(1);
    }
}

/**
 * Run a shell command and return its stdout (tolerant of failure — used for
 * version probes where absence just means "unknown").
 */
function shellProcessOutput(string $cmd): string
{
    exec($cmd . ' 2>&1', $out);
    return implode(' ', $out);
}

// --- Result writer ---------------------------------------------------------------------------

/**
 * Write the combined dataset in run.php's JSON shape so report.php consumes
 * it unchanged: apps[].modes.<mode>.requests[] with the same stat fields.
 * Floor entries ride in apps[] as 'floor-*' pseudo-apps.
 */
function writeRealResults(string $outPrefix, array $results): void
{
    $dir = dirname($outPrefix);
    if ($dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($outPrefix . '.json', json_encode($results, JSON_PRETTY_PRINT));
    echo "Combined dataset written: {$outPrefix}.json\n";
}
