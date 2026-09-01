<?php

declare(strict_types=1);

/**
 * Smoke test driver: boot + hit the benchmark endpoints once, per app.
 *
 * Each app runs in its own fresh PHP process (smoke-app.php child) because
 * full-stack frameworks define function_exists-guarded global helpers with
 * colliding names (config(), view(), env(), ...) — two frameworks cannot
 * share one PHP process.
 *
 * Usage: php smoke.php <adapterKey>
 */

require_once __DIR__ . '/vendor/autoload.php';

$key = $argv[1] ?? 'azera';

$known = [
    'azera',
    'laravel',
    'symfony',
    'spiral',
    'codeigniter',
    'cakephp',
];

if (!in_array($key, $known, true)) {
    fwrite(STDERR, "Unknown adapter: {$key}\n");
    exit(1);
}

$cmd = sprintf(
    '%s %s %s',
    escapeshellarg(PHP_BINARY),
    escapeshellarg(__DIR__ . '/smoke-app.php'),
    escapeshellarg($key),
);

passthru($cmd, $exitCode);
exit($exitCode);