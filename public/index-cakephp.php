<?php

/**
 * Web entry point for the CakePHP 5 benchmark app (router script for PHP's
 * built-in development server).
 *
 * Run with:
 *   php start-web-cakephp.php
 *   # or directly:
 *   php -S localhost:8885 -t public public/index-cakephp.php
 *
 * Then open http://localhost:8885/ in your browser.
 *
 * This is the stock Cake 5 serving mode: define the app-skel path constants
 * (normally webroot/index.php's job), boot the Application once per request,
 * then Server::run($request) → Response — same code path the benchmark
 * adapter drives in-process, minus the worker-mode machinery.
 *
 * Benchmark-layout adjustments vs a stock app skeleton:
 *  - App namespace is `App\Cake` (Configure App.namespace) because `App\`
 *    belongs to the azera app in the shared competition layout.
 *  - The DB path is baked into App\Cake\Db (shared data/bench.sqlite).
 *  - Server::bootstrap() runs the full bootstrap on EVERY request (FPM
 *   semantics); the app class already guards its once-per-process pieces.
 *
 * Routes (apps/cakephp/src/Application.php routes()):
 *   GET  /                — welcome page (Cake View)
 *   GET  /items           — ORM list with pagination
 *   GET  /items/1         — ORM item detail
 *   POST /items           — ORM upsert (JSON)
 *   GET  /items-qb, /items-qb/1 — query-builder list / detail
 *   POST /items-qb        — query-builder upsert (JSON)
 *   GET  /api/items, /api/items/1 — REST API (JSON)
 *   POST /api/items
 *   GET  /features, /features/<name> — feature demo endpoints
 */

declare(strict_types=1);

use App\Cake\Application as BenchApp;
use Cake\Http\Server;

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
$cakeCore = $root . 'vendor' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR;
$writable = $root . 'writable' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR;

// --- App-skel path constants (stock webroot/index.php defines these) --------
if (!is_dir($writable)) {
    @mkdir($writable, 0777, true);
}
if (!defined('CAKE_CORE_INCLUDE_PATH')) {
    define('CAKE_CORE_INCLUDE_PATH', $cakeCore);
    define('CORE_PATH', $cakeCore);
    define('CAKE', $cakeCore . 'src' . DIRECTORY_SEPARATOR);
    define('ROOT', $root);
    define('APP_DIR', 'cakephp');
    define('APP', $root . 'apps' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR);
    define('TMP', $writable);
    define('LOGS', $writable . 'logs' . DIRECTORY_SEPARATOR);
    define('CACHE', $writable . 'cache' . DIRECTORY_SEPARATOR);
}

// --- PSR-4 autoloader for the benchmark app namespace ------------------------
spl_autoload_register(function (string $class): void {
    $prefix   = 'App\\Cake\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file     = dirname(__DIR__) . '/apps/cakephp/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Global h()/pr()/pluginSplit() wrappers (opt-in file in Cake 5; templates
// call global h()).
require_once $cakeCore . 'src' . DIRECTORY_SEPARATOR . 'Core' . DIRECTORY_SEPARATOR . 'functions_global.php';

// --- App-class + template resolution (stock app/config/app.php sets these) --
// App\Cake namespace because plain App\ belongs to the azera app in the
// shared competition layout.
\Cake\Core\Configure::write('App.namespace', 'App\Cake');
\Cake\Core\Configure::write('App.paths.templates', [$root . 'apps' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR]);
// Response charset is string-typed; App.encoding must exist.
\Cake\Core\Configure::write('App.encoding', 'UTF-8');

// PHP's built-in server sets PHP_SELF to the REQUEST path (not the router
// script), so Cake's UriFactory::getBase() would compute base=/items for
// /items/9 and strip it from the route path ("A route matching /9 ...").
// Pin the base to '' — the app is served from the docroot root.
\Cake\Core\Configure::write('App.base', '');

// Shared SQLite connection for the Table ORM (matches App\Cake\Db).
\App\Cake\Db::init($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite');

if (!file_exists($root . 'data' . DIRECTORY_SEPARATOR . 'bench.sqlite')) {
    http_response_code(500);
    echo "Database not found. Run: php seed.php\n";
    exit(1);
}

// Initialize the typed static RouteCollection (Router::reload() is what a
// stock app's bootstrap reaches RoutingMiddleware through before any
// Router static access; the DB/config wiring below matches the adapter).
\Cake\Routing\Router::reload();

// I18n/Validator need real cache configs for the translator pool (a stock
// app gets these from config/app.php).
if (\Cake\Cache\Cache::getConfig('_cake_translations_') === null) {
    \Cake\Cache\Cache::setConfig('_cake_translations_', [
        'className' => 'Cake\Cache\Engine\ArrayEngine',
        'duration'  => '+10 seconds',
    ]);
}
if (\Cake\Cache\Cache::getConfig('_cake_core_') === null) {
    \Cake\Cache\Cache::setConfig('_cake_core_', [
        'className' => 'Cake\Cache\Engine\ArrayEngine',
        'duration'  => '+10 seconds',
    ]);
}

// --- Boot the application and serve the request ------------------------------
try {
    $server = new Server(new BenchApp($root . 'apps' . DIRECTORY_SEPARATOR . 'cakephp' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR));

    // Let Cake build the request from the real SAPI superglobals (no spoofing
    // needed — this is a real HTTP request).
    $response = $server->run();
    $server->emit($response);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo '500 ' . get_class($e) . ': ' . $e->getMessage() . "\n";
    exit(1);
}