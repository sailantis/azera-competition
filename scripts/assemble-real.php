<?php

declare(strict_types=1);

/**
 * Assemble the canonical real-deployment dataset from PER-APP run-http.php
 * outputs — the counterpart of merge-app.php for building a dataset from
 * scratch instead of replacing one app's block in an existing one.
 *
 * Why this exists: the real-deployment run is slow (nginx+FPM runs the app's
 * entry script per request, and each row includes that boot), so apps are
 * measured one at a time. Each run writes a single-app dataset (temp/real-<app>.json) whose
 * `floors` block holds the one-time webserver-overhead probes. This script
 * concatenates the app blocks in canonical order and keeps the floor probes
 * from the FIRST run (they are per server, not per app).
 *
 * Usage:
 *   php temp/assemble-real.php results/real-deployments.json \
 *       temp/real-azera.json temp/real-laravel.json ...
 *
 * Guards (a partially-assembled dataset must never reach the report tooling):
 *   - every input must hold exactly one app block
 *   - all inputs must agree on php_version / opcache / sapi (same as
 *     merge-app.php's comparability check)
 *   - every app must offer the SAME mode => request-count shape
 */

$args = array_slice($argv, 1);
if (count($args) < 3) {
    fwrite(STDERR, "Usage: php temp/assemble-real.php <out.json> <in1.json> <in2.json> [...]\n");
    exit(1);
}
$outPath = array_shift($args);

// budgetOfMode()/budgetsByAppMode()/assertUniformBudget() — see bench-lib.php.
require_once __DIR__ . '/bench-lib.php';
// assertEnvComparable() — the shared runtime-comparability check.
require_once __DIR__ . '/env-sanity.php';

$san = static fn(array $d): array => envSanity($d);

$combined   = null;
$refEnv     = null;
$refShape   = null;
$apps       = [];
$provenance = [];

foreach ($args as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing input: {$path}\n");
        exit(1);
    }
    $raw = json_decode((string) file_get_contents($path), true);
    if (!is_array($raw) || !isset($raw['apps']) || count($raw['apps']) !== 1) {
        fwrite(STDERR, "{$path}: expected exactly one app block\n");
        exit(1);
    }
    $app = $raw['apps'][0];
    $key = (string) ($app['app'] ?? '');
    if ($key === '') {
        fwrite(STDERR, "{$path}: app block has no name\n");
        exit(1);
    }
    if (isset($apps[$key])) {
        fwrite(STDERR, "Duplicate app block \"{$key}\"\n");
        exit(1);
    }

    // Comparability: same runtime everywhere.
    $env = $san($raw);
    if ($refEnv === null) {
        $refEnv   = $env;
        $combined = $raw;
    } elseif ($env !== $refEnv) {
        fwrite(STDERR, "Env mismatch at {$path}:\n  ref:   " . json_encode($refEnv)
            . "\n  this:  " . json_encode($env) . "\n");
        exit(1);
    }

    // Shape: same modes, same request count per mode.
    $shape = [];
    foreach ($app['modes'] as $mode => $m) {
        $shape[$mode] = count($m['requests'] ?? []);
    }
    ksort($shape);
    if ($refShape === null) {
        $refShape = $shape;
    } elseif ($shape !== $refShape) {
        fwrite(STDERR, "Shape mismatch at {$path}:\n  ref:   " . json_encode($refShape)
            . "\n  this:  " . json_encode($shape) . "\n");
        exit(1);
    }

    $apps[$key] = $app;
    $provenance[$key] = [
        'timestamp'   => $raw['env']['timestamp'] ?? null,
        'source_file' => basename($path),
    ];
}
$order = ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
$keys  = array_keys($apps);
usort($keys, static function (string $a, string $b) use ($order): int {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);
    $ia = $ia === false ? PHP_INT_MAX : $ia;
    $ib = $ib === false ? PHP_INT_MAX : $ib;
    return $ia === $ib ? strcmp($a, $b) : $ia <=> $ib;
});

$combined['apps'] = array_map(static fn(string $k): array => $apps[$k], $keys);
// Provenance: which run each app block came from. The env block itself is the
// first run's (identical runtime everywhere by the check above) and keeps the
// floor-* webserver-overhead probes measured with it.
$combined['env']['app_refresh'] = $provenance;

// The inputs were measured one after another, so the first run's timestamp
// describes only its own block. Report the whole window instead: `timestamp`
// becomes the LAST measurement (the report prints it as "Measured …") and
// `measured_window` keeps both ends.
$stamps = array_values(array_filter(array_map(
    static fn(array $p): ?string => $p['timestamp'],
    $provenance
)));
if ($stamps !== []) {
    sort($stamps);
    $combined['env']['measured_window'] = ['first' => $stamps[0], 'last' => $stamps[count($stamps) - 1]];
    if (count($stamps) > 1) {
        $combined['env']['timestamp'] = $stamps[count($stamps) - 1];
    }
}

// --- Budget: both deployment models must share one sample -------------------
// Every app/mode and every floor probe is checked by the shared helper (see
// bench-lib.php for why an asymmetry must never reach the report tooling).
$budget = assertUniformBudget(budgetsByAppMode($combined));
if (!$budget['ok']) {
    fwrite(STDERR, "Budget mismatch — every mode must share one iterations/runs sample:\n");
    foreach ($budget['byAppMode'] as $label => $value) {
        fwrite(STDERR, sprintf("    %-24s %s\n", $label, $value));
    }
    fwrite(STDERR, "Re-measure the odd rows with the same budget (http-bench.php --iterations-per-run/--runs).\n");
    exit(1);
}

$combined['env']['budget'] = $budget['budget'];

file_put_contents($outPath, json_encode($combined, JSON_PRETTY_PRINT) . "\n");

echo "Wrote {$outPath}\n";
echo '  budget: ', ($combined['env']['budget'] ?? '?'), "\n";
echo '  apps: ', implode(', ', $keys), "\n";
foreach ($combined['env']['app_refresh'] as $k => $p) {
    printf("    %-12s %s\n", $k, $p['timestamp'] ?? '?');
}
$floors = array_map(static fn(array $f): string => (string) $f['app'], $combined['floors'] ?? []);
echo '  floors: ', implode(', ', $floors), "\n";
if (isset($combined['env']['measured_window'])) {
    echo '  measured: ',
        $combined['env']['measured_window']['first'],
        ' → ',
        $combined['env']['measured_window']['last'],
        "\n";
}