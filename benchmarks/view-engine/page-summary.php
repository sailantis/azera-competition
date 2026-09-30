<?php
// Read a (possibly multi-page) view-engine dataset: one matrix per page, plus
// what each page COSTS over the scalars page in the same run.
//
// Why this exists next to scripts/view-engine-report.php: the report's job is to
// PUBLISH a dataset. This one is for reading one, and the question it answers is
// the one a published chart cannot — "is the mixed page still doing any work
// over plain scalars, and where do the objects rows sit?" — because a report
// renders each page on its own axis by design (they measure different work).
//
// A page missing from a dataset is named as missing rather than skipped. A
// one-page dataset and a three-page one print identically if you only list the
// rows that exist, and the difference is exactly what makes a figure comparable
// or not.
//
// Usage:
//   php benchmarks/view-engine/page-summary.php [dataset.json]
//   php benchmarks/view-engine/page-summary.php a.json b.json    # 2nd = baseline

function psLoad(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "no such dataset: {$path}\n");
        exit(1);
    }
    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json) || !isset($json['results']) || !is_array($json['results'])) {
        fwrite(STDERR, "not a view-engine dataset: {$path}\n");
        exit(1);
    }

    $byPage = [];
    foreach ($json['results'] as $row) {
        if (!isset($row['engine'])) {
            continue;
        }
        // A row with no `page` field comes from a single-page dataset, and the
        // page it measured is `sample` — the harness's original and only page.
        $page = (string) ($row['page'] ?? 'sample');
        $byPage[$page][(string) $row['engine']] = $row;
    }

    return [$json['env'] ?? [], $byPage];
}

/** Canonical order: the page table first, unknown pages after it. */
function psPageOrder(array $pages): array
{
    $canonical = array_keys(require __DIR__ . '/pages.php');
    $out       = [];
    foreach ($canonical as $p) {
        if (isset($pages[$p])) {
            $out[] = $p;
        }
    }
    foreach (array_keys($pages) as $p) {
        if (!in_array($p, $out, true)) {
            $out[] = $p;
        }
    }

    return $out;
}

/** Canonical engine order, so a known engine is never shuffled to the end. */
function psEngineOrder(array $pages): array
{
    $canonical = ['native', 'clarity', 'clarity-open', 'plates', 'blade', 'twig', 'stempler'];
    $seen      = [];
    foreach ($pages as $rows) {
        foreach (array_keys($rows) as $e) {
            $seen[$e] = true;
        }
    }
    $out = [];
    foreach ($canonical as $e) {
        if (isset($seen[$e])) {
            $out[] = $e;
        }
    }
    foreach (array_keys($seen) as $e) {
        if (!in_array($e, $out, true)) {
            $out[] = $e;
        }
    }

    return $out;
}

$argvAll = array_slice($argv, 1);
// The default points at the dataset of record, not at a long-deleted one. It
// used to name `...-r8-pages-...`, which no longer exists, so running this with
// no argument failed on a missing file rather than showing the latest figures.
$target   = $argvAll[0] ?? 'benchmarks/view-engine/view-engine-2026-09-27-ffr5-avg-10000x30-items200.json';
$baseline = $argvAll[1] ?? null;

[$envA, $pagesA] = psLoad($target);
[$envB, $pagesB] = $baseline !== null ? psLoad($baseline) : [[], []];

$budget = static function (array $env): string {
    return sprintf(
        'items=%s  iterations=%s  runs=%s  engines=%s',
        (string) ($env['items'] ?? '?'),
        (string) ($env['iterations_per_run'] ?? '?'),
        (string) ($env['runs'] ?? '?'),
        implode(',', (array) ($env['engines'] ?? []))
    );
};

printf("%s\n  %s\n", basename($target), $budget($envA));
if ($baseline !== null) {
    printf("baseline: %s\n  %s\n", basename($baseline), $budget($envB));
}

$orderPages = psPageOrder(array_merge_recursive($pagesA, $pagesB));
$orderEng   = psEngineOrder($pagesA);

