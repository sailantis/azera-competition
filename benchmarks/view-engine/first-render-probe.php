<?php

declare(strict_types=1);

/**
 * ONE engine's FIRST render, timed in a fresh process with a cold template
 * cache. Prints milliseconds on stdout, or nothing and a non-zero status.
 *
 * WHY A SEPARATE PROCESS, AND WHY THAT IS THE WHOLE POINT
 *
 * The harness used to time the first render IN ITS OWN PROCESS, as:
 *
 *     render (untimed)  ->  flushCache()  ->  render (timed)
 *
 * The untimed render was there to stop the number from including the engine's
 * one-time class loading. It did that, but it introduced a worse defect, because
 * deleting cache FILES cannot un-declare a PHP CLASS. For any engine whose
 * compile path is guarded by `class_exists()`, the class is still declared after
 * the flush, so the timed render SKIPS THE COMPILE ENTIRELY and the published
 * "first render" was a warm render with a compile's label on it.
 *
 * Measured on the bench VM, this was not a small error. The discriminator is
 * whether the timed render WROTE A CACHE FILE AGAIN:
 *
 *   engine    timed render   steady render   rewrote cache?   error
 *   clarity        0.98 ms        0.42 ms   yes (1/0/1)      -
 *   blade          1.94 ms        0.75 ms   yes (3/0/3)      -
 *   twig           1.42 ms        1.23 ms   NO  (3/0/0)      28x too fast
 *   stempler       0.46 ms        0.41 ms   NO  (2/0/0)      51x too fast
 *
 * Blade's and Twig's own compile paths make the mechanism explicit:
 * `elseif (!\class_exists($class))` — once the class exists, the compile is
 * skipped. Twig's and Stempler's true cold compile is 36 ms and 24 ms, and the
 * chart drew them at 1.4 ms and 0.5 ms, which is why a compiling engine appeared
 * to lose to one that does not compile at all.
 *
 * A fresh process is the only measurement that cannot be gamed this way. There is
 * no class to inherit, no bootstrap already paid, and no ordering to get wrong.
 *
 * WHAT THE NUMBER INCLUDES, DELIBERATELY
 *
 *   - the engine's own class loading (autoload and link of its runtime);
 *   - the template compile;
 *   - the cache write;
 *   - one execution of the compiled template.
 *
 * This is the cost of the first request after a deploy, which is the question
 * the chart's title asks. It therefore puts caching engines well above `native`,
 * which has no compile step at all — its "compile" is a render. That is the
 * honest answer, not a defect, and it is why the caption says "first render"
 * rather than "compile": for `native` those are the same thing and for the
 * others they are not.
 *
 * OPCACHE IS ON (the child runs `opcache.enable_cli=1`), matching a real
 * deployment. It does NOT make the reading order-dependent, because the CLI
 * segment is PER-PROCESS: PHP's anonymous shared mmap is inherited by fork(), not
 * across exec(), so this child compiles the engine's own source itself. The
 * number therefore includes both the engine compile and the template compile —
 * the honest "first request after a deploy" cost, which is the question the
 * chart's title asks. The caller hands in a private, empty template cache every
 * time, so the TEMPLATE side is genuinely cold.
 *
 * CORRECTION 2026-09-29: an earlier version claimed the harness warmed a SHARED
 * segment with a throwaway child (primeOpcodeCache()), making the measured render
 * an opcode cache HIT. That is WRONG — the next process cannot see the previous
 * process's CLI segment. primeOpcodeCache() is harmless and would matter only
 * under `opcache.file_cache`, which IS shared across processes.
 *
 * A SECOND RENDER IS ALSO PRINTED. It is the same process, immediately after,
 * so the difference between the two columns is the compile cost alone — useful
 * for a reader who wants to separate "compiling" from "booting", and free to
 * take. It is NOT the published figure; the published figure is the first.
 *
 * Usage: php first-render-probe.php <engine> <page> [items] [private-cache-dir]
 *
 * Prints "<first_ms> <second_ms> <output_bytes>", or nothing on an unusable
 * argument so the caller records the reading as ABSENT rather than filling it.
 */

require_once __DIR__ . '/engines.php';

$engine   = (string) ($argv[1] ?? '');
$pageKey  = (string) ($argv[2] ?? 'sample');
$items    = (int) ($argv[3] ?? 200);
$cacheDir = (string) ($argv[4] ?? '');

$viewPath = __DIR__ . '/templates';

// The page table is SHARED with the harness and the other probe rather than
// duplicated: this is spawned BY the harness, so a private copy would let the
// first-render column describe a page the run never chose.
$pages = require __DIR__ . '/pages.php';

if (!isset($pages[$pageKey])) {
    exit(1);
}

$template = $pages[$pageKey]['template'];
$vars     = $pages[$pageKey]['vars']($items);

try {
    // No penalty: this measures the cost of arriving at a rendered page, and the
    // penalty is a conversion the harness subtracts from a render time this
    // probe does not record.
    ['engine' => $view] = benchmarkEngine($engine, $template, false);
} catch (InvalidArgumentException) {
    exit(1);
}

$view->setViewPath($viewPath);
$view->addNamespace('benchmarks', $viewPath);

// A PRIVATE, EMPTY cache directory when one is supplied. Without it a persistent
// cache from an earlier run turns "first render" into "warm render", which is the
// defect above wearing a different hat. Engines with no disk cache
// (native, plates) throw by design, so this asks rather than assumes.
if ($cacheDir !== '' && method_exists($view, 'setCachePath')) {
    try {
        $view->setCachePath($cacheDir);
    } catch (LogicException) {}
}

$start  = hrtime(true);
$output = $view->render($template, $vars);
$first  = (hrtime(true) - $start) / 1e6;

$start = hrtime(true);
$view->render($template, $vars);
$second = (hrtime(true) - $start) / 1e6;

printf("%.4f %.4f %d\n", $first, $second, strlen($output));