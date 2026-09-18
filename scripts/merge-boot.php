<?php

declare(strict_types=1);

/**
 * Merge boot-only probe results into an existing real-deployment dataset.
 *
 * Why this exists: the boot probe is a ONE-SHOT measurement. The latency
 * numbers in results/real-deployments.json came from a ~2 hour 1000x10 run on
 * the bench VM, and re-measuring them to add a boot value would cost hours and
 * change nothing. So `run-http.php --boot-only` measures boot alone (minutes),
 * and this script grafts the result onto the existing dataset.
 *
 * It refuses to touch a dataset whose env disagrees with the probe's env
 * (different PHP / OS / SAPI), because a boot number is only comparable within
 * one runtime — the same class of check assemble-real.php and merge-app.php
 * make.
 *
 * Usage:
 *   php scripts/merge-boot.php <dataset.json> <boot1.json> <boot2.json> ...
 *
 * Each boot file is the --boot-only output of scripts/http-bench.php for one
 * (server, app) pair.
 */

$args = array_slice($argv, 1);
if (count($args) < 2) {
    fwrite(STDERR, "Usage: php scripts/merge-boot.php <dataset.json> <boot1.json> [boot2.json ...]\n");
    exit(1);
}
$datasetPath = array_shift($args);

$dataset = json_decode((string) file_get_contents($datasetPath), true);
if (!is_array($dataset) || !isset($dataset['apps'])) {
    fwrite(STDERR, "{$datasetPath}: not a result dataset\n");
    exit(1);
}

// server => mode, matching run-http.php's $modeOf.
$modeOf = static fn(string $server): string => $server === 'rr' ? 'roadrunner' : 'php-fpm';

// Index the dataset's app blocks by name.
$byApp = [];
foreach ($dataset['apps'] as $i => $app) {
    $byApp[(string) ($app['app'] ?? '')] = $i;
}

$applied = [];
foreach ($args as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "missing boot file: {$path}\n");
        exit(1);
    }
    $b = json_decode((string) file_get_contents($path), true);
    if (!is_array($b) || empty($b['boot_only'])) {
        fwrite(STDERR, "{$path}: not a --boot-only result (expected boot_only: true)\n");
        exit(1);
    }
    $appKey = (string) ($b['app'] ?? '');
    $server = (string) ($b['server'] ?? '');
    if ($appKey === '' || !isset($byApp[$appKey])) {
        fwrite(STDERR, "{$path}: app '{$appKey}' is not in the dataset\n");
        exit(1);
    }
    $mode = $modeOf($server);

    // The app block must actually carry this mode, or the boot would land on
    // a server the dataset never measured.
    $idx = $byApp[$appKey];
    if (!isset($dataset['apps'][$idx]['modes'][$mode])) {
        fwrite(STDERR, "{$path}: dataset has no '{$mode}' block for '{$appKey}'\n");
        exit(1);
    }
    if (!isset($b['boot_samples'])) {
        fwrite(STDERR, "{$path}: no boot samples collected — refusing to write a partial probe\n");
        exit(1);
    }

    $dataset['apps'][$idx]['boot_by_mode'][$mode] = $b['boot'];
    $dataset['apps'][$idx]['boot_samples_by_mode'][$mode] = $b['boot_samples'];
    $dataset['apps'][$idx]['boot_kind_by_mode'][$mode] = $b['boot_kind'] ?? null;
    // Legacy scalar shape = first mode measured, for paths not yet mode-aware.
    if (!isset($dataset['apps'][$idx]['boot'])) {
        $dataset['apps'][$idx]['boot']         = $b['boot'];
        $dataset['apps'][$idx]['boot_samples'] = $b['boot_samples'];
        $dataset['apps'][$idx]['boot_kind']    = $b['boot_kind'] ?? null;
    }

    // php-fpm boot IS inside every timed row, so stamp boot_ms on the rows too
    // (the report's FPM band reads it). RoadRunner's worker boot happens once
    // before serving, so its rows must NOT carry it — that would double-count.
    if ($mode === 'php-fpm') {
        foreach ($dataset['apps'][$idx]['modes'][$mode]['requests'] as &$row) {
            $row['boot_ms'] = $b['boot_samples']['median'];
        }
        unset($row);
    }

    $applied[] = "{$appKey}/{$mode}: median {$b['boot_samples']['median']} ms (n={$b['boot_samples']['count']})";
}

// Stamp the probe so the report stops using the legacy startup proxy.
$dataset['env']['boot_probe'] = 50;

file_put_contents($datasetPath, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Merged " . count($applied) . " boot block(s) into {$datasetPath}:\n";
foreach ($applied as $line) {
    echo "  {$line}\n";
}
echo "env.boot_probe stamped — real views will now render measured boot.\n";
