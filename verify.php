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
    'GET /items-orm'               => '<table>',
    'GET /items-orm/1'             => 'Item 1',
    'POST /items-orm'              => 'flash',
    'GET /features/aop'            => '"new_id"',
    'GET /features/cache'          => '"item_count"',
    'GET /features/log'            => '"log_entries"',
    'GET /features/retry'          => '"result"',
    'GET /features/pipeline'       => '"log_entries"',
    'GET /features/events'         => '"listener_log"',
    'GET /features/validation'     => '"valid_payload"',
    'GET /features/config'         => '"feature"',
    'GET /features/db-events'      => '"events"',
    'GET /features/orm'            => '"report"',
    'GET /features/orm-hydrate'    => '"report"',
    'GET /features/request-scoped' => '"count_after"',
    'GET /features/rate-limit'     => '"allowed"',
];

$failures = 0;

// Each app is verified in its own fresh PHP process (verify-app.php child):
// full-stack frameworks define function_exists-guarded global helpers with
// colliding names (config(), view(), env(), ...), so two frameworks can
// never share one process — whichever loads first shadows the other.
foreach ($apps as $key) {
    $key = trim($key);
    if (!isset($adapterClasses[$key])) {
        echo "Unknown adapter: {$key}\n";
        $failures++;
        continue;
    }

    $tmpJson = tempnam(sys_get_temp_dir(), 'verify-') . '.json';
    $cmd     = sprintf(
        '%s %s --app=%s --out-json=%s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/verify-app.php'),
        escapeshellarg($key),
        escapeshellarg($tmpJson),
    );

    passthru($cmd, $childExit);
    $failures += $childExit;
    if (is_file($tmpJson)) {
        unlink($tmpJson);
    }
}

echo $failures === 0
    ? "\nAll adapters passed parity.\n"
    : "\n{$failures} parity failure(s).\n";

exit($failures === 0 ? 0 : 1);