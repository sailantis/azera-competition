<?php

declare(strict_types=1);

/**
 * Smoke test (child process of smoke.php) — runs ONE app and exits with the
 * failure count.
 *
 * smoke.php spawns this per app because full-stack frameworks define
 * function_exists-guarded global helpers with colliding names (config(),
 * view(), env(), ...) — two frameworks cannot share one PHP process.
 */

$key = $argv[1] ?? 'azera';

// CI4's global helpers (config(), view(), env(), ...) are function_exists-
// guarded and collide with Laravel's, which composer's `files` autoload
// includes eagerly on vendor/autoload.php. For codeigniter, pre-load CI4's
// Common.php BEFORE the composer autoloader so its helpers win the race and
// Laravel's guarded definitions simply skip the taken names.
if ($key === 'codeigniter') {
    require_once __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WebAppAdapter.php';

$map = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
];

if (!isset($map[$key])) {
    fwrite(STDERR, "Unknown adapter: {$key}\n");
    exit(1);
}

require_once __DIR__ . "/adapters/{$map[$key]}.php";

$checks = [
    'GET /',
    'GET /items',
    'GET /items/1',
    'POST /items',
    'GET /items-qb',
    'GET /items-qb/1',
    'POST /items-qb',
    'GET /api/items',
    'GET /api/items/1',
    'POST /api/items',
    'GET /features/aop',
    'GET /features/cache',
    'GET /features/log',
    'GET /features/retry',
    'GET /features/pipeline',
    'GET /features/db-events',
    'GET /features/events',
    'GET /features/validation',
    'GET /features/config',
    'GET /features/request-scoped',
    'GET /features/rate-limit',
];

$adapter = new $map[$key]();
$adapter->bootstrap();

$failures = 0;

foreach ($checks as $req) {
    [$method, $uri] = explode(' ', $req, 2);

    $t0 = hrtime(true);
    try {
        $body = $adapter->dispatch($method, $uri);
        $err  = null;
    } catch (Throwable $e) {
        $body = '';
        $err  = get_class($e) . ': ' . $e->getMessage();
    }
    $ms = (hrtime(true) - $t0) / 1e6;

    $bad = $err !== null || $body === '' || str_starts_with($body, '500 ') || str_starts_with($body, 'Not Found');

    if ($bad) {
        $failures++;
        printf("[FAIL] %-28s %7.2fms  %s\n", $req, $ms, $err ?? substr(str_replace("\n", ' ', $body), 0, 140));
    } else {
        printf("[ OK ] %-28s %7.2fms  %s\n", $req, $ms, substr(str_replace("\n", ' ', strip_tags($body)), 0, 60));
    }
}

echo $failures === 0 ? "\nAll smoke checks passed.\n" : "\n{$failures} failure(s).\n";
exit($failures === 0 ? 0 : 1);