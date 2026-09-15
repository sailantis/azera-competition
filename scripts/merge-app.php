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
 * Writes <target-prefix>.json in place, then re-emits its .csv + .md companions
 * by delegating to `run.php --export` (which uses the harness' own writers, so
 * the three stay in step). Regenerate the published views via
 * scripts/report.php afterwards). A .bak of the original JSON is kept.
 */

require_once __DIR__ . '/report/BenchmarkConfig.php';
// budgetsByAppMode()/assertUniformBudget() — see bench-lib.php.
require_once __DIR__ . '/bench-lib.php';

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

// Budget: this splice is exactly how the historical asymmetry (an RR-only
// refresh at 1000x10 against a 300x5 FPM block) happened, so the refreshed
// app is checked against every other app before anything is written.
// See bench-lib.php::assertUniformBudget() for why the sample must be equal.
$budgets = budgetsByAppMode($source); // the app(s) being spliced in
foreach ($target['apps'] as $a) {
    if (($a['app'] ?? '') === $appKey) {
        continue; // replaced by the source below; its budget is in $budgets
    }
    foreach (budgetsByAppMode(['apps' => [$a]]) as $key => $b) {
        $budgets[$key] = $b;
    }
}
$budget = assertUniformBudget($budgets);
if (!$budget['ok']) {
    fwrite(STDERR, "Budget mismatch — a merge must not mix iterations/runs samples:\n");
    foreach ($budget['byAppMode'] as $label => $value) {
        fprintf(STDERR, "    %-24s %s%s\n", $label, $value,
            str_starts_with($label, "{$appKey}/") ? '   (refreshing)' : ''
        );
    }
    fwrite(STDERR, "Re-measure the refreshed app with the dataset's budget, or refresh every mode.\n");
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

// --- CSV + MD: hand off to the harness' own writers -------------------------
// run.php --export re-emits the .csv/.md companions from the dataset on disk
// using the SAME code that produced them, so the trio cannot drift apart. This
// script used to carry a copy of run.php's CSV writer — and never refreshed
// the .md at all, which is how free-for-all-opcache-iso.md came to sit a day
// behind its .json.

exportCompanions($targetPrefix);

function exportCompanions(string $prefix): void
{
    $cmd = escapeshellarg(PHP_BINARY) . ' '
        . escapeshellarg(__DIR__ . '/../run.php')
        . ' --export=' . escapeshellarg($prefix);
    passthru($cmd, $code);
    if ($code !== 0) {
        fwrite(STDERR, "  ! companion export failed (exit {$code}); .csv/.md may be stale\n");
    }
}