<?php
/**
 * Web entry point for the Azera benchmark app.
 *
 * Run with PHP's built-in server:
 *
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

require __DIR__ . '/../vendor/autoload.php';

// PSR-4 autoloader for the App namespace (same as the adapter)
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
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

$ctx = \App\Bootstrap::boot($dbPath);

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