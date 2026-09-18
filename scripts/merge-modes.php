<?php

declare(strict_types=1);

/**
 * Splice the PHP-FPM half of a fresh real-deployment run into the canonical
 * dataset, leaving the RoadRunner half untouched.
 *
 * Why this exists: scripts/run-http.php writes a NEW dataset, so a
 * `--servers=fpm` re-measure replaces the file with an FPM-only one and drops
 * every `roadrunner` block plus `floor-rr`. merge-app.php cannot help: it
 * replaces a whole APP block and so requires the source and target to offer
 * the same mode => request-count shape, which is exactly what a single-server
 * run does not have.
 *
 * Scope of the splice, and why it is the honest one:
 *
 *   - The refresh changes FRAMEWORK BOOT, which in this deployment model is
 *     paid inside every request (the FPM entry script re-runs per request).
 *     So the FPM latency rows are stale for the app that changed — and only
 *     those rows can be corrected by re-measuring.
 *   - The RoadRunner rows measure POST-boot work in a resident worker; the
 *     same change cannot move them beyond a one-time first-request bucket
 *     sort. Re-measuring them would only re-roll good numbers, so they are
 *     deliberately preserved.
 *
 * Usage:
 *   php scripts/merge-modes.php <target.json> <fresh.json> <mode> [<mode> ...]
 *   e.g. php scripts/merge-modes.php results/real-deployments.json \
 *            results/fpm-refresh.json php-fpm
 *
 * Guards (a partially-spliced dataset must never reach the report tooling):
 *   - both files must carry an `apps` list, and every target app must exist
 *     in the fresh run
 *   - every spliced mode must offer the SAME request set in both files, so a
 *     renamed/removed endpoint cannot silently drop a row
 *   - the measurement budget (iterations x runs) must be identical across
 *     every app AND both modes of the RESULT — the historical asymmetry this
 *     project already paid for, see bench-lib.php::assertUniformBudget()
 *   - php/opcache/sapi must agree, or the boot numbers are not comparable
 */

require_once __DIR__ . '/bench-lib.php';

$args = array_slice($argv, 1);
if (count($args) < 3) {
    fwrite(STDERR, "Usage: php scripts/merge-modes.php <target.json> <fresh.json> <mode> [<mode> ...]\n");
    exit(1);
}
$targetPath = array_shift($args);
$freshPath  = array_shift($args);
$modes      = array_values(array_filter(array_map('trim', $args), static fn(string $m): bool => $m !== ''));

foreach ([[$targetPath, 'target'], [$freshPath, 'fresh']] as [$path, $what]) {
    if (!is_file($path)) {
        fwrite(STDERR, "missing {$what} file: {$path}\n");
        exit(1);
    }
}

$target = json_decode((string) file_get_contents($targetPath), true);
$fresh  = json_decode((string) file_get_contents($freshPath), true);
foreach ([[$target, $targetPath], [$fresh, $freshPath]] as [$data, $path]) {
    if (!is_array($data) || !isset($data['apps'])) {
        fwrite(STDERR, "{$path}: not a result dataset (no \"apps\")\n");
        exit(1);
    }
}

// --- sanity checks: the two runs must be comparable ------------------------

$san = static function (array $d): array {
    $out = [];
    foreach (['php_version', 'opcache', 'sapi'] as $k) {
        if (isset($d['env'][$k])) {
            $out[$k] = $d['env'][$k];
        }
    }

    return $out;
};
if ($san($target) !== $san($fresh)) {
    fwrite(STDERR, "Env mismatch (php/opcache/sapi differ) — boot numbers would not be comparable:\n");
    fwrite(STDERR, '  target: ' . json_encode($san($target)) . "\n");
    fwrite(STDERR, '  fresh : ' . json_encode($san($fresh)) . "\n");
    exit(1);
}

// Index the fresh run by app.
$freshByApp = [];
foreach ($fresh['apps'] as $app) {
    $key = (string) ($app['app'] ?? '');
    if ($key !== '') {
        $freshByApp[$key] = $app;
    }
}

// Which modes each app offers on each side, so a mode that exists in only one
// file is reported instead of silently skipped.
$requestSetOf = static function (array $app, string $mode): ?array {
    $rows = $app['modes'][$mode]['requests'] ?? null;
    if (!is_array($rows)) {
        return null;
    }
    $names = array_map(static fn($r): string => (string) ($r['request'] ?? ''), $rows);
    sort($names);

    return $names;
};

$spliced  = [];
$skipped  = [];
$failures = [];

