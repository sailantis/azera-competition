<?php

declare(strict_types=1);

/**
 * Warm Laravel's route cache without an Artisan entry point.
 *
 * `php artisan route:cache` is the production deploy step, but apps/laravel has no
 * `artisan`: the bench app is booted by a plain `bootstrap/app.php`, not a console
 * kernel, so the command has nothing to run. This script performs the same three steps
 * the command does, so the artifact appears exactly where
 * `Application::routesAreCached()` looks for it:
 *
 *   1. boot the application and its console kernel,
 *   2. `refreshNameLookups()` / `refreshActionLookups()` and
 *      `prepareForSerialization()` on every route,
 *   3. `var_export($routes->compile())` into the `routes.stub` template at
 *      `Application::getCachedRoutesPath()`.
 *
 * WHY THIS MATTERS FOR THE BENCHMARK. Without it Laravel rebuilds its 124-route table on
 * every request while Symfony serves a compiled matcher from disk and Azera serves a
 * compiled table. The routing column would then measure which project had a cache file,
 * not which router is faster. The bench app's filler routes were changed from closures
 * to a controller method for the same reason: a Closure is the one route shape that
 * cannot be part of the compiled table.
 *
 * Run it before the server starts (scripts/deploy-lib.php::warmRouteCaches does, for the
 * per-app blocks) so the compile lands in the deploy step rather than in a timed row.
 *
 * Usage (from the repository root):
 *   php scripts/warm-laravel-routes.php
 */

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';
require_once $root . '/WebAppAdapter.php';
require_once $root . '/adapters/BenchmarkAutoloader.php';

// The bench app's namespace is mapped by the adapters, not by composer: a bare
// `require vendor/autoload.php` cannot resolve App\Laravel\Providers\AppServiceProvider.
BenchmarkAutoloader::map('App\\Laravel\\', $root . '/apps/laravel/app');

// --- Clear the cache BEFORE booting --------------------------------------------
//
// `bootstrap/app.php` registers its routes through `withRouting(using: ...)`, and
// Laravel SKIPS that callback entirely when a compiled routes file exists — in that
// case it loads the compiled table instead. So a warmer that boots with the old
// cache still in place re-exports the OLD routes and the new ones never appear.
//
// That is not hypothetical: after `GET /echo` was added, this warmer ran, reported
// success, wrote a fresh-looking 133 KB table, and the FPM arm 404'd on `/echo`
// because the table it re-exported contained no such route. The compiled file's
// mtime was new and its contents were a copy of the previous generation — the worst
// kind of failure, since every signal said the cache had been rebuilt.
//
// This is the `route:clear` half of what `route:cache` does, and it has to happen
// before the application boots for the same reason the command runs it first.
$cacheDir = $root . '/apps/laravel/bootstrap/cache';
foreach (glob($cacheDir . '/routes-*.php') ?: [] as $stale) {
    // A file that cannot be removed will be silently reloaded, so a failure here is
    // fatal rather than a warning: the run would otherwise measure a stale table.
    if (!@unlink($stale)) {
        fwrite(STDERR, "  laravel: could not remove the stale route cache {$stale}\n");
        exit(1);
    }
}

$app = require $root . '/apps/laravel/bootstrap/app.php';

$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routes = $app['router']->getRoutes();
$routes->refreshNameLookups();
$routes->refreshActionLookups();

$count = count($routes);
if ($count === 0) {
    fwrite(STDERR, "  laravel: no routes registered — refusing to write an empty cache\n");
    exit(1);
}

foreach ($routes as $route) {
    $route->prepareForSerialization();
}

$stub = (string) file_get_contents(
    $root . '/vendor/laravel/framework/src/Illuminate/Foundation/Console/stubs/routes.stub'
);
$code = str_replace('{{routes}}', var_export($routes->compile(), true), $stub);

$path = $app->getCachedRoutesPath();
$dir  = dirname($path);
if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
    fwrite(STDERR, "  laravel: cannot create {$dir}\n");
    exit(1);
}

if (@file_put_contents($path, $code) === false) {
    fwrite(STDERR, "  laravel: cannot write {$path}\n");
    exit(1);
}

// The verification is on the ARTIFACT, not on routesAreCached().
//
// routesAreCached() memoizes its answer the first time it runs — during provider
// registration, inside the very boot above — so on a first run it reports the state
// that existed BEFORE this script wrote the file. It answers "was there a cache when
// this app booted", not "is there one now", and using it here made a successful warm
// look like a failure. The table takes effect on the NEXT boot, which is the server's.
$written = is_file($path) ? (int) filesize($path) : 0;
if ($written <= 0) {
    fwrite(STDERR, "  laravel: {$path} is missing or empty after the write\n");
    exit(1);
}

printf(
    "  laravel: %d routes, %s bytes, cached at %s\n",
    $count,
    number_format($written),
    substr($path, strlen($root) + 1),
);

exit(0);
