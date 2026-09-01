<?php

/**
 * Parity checker (child process of verify.php) — verifies ONE app and
 * reports the result as JSON via --out-json.
 *
 * verify.php spawns this per app because full-stack frameworks define
 * function_exists-guarded global helpers with colliding names (config(),
 * view(), env(), ...) — two frameworks cannot share one PHP process.
 */

require_once __DIR__ . '/WebAppAdapter.php';

$opts   = getopt('', ['app::', 'out-json::']);
$appKey = trim((string) ($opts['app'] ?? 'azera'));

// CI4's global helpers (config(), view(), env(), ...) are function_exists-
// guarded and collide with Laravel's, which composer's `files` autoload
// would load first. In this child process only ONE framework runs — for
// codeigniter, pre-load CI4's Common.php so its helpers win the race and
// Laravel's guarded definitions simply skip the taken names.
if ($appKey === 'codeigniter') {
    require_once __DIR__ . '/vendor/codeigniter4/framework/system/Common.php';
}

require_once __DIR__ . '/vendor/autoload.php';

$adapterClasses = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
];

// Expected substring per endpoint (loose — we check that key content appears).
// Keep in sync with verify.php.
$expect = [
    'GET /'                        => 'Welcome',
    'GET /items'                   => '<table>',
    'GET /items/1'                 => 'Item 1',
    'POST /items'                  => 'flash',
    'GET /items-qb'                => '<table>',
    'GET /items-qb/1'              => 'Item 1',
    'POST /items-qb'               => 'flash',
    'GET /features/aop'            => '"new_id"',
    'GET /features/cache'          => '"item_count"',
    'GET /features/log'            => '"log_entries"',
    'GET /features/retry'          => '"result"',
    'GET /features/pipeline'       => '"log_entries"',
    'GET /features/events'         => '"listener_log"',
    'GET /features/validation'     => '"valid_payload"',
    'GET /features/config'         => '"feature"',
    'GET /features/db-events'      => '"events"',
    'GET /features/request-scoped' => '"count_after"',
    'GET /features/rate-limit'     => '"allowed"',
];

$key         = trim((string) ($opts['app'] ?? 'azera'));
$outJsonPath = $opts['out-json'] ?? null;

if (!isset($adapterClasses[$key])) {
    echo "Unknown adapter: {$key}\n";
    $result = ['app' => $key, 'failures' => 1, 'lines' => ["[FAIL] unknown adapter {$key}"]];
    if (is_string($outJsonPath) && $outJsonPath !== '') {
        file_put_contents($outJsonPath, json_encode($result));
    }
    exit(1);
}

$class = $adapterClasses[$key];
require_once __DIR__ . "/adapters/{$class}.php";

$lines    = [];
$failures = 0;

echo "\n=== {$key} ===\n";
/** @var WebAppAdapter $adapter */
$adapter = new $class();
$adapter->bootstrap();

foreach ($expect as $req => $needle) {
    [$method, $uri] = explode(' ', $req, 2);
    try {
        $body = $adapter->dispatch($method, $uri);
    } catch (\Throwable $e) {
        echo "  [FAIL] {$req} — threw: " . $e->getMessage() . "\n";
        $failures++;
        continue;
    }

    if (stripos($body, $needle) === false) {
        echo "  [FAIL] {$req} — expected substring '{$needle}' not found\n";
        echo "         got: " . substr($body, 0, 200) . "\n";
        $failures++;
        continue;
    }

    echo "  [ OK ] {$req}\n";
}

$result = ['app' => $key, 'failures' => $failures, 'lines' => $lines];
if (is_string($outJsonPath) && $outJsonPath !== '') {
    file_put_contents($outJsonPath, json_encode($result));
}

exit($failures === 0 ? 0 : 1);