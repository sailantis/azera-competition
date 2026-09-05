<?php
/**
 * Web server starter for all benchmark apps (consolidates the former
 * start-web-<framework>.php scripts).
 *
 * Usage:
 *   php start-web.php                    # usage + app/port table
 *   php start-web.php azera              # start azera on its default port
 *   php start-web.php laravel --port=9000
 *   php start-web.php --app=spiral --host=0.0.0.0 --port=8080
 *
 * Default ports keep simultaneous local runs collision-free:
 *   symfony 8883, laravel 8884, cakephp 8885, codeigniter 8886,
 *   spiral 8887, azera 8888.
 *
 * Seeds the shared benchmark database automatically if it doesn't exist,
 * then launches PHP's built-in development server with the app's router
 * script. Press Ctrl+C to stop.
 */

// --- App registry -----------------------------------------------------------
// One line per framework: default port + router script. Adding a framework
// means adding a row here, not a new copy of the starter.

const APPS = [
    'symfony'     => ['port' => 8883, 'router' => 'public/index-symfony.php'],
    'laravel'     => ['port' => 8884, 'router' => 'public/index-laravel.php'],
    'cakephp'     => ['port' => 8885, 'router' => 'public/index-cakephp.php'],
    'codeigniter' => ['port' => 8886, 'router' => 'public/index-codeigniter.php'],
    'spiral'      => ['port' => 8887, 'router' => 'public/index-spiral.php'],
    'azera'       => ['port' => 8888, 'router' => 'public/index-azera.php'],
];

// --- Args -------------------------------------------------------------------
// Parsed manually from $argv (not getopt()): PHP's getopt() stops at the
// first positional argument, so `php start-web.php azera --port=9000` would
// silently drop the --port. Accepted forms:
//   php start-web.php azera
//   php start-web.php azera --port=9000   /  --port 9000
//   php start-web.php --app=azera --host=0.0.0.0

$app  = null;
$host = 'localhost';
$port = null;

$argList = array_slice($GLOBALS['argv'] ?? [], 1);
for ($i = 0; $i < count($argList); $i++) {
    $arg = $argList[$i];
    if ($arg === '--app' || $arg === '--host' || $arg === '--port') {
        $arg .= '=' . ($argList[++$i] ?? ''); // space-separated value form
    }
    if (preg_match('/^--app=(.+)$/', $arg, $m)) {
        $app = $m[1];
    } elseif (preg_match('/^--host=(.+)$/', $arg, $m)) {
        $host = $m[1];
    } elseif (preg_match('/^--port=(\d+)$/', $arg, $m)) {
        $port = (int) $m[1];
    } elseif ($arg !== '' && $arg[0] !== '-') {
        $app = $arg; // positional framework name
    } else {
        echo "Unknown option: {$arg}\n";
        exit(1);
    }
}

if ($app === null || !isset(APPS[$app])) {
    echo "Azera Competition — web server starter\n\n";
    echo "Usage:\n";
    echo "  php start-web.php <app> [--host=<host>] [--port=<port>]\n\n";
    echo "Apps (default ports):\n";
    foreach (APPS as $name => $cfg) {
        printf("  %-12s %d\n", $name, $cfg['port']);
    }
    exit($app === null ? 0 : 1);
}

$defaultPort = APPS[$app]['port'];
$router      = __DIR__ . '/' . APPS[$app]['router'];
$port        = $port ?? $defaultPort;

// --- Paths ---
$root       = __DIR__;
$dbPath     = $root . '/data/bench.sqlite';
$seedScript = $root . '/seed.php';
$docRoot    = $root . '/public';

if (!is_file($router)) {
    echo "Router script not found: {$router}\n";
    exit(1);
}

// --- Auto-seed if DB doesn't exist (shared DB with the other apps) ---
if (!file_exists($dbPath)) {
    echo "Database not found, seeding (1000 rows)...\n";
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($seedScript);
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "Seed failed (exit {$exitCode}). Aborting.\n";
        exit(1);
    }
}

// --- Start the built-in server ---
echo "Azera Competition — {$app} web server starting...\n";
echo "  URL:  http://{$host}:{$port}/\n";
echo "  Root: {$docRoot}\n";
echo "  PHP:  " . PHP_BINARY . " (" . PHP_VERSION . ")\n";
echo "  DB:   {$dbPath}\n";
echo "  Press Ctrl+C to stop.\n\n";

// Cross-platform process launch.
// On Windows, passthru runs the command in the foreground with stdio
// inheritance — simplest and most reliable across PHP/Windows versions.
// Don't escapeshellarg the doc-root/router paths — PHP's built-in server on
// Windows misparses quoted router/doc-root paths ("Path cannot be empty").
// The paths are internal (from realpath) and contain no spaces in practice.
$docRootReal = realpath($docRoot);
$routerReal  = realpath($router);

passthru(
    sprintf(
        '%s -d opcache.enable_cli=1 -S %s:%d -t %s %s',
        escapeshellarg(PHP_BINARY),
        $host,
        $port,
        $docRootReal,
        $routerReal
    ),
    $exitCode
);
exit($exitCode);