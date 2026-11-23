<?php

/**
 * Web entry point for the Phalcon 6 benchmark app.
 *
 * Run with:
 *   php start-web.php phalcon
 *   # or directly:
 *   php -S localhost:8887 -t public public/index-phalcon.php
 *
 * Phalcon 6 is pure PHP, so this is a plain composer package — there is no
 * extension to load and no build step. The boot happens here, per request,
 * which is the FPM shape: build the container, register the routes, compile the
 * template, handle the one request the process was started for.
 *
 * The in-process adapter drives the same `Bootstrap::handle()`; this file only
 * supplies the real SAPI superglobals and turns the returned body into a
 * response. Keeping one code path for both is what stops the FPM arm and the
 * worker arm from drifting into two subtly different benchmarks.
 */

require_once __DIR__ . '/../boot-probe.php';
require_once __DIR__ . '/../response-guard.php';
boot_probe_start();

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;

// PSR-4 autoloader for the benchmark app namespace. `App\` belongs to the azera
// app in the shared competition layout, so this one is `App\Phalcon\`.
spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'App\\Phalcon\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = $root . 'apps/phalcon/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

if (!file_exists($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite')) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit(1);
}

// Volt's compiled templates.
$cacheDir = $root . 'apps/phalcon/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0777, true);
}

try {
    $app = App\Phalcon\Bootstrap::boot($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite');

    // Boot complete: container built, routes registered, template compiled.
    boot_probe_record(bench_probe_kind(), 'phalcon');
    mem_probe_arm('phalcon');

    $body = $app->handle(
        (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'),
        (string) ($_SERVER['REQUEST_URI'] ?? '/'),
    );

    // Every endpoint in this app is HTML or JSON by construction; the body is
    // the response, and the transport sets the type. The status comes from the
    // shared rule so the FPM arm, the volucer arm and the RR arm agree on what
    // an error body is (see response-guard.php).
    $status = benchStatusFromBody($body);
    if ($status !== 200) {
        http_response_code($status);
    }

    header('Content-Type: text/html; charset=utf-8');
    echo $body;

    $app->cleanup();
} catch (Throwable $e) {
    http_response_code(500);
    echo '500 ' . get_class($e) . ': ' . $e->getMessage();
}
