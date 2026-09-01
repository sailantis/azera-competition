<?php

/**
 * Web server starter for the Symfony benchmark app.
 *
 * Usage:
 *   php start-web-symfony.php             # start on default port 8883
 *   php start-web-symfony.php --port=9000 # custom port
 *   php start-web-symfony.php --host=0.0.0.0 --port=8080
 *
 * Seeds the shared benchmark database automatically if it doesn't exist,
 * then launches PHP's built-in development server with Symfony's router
 * script. Press Ctrl+C to stop.
 *
 * (Sibling starters: start-web-azera.php 8888, start-web-spiral.php 8887,
 *  start-web-codeigniter.php 8886, start-web-cakephp.php 8885,
 *  start-web-laravel.php 8884.)
 */

// --- Args ---
$opts = getopt('', ['host::', 'port::']);
$host = $opts['host'] ?? 'localhost';
$port = (int) ($opts['port'] ?? 8883);

// --- Paths ---
$root       = __DIR__;
$dbPath     = $root . '/data/bench.sqlite';
$seedScript = $root . '/seed.php';
$docRoot    = $root . '/public';
$router     = $root . '/public/index-symfony.php';

// --- Auto-seed if DB doesn't exist (shared DB with the other apps) ---
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
echo "Azera Competition — Symfony web server starting...\n";
echo "  URL:  http://{$host}:{$port}/\n";
echo "  Root: {$docRoot}\n";
echo "  PHP:  " . PHP_BINARY . " (" . PHP_VERSION . ")\n";
echo "  DB:   {$dbPath}\n";
echo "  Press Ctrl+C to stop.\n\n";

// Cross-platform process launch.
// On Windows, passthru runs the command in the foreground with stdio
// inheritance — simplest and most reliable across PHP/Windows versions.
// Don't escapeshellarg the doc-root/router paths — PHP's built-in server on
// Windows misparses quoted router/docroot paths ("Path cannot be empty").
// The paths are internal (from realpath) and contain no spaces in practice.
$php         = PHP_BINARY;
$docRootReal = realpath($docRoot);
$routerReal  = realpath($router);

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