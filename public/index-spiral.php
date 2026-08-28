<?php
/**
 * Web entry point for the Spiral benchmark app (router script for PHP's
 * built-in development server).
 *
 * Run with:
 *   php start-web-spiral.php
 *   # or directly:
 *   php -S localhost:8887 -t public public/index-spiral.php
 *
 * Then open http://localhost:8887/ in your browser.
 *
 * Routes (defined in apps/spiral/src/Bootloader/AppRoutesBootloader.php):
 *   GET  /                — welcome page (Stempler)
 *   GET  /items           — ORM list with pagination
 *   GET  /items/1         — ORM item detail
 *   POST /items           — ORM upsert (JSON)
 *   GET  /items-qb, /items-qb/1 — query-builder list / detail
 *   POST /items-qb        — query-builder upsert (JSON)
 *   GET  /api/items, /api/items/1 — REST API (JSON)
 *   POST /api/items
 *   GET  /features, /features/<name> — feature demo pages
 */

declare(strict_types=1);

use Laminas\Diactoros\ServerRequestFactory;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use Psr\Http\Message\ResponseInterface;
use Spiral\Boot\Environment;
use Spiral\Core\Container;
use Spiral\Core\Scope;
use Spiral\Router\Exception\RouteNotFoundException;
use Spiral\Router\RouterInterface;

require __DIR__ . '/../vendor/autoload.php';

// Standard guard for PHP's built-in server: let it serve real files from the
// docroot (none expected today, but keeps static assets working if added).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

// PSR-4 autoloader for the Spiral benchmark app namespace (same as the adapter).
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\Spiral\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = __DIR__ . '/../apps/spiral/src/' . str_replace('\\', '/', $relative) . '.php';
    //if (is_file($file)) {
    require $file;
    //}
});

$root = dirname(__DIR__) . '/';

if (!file_exists($root . 'data/bench.sqlite')) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit;
}

// --- Boot the Spiral kernel (same boot sequence as SpiralAdapter) ----------
$kernel = App\Spiral\Kernel::create(
    directories: [
        'root' => $root,
        'app'  => $root . 'apps/spiral/',
    ],
    handleErrors: false
);

$container = null;
$kernel->bootstrapped(static function () use (&$container, $kernel): void {
    // AbstractKernel::$container is protected readonly — read it via
    // reflection once boot completes (no public accessor exists).
    $prop      = new ReflectionProperty(Spiral\Boot\AbstractKernel::class, 'container');
    $container = $prop->getValue($kernel);
});

$kernel->run(new Environment([
    'APP_DEBUG'               => false,
    'VIEW_CACHE'              => true,
    'CYCLE_SCHEMA_CACHE'      => true,
    'TOKENIZER_CACHE_TARGETS' => true,
]));

if (!$container instanceof Container) {
    http_response_code(500);
    echo "Spiral kernel failed to boot. Check the server console for details.\n";
    exit;
}

// --- Dispatch the real HTTP request through the router ---------------------
$request = ServerRequestFactory::fromGlobals();

try {
    // Same dispatch path as SpiralAdapter: route handling inside the `http`
    // scope (where CurrentRequest / request bindings live).
    $response = $container->runScope(
        new Scope(name: 'http'),
        static function (Container $c) use ($request): ResponseInterface {
            return $c->get(RouterInterface::class)->handle($request);
        },
    );
} catch (RouteNotFoundException) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    echo '<h1>404 Not Found</h1>';
    echo "<p>No route matched: {$method} {$path}</p>";
    echo '<p><a href="/">Go home</a></p>';
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '500 ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit;
}

if (!$response instanceof ResponseInterface) {
    http_response_code(500);
    echo "Router returned no response.\n";
    exit;
}

(new SapiEmitter())->emit($response);