<?php

declare(strict_types=1);

/**
 * ONE engine's STEADY-STATE render cost for ONE page, timed in a fresh process
 * with a warm template cache. Prints the per-render statistics as JSON, or
 * nothing and a non-zero status on an unusable argument.
 *
 * WHY A SEPARATE PROCESS PER (ENGINE, PAGE) CELL, AND WHY THAT IS THE WHOLE POINT
 *
 * The harness used to time the render loop in its OWN process, walking every
 * (engine, page) cell in sequence. It called `flushCache()` before each cell, so
 * it looked like each cell was measured from a cold cache — and for the memory
 * and first-render columns that was never the issue, because those were already
 * measured in children.
 *
 * The render loop was a different matter, and the defect is the same one that
 * broke the FIRST-RENDER column before it: deleting cache FILES cannot
 * un-declare a PHP CLASS. An engine whose compile branch is guarded by
 * `class_exists()` therefore SKIPS THE COMPILE on the second and every later
 * measurement in that process, and — critically — often skips the CACHE WRITE
 * with it. The engine then renders in a state no application ever sees.
 *
 * Stempler made this visible and it was not subtle once measured. Its
 * `StemplerEngine::compile()` calls `StemplerCache::isFresh()` on EVERY `get()`,
 * and that call has two costs:
 *
 *     map file ABSENT   -> file_exists() is false, early out          ~0.75 us
 *     map file PRESENT  -> `include` the map + filemtime per dep      ~8.0 us
 *                          (plus include_once of the class file)     ~5.0 us
 *
 * The map file is written only inside Stempler's compile branch, which is guarded
 * by `!class_exists($class)`. So the FIRST measurement of a cell in a process
 * writes the map and then pays ~13 us on every render; the SECOND measurement of
 * that same cell finds the class already declared, skips the branch, never
 * rewrites the map, and pays ~0.75 us on every render. Measured on the bench VM
 * on one warmed object, back to back:
 *
 *     cycle 1   after warm: map=1 class=1   median 0.1161 ms
 *     cycle 2   after warm: map=0 class=0   median 0.1022 ms
 *
 * The map file's mere EXISTENCE therefore decided the published figure, and the
 * harness walked cells in a fixed order, so which engine landed on which side
 * depended on where it sat in the queue rather than on how fast it renders.
 *
 * Only Stempler was affected, and the reason is worth stating because it is what
 * makes this a real defect rather than a quirk: Clarity's `Cache::load()` is
 * REGISTRY-FIRST and its `flush()` CLEARS that registry, so a flush genuinely
 * forces Clarity's reload and its steady state performs no per-render filesystem
 * work at all. Stempler's cache has no in-process registry to invalidate, so it
 * revalidates against the filesystem on every render — which is a real cost, and
 * one its published figure was quietly not paying.
 *
 * A fresh process per cell is the only measurement that cannot be gamed this way.
 * There is no class to inherit, no map file to find already written, and no
 * ordering to get wrong.
 *
 * WHAT THIS PROBE MEASURES, AND WHAT IT DELIBERATELY DOES NOT
 *
 * It measures the STEADY STATE: the cost of a render once the template is
 * compiled and cached and the engine is loaded. That is what a server pays per
 * request after the first one, and it is why the warm-up render below is
 * untimed and outside every statistic.
 *
 * It does NOT measure the first render — that is `first-render-probe.php`, a
 * different question with a different answer — and it does NOT measure memory,
 * which is `peak-probe.php`. Keeping the three apart is deliberate: each was
 * once folded into the harness's own process, and each produced a column that
 * looked like a measurement and was an artifact of measurement order.
 *
 * The private cache directory is REQUIRED, not optional. Without it the render
 * reads whatever the machine's shared temp cache holds, and on the bench VM that
 * is hundreds of compiled classes from earlier runs — so the same job measured
 * differently depending on what had run before it. The directory is created,
 * used, and left for the caller to remove (the caller keyed it by its own PID
 * and knows when the whole run is over).
 *
 * Usage:
 *   php steady-probe.php <engine> <page> <items> <iterations> <runs> <cache-dir> [penalty-separate]
 *
 * Prints a JSON object:
 *   {"samples":[...per-render ms, `runs` x `iterations` of them...],
 *    "run_summaries":[{"mean":..,"min":..,"median":..,"p95":..,"count":N,
 *                      "penalty":{...}|null}, ...],
 *    "penalty_samples":[...],
 *    "output_bytes":N}
 *
 * The per-run summaries are printed rather than reduced here so the HARNESS keeps
 * ownership of how a run is aggregated. A probe that chose its own aggregate
 * would be a second place for `trimmed_mean` to be defined, and the two could
 * disagree without anyone noticing.
 *
 * The penalty is the template-name conversion Clarity itself performs, applied
 * immediately before each timed render so every engine pays the same lookup. It
 * is returned by the shared factory (`engines.php`), never re-implemented here,
 * because a private copy is how a probe ends up measuring different work than the
 * harness under the same label.
 */

