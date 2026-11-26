<?php
/**
 * Functional parity checker.
 *
 * Runs each app's endpoints once (in a per-app child process, verify-app.php)
 * and asserts the response is a success and contains the expected content.
 * The expectation table lives there, ONCE: this file used to carry a second,
 * unread copy that had already drifted — it omitted `GET /features/aop-tx-direct`,
 * so the control row of the AOP pair was verified by nobody.
 *
 * An endpoint an app is deliberately excluded from
 * (BenchmarkConfig::excludedRequests()) is skipped, not failed — the child
 * applies the same map the measurement uses.
 *
 * Usage:
 *   php verify.php                 # verify every adapters (the documented gate)
 *   php verify.php --apps=azera    # verify a subset
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/WebAppAdapter.php';
require_once __DIR__ . '/bench-view-globals.php';

$opts = getopt('', ['apps::']);

// Default to EVERY adapter, as the docblock and README's "Verify functional
// parity across all adapters" both promise. Defaulting to azera alone meant the
// documented gate checked one of seven entrants: it reported "All adapters
// passed parity" after booting a single app, and Phalcon's deliberate
// `/features/aop-tx` 404 plus CodeIgniter's fatal both sat outside its reach until
// someone remembered `--apps=`.
$apps = isset($opts['apps'])
    ? explode(',', $opts['apps'])
    : ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp', 'phalcon'];

$adapterClasses = [
    'azera'       => 'AzeraAdapter',
    'laravel'     => 'LaravelAdapter',
    'symfony'     => 'SymfonyAdapter',
    'spiral'      => 'SpiralAdapter',
    'codeigniter' => 'CodeIgniterAdapter',
    'cakephp'     => 'CakePhpAdapter',
    'phalcon'     => 'PhalconAdapter',
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
