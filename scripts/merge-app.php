<?php

declare(strict_types=1);

/**
 * Replace one app's result block in an existing benchmark dataset with a
 * fresh run of that single app.
 *
 * Rationale: run.php has no merge-into-existing option, and a change that
 * only affects one framework (e.g. an azera-framework AppContext refactor)
 * does not invalidate the other frameworks' numbers. Re-running all six
 * apps costs ~1h on the VM; re-running one app costs minutes. This script
 * splices the fresh app block (and its boot block) into the canonical
 * dataset so the published report reflects the new code without discarding
 * unchanged rows.
 *
 * Usage:
 *   php scripts/merge-app.php <target-prefix> <source-prefix> <app-key>
 *   e.g. php scripts/merge-app.php results/free-for-all-opcache-iso temp/azera-refresh azera
 *
 * Writes <target-prefix>.json + .csv in place (the .md is NOT regenerated —
 * the CSV/JSON are what the report tooling consumes; regenerate reports via
 * scripts/report.php afterwards). A .bak of the original JSON is kept.
 */

require_once __DIR__ . '/report/BenchmarkConfig.php';

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/merge-app.php <target-prefix> <source-prefix> <app-key>\n");
    exit(1);
}

$targetPrefix = preg_replace('/\.json$/', '', $argv[1]);
$sourcePrefix = preg_replace('/\.json$/', '', $argv[2]);
$appKey       = $argv[3];

$targetJson = $targetPrefix . '.json';
$sourceJson = $sourcePrefix . '.json';

if (!is_file($targetJson)) {
    fwrite(STDERR, "Target not found: {$targetJson}\n");
    exit(1);
}
if (!is_file($sourceJson)) {
    fwrite(STDERR, "Source not found: {$sourceJson}\n");
    exit(1);
}

$target = json_decode((string) file_get_contents($targetJson), true);
$source = json_decode((string) file_get_contents($sourceJson), true);
if (!is_array($target) || !isset($target['apps']) || !is_array($source) || !isset($source['apps'])) {
    fwrite(STDERR, "Malformed result file (no \"apps\").\n");
    exit(1);
}

$srcApps = array_values(array_filter(
    $source['apps'],
    static fn(array $a): bool => ($a['app'] ?? '') === $appKey
));
if (count($srcApps) !== 1) {
    fwrite(STDERR, "Source must contain exactly one \"{$appKey}\" app block, found " . count($srcApps) . ".\n");
    exit(1);
}

$dstIndex = null;
foreach ($target['apps'] as $i => $a) {
    if (($a['app'] ?? '') === $appKey) {
        $dstIndex = $i;
        break;
    }
}
if ($dstIndex === null) {
    fwrite(STDERR, "Target has no \"{$appKey}\" app block to replace.\n");
    exit(1);
}

// --- sanity checks: the two runs must be comparable -----------------------

$san = static function (array $d): array {
    $sanitized = [];
    foreach (['php_version', 'opcache', 'sapi'] as $k) {
        if (isset($d['env'][$k])) {
            $sanitized[$k] = $d['env'][$k];
        }
    }

    return $sanitized;
};
if ($san($target) !== $san($source)) {
    fwrite(STDERR, "Env mismatch (php/opcache/sapi differ):\n");
    fwrite(STDERR, '  target: ' . json_encode($san($target)) . "\n");
    fwrite(STDERR, '  source: ' . json_encode($san($source)) . "\n");
    exit(1);
}

$srcApp = $srcApps[0];
$dstApp = $target['apps'][$dstIndex];

$modeShape = static function (array $app): array {
    $shape = [];
    foreach ($app['modes'] as $mode => $m) {
        $shape[$mode] = count($m['requests'] ?? []);
    }

    return $shape;
};
if ($modeShape($srcApp) !== $modeShape($dstApp)) {
    fwrite(STDERR, "Mode/request-count mismatch:\n");
    fwrite(STDERR, '  target: ' . json_encode($modeShape($dstApp)) . "\n");
    fwrite(STDERR, '  source: ' . json_encode($modeShape($srcApp)) . "\n");
    exit(1);
}

// --- splice ---------------------------------------------------------------

if (!is_file($targetJson . '.bak')) {
    copy($targetJson, $targetJson . '.bak');
}

$target['apps'][$dstIndex] = $srcApp;

// Track provenance: which app was refreshed, from which run.
$target['env']['app_refresh'] = $target['env']['app_refresh'] ?? [];
$target['env']['app_refresh'][$appKey] = [
    'timestamp'           => $source['env']['timestamp'] ?? date('c'),
    'azera_framework_ref' => $source['env']['azera_framework_ref'] ?? null,
    'source_dataset'      => basename($sourceJson),
];
if (isset($source['env']['azera_framework_ref'])) {
    $target['env']['azera_framework_ref'] = $source['env']['azera_framework_ref'];
}

file_put_contents($targetJson, json_encode($target, JSON_PRETTY_PRINT));
echo "Merged {$appKey} block from {$sourceJson} into {$targetJson}\n";
echo '  modes: ', implode(', ', array_keys($srcApp['modes'])), "\n";
echo '  requests per mode: ',
    implode(', ', array_map(
    static fn(array $m): int => count($m['requests']),
    $srcApp['modes']
)),
    "\n";

// --- CSV: rebuild in full from the merged JSON (same shape/shape/order as
// run.php writeResults() emits, so the file looks like a fresh export) ------

$csvPath = $targetPrefix . '.csv';
if (!is_file($csvPath)) {
    echo "  (no CSV at {$csvPath} — skipped)\n";
    exit(0);
}

$fp = fopen($csvPath, 'w');
fputcsv($fp, [
    'app',
    'mode',
    'request',
    'iterations_per_run',
    'runs',
    'trimmed_mean_ms',
    'handle_ms',
    'cleanup_ms',
    'min_ms',
    'mean_ms',
    'median_ms',
    'p95_ms',
    'peak_mem',
]);
foreach ($target['apps'] as $app) {
    foreach ($app['modes'] as $modeName => $mode) {
        foreach ($mode['requests'] as $req) {
            fputcsv($fp, [
                $app['app'],
                $modeName,
                $req['request'],
                $req['iterations_per_run'],
                $req['runs'],
                $req['trimmed_mean_ms'],
                $req['handle_ms'] ?? '',
                $req['cleanup_ms'] ?? '',
                $req['min_ms'],
                $req['mean_ms'],
                $req['median_ms'],
                $req['p95_ms'],
                $req['peak_mem'],
            ]);
        }
    }
}
fclose($fp);

echo "  CSV rebuilt from merged JSON ({$csvPath})\n";