require_once __DIR__ . '/engines.php';
// The percentile/median convention, SHARED with the harness. A private copy here
// would be a second definition of p95 that could drift from the one the harness
// prints, and the whole point of a probe is that its number and the harness's
// number mean the same thing.
require_once __DIR__ . '/stats-lib.php';

$engine          = (string) ($argv[1] ?? '');
$pageKey         = (string) ($argv[2] ?? '');
$items           = (int) ($argv[3] ?? 200);
$iterations      = (int) ($argv[4] ?? 0);
$runs            = (int) ($argv[5] ?? 0);
$cacheDir        = (string) ($argv[6] ?? '');
$flags           = array_slice($argv, 7);
$penaltySeparate = in_array('penalty-separate', $flags, true);
$noPenalty       = in_array('no-penalty', $flags, true);

if ($iterations < 1 || $runs < 1) {
    exit(1);
}

$viewPath = __DIR__ . '/templates';

// The page table is SHARED with the harness and the other probes rather than
// duplicated: this is spawned BY the harness, so a private copy would let a row
// describe a page the run never chose.
$pages = require __DIR__ . '/pages.php';

if (!isset($pages[$pageKey])) {
    exit(1);
}

$template = $pages[$pageKey]['template'];
$vars     = $pages[$pageKey]['vars']($items);

try {
    ['engine' => $view, 'penalty' => $penalty] = benchmarkEngine($engine, $template);
} catch (InvalidArgumentException) {
    exit(1);
}

// `--no-penalty` drops the lookup entirely. The harness forwards its own flag so
// a dataset's `penalty_enabled` field describes what was actually measured: a
// run that recorded the flag but still charged the penalty would be lying about
// its own numbers in a way no reader could see.
if ($noPenalty) {
    $penalty = null;
}

$view->setViewPath($viewPath);
$view->addNamespace('benchmarks', $viewPath);

// A PRIVATE cache directory. Required, so a caller that forgets it gets a hard
// failure rather than a number that depends on the machine's temp directory.
if ($cacheDir !== '' && method_exists($view, 'setCachePath')) {
    try {
        $view->setCachePath($cacheDir);
    } catch (LogicException) {}
}

// THE WARM-UP, UNTIMED AND OUTSIDE EVERY STATISTIC.
//
// This is the render that compiles the template and populates the cache — the
// cost `first_render_ms` reports in its own probe. Everything timed below runs
// against the compiled, cached artifact, which is the steady state.
//
// It is also what guarantees the cache WRITE has happened, so the render loop
// measures the same state for every engine: an engine that only writes its cache
// when the compile branch runs would otherwise be timed against a cache it had
// never written, and the column would report whichever engines happened to
// compile rather than which render fastest.
$view->render($template, $vars);

$samples        = [];
$penaltySamples = [];
$runSummaries   = [];

for ($r = 0; $r < $runs; $r++) {
    $times        = [];
    $penaltyTimes = [];

    for ($i = 0; $i < $iterations; $i++) {
        $t0          = hrtime(true);
        $penaltyTime = 0.0;

        if ($penalty !== null) {
            $p0 = hrtime(true);
            $penalty();
            $p1          = hrtime(true);
            $penaltyTime = ($p1 - $p0) / 1e6;
        }

        $view->render($template, $vars);
        $t1 = hrtime(true);

        $iterTime = ($t1 - $t0) / 1e6;
        if ($penalty !== null && !$penaltySeparate) {
            // Subtract the penalty from the recorded render time, so the column
            // is pure render cost. `--penalty-separate` keeps it in, which is
            // how the harness audits that the subtraction is not hiding work.
            $iterTime = max(0.0, $iterTime - $penaltyTime);
        }

        $times[] = $iterTime;
        if ($penalty !== null) {
            $penaltyTimes[] = $penaltyTime;
        }
    }

    // Both summaries come from the SHARED helper, so a p95 computed here and one
    // computed by the harness describe the same index of the same distribution.
    $runSummaries[] = viewEngineStats($times) + [
            'penalty' => $penaltyTimes === [] ? null : viewEngineStats($penaltyTimes),
        ];

    foreach ($times as $t) {
        $samples[] = $t;
    }
    foreach ($penaltyTimes as $t) {
        $penaltySamples[] = $t;
    }
}

$output = $view->render($template, $vars);

echo json_encode([
    'samples'         => $samples,
    'run_summaries'   => $runSummaries,
    'penalty_samples' => $penaltySamples,
    'output_bytes'    => strlen($output),
], JSON_UNESCAPED_SLASHES);