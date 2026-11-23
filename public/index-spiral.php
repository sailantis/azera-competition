<?php
/**
 * Web entry point for the Spiral benchmark app (router script for PHP's
 * built-in development server).
 *
 * Run with:
 *   php start-web.php spiral
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

// Boot probe: the clock MUST start before any framework code loads.
require_once __DIR__ . '/../boot-probe.php';
boot_probe_start();

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

// Drop Stempler's compiled views when they were built from ANOTHER checkout.
// Stempler records the absolute source path inside each compiled template, so a
// moved/copied repo keeps pointing at the old one and every HTML route dies with
// FileNotFoundException for a file that exists right here. Mirrors the guard in
// SpiralAdapter::clearForeignViewCache() — see that docblock for the details.
(function (string $root): void {
    $dir = $root . 'runtime/cache/views';
    if (!is_dir($dir)) {
        return;
    }

    $current = str_replace('\\', '/', (string) realpath($root));

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
    );
    foreach ($it as $file) {
        if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
            continue;
        }

        $src = (string) file_get_contents($file->getPathname());
        if (preg_match_all("#(['\"])([^'\"]+\.dark\.php)\\1#", $src, $m) === false) {
            continue;
        }

        foreach ($m[2] as $path) {
            if (!str_starts_with(str_replace('\\', '/', $path), $current)) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST,
                );
                foreach ($files as $f) {
                    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
                }
                @rmdir($dir);

                return;
            }
        }
    }
})($root);

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

    // Boot complete: all bootloaders ran, the container is built. This
    // callback is the kernel's own boot-complete hook.
    boot_probe_record(bench_probe_kind(), 'spiral');
    mem_probe_arm('spiral');
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

// --- Dispatch the real HTTP request through the framework's dispatcher ------
$request = ServerRequestFactory::fromGlobals();

try {
    // Spiral\Http\Http, not RouterInterface::handle().
    //
    // The router alone skips the middleware pipeline, the CurrentRequest binding
    // and ErrorHandlerMiddleware, so a route miss escaped as an exception and this
    // file had to hand-roll a 404 page. Http::handle() is what Spiral's own
    // workers call; the adapter makes the same call, which keeps the FPM arm and
    // the resident arm on one code path.
    $response = $container->runScope(
        new Scope(name: Spiral\Framework\Spiral::Http->value),
        static function (Container $c) use ($request): ResponseInterface {
            return $c->get(\Spiral\Http\Http::class)->handle($request);
        },
    );
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