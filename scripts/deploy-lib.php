<?php

declare(strict_types=1);

/**
 * Deployment + server management helpers for scripts/run-http.php.
 *
 * Owns: config stamping (RR yaml / FPM pool / nginx vhost from the deploy/
 * templates), SQLite seeding, RR process lifecycle, FPM + nginx service
 * management (non-root user with passwordless sudo on the benchmark VM —
 * distro packages), readiness polling, and the http-bench child invocation.
 */

// --- Config stamping -----------------------------------------------------------

/**
 * Stamp every per-app config the benchmark needs, once, into $deployDir:
 *   .rr-<app>.yaml (from deploy/rr/config-template.php)
 *   bench-<app>.conf  (FPM pool, from deploy/fpm/pool.conf.template)
 *   bench-<app>.nginx (nginx vhost, from deploy/fpm/vhost.conf.template)
 * Returns the port map: ['rr' => [app => port], 'fpm' => [app => port]].
 */
function stampAllDeployConfigs(
    string $root,
    string $deployDir,
    array $apps,
    int $fpmPortBase,
    int $rrPortBase,
    array &$ports,
    string $benchUser,
): void {
    if (!is_dir($deployDir)) {
        mkdir($deployDir, 0777, true);
    }

    $rrTemplate = require "{$root}/deploy/rr/config-template.php";
    $poolTpl    = (string) file_get_contents("{$root}/deploy/fpm/pool.conf.template");
    $vhostTpl   = (string) file_get_contents("{$root}/deploy/fpm/vhost.conf.template");

    $i = 0;
    foreach ($apps as $appKey) {
        $appKey = trim($appKey);

        $rrPort  = $rrPortBase + $i;
        $fpmPort = $fpmPortBase + $i;
        $ports['rr'][$appKey] = $rrPort;
        $ports['fpm'][$appKey] = $fpmPort;

        $rrYaml = str_replace(
            ['{{APP}}', '{{PORT}}', '{{ROOT}}'],
            [$appKey, (string) $rrPort, $root],
            $rrTemplate()
        );
        file_put_contents("{$deployDir}/.rr-{$appKey}.yaml", $rrYaml);

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

        $i++;
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

// --- SQLite seeding --------------------------------------------------------------

function seedDatabase(string $root, int $rows): void
{
    echo "  seeding SQLite ({$rows} rows)...\n";
    shellRun(sprintf(
        'cd %s && php seed.php --rows=%d',
        escapeshellarg($root),
        $rows
    ), 'seed.php');
}

// --- RoadRunner -------------------------------------------------------------------

/**
 * Pgrep pattern matching a RoadRunner process for one config file.
 * A trailing `\)` anchors the path so `.rr-azera.yaml` never matches
 * `.rr-azera-b.yaml`.
 */
function rrProcessPattern(string $deployDir, string $appKey): string
{
    return 'rr serve -c ' . $deployDir . '/.rr-' . $appKey . '.yaml)';
}

/**
 * Kill any RoadRunner process still bound to this app's config (orphans from
 * an earlier run or a crashed stop). MUST run before startRoadRunner(): an
 * orphan keeps the port, the new `rr serve` silently fails to bind, and
 * waitForServer() would then "succeed" against the OLD app's worker — which
 * is exactly how every RR block after the first got benchmarked against
 * azera's worker on 2026-09-14.
 */
function killOrphanRoadRunners(string $deployDir, string $appKey): void
{
    $pat = rrProcessPattern($deployDir, $appKey);
    exec('pkill -f ' . escapeshellarg($pat) . ' 2>/dev/null', $o1, $rc1);
    if ($rc1 === 0) {
        // Give the port a moment to be released before we try to bind it.
        usleep(300_000);
        echo "  killed orphaned RoadRunner ({$appKey}) holding the port\n";
    }
}

/**
 * Kill EVERY benchmark RoadRunner (any app) at orchestrator start. A batch
 * runs one orchestrator per app; without this sweep an orphan left by the
 * previous invocation keeps its port and the next invocation's first block
 * cannot bind — the same failure at a coarser granularity.
 */
function killAllBenchRoadRunners(string $deployDir): void
{
    exec('pkill -f ' . escapeshellarg('rr serve -c ' . $deployDir . '/.rr-') . ' 2>/dev/null', $o, $rc);
    if ($rc === 0) {
        usleep(400_000);
        echo "  killed stale benchmark RoadRunner process(es) before starting\n";
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

/**
 * Start RoadRunner for one app. Config must have been stamped for $appKey.
 *
 * The returned pid is the REAL RoadRunner process, not a shell wrapper:
 * prefixing the command with `sh -c` (the pre-2026-09-14 shape) made
 * proc_open return the wrapper's pid, so stopRoadRunner() SIGTERMed the
 * wrapper and orphaned the actual `rr` binary — which kept the port and made
 * the next app's block benchmark the previous app. The command is therefore
 * chdir'ed into PHP and `exec`'d so the pid we get IS the process holding
 * the listening socket (exec also puts it in this process's group, so the
 * group-kill fallback reaches the workers too).
 *
 * @return array{proc: resource, pipes: array}
 */
function startRoadRunner(string $root, string $deployDir, string $appKey, string $rrBinary, int $port): array
{
    if (is_dir($root)) {
        chdir($root);
    }

    // An orphan from a previous block would hold the port (see above).
    killOrphanRoadRunners($deployDir, $appKey);
    if (!waitForPortFree($port)) {
        fwrite(STDERR, "[run-http] Port {$port} is still in use before starting RoadRunner ({$appKey}) — refusing to benchmark a stale server.\n");
        exit(1);
    }

    echo "  starting RoadRunner ({$appKey}, port {$port})...\n";

    // NOTE: -d xdebug.mode=off is a PHP flag — the Go binary rejects it
    // ("unknown command"). Xdebug-off applies to the PHP WORKERS, which
    // inherit php.ini (CLI ini already has xdebug disabled on the VM);
    // RR itself needs `-o logs.level=…` style overrides only.
    $cmd = sprintf(
        'exec %s serve -c %s 2>&1',
        escapeshellarg($rrBinary),
        escapeshellarg("{$deployDir}/.rr-{$appKey}.yaml")
    );

    $pipes = [];
    $logPath = "{$deployDir}/rr-{$appKey}.log";
    $logFp   = fopen($logPath, 'w');
    $descr   = $logFp === false
        ? [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']]
        : [['pipe', 'r'], $logFp, $logFp];

    $proc  = proc_open($cmd, $descr, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "[run-http] Failed to start RoadRunner for {$appKey}\n");
        exit(1);
    }

    if ($logFp === false) {
        // Fallback: keep the pipes non-blocking so a chatty worker cannot
        // deadlock the orchestrator on a full pipe buffer.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
    }

    return ['proc' => $proc, 'pipes' => $pipes, 'deployDir' => $deployDir, 'appKey' => $appKey, 'log' => $logPath];
}

/**
 * Stop RoadRunner hard: SIGTERM the group, SIGKILL the group, then pkill any
 * worker still matching this config. The pkill is what makes the stop
 * trustworthy — without it an orphan can outlive the block and poison the
 * next one (see killOrphanRoadRunners()).
 */
function stopRoadRunner(array $procRef): void
{
    if (!is_resource($procRef['proc'])) {
        return;
    }

    $status = proc_get_status($procRef['proc']);
    $pid    = (int) ($status['pid'] ?? 0);
    if ($pid > 0) {
        // Negative pid = the whole process group (startRoadRunner exec'd rr
        // into this group); rr forwards shutdown to its PHP workers itself,
        // AND the group kill reaches any worker that ignored the forward
        // (which otherwise lingers holding RR's log fd).
        @posix_kill(-$pid, 15);
        @posix_kill($pid, 15);
        for ($i = 0; $i < 30; $i++) {
            $status = proc_get_status($procRef['proc']);
            if (!$status['running']) {
                break;
            }
            usleep(100_000);
        }
        $status = proc_get_status($procRef['proc']);
        if ($status['running']) {
            @posix_kill(-$pid, 9);
            @posix_kill($pid, 9);
            usleep(100_000);
        }
    }
    proc_close($procRef['proc']);

    // Hammer any survivor so the port cannot stay bound into the next app's
    // block (this is the bug the whole hardening exists for).
    if (isset($procRef['deployDir'], $procRef['appKey'])) {
        killOrphanRoadRunners($procRef['deployDir'], $procRef['appKey']);
    }

    echo "  RoadRunner stopped.\n";
}

// --- FPM + nginx (system services, root assumed) ------------------------------------

/**
 * Install + start the stamped FPM pool and nginx vhost for all benchmark
 * apps (idempotent: overwrites configs, then reloads services). With
 * pm.max_requests=1 the running pool IS the cold model, so this runs once
 * per orchestrator invocation, not per block.
 */
function ensureFpmRunning(string $root, string $phpFpmBin, string $deployDir): array
{
    static $installed = false;
    if ($installed) {
        return ['proc' => null, 'pipes' => []];
    }

    echo "  installing FPM pools + nginx vhosts...\n";

    foreach (glob("{$deployDir}/bench-*.conf") ?: [] as $pool) {
        shellRun(sprintf('sudo -n cp %s /etc/php/8.3/fpm/pool.d/%s', escapeshellarg($pool), escapeshellarg(basename($pool))), 'cp pool');
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

    // php-fpm --test opens the GLOBAL error_log (/var/log/php8.3-fpm.log)
    // during validation — root-writable only, so the test itself must run
    // via sudo even though the benchmark user owns everything else.
    shellRun("sudo -n {$phpFpmBin} --test", 'fpm config test');
    shellRun('sudo -n systemctl reload php8.3-fpm || sudo -n systemctl restart php8.3-fpm', 'fpm reload');
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
 */
function removeDeployConfigs(string $deployDir): void
{
    foreach (glob("{$deployDir}/bench-*.conf") ?: [] as $pool) {
        @unlink('/etc/php/8.3/fpm/pool.d/' . basename($pool));
    }
    foreach (glob("{$deployDir}/bench-*.nginx") ?: [] as $vhost) {
        // Mirror ensureFpmRunning(): installed as <name>.conf (include glob).
        @unlink('/etc/nginx/conf.d/' . str_replace('.nginx', '.conf', basename($vhost)));
    }
    shellRun('sudo -n sh -c "rm -f /etc/php/8.3/fpm/pool.d/bench-* /etc/nginx/conf.d/bench-*"', 'rm configs');
    shellRun('sudo -n systemctl reload php8.3-fpm || true', 'fpm reload');
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
 * Poll the server's GET / until it answers with a non-broken body (30 s).
 * Uses the shared abort guard semantics via curl.
 *
 * When the app has a fingerprint, /features/config MUST contain it: a server
 * that answers is not proof that the RIGHT app is behind the port. An
 * orphaned RoadRunner from a previous block answers every request happily,
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
            $ready    = $body !== 'Not Found' && !str_starts_with($body, '500 ');

            if ($ready && $fingerprint !== null) {
                $probe = httpRequest($baseUrl, 'GET', '/features/config');
                if (!str_contains($probe, $fingerprint)) {
                    throw new RuntimeException(
                        "WRONG WORKER on {$baseUrl} for {$appKey}: /features/config did not contain the app fingerprint.\n"
                            . "  expected: {$fingerprint}\n"
                            . "  body head: " . substr($probe, 0, 200) . "\n"
                            . "  A stale RoadRunner from a previous block is holding the port.\n"
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

function runHttpBench(string $root, string $server, string $appKey, string $baseUrl, int $itersPerRun, int $runs, string $tmpJson, string $extraArgs = ''): void
{
    echo "  benchmarking {$server}/{$appKey} @ {$baseUrl}...\n";
    shellRun(sprintf(
        'cd %s && php scripts/http-bench.php --server=%s --app=%s --base-url=%s --iterations-per-run=%d --runs=%d --out-json=%s %s',
        escapeshellarg($root),
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
    bool $useRr,
    bool $useFpm,
    int $rrPort,
    int $fpmPort,
    int $itersPerRun,
    int $runs,
    string $rrBinary,
    string $phpFpmBin,
    string $benchUser,
): array {
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

        $floors[] = measureFloorApp($root, 'floor-http', 'php-fpm', 'http://127.0.0.1:9900', $itersPerRun, $runs);

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
        shellRun(sprintf('sudo -n cp %s /etc/php/8.3/fpm/pool.d/%s', escapeshellarg("{$deployDir}/bench-floor-php.conf"), escapeshellarg('bench-floor-php.conf')), 'cp floor pool');
        shellRun('sudo -n systemctl reload php8.3-fpm || sudo -n systemctl restart php8.3-fpm', 'fpm reload');
        shellRun('sudo -n systemctl reload nginx || sudo -n systemctl restart nginx', 'nginx reload');

        $floors[] = measureFloorApp($root, 'floor-php', 'php-fpm', 'http://127.0.0.1:9901', $itersPerRun, $runs);
    }

    // floor-rr: hello-world through RoadRunner (bare worker cost).
    if ($useRr) {
        // The config file is named .rr-<floorKey>.yaml so startRoadRunner()
        // (which derives the path AND the orphan-pgrep pattern from the app
        // key) can manage the floor worker exactly like a real app.
        $floorKey = 'floor-rr';
        $worker = "{$root}/temp/floor-rr-worker.php";
        file_put_contents($worker, floorRrWorkerSource());
        $cfg = "{$deployDir}/.rr-{$floorKey}.yaml";
        file_put_contents($cfg, str_replace(
            ['{{APP}}', '{{PORT}}'],
            [$floorKey, '9902'],
            <<<YAML
            version: "3"
            server:
              command: "php -d xdebug.mode=off {$worker}"
            http:
              address: "127.0.0.1:9902"
              pool:
                num_workers: 1
            logs:
              level: error
            YAML
        ));

        $proc = startRoadRunner($root, $deployDir, $floorKey, $rrBinary, 9902);
        try {
            waitForServer('http://127.0.0.1:9902', 'rr', $floorKey, $proc['log'] ?? null);
            $floors[] = measureFloorApp($root, 'floor-rr', 'roadrunner', 'http://127.0.0.1:9902', $itersPerRun, $runs);
        } finally {
            stopRoadRunner($proc);
        }
    }

    return $floors;
}

/**
 * Measure one floor pseudo-app (single request label GET /) via http-bench
 * and return an app entry shaped like the real apps.
 */
function measureFloorApp(string $root, string $name, string $mode, string $baseUrl, int $itersPerRun, int $runs): array
{
    $tmpJson = "{$root}/temp/bench-floor-{$name}.json";
    // Floors are single-endpoint probes (GET / only) — never the full label
    // list; the report only reads the GET / entry.
    runHttpBench($root, $mode === 'roadrunner' ? 'rr' : 'fpm', $name, $baseUrl, $itersPerRun, $runs, $tmpJson, '--requests="GET /"');

    $data = json_decode((string) file_get_contents($tmpJson), true);
    unlink($tmpJson);

    return [
        'app'   => $name,
        'modes' => $data['modes'] ?? [],
    ];
}

/**
 * Source of the minimal RoadRunner floor worker (hello world, no framework).
 */
function floorRrWorkerSource(): string
{
    return <<<'PHP'
    <?php
    use Spiral\RoadRunner\Http\PSR7Worker;
    use Spiral\RoadRunner\Worker;
    use Nyholm\Psr7\Factory\Psr17Factory;
    use Nyholm\Psr7\Response;

    require __DIR__ . '/../vendor/autoload.php';

    $psr7 = new PSR7Worker(Worker::create(), new Psr17Factory(), new Psr17Factory(), new Psr17Factory());
    while (true) {
        try {
            $request = $psr7->waitRequest();
            if ($request === null) {
                break;
            }
            $psr7->respond(new Response(200, ['Content-Type' => 'text/plain'], 'floor-rr'));
        } catch (Throwable $e) {
            $psr7->respond(new Response(500, [], '500 ' . $e->getMessage()));
        }
    }
    PHP;
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