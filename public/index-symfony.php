<?php

/**
 * Web entry point for the Symfony benchmark app (router script for PHP's
 * built-in development server).
 *
 * Run with:
 *   php start-web.php symfony
 *   # or directly:
 *   php -S localhost:8883 -t public public/index-symfony.php
 *
 * Then open http://localhost:8883/ in your browser.
 *
 * This is the stock Symfony serving mode: build the Kernel once per
 * request, boot it, handle() the request built from the real SAPI
 * superglobals, then terminate() — same code path the benchmark adapter
 * drives in-process, minus the worker-mode machinery.
 *
 * Benchmark-layout adjustments vs a stock app skeleton:
 *  - App namespace is `App\Symfony` because `App\` belongs to the azera app
 *    in the shared competition layout.
 *  - The DB path is baked into config/packages/doctrine.yaml
 *    (shared data/bench.sqlite).
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

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;

// --- PSR-4 autoloader for the benchmark app namespace ------------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Symfony\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = dirname(__DIR__) . '/apps/symfony/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// --- Runtime dirs Symfony requires before boot -------------------------------
$writable = $root . 'writable' . DIRECTORY_SEPARATOR . 'symfony' . DIRECTORY_SEPARATOR;
foreach (['', 'twig', 'doctrine', 'doctrine/proxies', 'doctrine/cache'] as $dir) {
    if (!is_dir($writable . $dir)) {
        @mkdir($writable . $dir, 0777, true);
    }
}

if (!file_exists($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite')) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit(1);
}

// --- Boot the kernel and serve the request ----------------------------------
try {
    $kernel = new App\Symfony\Kernel('bench', false);
    $kernel->boot();

    $request  = Symfony\Component\HttpFoundation\Request::createFromGlobals();
    $response = $kernel->handle($request);
    $response->send();
    $kernel->terminate($request, $response);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '500 ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}