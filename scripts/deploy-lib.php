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
 * Start RoadRunner for one app. Config must have been stamped for $appKey.
 * Returns the process handle for stopRoadRunner().
 *
 * @return array{proc: resource, pipes: array}
 */
function startRoadRunner(string $root, string $deployDir, string $appKey, string $rrBinary, int $port): array
{
    echo "  starting RoadRunner ({$appKey}, port {$port})...\n";

    $cmd = sprintf(
        'cd %s && %s -d xdebug.mode=off serve -c %s 2>&1',
        escapeshellarg($root),
        escapeshellarg($rrBinary),
        escapeshellarg("{$deployDir}/.rr-{$appKey}.yaml")
    );

    $pipes = [];
    $proc  = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "[run-http] Failed to start RoadRunner for {$appKey}\n");
        exit(1);
    }

    // Let RR relay logs to our stdout (non-blocking read pump happens via
    // waitForServer polls; RR's own log level is error-only in the config).
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    return ['proc' => $proc, 'pipes' => $pipes];
}

/**
 * Graceful stop: send SIGTERM, wait briefly, SIGKILL as the hammer.
 */
function stopRoadRunner(array $procRef): void
{
    if (!is_resource($procRef['proc'])) {
        return;
    }
    $status = proc_get_status($procRef['proc']);
    $pid    = (int) ($status['pid'] ?? 0);
    if ($pid > 0) {
        // rr serve spawns workers; SIGTERM the whole process group (rr
        // forwards shutdown to workers itself when it gets the signal).
        posix_kill($pid, 15);
        for ($i = 0; $i < 30; $i++) {
            $status = proc_get_status($procRef['proc']);
            if (!$status['running']) {
                break;
            }
            usleep(100_000);
        }
        $status = proc_get_status($procRef['proc']);
        if ($status['running']) {
            posix_kill($pid, 9);
        }
    }
    proc_close($procRef['proc']);
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
 * Poll the server's GET / until it answers with a non-broken body (30 s).
 * Uses the shared abort guard semantics via curl.
 */
function waitForServer(string $baseUrl, string $server, string $appKey): void
{
    require_once __DIR__ . '/bench-lib.php';

    $deadline = microtime(true) + 30;
    while (microtime(true) < $deadline) {
        try {
            $body = httpRequest($baseUrl, 'GET', '/');
            if ($body !== 'Not Found' && !str_starts_with($body, '500 ')) {
                echo "  server ready ({$server}/{$appKey})\n";
                return;
            }
        } catch (RuntimeException) {}
        usleep(250_000);
    }

    fwrite(STDERR, "[run-http] Server {$server}/{$appKey} did not become ready in 30s, aborting.\n");
    exit(1);
}

// --- http-bench child -------------------------------------------------------------------

function runHttpBench(string $root, string $server, string $appKey, string $baseUrl, int $itersPerRun, int $runs, string $tmpJson): void
{
    echo "  benchmarking {$server}/{$appKey} @ {$baseUrl}...\n";
    shellRun(sprintf(
        'cd %s && php scripts/http-bench.php --server=%s --app=%s --base-url=%s --iterations-per-run=%d --runs=%d --out-json=%s',
        escapeshellarg($root),
        escapeshellarg($server),
        escapeshellarg($appKey),
        escapeshellarg($baseUrl),
        $itersPerRun,
        $runs,
        escapeshellarg($tmpJson)
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
        shellRun(sprintf('sudo -n cp %s /etc/nginx/conf.d/%s', escapeshellarg("{$deployDir}/bench-floor-http.nginx"), escapeshellarg('bench-floor-http.nginx')), 'cp floor vhost');
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

        shellRun(sprintf('sudo -n cp %s /etc/nginx/conf.d/%s', escapeshellarg("{$deployDir}/bench-floor-php.nginx"), escapeshellarg('bench-floor-php.nginx')), 'cp floor vhost');
        shellRun(sprintf('sudo -n cp %s /etc/php/8.3/fpm/pool.d/%s', escapeshellarg("{$deployDir}/bench-floor-php.conf"), escapeshellarg('bench-floor-php.conf')), 'cp floor pool');
        shellRun('sudo -n systemctl reload php8.3-fpm || sudo -n systemctl restart php8.3-fpm', 'fpm reload');
        shellRun('sudo -n systemctl reload nginx || sudo -n systemctl restart nginx', 'nginx reload');

        $floors[] = measureFloorApp($root, 'floor-php', 'php-fpm', 'http://127.0.0.1:9901', $itersPerRun, $runs);
    }

    // floor-rr: hello-world through RoadRunner (bare worker cost).
    if ($useRr) {
        $worker = "{$root}/temp/floor-rr-worker.php";
        file_put_contents($worker, floorRrWorkerSource());
        $cfg = "{$root}/temp/.rr-floor.yaml";
        file_put_contents($cfg, str_replace(
            ['{{APP}}', '{{PORT}}'],
            ['floor-rr', '9902'],
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

        $proc = startRoadRunner($root, dirname($cfg), 'floor-rr', $rrBinary, 9902);
        try {
            waitForServer('http://127.0.0.1:9902', 'rr', 'floor-rr');
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
    runHttpBench($root, $mode === 'roadrunner' ? 'rr' : 'fpm', $name, $baseUrl, $itersPerRun, $runs, $tmpJson);

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