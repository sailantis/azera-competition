<?php

declare(strict_types=1);

/**
 * What the template sandbox costs, measured on a page that is ALMOST PURE
 * variable access.
 *
 * WHY THIS IS COMMITTED
 *
 * Clarity has two modes: sandboxed (the default) and open (`Policy::open()`,
 * the former `setSandboxMode(false)`, which grants templates full PHP for
 * Blade / Stempler / Plates parity). They compile the SAME template
 * differently — open mode seeds the render scope with one `extract($__c_va,
 * EXTR_SKIP)` and then reads PHP locals, where the sandbox reads
 * `$__c_va['name']` at every access. That is a real trade (one `extract()` against
 * one hash lookup per access) and it is ONLY observable as a time difference, so
 * no correctness test can guard it: a future change that made open mode
 * expensive, or that quietly reintroduced the hash lookup, would render identical
 * pages.
 *
 * This probe is what makes the claim falsifiable, in the same spirit as
 * `access-path-probe.php` (which does this for the removed `castToArray()`
 * traversal).
 *
 * WHAT IT MEASURES, AND WHY THIS SHAPE
 *
 * The measured benchmark pages cannot answer the question. On `mixed` — the
 * widest page — the 44 flat (mode-sensitive) accesses are roughly 3% of a render
 * whose time is dominated by 200 loop iterations of echoing and escaping, and the
 * loop's field reads are read from a local in BOTH modes. So a per-access
 * difference has nowhere to appear, and the published page deltas duly came back
 * between -2.6% and -0.2% with an INCONSISTENT SIGN, i.e. noise.
 *
 * Widening a template cannot fix that either: the number of flat accesses is
 * fixed by the template's text, so a loop only ever adds iterations, never
 * accesses. This probe therefore removes the loop entirely and builds a page of
 * `--accesses` flat accesses and nothing else. If the effect is invisible even
 * here, it is invisible under any shape a real template can have.
 *
 * THE TWO ARMS MUST AGREE
 *
 * Both arms render the same page and the probe exits non-zero if the outputs
 * differ, so "open mode is cheaper" can never be reported for a page that is not
 * the same page. It also ALTERNATES which arm runs first, because a fixed order
 * is a warm-heap bias rather than a comparison — the trap `access-path-probe.php`
 * documents and an earlier probe of this shape fell into.
 *
 * Usage: php mode-cost-probe.php [accesses] [iterations] [repeats]
 *   accesses   default 200   flat variable accesses in the page
 *   iterations default 20000 renders per arm
 *   repeats    default 7     arm pairs; medians are taken across repeats
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Core\Engines\ClarityEngine;
use Clarity\Engine\Policy as ClarityPolicy;

$accesses   = (int) ($argv[1] ?? 200);
$iterations = (int) ($argv[2] ?? 20000);
$repeats    = max(1, (int) ($argv[3] ?? 7));

$tplDir = sys_get_temp_dir() . '/mode-cost-tpl-' . getmypid();
@mkdir($tplDir, 0777, true);

/**
 * Remove a tree, ignoring errors.
 *
 * Windows keeps a handle on a just-written file, and a directory that is already
 * gone needs no handling, so a failure here must not turn a completed
 * measurement into an error.
 */
$removeTree = static function (string $dir) use (&$removeTree): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (@scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $p = $dir . '/' . $item;
        is_dir($p) ? $removeTree($p) : @unlink($p);
    }
    @rmdir($dir);
};

// Build the page: N flat accesses, no loop. Every value is HTML-hostile so the
// escape path does real work in both arms (on benign integers an escape and a
// non-escape are byte-identical, which would leave half the per-access cost
// untested).
$vars = [];
$body = '';
for ($i = 1; $i <= $accesses; $i++) {
    $name = 'v' . $i;
    $vars[$name] = '<b>&"value' . $i . '"</b>';
    $body .= '{{ ' . $name . ' }}';
}
file_put_contents($tplDir . '/micro.clarity.html', $body);

