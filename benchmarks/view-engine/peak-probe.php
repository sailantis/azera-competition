<?php

declare(strict_types=1);

/**
 * Memory for ONE engine, measured in a fresh process: the heap floor, what it
 * retains after the first render, what it retains after the whole run, and the
 * high-water mark over that run.
 *
 * WHY A SEPARATE PROCESS
 *
 * `memory_get_peak_usage()` is a PROCESS high-water mark that never decreases.
 * Measured in the harness's own process, every engine after the first reports
 * the maximum of all of them rather than its own footprint — the 2026-09-22 run
 * produced four identical readings of 52.44 MB and one honest 23.08 MB, i.e. a
 * column that looked like a measurement and was an order-of-measurement
 * artifact. A child process is the only honest way to answer "what does this
 * engine cost alone", and it runs once per ENGINE rather than per iteration,
 * because a process spawn per render would swamp the thing being measured.
 *
 * WHY A COMMITTED FILE RATHER THAN GENERATED CODE
 *
 * This started as a script the harness wrote into the template directory at run
 * time. It broke silently: the child died on a bad `require` path and
 * `shell_exec()` returned nothing, which a `?? memory_get_peak_usage(...)`
 * fallback turned into the parent's cumulative figure — plausible-looking,
 * wrong, and invisible. A real file can be linted, run by hand, and covered by a
 * test; a string built at run time can only be debugged from its symptoms.
 *
 * OPCACHE IS ON, BUT THE CLI SEGMENT IS PER-PROCESS.
 *
 * This child runs with `opcache.enable_cli=1`, because a real deployment runs
 * with OPcache on (RoadRunner included). It matters for what the figures MEAN,
 * but it does NOT make this child share bytecode with any other: PHP's anonymous
 * shared mmap is inherited by fork(), not across exec(), so every shell_exec'd
 * `php` gets its OWN fresh segment and compiles everything it loads.
 *
 * So the `peakN` this probe prints INCLUDES the per-process compile of the
 * engine's own source files. That is DELIBERATE: it is the honest high-water mark
 * of the process that handles the first request after a deploy, which is the
 * question this chart asks. It also makes the peak sensitive to the LARGEST
 * single source file, because compiles are sequential and each returns to
 * baseline — which is exactly why splitting a monolithic engine file lowers it.
 *
 * CORRECTION 2026-09-29: an earlier version of this docblock claimed the harness
 * primed a SHARED segment via primeOpcodeCache(). That is WRONG for this
 * topology. Proven: a second process sees `before=false` for a file the first
 * process compiled, and "no prime" vs "with prime" gave byte-identical readings.
 * primeOpcodeCache() is now harmless and would only matter under
 * `opcache.file_cache` (which IS shared across processes).
 *
 * The TEMPLATE cache is the other temperature, and it is made cold by the
 * caller: a private, empty directory is handed in below.
 *
 * Usage: php peak-probe.php <engine> <items> [private-cache-dir] [renders] [page]
 *
 * Prints `key=value` pairs separated by `;`, or nothing on an unusable argument:
 *
 *   base    heap with the engine constructed and configured, nothing rendered
 *   use1    heap retained after render 1
 *   useN    heap retained after all R renders, gc collected
 *   peakN   the process high-water mark over the whole run
 *   cycles  cycles actually collected by the gc call before useN
 *
 * WHY FOUR FIGURES AND NOT ONE
 *
 * THE OLD SUSTAINED COLUMN WAS DEAD. It reported the peak after R renders and
 * compared it with the peak after 1 render, and those are the SAME NUMBER on
 * this workload: a high-water mark is set by the compile during render 1 and
 * nothing in ten thousand further renders exceeds it. Measured 2026-09-28, the
 * published `mem_delta` column ran from 16 to 304 bytes across every engine and
 * every page — 0.00%. It was not a small effect, it was structurally incapable
 * of being an effect, which is the third time this file's neighbourhood has
 * shipped a column that looked like a measurement and was an ordering artifact
 * (the staircase medians, then the in-process delta, then this).
 *
 * WHAT REPLACES IT. `memory_get_usage()` asks a question a peak cannot: what is
 * still held NOW. On this workload it is also FLAT across renders — measured
 * 0.00% growth from 1 render to 10,000 — and that flatness is the finding, not a
 * failure to find one: no engine leaks, and the retained figure is what a
 * serving process actually carries. The peak is still recorded beside it,
 * because the transient compile spike it captures is real and the first request
 * after a deploy really does pay it.
 *
 * `base` is the missing axis those two were being read against: every engine
 * starts at the PHP runtime plus its own classes, so most of the DIFFERENCE
 * between engines is the first render's retained cost rather than a per-render
 * one — kilobytes for the engine that never loads a compiler against a couple of
 * megabytes for one that does. Reported per-engine so the chart can state that
 * instead of anchoring every bar at zero.
 *
 * gc_collect_cycles() IS CALLED AND NOW COLLECTS SOMETHING — FOR ONE ENGINE.
 *
 * Corrected 2026-10-01. This section said the call "collects nothing", which was
 * true of the six engines measured at the time and is FALSE for Latte, the
 * seventh: on the sample page it returns a few hundred cycles per run. The cause
 * is Latte's own `{block}`/layout rendering, which builds objects that reference
 * each other, so PHP reclaims them with the cycle collector rather than by
 * refcounting — measured at ~4 KB of garbage per render, and reproduced with a
 * bare engine and no template cache, i.e. WITHOUT this harness. A single
 * template with no layout produced none at all.
 *
 * WHY THE PEAK IS STILL THE HONEST SERVER NUMBER, NOT A LOOP ARTIFACT. PHP frees
 * cyclic garbage in BATCHES — the collector runs when the root buffer fills (on
 * the order of 10,000 roots), so up to a batch's worth is live at once and
 * `memory_get_peak_usage()` records exactly that instant. A long-lived worker
 * sees the same sawtooth: the batch is reclaimed automatically, so the peak is a
 * real transient of a few megabytes above base rather than a cost invented by
 * looping. PHP-FPM does not carry it, because `php_request_shutdown` frees
 * request-scoped garbage at the end of every request. This workspace's
 * RoadRunner pool is configured `max_jobs: 0` (never recycled), so the peak is
 * the figure that describes it.
 *
 * WHY THE CALL IS STILL MADE BEFORE READING useN. The batch makes a raw
 * `memory_get_usage()` depend on where the run stopped in the sawtooth, and the
 * published figure must not. Collecting first is what makes "retained" mean what
 * it says. It is NOT a way to hide the garbage: a collection cannot lower a peak
 * that has already been recorded, and `peakN` is read BEFORE this call.
 *
 * `cycles` is reported beside it so a reader can see whether it did any work; it
 * is a diagnostic, not a measurement of the engine.
 */

