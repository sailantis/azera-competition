<?php
/**
 * Web server starter script.
 *
 * Usage:
 *   php start-web.php              # start on default port 8888
 *   php start-web.php --port=9000  # custom port
 *   php start-web.php --host=0.0.0.0 --port=8080
 *
 * Seeds the database automatically if it doesn't exist, then launches
 * PHP's built-in development server.  Press Ctrl+C to stop.
 */

// --- Args ---
$opts = getopt('', ['host::', 'port::']);
$host = $opts['host'] ?? 'localhost';
$port = (int) ($opts['port'] ?? 8888);

// --- Paths ---
$root       = __DIR__;
$dbPath     = $root . '/data/bench.sqlite';
$seedScript = $root . '/seed.php';
$docRoot    = $root . '/public';
$router     = $root . '/public/index-azera.php';

// --- Auto-seed if DB doesn't exist ---
if (!file_exists($dbPath)) {
    echo "Database not found, seeding (1000 rows)...\n";
    $php = PHP_BINARY;
    $cmd = escapeshellarg($php) . ' ' . escapeshellarg($seedScript);
    passthru($cmd, $exitCode);
    if ($exitCode !== 0) {
        echo "Seed failed (exit {$exitCode}). Aborting.\n";
        exit(1);
    }
}

// --- Start the built-in server ---
echo "Azera Competition — web server starting...\n";
echo "  URL:  http://{$host}:{$port}/\n";
echo "  Root: {$docRoot}\n";
echo "  PHP:  " . PHP_BINARY . " (" . PHP_VERSION . ")\n";
echo "  DB:   {$dbPath}\n";
echo "  Press Ctrl+C to stop.\n\n";

$php  = PHP_BINARY;
$argv = [
    '-d',
    'opcache.enable_cli=1',
    '-S',
    "{$host}:{$port}",
    '-t',
    $docRoot,
    $router,
];

// Cross-platform process launch.
// On Unix, pcntl_exec replaces the current process cleanly.
// On Windows, passthru runs the command in the foreground with stdio
// inheritance — simplest and most reliable across PHP/Windows versions.
$php         = PHP_BINARY;
$docRootReal = realpath($docRoot);
$routerReal  = realpath($router);

// Windows / no-pcntl: run in foreground, inherit stdio.
// Don't escapeshellarg the path arguments — PHP's built-in server on
// Windows misparses quoted router/doc-root paths ("Path cannot be empty").
// The paths are internal (from realpath) and contain no spaces in practice.
passthru(
    sprintf(
        '%s -d opcache.enable_cli=1 -S %s:%d -t %s %s',
        escapeshellarg($php),
        $host,
        $port,
        $docRootReal,
        $routerReal
    ),
    $exitCode
);
exit($exitCode);