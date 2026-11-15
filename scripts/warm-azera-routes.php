<?php

declare(strict_types=1);

/**
 * Compile the Azera bench app's route table, once, from the CLI.
 *
 * This is the deploy step. Declaring routes inside `cached()` means the first read
 * writes the table, so a single bootstrap is all it takes — the same work
 * `azera routes:cache` performs, and it lands BEFORE the server starts, so no timed row
 * carries the compile.
 *
 * It exists because the comparison must be "same deploy step for every entrant", not
 * "whoever happens to have a cache file in their tree wins the routing column". Symfony
 * ships its matcher as a disk cache, Laravel has `route:cache`, Azera has this; the
 * frameworks with no persisted route table are reported as such rather than silently
 * penalised.
 *
 * Idempotent: with a fresh table it prints the status and writes nothing.
 *
 * Usage (from the repository root):
 *   php scripts/warm-azera-routes.php
 */

$root = dirname(__DIR__);

require_once $root . '/vendor/autoload.php';
require_once $root . '/WebAppAdapter.php';
require_once $root . '/adapters/BenchmarkAutoloader.php';
require_once $root . '/adapters/AzeraAdapter.php';

BenchmarkAutoloader::map('App\\Azera\\', $root . '/apps/azera');

// boot() builds the container and declares the cached() units. compileCache() is the
// documented deploy-step API and is what `azera routes:cache` calls: it builds the table
// from the units, writes it, and returns the route count. The first match() would also
// write it as a side effect, but only when the framework's own write guard allows --
// relying on that made this script report "0 routes, not written" while the table was
// silently deferred, which is exactly the failure it exists to catch.
$adapter = new AzeraAdapter();
$adapter->bootstrap();

$router = \Azera\AppContext::instance()->router();

// compileCache() throws when the framework REFUSES to write the table -- e.g. a
// group-writable cache directory -- rather than writing nothing. That refusal is the
// interesting case for a benchmark: the cache still works, the table is just rebuilt
// per request, so the run measures no cache at all while claiming one. Report the
// framework's own reason instead of a bare "STALE".
try {
    $routes = $router->compileCache();
} catch (\Throwable $e) {
    fwrite(STDERR, '  azera: route cache was NOT written: ' . $e->getMessage() . "\n");
    exit(1);
}

$status = $router->cacheStatus();
$path   = $router->cachePath();

if ($status['path'] === null) {
    fwrite(STDERR, "  azera: no route cache path — is the app's cached() declaration missing?\n");
    exit(1);
}

printf(
    "  azera: %d routes compiled, %s, %s\n",
    $routes,
    $status['exists'] ? (number_format((int) @filesize($path)) . ' bytes') : 'not written',
    $status['fresh'] ? 'fresh' : 'STALE',
);

// A stale or missing table after a bootstrap means the cache did not engage, and the
// benchmark would then measure a rebuild per request while claiming a cache. Fail
// loudly: a run that silently loses its deploy step is worse than no run.
if (!$status['exists'] || !$status['fresh']) {
    fwrite(STDERR, "  azera: route cache did not warm ({$path})\n");
    exit(1);
}

exit(0);
