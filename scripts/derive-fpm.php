<?php

declare(strict_types=1);

/**
 * Derive a "php-fpm" dataset from a combined warm+cold result file.
 *
 * The harness' cold mode (bootstrap per iteration, opcache retained — the
 * same bytecode-sharing real FPM workers enjoy) models the per-request cost
 * profile of a classic PHP-FPM deployment, while warm mode models a
 * RoadRunner-style long-lived worker. The report views should present those
 * as two deployment stories, not two abstract "modes" — so this script
 * relabels each mode into its real-world name:
 *
 *   warm -> roadrunner   cold -> php-fpm
 *
 * env.php_fpm_note is added so every rendered report carries the caveat that
 * cold mode models the *application* boot FPM pays per request, not FPM's own
 * worker-management overhead (fork/refill), which the harness does not
 * simulate.
 *
 * Usage:
 *   php scripts/derive-fpm.php results/free-for-all-opcache
 *   # writes results/free-for-all-opcache-deployments.json
 *
 * The report manifest's "deployments" dataset points at the derived file.
 */

require_once __DIR__ . '/report/BenchmarkConfig.php';

if ($argc < 2) {
    fwrite(STDERR, "Usage: php scripts/derive-fpm.php <result-prefix>\n");
    fwrite(STDERR, "  e.g. php scripts/derive-fpm.php results/free-for-all-opcache\n");
    exit(1);
}

$prefix = preg_replace('/\.json$/', '', $argv[1]);
$src    = $prefix . '.json';
$dst    = $prefix . '-deployments.json';

if (!is_file($src)) {
    fwrite(STDERR, "Result file not found: {$src}\n");
    exit(1);
}

$raw = json_decode((string) file_get_contents($src), true);
if (!is_array($raw) || !isset($raw['apps'])) {
    fwrite(STDERR, "Malformed result file (no \"apps\"): {$src}\n");
    exit(1);
}

$rename = ['warm' => 'roadrunner', 'cold' => 'php-fpm'];
$found  = [];
foreach ($raw['apps'] as &$app) {
    foreach ($rename as $from => $to) {
        if (isset($app['modes'][$from])) {
            $app['modes'][$to] = $app['modes'][$from];
            unset($app['modes'][$from]);
            $found[$to] = true;
        }
    }
}
unset($app);

if (isset($found['roadrunner']) xor isset($found['php-fpm'])) {
    fwrite(STDERR, "Warning: dataset has only one of warm/cold — the derived view will be partial.\n");
}
if ($found === []) {
    fwrite(STDERR, "No warm/cold modes found in {$src}; nothing to derive.\n");
    exit(1);
}

$raw['env']['derived_from']      = basename($src);
$raw['env']['deployment_models'] = [
    'php-fpm'    => 'harness cold mode: the framework boots per request (opcache retained, as with real FPM workers sharing bytecode). Models the application cost FPM pays per request; FPM worker management itself is not simulated.',
    'roadrunner' => 'harness warm mode: the framework boots once and serves many requests from a resident worker (RoadRunner/Octane/Swoole style).',
];

file_put_contents($dst, json_encode($raw, JSON_PRETTY_PRINT));
echo "Wrote {$dst}\n";
echo '  modes: ', implode(', ', array_keys($found)), PHP_EOL;