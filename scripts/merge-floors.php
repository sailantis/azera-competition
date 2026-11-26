<?php

declare(strict_types=1);

/**
 * Graft a floors-only probe into a dataset, leaving every measured row untouched.
 *
 * WHY THIS EXISTS. `floors` is the one block of a real-deployment dataset that is
 * independent of the apps: each floor is a single `GET /echo` to a server with no
 * framework in it, so it describes the SERVER, not any row measured on it. That
 * makes it the only block that can be added to an existing dataset without
 * re-timing anything — unlike boot or memory probes, which are per (server, app)
 * and belong to the run whose latency rows they sit beside.
 *
 * The case this was written for: the 2026-11-23 dataset carried floors for nginx,
 * PHP-FPM but NONE for the Volucer arm, so those rows had no
 * reference point for server overhead. Re-running 26 endpoints x 7 apps x 2 arms
 * to obtain four probe numbers would have re-rolled every published figure.
 *
 * WHAT IT REFUSES. A floors-only file that also carries apps (that is a full run —
 * use it as the dataset instead), and a target whose floors block it would silently
 * reduce. It REPLACES whole floor entries by app key, so re-grafting is idempotent,
 * and it prints every floor it writes with its mode, because the mode is the thing
 * that decides which rows the floor belongs to.
 *
 * Usage:
 *   php scripts/merge-floors.php results/real-deployments.json temp/floor-probe-floors.json
 */

$args = array_slice($argv, 1);
if (count($args) !== 2) {
    fwrite(STDERR, "Usage: php scripts/merge-floors.php <target.json> <floors.json>\n");
    exit(1);
}

// assertRowsNotBelowFloor()/describeFloorViolations() — see bench-lib.php.
require_once __DIR__ . '/bench-lib.php';

[$targetPath, $floorsPath] = $args;

foreach ([$targetPath, $floorsPath] as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "merge-floors: no such file: {$path}\n");
        exit(1);
    }
}

$target = json_decode((string) file_get_contents($targetPath), true);
$incoming = json_decode((string) file_get_contents($floorsPath), true);

if (!is_array($target) || !isset($target['apps']) || !is_array($target['apps'])) {
    fwrite(STDERR, "merge-floors: {$targetPath} is not a real-deployment dataset (no apps block)\n");
    exit(1);
}

// Accept both shapes: a bare floors array (what run-http.php --floors-only writes)
// and a full dataset envelope (so a probe can be inspected as a dataset and then
// grafted from the same file).
if (is_array($incoming) && isset($incoming['floors'])) {
    if (($incoming['apps'] ?? []) !== []) {
        fwrite(STDERR, "merge-floors: {$floorsPath} carries app rows — it is a full run, not a floors probe.\n"
            . "              Use it as the dataset instead of grafting from it.\n");
        exit(1);
    }
    $incoming = $incoming['floors'];
}

if (!is_array($incoming) || $incoming === []) {
    fwrite(STDERR, "merge-floors: {$floorsPath} carries no floors to graft\n");
    exit(1);
}

// Index the target's existing floors by app key so a re-graft replaces rather than
// duplicates. Duplicates would print twice in any future consumer and neither copy
// would be obviously the current one.
$byKey = [];
foreach (($target['floors'] ?? []) as $floor) {
    $key = $floor['app'] ?? null;
    if (is_string($key) && $key !== '') {
        $byKey[$key] = $floor;
    }
}

$written = [];
foreach ($incoming as $floor) {
    $key = $floor['app'] ?? null;
    if (!is_string($key) || $key === '') {
        fwrite(STDERR, "merge-floors: a floor entry has no `app` key — refusing to write an unattributable probe\n");
        exit(1);
    }

    $modes = array_keys((array) ($floor['modes'] ?? []));
    if (count($modes) !== 1) {
        fwrite(STDERR, "merge-floors: '{$key}' carries " . count($modes)
            . " mode(s); a floor probe is one server, so it must carry exactly one\n");
        exit(1);
    }

    $byKey[$key] = $floor;
    $written[] = [$key, $modes[0]];
}

// Keep the original ORDER for entries that were already there, and append the new
// ones — a dataset's floors block is read top to bottom, so a graft must not reshuffle
// the probes a reader already knows.
$ordered = [];
foreach (($target['floors'] ?? []) as $floor) {
    $key = $floor['app'] ?? null;
    if (is_string($key) && isset($byKey[$key])) {
        $ordered[] = $byKey[$key];
        unset($byKey[$key]);
    }
}
foreach ($byKey as $floor) {
    $ordered[] = $floor;
}

$target['floors'] = $ordered;

// --- The graft must not contradict the rows it now sits beside ----------------
// THIS IS THE HOLE THAT PRODUCED THE 2026-11-23 `/echo` ROW. The Volucer floors in
// that dataset were added by a later `--quick` pass (100x3) and grafted in here,
// which checked only the app key and the mode count. The result was a floor probed
// on a different sample than the rows, and one Phalcon row landed 13.9% BELOW it.
// assertUniformBudget() in the other writers did not catch it because floors are
// checked as part of the same dataset and the asymmetry was between a grafted floor
// and rows measured earlier — a window this script is the only place to close.
$floorCheck = assertRowsNotBelowFloor($target);
if (!$floorCheck['ok']) {
    fwrite(STDERR, "merge-floors: refusing to graft — " . describeFloorViolations($floorCheck)
        . "\n  The dataset above is UNCHANGED.\n");
    exit(1);
}

file_put_contents(
    $targetPath,
    json_encode($target, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
);

echo "Grafted into {$targetPath}:\n";
foreach ($written as [$key, $mode]) {
    echo "  {$key} (mode {$mode})\n";
}
echo 'Floors block now holds ' . count($ordered) . " probe(s).\n";
