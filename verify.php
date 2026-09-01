<?php
/**
 * Functional parity checker.
 *
 * Runs each adapter's 4 benchmark endpoints once and asserts that the
 * output contains the expected substring.  Fails fast on any mismatch so
 * you can catch parity regressions before running a long benchmark.
 *
 * Usage:
 *   php verify.php                 # verify all adapters
 *   php verify.php --apps=azera    # verify a subset
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WebAppAdapter.php';

$opts = getopt('', ['apps::']);
$apps = isset($opts['apps']) ? explode(',', $opts['apps']) : ['azera'];

$adapterClasses = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
];

// Expected substring per endpoint (loose — we check that key content appears).
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

$failures = 0;

foreach ($apps as $key) {
    $key = trim($key);
    if (!isset($adapterClasses[$key])) {
        echo "Unknown adapter: {$key}\n";
        $failures++;
        continue;
    }

    $class = $adapterClasses[$key];
    require_once __DIR__ . "/adapters/{$class}.php";

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
}

echo $failures === 0
    ? "\nAll adapters passed parity.\n"
    : "\n{$failures} parity failure(s).\n";

exit($failures === 0 ? 0 : 1);