require_once __DIR__ . '/engines.php';

$engine       = (string) ($argv[1] ?? '');
$items        = (int) ($argv[2] ?? 200);
$privateCache = (string) ($argv[3] ?? '');
$renders      = max(1, (int) ($argv[4] ?? 1));
$pageKey      = (string) ($argv[5] ?? 'sample');

$viewPath = __DIR__ . '/templates';

// The page table is SHARED with run.php and verify.php rather than duplicated
// here. This probe is spawned by the harness, so a private copy of the table
// would let the memory column describe a page the run never chose — and a wrong
// number that looks plausible is the failure mode this probe exists to avoid.
$pages = require __DIR__ . '/pages.php';

if (!isset($pages[$pageKey])) {
    // Nothing on stdout and a non-zero status, same convention as an unknown
    // engine: the caller records the reading as ABSENT rather than filling it.
    exit(1);
}

$template = $pages[$pageKey]['template'];
$vars     = $pages[$pageKey]['vars']($items);

// The engine is built by the SAME factory the harness uses. This probe used to
// hold its own switch, which is how `clarity-open` needed to be taught to two
// separate files — and how a key the probe does not know would have measured the
// SANDBOXED engine and printed it under the open-mode row, i.e. a plausible
// number for the wrong work. `engines.php` carries the full account.
//
// The private cache directory is applied below from the caller's argument, for
// every engine alike.
//
// No penalty: this measures memory, and the penalty is a conversion the harness
// subtracts from a render time this probe does not record.
try {
    ['engine' => $view] = benchmarkEngine($engine, $template, false);
} catch (InvalidArgumentException) {
    // Nothing on stdout, and a non-zero status, so the caller records the
    // reading as ABSENT rather than substituting another engine's.
    exit(1);
}

$view->setViewPath($viewPath)->addNamespace('benchmarks', $viewPath);

// A PRIVATE, EMPTY cache directory when one is supplied. The engines that
// compile to disk (Clarity, Twig, Blade) otherwise reuse whatever the machine's
// shared temp cache happens to hold — on the bench VM that is hundreds of
// compiled classes from earlier runs, and the same render measured 47.7 MB there
// against 1.6 MB here. A number that changes with the machine's temp directory
// is not a measurement of the engine. Native and Plates keep no disk cache and
// throw by design, so this asks rather than assumes.
if ($privateCache !== '' && method_exists($view, 'setCachePath')) {
    try {
        $view->setCachePath($privateCache);
    } catch (LogicException) {}
}

// THE FLOOR, taken before any render. Everything above it — the runtime, the
// autoloader, this engine's own classes — is the cost of having the engine
// loaded at all, and it is the axis the other figures are differences from.
gc_collect_cycles();
$base = memory_get_usage(false);

// RENDER 1, then stop and ask what is held. This is the figure the harness used
// to read as a PEAK, and the difference matters: the peak here is the transient
// allocation high-water mark of compiling and writing the cache, while the
// retained figure is what survives it. The engine, its compiler and the compiled
// class are all loaded by now and are retained, so use1 and useN agree on every
// engine that creates no garbage — which is why the harness publishes ONE
// retained figure and reports growth separately rather than drawing a second
// mark that cannot move.
//
// `use1` is taken WITHOUT a gc call, so on an engine whose render leaves
// collectable cycles (Latte) it reads high by whatever the buffer happened to
// hold at that moment — on the sample page 1682 KB against a post-collection
// 1472 KB, a -12% "growth" that is really the collector's backlog. That is why
// `useN`, the post-collection reading, is the published figure and this one is
// not.
$view->render($template, $vars);
$use1 = memory_get_usage(false);

// RENDERS 2..R.
for ($i = 1; $i < $renders; $i++) {
    $view->render($template, $vars);
}

// The high-water mark over the WHOLE run, read before the gc call so the
// collector cannot lower it and hide a peak that really happened.
$peakN = memory_get_peak_usage(false);

// Collect BEFORE reading the retained figure, so the number is defined as
// post-collection rather than as whatever the root buffer happened to hold.
$cycles = gc_collect_cycles();
$useN   = memory_get_usage(false);

echo implode(';', [
    'base=' . $base,
    'use1=' . $use1,
    'useN=' . $useN,
    'peakN=' . $peakN,
    'cycles=' . $cycles,
]);