<?php

declare(strict_types=1);

/**
 * Splice selected deployment modes of a fresh real-deployment run into the
 * canonical dataset, leaving every other mode untouched.
 *
 * Why this exists: scripts/run-http.php writes a NEW dataset, so a partial
 * re-measure (`--servers=fpm`, or just the cold pair) replaces the file with one
 * that carries only what it measured and drops every other mode block plus the
 * floors belonging to the servers it did not start. merge-app.php cannot help: it
 * replaces a whole APP block and so requires the source and target to offer the
 * same mode => request-count shape, which is exactly what a partial run does not
 * have.
 *
 * Scope of the splice, and why it is the honest one:
 *
 *   - A mode is spliced ONLY when its measurement is actually stale. A cold
 *     (per-request-boot) mode is stale the moment anything in the boot path, the
 *     server's php arguments, or the framework changes — the entry script re-runs
 *     for every request, so all of it is inside the timed rows.
 *   - A resident mode measures POST-boot work in a long-lived worker, so the same
 *     change cannot move it beyond a one-time first-request bucket sort.
 *     Re-measuring it would only re-roll good numbers, so it is preserved.
 *     That reasoning is per MODE, not per server: it is why the 2026-11-10
 *     volucer-attach blocks were kept while the volucer-cgi blocks were
 *     re-measured after the spawn_cgi arm's php arguments were equalised with
 *     the FPM pool's.
 *
 * A mode the target does not yet carry is ADDED. That is the "this dataset never
 * measured this server" case — without it, correcting one half of a dataset whose
 * other half came from an earlier measurement would be impossible without
 * re-running everything.
 *
 * Usage:
 *   php scripts/merge-modes.php <target.json> <fresh.json> <mode> [<mode> ...]
 *   e.g. php scripts/merge-modes.php results/real-deployments.json \
 *            results/fpm-refresh.json php-fpm
 *        php scripts/merge-modes.php results/real-deployments.json \
 *            results/cold-refresh.json php-fpm volucer-cgi
 *
 * Guards (a partially-spliced dataset must never reach the report tooling):
 *   - both files must carry an `apps` list, and every target app must exist
 *     in the fresh run
 *   - every spliced mode must offer the SAME request set in both files, so a
 *     renamed/removed endpoint cannot silently drop a row (a mode absent from
 *     the TARGET is exempt: there is no shape to compare against)
 *   - the measurement budget (iterations x runs) must be identical across
 *     every app AND every mode of the RESULT — the historical asymmetry this
 *     project already paid for, see bench-lib.php::assertUniformBudget()
 *   - php/opcache/sapi must agree, or the boot numbers are not comparable
 */

require_once __DIR__ . '/bench-lib.php';
// assertEnvComparable() — the shared runtime-comparability check.
require_once __DIR__ . '/env-sanity.php';

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

