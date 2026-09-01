<?php

/**
 * Web entry point for the CodeIgniter 4 benchmark app (router script for
 * PHP's built-in development server).
 *
 * Run with:
 *   php start-web-codeigniter.php
 *   # or directly:
 *   php -S localhost:8886 -t public public/index-codeigniter.php
 *
 * Then open http://localhost:8886/ in your browser.
 *
 * Boot sequence mirrors the stock vendor/codeigniter4/framework/public/index.php
 * (check PHP version → FCPATH → Config\Paths → Boot::bootWeb()), with two
 * benchmark-layout adjustments:
 *
 *  1. APP_NAMESPACE must be defined BEFORE Boot::loadConstants()/Constants.php
 *     runs, because our Config\Autoload maps `Ci4App` (the plain `App\`
 *     namespace belongs to the azera app in the shared competition layout).
 *     bootWeb() skips loadConstants() when APP_NAMESPACE is already defined
 *     and the adapter-style require of the vendor Constants.php fills in
 *     COMPOSER_PATH + the timing/exit-code constants.
 *  2. The database path is already baked into Config\Database (shared
 *     data/bench.sqlite), so no post-boot config surgery is needed.
 *
 * Unlike the in-proc adapter (which uses run(null, true) + worker resets),
 * this is a plain per-request FPM-style boot: CodeIgniter::run() sends the
 * response and the process ends — the most idiomatic CI4 serving mode.
 *
 * Routes (apps/codeigniter/Config/Routes.php):
 *   GET  /                — welcome page (CI4 View)
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

use CodeIgniter\Boot;
use Config\Paths;

// Standard guard for PHP's built-in server: let it serve real files from the
// docroot (none expected today, but keeps static assets working if added).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;
    if ($path !== '/' && is_file($file)) {
        return false;
    }
}

// --- CHECK PHP VERSION (stock index.php preamble) ---------------------------
$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    exit(1);
}

// --- SET THE CURRENT DIRECTORY ----------------------------------------------
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// --- BENCHMARK-LAYOUT CONSTANTS (before any framework file loads) -----------
// Heavy parity with the in-process adapter's bootFramework(): the constants
// must exist before Config\* classes (which reference APPPATH/WRITEPATH in
// constant expressions) are autoloaded.
require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__) . DIRECTORY_SEPARATOR;
$appDir = $root . 'apps' . DIRECTORY_SEPARATOR . 'codeigniter' . DIRECTORY_SEPARATOR;
$writeDir = $root . 'writable' . DIRECTORY_SEPARATOR . 'ci4' . DIRECTORY_SEPARATOR;

foreach ([$writeDir, $writeDir . 'cache', $writeDir . 'logs', $writeDir . 'session'] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

if (!defined('APP_NAMESPACE')) {
    define('APP_NAMESPACE', 'Ci4App');
}
if (!defined('ROOTPATH')) {
    define('ROOTPATH', $root);
}
if (!defined('APPPATH')) {
    define('APPPATH', $appDir);
}
if (!defined('SYSTEMPATH')) {
    define('SYSTEMPATH', $root . 'vendor' . DIRECTORY_SEPARATOR . 'codeigniter4' . DIRECTORY_SEPARATOR . 'framework' . DIRECTORY_SEPARATOR . 'system' . DIRECTORY_SEPARATOR);
}
if (!defined('WRITEPATH')) {
    define('WRITEPATH', $writeDir);
}
if (!defined('TESTPATH')) {
    define('TESTPATH', $root . 'tests' . DIRECTORY_SEPARATOR);
}
if (!defined('ENVIRONMENT')) {
    define('ENVIRONMENT', 'production');
}
if (!defined('CI_DEBUG')) {
    define('CI_DEBUG', false);
}

// Framework constants (COMPOSER_PATH, timing + exit-code constants).
// bootWeb() skips loadConstants() because APP_NAMESPACE is pre-defined; the
// require is guarded so CI_DEBUG/ENVIRONMENT from above win.
require_once SYSTEMPATH . '..' . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR . 'Constants.php';

// --- BOOT THE APPLICATION (stock Boot::bootWeb sequence) ---------------------
// Composer does not map the `Config\` namespace (CI4's autoloader registers
// it during boot) — the stock index.php likewise requires Paths.php manually.
require_once APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Paths.php';

$paths = new Paths();

exit(Boot::bootWeb($paths));