foreach ($orderPages as $page) {
    $rowsA = $pagesA[$page] ?? [];
    $rowsB = $pagesB[$page] ?? [];

    printf("\n=== page: %s ===\n", $page);
    if ($rowsA === []) {
        printf("  NOT MEASURED in %s\n", basename($target));
    }

    // Per-engine medians on this page, fastest first (rows the target did not
    // measure sink to the bottom rather than being ranked on another run's
    // number, which would put them in a race they never ran).
    $medA = [];
    foreach ($rowsA as $e => $r) {
        $medA[$e] = isset($r['median_ms']) ? (float) $r['median_ms'] : null;
    }
    $list = [];
    foreach ($orderEng as $e) {
        if (isset($medA[$e])) {
            $list[] = $e;
        }
    }
    usort($list, static function (string $a, string $b) use ($medA): int {
        return ($medA[$a] ?? INF) <=> ($medA[$b] ?? INF);
    });

    $base = $list !== [] ? $medA[$list[0]] : null;

    printf("  %-10s %11s %11s %11s %9s %9s %9s\n", 'engine', 'median', 'min', 'p95', 'speed', 'first', 'retained MB');
    foreach ($list as $e) {
        $r = $rowsA[$e];
        $m = (float) $medA[$e];
        printf(
            "  %-10s %11.4f %11.4f %11.4f %8s %9.4f %9s\n",
            $e,
            $m,
            (float) ($r['min_ms'] ?? 0),
            (float) ($r['p95_ms'] ?? 0),
            $base !== null && $base > 0 ? sprintf('%.2fx', $m / $base) : '-',
            (float) ($r['first_render_ms'] ?? 0),
            isset($r['retained_mem']) && is_numeric($r['retained_mem'])
                ? sprintf('%.2f', ((float) $r['retained_mem']) / 1048576)
                : '-'
        );
    }
    foreach ($orderEng as $e) {
        // Only name an engine as absent when the page WAS measured — on a page
        // the dataset does not cover, listing every missing engine would suggest a
        // per-engine problem rather than a missing page.
        if ($rowsA !== [] && !isset($rowsA[$e])) {
            printf("  %-10s %11s\n", $e, '-');
        }
    }
}

// What each page costs over the scalars page, inside this one run. This is the
// comparison the pages exist to make, and it is only valid WITHIN a dataset
// (both halves then share the machine, the load and the budget).
if (count($orderPages) > 1) {
    $sample = $pagesA['sample'] ?? [];
    if ($sample === []) {
        printf("\n(page deltas need a `sample` page in the dataset; none measured)\n");
        exit(0);
    }
    printf("\n=== cost over the sample page (same run) ===\n");
    printf("  %-10s %12s %12s %12s %10s\n", 'engine', 'page', 'sample', 'delta ms', 'delta %');
    foreach ($orderPages as $page) {
        if ($page === 'sample' || !isset($pagesA[$page])) {
            continue;
        }
        foreach (psEngineOrder([$pagesA[$page], $sample]) as $e) {
            $a = $pagesA[$page][$e]['median_ms'] ?? null;
            $s = $sample[$e]['median_ms'] ?? null;
            if ($a === null || $s === null) {
                continue;
            }
            $a = (float) $a;
            $s = (float) $s;
            printf("  %-10s %12s %12.4f %12.4f %9.1f%%\n", $e, $page, $s, $a - $s, $s > 0 ? ($a - $s) / $s * 100 : 0.0);
        }
    }
}

// Objects vs plain arrays, on the same content.
//
// Printed whenever BOTH pages are present. The pair was ORIGINALLY motivated by
// a Clarity-specific cost — the engine used to convert the whole variable tree
// to arrays on every render, so it paid on objects something the others did not.
// That conversion is gone and the compiler now emits real `$item->label` reads,
// so a difference here is a property of the ACCESS SHAPE rather than of one
// engine's pre-processing. A positive number means the engine prefers OBJECTS;
// negative means it prefers ARRAYS.
$pair = ['entities', 'entities-array'];
if (isset($pagesA[$pair[0]], $pagesA[$pair[1]])) {
    printf("\n=== objects vs the same data as arrays (same run) ===\n");
    printf("  %-10s %12s %12s %12s %10s\n", 'engine', 'objects', 'arrays', 'delta ms', 'objects vs arrays');
    foreach (psEngineOrder([$pagesA[$pair[0]], $pagesA[$pair[1]]]) as $e) {
        $o = $pagesA[$pair[0]][$e]['median_ms'] ?? null;
        $r = $pagesA[$pair[1]][$e]['median_ms'] ?? null;
        if ($o === null || $r === null) {
            continue;
        }
        $o = (float) $o;
        $r = (float) $r;
        printf(
            "  %-10s %12.4f %12.4f %12.4f %9.1f%%\n",
            $e,
            $o,
            $r,
            $o - $r,
            $r > 0 ? ($o - $r) / $r * 100 : 0.0
        );
    }
}

// Optional baseline comparison, per page and per engine.
if ($baseline !== null) {
    printf("\n=== vs baseline (%s) ===\n", basename($baseline));
    printf("  %-10s %-10s %12s %12s %11s\n", 'page', 'engine', 'baseline', 'target', 'delta %');
    foreach ($orderPages as $page) {
        foreach ($orderEng as $e) {
            $a = $pagesA[$page][$e]['median_ms'] ?? null;
            $b = $pagesB[$page][$e]['median_ms'] ?? null;
            if ($a === null || $b === null) {
                continue;
            }
            $a = (float) $a;
            $b = (float) $b;
            printf("  %-10s %-10s %12.4f %12.4f %10.1f%%\n", $page, $e, $b, $a, $b > 0 ? ($a - $b) / $b * 100 : 0.0);
        }
    }
}