/**
 * One arm: build an engine in the given mode, warm it, then time the renders.
 *
 * Each arm gets a PRIVATE, freshly-emptied cache directory. The compiled class is
 * mode-specific and its filename is derived from the template name alone, so
 * without this the two arms would evict each other's class and each render would
 * pay a recompile — which would swamp the difference being measured.
 *
 * @param array<string,mixed> $vars
 * @return array{median:float,min:float,out:string}
 */
function armCost(string $tplDir, bool $sandboxed, array $vars, int $iterations): array
{
    global $removeTree;

    $cache = sys_get_temp_dir() . '/mode-cost-cache-' . ($sandboxed ? 'sb' : 'op') . '-' . getmypid();
    $removeTree($cache);
    mkdir($cache, 0777, true);

    $e = new ClarityEngine();
    $e->setExtension('.clarity.html');
    $e->setPolicy($sandboxed ? ClarityPolicy::sandboxed() : ClarityPolicy::open());
    $e->setViewPath($tplDir)->addNamespace('benchmarks', $tplDir);
    $e->setCachePath($cache);

    $out   = $e->render('micro', $vars); // compile + warm, never timed
    $times = [];
    for ($i = 0; $i < $iterations; $i++) {
        $t0 = hrtime(true);
        $e->render('micro', $vars);
        $times[] = (hrtime(true) - $t0) / 1e6;
    }
    $removeTree($cache);
    sort($times);

    return [
        'median' => $times[(int) (\count($times) / 2)],
        'min'    => $times[0],
        'out'    => $out,
    ];
}

printf(
    "mode cost probe: %d flat accesses, no loop, %d renders x %d repeats\n\n",
    $accesses,
    $iterations,
    $repeats
);

$sand      = [];
$open      = [];
$identical = true;

try {
    for ($r = 0; $r < $repeats; $r++) {
        // Alternate which arm runs first: whichever runs second otherwise
        // benefits from a warmer heap.
        if ($r % 2 === 0) {
            $a = armCost($tplDir, true, $vars, $iterations);
            $b = armCost($tplDir, false, $vars, $iterations);
        } else {
            $b = armCost($tplDir, false, $vars, $iterations);
            $a = armCost($tplDir, true, $vars, $iterations);
        }
        $sand[] = $a['median'];
        $open[] = $b['median'];
        if ($a['out'] !== $b['out']) {
            $identical = false;
        }
    }
} finally {
    @unlink($tplDir . '/micro.clarity.txt');
    @unlink($tplDir . '/micro.clarity.html');
    $removeTree($tplDir);
}

// Non-vacuity: a speed result for two different pages is not a result.
if (!$identical) {
    fwrite(STDERR, "MISMATCH: the two modes rendered DIFFERENT pages; the timing comparison is void.\n");
    exit(1);
}

sort($sand);
sort($open);
$mid   = (int) (\count($sand) / 2);
$sm    = $sand[$mid];
$om    = $open[$mid];
$delta = ($om / $sm - 1) * 100;

// The spread ACROSS REPEATS is the noise floor. A difference smaller than it is
// not measurable, however consistent the sign looks in one run — which is exactly
// how the published page deltas (-2.6%..-0.2%, inconsistent sign) were read as a
// finding when they were noise.
$sandSpread = ($sand[\count($sand) - 1] / $sand[0] - 1) * 100;
$openSpread = ($open[\count($open) - 1] / $open[0] - 1) * 100;

printf("  sandboxed median: %.6f ms   (spread across repeats: %.1f%%)\n", $sm, $sandSpread);
printf("  open      median: %.6f ms   (spread across repeats: %.1f%%)\n", $om, $openSpread);
printf("  open vs sandboxed: %+.2f%%   -> %+.3f ns per access\n\n", $delta, ($om - $sm) / $accesses * 1e6);

$noise = max($sandSpread, $openSpread, 0.1);
if (abs($delta) < $noise) {
    printf(
        "VERDICT: the modes are indistinguishable at this width (|%.2f%%| < %.1f%% noise floor).\n"
            . "         Open mode costs nothing measurable, and cannot be shown to save anything either.\n",
        $delta,
        $noise
    );
} elseif ($delta > 0) {
    printf("VERDICT: open mode is SLOWER by %.2f%% at this width.\n", $delta);
} else {
    printf("VERDICT: open mode is FASTER by %.2f%% at this width.\n", -$delta);
}