foreach ($target['apps'] as $i => $app) {
    $appKey   = (string) ($app['app'] ?? '');
    $freshApp = $freshByApp[$appKey] ?? null;
    if ($freshApp === null) {
        $failures[] = "fresh run has no '{$appKey}' block";
        continue;
    }

    foreach ($modes as $mode) {
        $targetSet = $requestSetOf($app, $mode);
        $freshSet  = $requestSetOf($freshApp, $mode);
        if ($targetSet === null) {
            $skipped[] = "{$appKey}/{$mode}: target has no such mode";
            continue;
        }
        if ($freshSet === null) {
            $failures[] = "{$appKey}/{$mode}: fresh run has no such mode";
            continue;
        }
        if ($targetSet !== $freshSet) {
            $onlyTarget = array_values(array_diff($targetSet, $freshSet));
            $onlyFresh  = array_values(array_diff($freshSet, $targetSet));
            $failures[] = "{$appKey}/{$mode}: request sets differ"
                . ($onlyTarget !== [] ? ' (only in target: ' . implode(', ', $onlyTarget) . ')' : '')
                . ($onlyFresh !== [] ? ' (only in fresh: ' . implode(', ', $onlyFresh) . ')' : '');
            continue;
        }

        // Splice the measured mode wholesale: the mode-level budget fields and
        // every row (including the fresh row-level boot_ms) move together.
        $before = count($target['apps'][$i]['modes'][$mode]['requests'] ?? []);
        $target['apps'][$i]['modes'][$mode] = $freshApp['modes'][$mode];
        $after = count($target['apps'][$i]['modes'][$mode]['requests'] ?? []);
        $spliced[] = "{$appKey}/{$mode}: {$after} rows (was {$before})";
    }

    // Boot blocks for a spliced mode must travel with it, or the report would
    // pair a fresh row with the PREVIOUS boot sample. Only the spliced modes
    // are replaced; the other mode's block (and the legacy scalar) stay.
    foreach ($modes as $mode) {
        foreach (['boot_by_mode', 'boot_samples_by_mode', 'boot_kind_by_mode'] as $field) {
            if (isset($freshApp[$field][$mode])) {
                $target['apps'][$i][$field][$mode] = $freshApp[$field][$mode];
            }
        }
    }
}

if ($failures !== []) {
    fwrite(STDERR, "Refusing to write — the two runs are not comparable:\n");
    foreach ($failures as $line) {
        fwrite(STDERR, "  ! {$line}\n");
    }
    exit(1);
}
if ($spliced === []) {
    fwrite(STDERR, "Nothing to splice (no matching mode found).\n");
    exit(1);
}

// --- floors ----------------------------------------------------------------
// The floor pseudo-apps are per server, so only the FPM ones are re-measured.
// They live on the target's `floors` list; replace the matching entries and
// keep floor-rr.
$freshFloors = [];
foreach (($fresh['floors'] ?? []) as $floor) {
    if (is_array($floor) && isset($floor['app'])) {
        $freshFloors[(string) $floor['app']] = $floor;
    }
}
$floorSpliced = [];
foreach (($target['floors'] ?? []) as $j => $floor) {
    $key = (string) ($floor['app'] ?? '');
    // A floor measured through FPM is the one the FPM rows stand on.
    $isFpmFloor = isset($floor['modes']['php-fpm']);
    if ($isFpmFloor && isset($freshFloors[$key])) {
        $target['floors'][$j] = $freshFloors[$key];
        $floorSpliced[] = $key;
    }
}

// --- budget: the RESULT must still carry one uniform sample ----------------

$budgets = budgetsByAppMode(['apps' => $target['apps'], 'floors' => $target['floors'] ?? []]);
$budget  = assertUniformBudget($budgets);
if (!$budget['ok']) {
    fwrite(STDERR, "Budget mismatch — the spliced dataset would mix iterations/runs samples:\n");
    foreach ($budget['byAppMode'] as $label => $value) {
        fprintf(STDERR, "    %-28s %s\n", $label, $value);
    }
    exit(1);
}

// --- write -----------------------------------------------------------------

if (!is_file($targetPath . '.bak')) {
    copy($targetPath, $targetPath . '.bak');
}

$target['env']['mode_refresh'] = $target['env']['mode_refresh'] ?? [];
foreach ($modes as $mode) {
    $target['env']['mode_refresh'][$mode] = [
        'timestamp' => $fresh['env']['timestamp'] ?? date('c'),
        'source'    => basename($freshPath),
        'apps'      => array_map(static fn(array $a): string => (string) $a['app'], $target['apps']),
    ];
}
// The dataset timestamp describes when its measurement was taken. A partial
// re-measure leaves the whole-dataset stamp alone (provenance for the spliced
// half is in env.mode_refresh) rather than claiming the RoadRunner half was
// measured today.
if (!isset($target['env']['timestamp'])) {
    $target['env']['timestamp'] = $fresh['env']['timestamp'] ?? date('c');
}
if (isset($fresh['env']['budget'])) {
    $target['env']['budget'] = $fresh['env']['budget'];
}
if (isset($fresh['env']['fpm_max_requests'])) {
    $target['env']['fpm_max_requests'] = $fresh['env']['fpm_max_requests'];
}

file_put_contents($targetPath, json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Spliced " . count($spliced) . " mode block(s) from {$freshPath}:\n";
foreach ($spliced as $line) {
    echo "  {$line}\n";
}
if ($skipped !== []) {
    echo "Skipped:\n";
    foreach ($skipped as $line) {
        echo "  - {$line}\n";
    }
}
echo 'Floors refreshed: ' . ($floorSpliced === [] ? '(none)' : implode(', ', $floorSpliced)) . "\n";
echo "Budget: {$budget['budget']}\n";
echo "Wrote {$targetPath}\n";