if (!assertEnvComparable($target, $fresh, 'the spliced mode\'s boot numbers')) {
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
        // A mode the caller asked for but the fresh run did not measure is a
        // REFUSAL, not a skip. Skipping would exit 0 having measured nothing, and
        // the operator would publish the stale rows believing they were refreshed
        // — the same silent-wrong-number failure this whole script exists to
        // prevent. A caller that does not want a mode simply does not request it.
        if ($freshSet === null) {
            $failures[] = "{$appKey}/{$mode}: fresh run has no such mode";
            continue;
        }
        if ($targetSet !== null && $targetSet !== $freshSet) {
            $onlyTarget = array_values(array_diff($targetSet, $freshSet));
            $onlyFresh  = array_values(array_diff($freshSet, $targetSet));
            $failures[] = "{$appKey}/{$mode}: request sets differ"
                . ($onlyTarget !== [] ? ' (only in target: ' . implode(', ', $onlyTarget) . ')' : '')
                . ($onlyFresh !== [] ? ' (only in fresh: ' . implode(', ', $onlyFresh) . ')' : '');
            continue;
        }

        // Splice the measured mode wholesale: the mode-level budget fields and
        // every row (including the fresh row-level boot_ms) move together.
        //
        // A mode the target does NOT yet have is ADDED rather than refused. That
        // is the "a server was added to an existing dataset" case: re-measuring
        // the volucer half of a run whose resident blocks came from an earlier
        // measurement means the target has no volucer mode at all, and refusing
        // would make a correct refresh impossible without re-running everything.
        // The append is only reachable with $targetSet === null, i.e. the key was
        // absent — an app that HAS the mode keeps its shape check above.
        $isNew  = $targetSet === null;
        $before = count($target['apps'][$i]['modes'][$mode]['requests'] ?? []);
        $target['apps'][$i]['modes'][$mode] = $freshApp['modes'][$mode];
        $after = count($target['apps'][$i]['modes'][$mode]['requests'] ?? []);
        $spliced[] = $isNew
            ? "{$appKey}/{$mode}: ADDED, {$after} rows"
            : "{$appKey}/{$mode}: {$after} rows (was {$before})";
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
// The floor pseudo-apps are per SERVER, and a partial run re-measures only the
// servers it selected — so a floor is refreshed when the mode it was measured
// through is one of the spliced modes, and left alone otherwise. Matching on the
// mode rather than on `php-fpm` specifically is what lets this serve an FPM
// refresh and a volucer refresh from the same code: the volucer arms have no
// floor at all (measureFloors() takes no arm list), so in that case nothing
// matches here and every existing floor is preserved untouched.
$floorSpliced = [];
foreach (($fresh['floors'] ?? []) as $freshFloor) {
    if (!is_array($freshFloor) || !isset($freshFloor['app'])) {
        continue;
    }
    $floorKey = (string) $freshFloor['app'];
    $freshModes = array_keys(is_array($freshFloor['modes'] ?? null) ? $freshFloor['modes'] : []);
    if (array_intersect($freshModes, $modes) === []) {
        continue;
    }
    foreach (($target['floors'] ?? []) as $j => $floor) {
        if ((string) ($floor['app'] ?? '') === $floorKey) {
            $target['floors'][$j] = $freshFloor;
            $floorSpliced[] = $floorKey;
        }
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

// --- provenance the fresh run established --------------------------------
//
// A splice must not leave the dataset describing the OLD measurement with the
// NEW numbers. Three cases, all of which have bitten or would:
//
//   frameworks       A re-measure can IMPROVE this stamp without changing any
//                    measurement (2026-11-10: `377d0f6+dirty` became
//                    `v0.4.1-4-g377d0f6+dirty` once the launcher learned to read
//                    the tag distance). Refusing to carry it would publish a
//                    weaker provenance than the run actually had.
//   opcache_jit*     Newly stamped; a canonical dataset from before that change
//                    carries no JIT keys at all, so a reader could not tell
//                    whether the refreshed half ran with JIT.
//   opcache_by_mode  Merged PER MODE, never replaced: the fresh run only measured
//                    some servers, so replacing the map would delete the answers
//                    for servers it never started.
//
// Each is copied only when the fresh run states it, so "not recorded" never
// becomes a value.
$carried = [];
if (isset($fresh['env']['frameworks'])) {
    $target['env']['frameworks'] = $fresh['env']['frameworks'];
    $carried[] = 'frameworks';
}
foreach (['opcache_jit', 'opcache_jit_buffer_size', 'opcache_jit_enabled'] as $key) {
    if (array_key_exists($key, $fresh['env'])) {
        $target['env'][$key] = $fresh['env'][$key];
        $carried[] = $key;
    }
}
if (isset($fresh['env']['opcache_by_mode']) && is_array($fresh['env']['opcache_by_mode'])) {
    $target['env']['opcache_by_mode'] = array_merge(
        is_array($target['env']['opcache_by_mode'] ?? null) ? $target['env']['opcache_by_mode'] : [],
        $fresh['env']['opcache_by_mode']
    );
    $carried[] = 'opcache_by_mode';
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
echo 'Provenance carried: ' . ($carried === [] ? '(none)' : implode(', ', $carried)) . "\n";
echo "Budget: {$budget['budget']}\n";
echo "Wrote {$targetPath}\n";
