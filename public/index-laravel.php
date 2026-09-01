<?php

/**
 * Web entry point for the Laravel 12 benchmark app (router script for PHP's
 * built-in development server).
 *
 * Run with:
 *   php start-web-laravel.php
 *   # or directly:
 *   php -S localhost:8884 -t public public/index-laravel.php
 *
 * Then open http://localhost:8884/ in your browser.
 *
 * This is the stock Laravel serving mode: build the Application once per
 * request, resolve the HTTP Kernel, handle() the request built from the real
 * SAPI superglobals, then terminate() — same code path the benchmark adapter
 * drives in-process, minus the worker-mode machinery.
 *
 * Benchmark-layout adjustments vs a stock app skeleton:
 *  - App namespace is `App\Laravel` because `App\` belongs to the azera app
 *    in the shared competition layout.
 *  - The DB path is baked into apps/laravel/config/database.php
 *    (shared data/bench.sqlite).
 *
 * Routes (apps/laravel/routes/{web,api}.php):
 *   GET  /                — welcome page (Blade)
 *   GET  /items           — ORM list with pagination
 *   GET  /items/1         — ORM item detail
 *   POST /items           — ORM upsert (HTML + flash)
 *   GET  /items-qb, /items-qb/1 — query-builder list / detail
 *   POST /items-qb        — query-builder upsert (HTML + flash)
 *   GET  /api/items, /api/items/1 — REST API (JSON)
 *   POST /api/items
 *   GET  /features, /features/<name> — feature demo endpoints
 */

declare(strict_types=1);

// Standard guard for PHP's built-in server: let it serve real files from the
// docroot (none expected today, but keeps static assets working if added).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

$root     = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$writable = $root . 'writable' . DIRECTORY_SEPARATOR . 'laravel' . DIRECTORY_SEPARATOR;

// --- PSR-4 autoloader for the benchmark app namespace ------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Laravel\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = dirname(__DIR__) . '/apps/laravel/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// --- Runtime dirs Laravel requires before boot -------------------------------
foreach (['', 'cache', 'views', 'framework/views', 'framework/cache'] as $dir) {
    if (!is_dir($writable . $dir)) {
        @mkdir($writable . $dir, 0777, true);
    }
}
$bootstrapCache = $root . 'apps' . DIRECTORY_SEPARATOR . 'laravel' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($bootstrapCache)) {
    @mkdir($bootstrapCache, 0777, true);
}

if (!file_exists($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite')) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit(1);
}

// --- Boot the application and serve the request ------------------------------
try {
    $app = require $root . 'apps' . DIRECTORY_SEPARATOR . 'laravel' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'app.php';

    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

    // Let Laravel build the request from the real SAPI superglobals (no
    // spoofing needed — this is a real HTTP request).
    $request = Illuminate\Http\Request::capture();

    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '500 ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}