<?php
/**
 * Web entry point for the Azera benchmark app.
 *
 * Run with PHP's built-in server:
 *
 *   php start-web.php azera
 *   # or directly:
 *   php -S localhost:8888 -t public public/index-azera.php
 *
 * Then open http://localhost:8888/ in your browser.
 *
 * Routes:
 *   GET  /            — plain "Hello"
 *   GET  /items       — list all items (table view)
 *   GET  /items/1     — single item detail
 *   POST /items       — create a new item (use curl or a form)
 */

// Boot probe: the clock MUST start as the first statement so the measurement
// covers "PHP start → framework ready" (see boot-probe.php).
require_once __DIR__ . '/../boot-probe.php';
boot_probe_start();

require __DIR__ . '/../vendor/autoload.php';

// PSR-4 autoloader for the App\Azera namespace (same mapping the adapter
// registers via BenchmarkAutoloader). The prefix MUST be the full namespace
// root the app declares: apps/azera/Bootstrap.php is `namespace App\Azera`,
// so stripping only 'App\' resolved App\Azera\Bootstrap to
// apps/azera/Azera/Bootstrap.php — a path that does not exist. FPM served a
// 500 on every request.
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Azera\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = __DIR__ . '/../apps/azera/' . str_replace('\\', '/', $relative) . '.php';
    //if (is_file($file)) {
    require $file;
    //}
});

$dbPath = __DIR__ . '/../data/bench.sqlite';

if (!file_exists($dbPath)) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit;
}

$ctx = \App\Azera\Bootstrap::boot($dbPath);

// Boot complete: container built, providers registered, routes loaded.
boot_probe_record('fpm', 'azera');

// FPM memory probe: arms a shutdown hook that appends one sample when this
// request finishes (see boot-probe.php for what each field means and why the
// write is gated on a header the timed loop never sends).
mem_probe_arm('azera');

$path   = $ctx->request()->path();
$method = $ctx->request()->method();

$route = $ctx->router()->match($path, $method);

if ($route === null) {
    http_response_code(404);
    echo "<h1>404 Not Found</h1>";
    echo "<p>No route matched: {$method} {$path}</p>";
    echo '<p><a href="/">Go home</a></p>';
    exit;
}

$ctx->dispatcher()->dispatch($route)->send();
