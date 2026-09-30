<?php

declare(strict_types=1);

/**
 * azera-competition — view-engine report generator.
 *
 * Turns the template-engine harness output
 * (benchmarks/view-engine/run.php) into a published page: static SVG charts, a
 * Markdown fragment and an HTML page, all drawn from the run's own JSON.
 *
 * WHY THIS EXISTS
 *
 * The engine comparison used to be published as hand-typed tables plus a
 * hand-drawn SVG, in three separate repositories. Nothing recomputed those
 * cells when a dataset changed, so they silently outlived the runs behind them
 * and the copies disagreed with each other by roughly a factor of two. Here a
 * chart and its table are rendered from the same JSON in one pass, so they
 * cannot contradict each other or the dataset.
 *
 * It reuses SvgChart — the same primitives the framework report draws with —
 * rather than a second chart implementation. The charts therefore look like the
 * rest of the benchmark site and inherit the hard constraints already paid for
 * there: presentation attributes only (GitHub strips <style>/class out of
 * rendered Markdown), a baked light card so an <img>-embedded SVG does not
 * depend on the host theme, and no timestamp anywhere in the output, so two
 * renders of one dataset are byte-identical.
 *
 * Usage:
 *   php scripts/view-engine-report.php --dataset=benchmarks/view-engine/<prefix>.json
 *   php scripts/view-engine-report.php --dataset=<file> --out=results/<dir>
 *   php scripts/view-engine-report.php --dataset=<file> --publish=clarity-docs
 *   php scripts/view-engine-report.php --dataset=<file> --publish=framework
 *   php scripts/view-engine-report.php --list
 *
 * Output (default docs/benchmarks/):
 *   view-engine.md          Markdown fragment (competition site + docs)
 *   view-engine.html        standalone page, linked from the dashboard
 *   svg/view-engine/*.svg   the charts
 *   view-engine.csv         the dataset, copied next to the page it describes
 */

require_once __DIR__ . '/report/SvgChart.php';

use AzeraCompetition\Report\SvgChart;

// -----------------------------------------------------------------------------
// Engine identity
// -----------------------------------------------------------------------------

/**
 * The order engines are presented in, and how each is styled.
 *
 * Canonical order rather than arrival order, so a dataset measured with a
 * different `--engines=...` sequence renders the same way. `clarity` and
 * `native` are engines inside the Azera framework (src/Core/Engines/); the rest
 * are the wrapped third-party packages named beside them.
 *
 * COLOUR POLICY — a legend is worthless if a reader cannot tell which row is
 * which, so the only hard requirement is that the hues stay distinguishable
 * against the chart's white card and from each other. Beyond that:
 *
 *  - `clarity` carries **Azera's brand blue `#3459e6`**, the same value the
 *    suite already paints the Azera bar with on every other page (`--accent` in
 *    both report pages, azera's chip in the framework palette, `--bs-primary` in
 *    the homepage theme) and the colour of `azera-logo-text.svg`. Clarity is an
 *    Azera engine, so one brand colour across the whole suite beats a second
 *    blue that matches nothing — a reader arriving from another page sees Azera
 *    and Clarity in the SAME blue. Clarity's own logo blue (`#4191fa`, from
 *    `docs/images/clarity-engine-logo.svg`) is a lighter tone of the same family
 *    (dE 35, i.e. recognisably related) and remains the colour of its logo, just
 *    not of this legend.
 *  - `native` carries **PHP's official logo indigo** (`#777bb3`, the php.net logo
 *    drawn by Colin Viebrock, CC BY-SA 4.0) — the right tie for it, because
 *    NativeEngine is plain PHP includes with no template language in front of
 *    them. Verified by downloading the official SVG and reading its fills rather
 *    than quoting the value from memory.
 *
 *  Both used to be drawn otherwise here: clarity was GREEN (`#2b9f4b`) until
 *  2026-09-22 — a leftover, not a brand, which contradicted the logo sitting
 *  beside the same chart in clarity's README — and native was a generic
 *  `#2b6fbf` unconnected to anything.
 *
 *  With both brand-anchored, clarity/native sit at dE 52 and the palette's
 *  CLOSEST pair is clarity/twig at dE 21 (a deep blue against a violet). That is
 *  deliberate and was checked numerically: dE 21 is well clear of the ~10
 *  perceptual collision threshold, and the two are never adjacent in a chart,
 *  which is ordered by median rather than by hue.
 *
 *  - `clarity-open` carries a LIGHTER tint of Clarity's own blue (`#6f8cf0`).
 *    The relationship is the point and is stated rather than left to be noticed:
 *    it is the SAME engine with the sandbox off, so a reader must not mistake it
 *    for a competitor, and painting it an unrelated hue would say exactly that.
 *    It is lighter rather than darker because every chart is drawn on a white
 *    card, where a tint of a blue already in the palette reads as related and a
 *    near-black would read as a different family. Checked numerically like the
 *    rest: its nearest neighbour is `native` at dE 25, comfortably above the
 *    ~10 collision threshold, and it sits at dE 25 from `clarity` itself — close
 *    enough to read as the same family, far enough to be told apart.
 *
 *  - the third-party hues are CHOSEN for distinguishability, NOT sampled from
 *    upstream brand assets (no logo file ships in those packages). Do not treat
 *    them as official, and do not "correct" one to a brand colour you happen to
 *    know — the chart only needs them to be different, and several are already
 *    adjacent to their own brand's territory.
 *
 * @return array<string,array{label:string,color:string,what:string}>
 */
function veEngineMeta(): array
{
    return [
        'clarity' => ['label' => 'Clarity', 'color' => '#3459e6', 'what' => 'Clarity DSL, compiled to a PHP class'],
        // The SAME engine with the sandbox disabled. Kept adjacent to `clarity`
        // in the canonical order so the two modes appear next to each other
        // wherever the order is used, and tinted from the same hue so the pair
        // reads as one engine in two configurations rather than two competitors.
        'clarity-open' => ['label' => 'Clarity (open)', 'color' => '#6f8cf0', 'what' => 'the same Clarity engine with the sandbox disabled (full PHP in templates)'],
        'native'       => ['label' => 'Native', 'color' => '#777bb3', 'what' => 'plain PHP includes (NativeEngine)'],
        'plates'       => ['label' => 'Plates', 'color' => '#e8590c', 'what' => 'League\\Plates'],
        'blade'        => ['label' => 'Blade', 'color' => '#e5484d', 'what' => 'Laravel Blade (laravel/framework)'],
        'twig'         => ['label' => 'Twig', 'color' => '#8044db', 'what' => 'Twig'],
        'stempler'     => ['label' => 'Stempler', 'color' => '#0b7285', 'what' => 'Spiral Stempler'],
    ];
}

function veEngineLabel(string $engine): string
{
    return veEngineMeta()[$engine]['label'] ?? $engine;
}

/**
 * How many entrants the dataset measured, in words where the number is a
 * familiar one.
 *
 * NOT hardcoded, and that is the whole point: the count is a property of the
 * RUN, and it changed the moment the open-mode Clarity entrant was added. A
 * sentence reading "Six engines" above a table of seven is precisely the drift
 * this file exists to prevent — the charts and the table are rebuilt from the
 * dataset, while a number typed into prose is recomputed by nothing.
 *
 * Counted from the rows rather than from `env.engines`, because the rows are
 * what the reader is looking at.
 */
function veEntrantPhrase(array $rows): string
{
    $engines = [];
    foreach ($rows as $row) {
        if (isset($row['engine'])) {
            $engines[(string) $row['engine']] = true;
        }
    }

    $n     = count($engines);
    $words = [
        1  => 'One',
        2  => 'Two',
        3  => 'Three',
        4  => 'Four',
        5  => 'Five',
        6  => 'Six',
        7  => 'Seven',
        8  => 'Eight',
        9  => 'Nine',
        10 => 'Ten',
    ];
    if ($n === 1) {
        return 'One engine';
    }

    return ($words[$n] ?? (string) $n) . ' engines';
}

function veEngineColor(string $engine): string
{
    return veEngineMeta()[$engine]['color'] ?? '#64748b';
}

/**
 * What a dataset's memory column measures, in the dataset's own words.
 *
 * Read from the env block rather than asserted here. The figure is not
 * self-describing, and it has changed repeatedly: a process high-water mark
 * (which made every engine after the first report the FIRST engine's peak),
 * then the allocator's 2 MiB bucket (which put every engine in one bucket), then
 * actual bytes for one render in a fresh process, then actual bytes after a run
 * — and now a RETAINED reading rather than a peak, which lowers every figure by
 * 5-11% with no engine change. A sentence hardcoded in the renderer would
 * describe whichever of those it was written against, on every later run.
 *
 * The fallback says so explicitly rather than guessing, because an older dataset
 * genuinely carries a different basis.
 */
function veMemoryBasis(array $env): string
{
    $basis = $env['retained_mem_basis'] ?? null;
    if (is_string($basis) && $basis !== '') {
        return $basis;
    }

    return 'basis not recorded by this dataset\'s harness';
}

/**
 * The probe's OPcache context, as a caption suffix — or '' when the dataset does
 * not record one.
 *
 * This is not trivia: the memory bars are only comparable with another dataset's
 * bars if both were taken the same way. The basis itself CHANGED on 2026-09-29:
 * the probe children moved from `opcache.enable_cli=0` (bytecode counted in the
 * process heap, so every figure was HIGHER) to OPcache ON. Note that ON does NOT
 * mean the engine source is served from a shared cache here: the CLI opcode
 * segment is per-process, so each probe child compiles the engine source itself.
 * A dataset carrying the old wording and one carrying the new
 * are NOT comparable, so a chart that silently carried one beside the other would
 * invite a wrong conclusion. The value is read from the dataset rather than
 * assumed, so a re-render of an old dataset keeps describing that old run.
 *
 * Returns the RAW value (no decoration): the two call sites need different
 * framing, and one of them is Markdown, where an undecorated identifier
 * containing underscores is rendered as EMPHASIS (`opcache.enable<em>cli</em>`)
 * rather than as text. The env block therefore wraps this in a code span; the
 * SVG caption takes it bare because SVG has no such syntax.
 */
function veProbeValue(array $env): ?string
{
    $probe = $env['opcache_probe'] ?? null;

    return is_string($probe) && $probe !== '' ? $probe : null;
}

// -----------------------------------------------------------------------------
// Dataset
// -----------------------------------------------------------------------------

/**
 * Load the harness JSON and return `[env, results]`.
 *
 * The dataset is REQUIRED to be an envelope. A bare list cannot state the PHP
 * version, the OPcache state, the item count or the engine versions, so a
 * report rendered from one could not caption its own charts honestly — which is
 * exactly how a published table came to carry an environment borrowed from a
 * different benchmark. Refusing the old shape is the point, not a regression:
 * the pre-envelope artifacts were deleted rather than kept readable.
 *
 * @return array{0:array<string,mixed>,1:list<array<string,mixed>>}
 */
function veLoadDataset(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "Dataset not found: {$path}\n");
        exit(1);
    }

    $json = json_decode((string) file_get_contents($path), true);
    if (!is_array($json)) {
        fwrite(STDERR, "Dataset is not valid JSON: {$path}\n");
        exit(1);
    }

    if (!isset($json['env'], $json['results']) || !is_array($json['results'])) {
        fwrite(STDERR,
            "Dataset is not in the envelope shape ({env, results}): {$path}\n"
                . "Re-run the harness — the environment block is what makes a caption honest.\n"
        );
        exit(1);
    }

    return [$json['env'], array_values($json['results'])];
}

/**
 * The pages a dataset covers, in canonical order.
 *
 * A dataset written before the harness measured more than one page has no
 * `page` field on its rows; those datasets are reported as the single implicit
 * page `sample`, which is the page they in fact measured. That keeps every
 * published figure from an older run reachable instead of making the renderer
 * refuse a dataset it used to accept.
 *
 * @param list<array<string,mixed>> $results
 * @return list<string>
 */
function vePages(array $results): array
{
    $seen = [];
    foreach ($results as $row) {
        $page = isset($row['page']) ? (string) $row['page'] : 'sample';
        $seen[$page] = true;
    }

    // Canonical order comes from the page table the harness uses, so the report
    // and the run cannot disagree about which page is primary. Any page the
    // table does not know (a dataset from a newer harness) is appended rather
    // than dropped.
    $order = array_keys(require __DIR__ . '/../benchmarks/view-engine/pages.php');
    $out   = [];
    foreach ($order as $page) {
        if (isset($seen[$page])) {
            $out[] = $page;
            unset($seen[$page]);
        }
    }
    foreach (array_keys($seen) as $page) {
        $out[] = $page;
    }

    return $out;
}

/**
 * The measured engines for ONE page, ordered canonically, each with its row.
 *
 * @param list<array<string,mixed>> $results
 * @return list<array<string,mixed>>
 */
function veRowsForPage(array $results, string $page): array
{
    $byEngine = [];
    foreach ($results as $row) {
        if (!isset($row['engine'])) {
            continue;
        }
        // A row without a page field is from a single-page dataset.
        if ((isset($row['page']) ? (string) $row['page'] : 'sample') !== $page) {
            continue;
        }
        $byEngine[(string) $row['engine']] = $row;
    }

    $order = array_keys(veEngineMeta());
    $out   = [];
    foreach ($order as $engine) {
        if (isset($byEngine[$engine])) {
            $out[] = $byEngine[$engine];
            unset($byEngine[$engine]);
        }
    }
    // An engine the config does not know (a newly added adapter) still gets a
    // row rather than being dropped from its own result table.
    foreach ($byEngine as $row) {
        $out[] = $row;
    }

    return $out;
}

/**
 * Every page's rows: page => list of engine rows.
 *
 * @param list<array<string,mixed>> $results
 * @return array<string,list<array<string,mixed>>>
 */
function veRowsByPage(array $results): array
{
    $out = [];
    foreach (vePages($results) as $page) {
        $out[$page] = veRowsForPage($results, $page);
    }

    return $out;
}

/**
 * Rows for the PRIMARY page — the shape every caller used before this file knew
 * about pages.
 *
 * It REFUSES a multi-page dataset rather than picking one. The previous
 * implementation keyed rows by engine alone (`$byEngine[$row['engine']] = $row`),
 * so an 18-row three-page dataset would silently overwrite each engine with
 * whichever page happened to come last and publish those numbers as the
 * headline — a wrong figure with no warning attached, on a chart whose whole
 * purpose is to be trusted. Callers that want a specific page must now say so.
 *
 * @param list<array<string,mixed>> $results
 * @return list<array<string,mixed>>
 */
function veRows(array $results): array
{
    $pages = vePages($results);
    if (count($pages) > 1) {
        throw new RuntimeException(
            'Dataset covers ' . count($pages) . ' pages (' . implode(', ', $pages) . '); '
                . 'use veRowsForPage()/veRowsByPage(). Picking one silently would publish '
                . 'an arbitrary page as the headline.'
        );
    }

    return $pages === [] ? [] : veRowsForPage($results, $pages[0]);
}

/** A measured figure, or null — never a coerced zero. */
function veNum(array $row, string $key): ?float
{
    return isset($row[$key]) && is_numeric($row[$key]) ? (float) $row[$key] : null;
}

/**
 * Sort a label => value map by its values, ascending, ties broken by label.
 *
 * The BAR CHARTS are read as a ranking, so their bars are ordered by the figure
 * they draw — fastest/most favourable first — rather than by engine key, which
 * is arbitrary in a dataset and puts the winner somewhere in the middle. Two
 * other parts of the page already sort this way and this keeps the three in
 * step: the render-time chart orders its rows by median, and the results table
 * orders its rows by median with a note saying so. A bar chart alone in hash
 * order made the reader do the ranking themselves.
 *
 * Ascending is the right direction for BOTH bar charts: the subtitle on each
 * says "lower is better", so first really is best.
 *
 * Ties keep the order the dataset gave them — `uasort()` is a STABLE sort as of
 * PHP 8.0 — so a run where two engines share a footprint draws them the same way
 * every time, rather than re-ordering them per regeneration. (Sorting on the
 * label as a tiebreaker would achieve that too, but it would order the bars by
 * something the chart does not show, and it would silently re-order ties the day
 * the dataset's engine list changed.)
 *
 * @param array<string,float> $values label => value
 * @return array<string,float>
 */
function veSortByValue(array $values): array
{
    uasort($values, static fn(float $a, float $b): int => $a <=> $b);

    return $values;
}

// -----------------------------------------------------------------------------
// Page presentation
// -----------------------------------------------------------------------------

/**
 * How each measured page is NAMED in the report.
 *
 * Kept here rather than in the harness's pages.php, and that is a deliberate
 * separation: pages.php defines what is MEASURED (a template, a set of vars),
 * which three programs depend on, while this defines what a READER is told about
 * it. A label is a publishing concern; putting it in the table the harness
 * iterates would let a copy edit change the shape of a run.
 *
 * An unknown page (a dataset from a newer harness) falls back to its own name in
 * `vePageLabel()`, so a new page publishes under a slightly plainer heading
 * instead of being dropped — the same additive rule pages.php follows.
 *
 * @return array<string,array{label:string,about:string}>
 */
function vePageMeta(): array
{
    return [
        'sample' => [
            'label' => 'Scalars',
            'about' => 'a layout, an included partial, a loop over scalars with a filter, and a nested loop inside it',
        ],
        'mixed' => [
            'label' => 'Mixed',
            'about' => 'the heavy page: 20 flat variables each read twice (as text and as a data-value attribute) plus 20 rows of six fields each, over 40 flat variable accesses per render and 1440 in total at 200 items, with every value HTML-special so the escape path does real work in both the body and an attribute context',
        ],
        'entities' => [
            'label' => 'Objects',
            'about' => 'objects instead of scalars: two properties, a nested object, a nullable property behind a default, and an array inside an object',
        ],
        'entities-array' => [
            'label' => 'Objects as arrays',
            'about' => 'the same content in the same order with the same values held as nested arrays, so access shape is the only difference',
        ],
    ];
}

/** The heading a page is published under. */
function vePageLabel(string $page): string
{
    return vePageMeta()[$page]['label'] ?? $page;
}

/** One line describing what the page renders, or an empty string. */
function vePageAbout(string $page): string
{
    return vePageMeta()[$page]['about'] ?? '';
}

/**
 * The page a ROW SET was measured on.
 *
 * A dataset written before the harness measured more than one shape has no
 * `page` field on its rows; those rows are the historical `sample` page, which
 * is the page they in fact measured (see vePages()).
 *
 * @param list<array<string,mixed>> $rows
 */
function veRowPage(array $rows): string
{
    return isset($rows[0]['page']) ? (string) $rows[0]['page'] : 'sample';
}

/**
 * Pick the shape a single-shape target should publish.
 *
 * `$preferred` is asked for by NAME rather than by taking whatever the map
 * happens to hold first, because the choice is a claim about what a reader
 * should see: a README's chart is the reader's first impression, and picking the
 * shape whose name sorts first would make that an accident of the page table.
 *
 * Falls back to the HEADLINE page, then to the first shape present, so a dataset
 * that predates the preferred shape still publishes rather than refusing — the
 * same additive rule the page table itself follows.
 *
 * @param array<string,list<array<string,mixed>>> $byPage
 */
function veShapeFor(array $byPage, string $preferred): string
{
    if (isset($byPage[$preferred])) {
        return $preferred;
    }
    $pages = array_keys($byPage);
    if (in_array('sample', $pages, true)) {
        return 'sample';
    }

    return $pages[0] ?? 'sample';
}

/**
 * The chart file key for a page: `<page>-<chart>`, or the bare chart name for
 * the primary page.
 *
 * The primary page keeps the UNPREFIXED names on purpose. Those filenames are
 * the published API of this page — clarity-engine's README and azera-framework's
 * docs embed `svgs` from `docs/benchmarks/svg/view-engine/` and this generator
 * copies one of them into each consumer repo at its own historical path — so a
 * page-aware renderer must not rename the headline chart out from under them.
 */
function veChartKey(string $chart, ?string $page): string
{
    return $page === null ? $chart : $page . '-' . $chart;
}

// -----------------------------------------------------------------------------
// Provenance strings (dataset-derived, never typed by hand)
// -----------------------------------------------------------------------------

/**
 * The one-line build stamp drawn at the foot of every chart.
 *
 * A chart is embedded as <img> and gets copied or linked on its own, so it has
 * to identify the builds it measured without relying on the page around it.
 * A version that could not be resolved is printed as "unknown" rather than
 * omitted: an absent engine reads as "not measured", while "unknown" states
 * honestly that the version was not recorded.
 */
function veVersionsLine(array $env, array $rows): string
{
    /** @var array<string,array{label?:string,version?:?string,ref?:?string}> $versions */
    $versions = is_array($env['engine_versions'] ?? null) ? $env['engine_versions'] : [];
    if ($versions === []) {
        return '';
    }

    $parts = [];
    foreach ($rows as $row) {
        $engine = (string) $row['engine'];
        $v      = $versions[$engine] ?? null;
        $label  = is_array($v) && isset($v['label']) ? (string) $v['label'] : veEngineLabel($engine);
        $ver    = is_array($v) ? ($v['version'] ?? null) : null;
        $ref    = is_array($v) ? ($v['ref'] ?? null) : null;

        $text = $label . ' ' . ($ver !== null && $ver !== '' ? (string) $ver : 'unknown');
        if ($ref !== null && $ref !== '') {
            // A branch version (dev-main) identifies nothing on its own, so the
            // ref is what makes that row reproducible.
            $text .= ' (' . $ref . ')';
        }
        $parts[] = $text;
    }

    return implode(' · ', $parts);
}

/**
 * The budget/provenance line for the page header, all of it read from the env
 * block so a caption cannot describe a run other than the one behind it.
 */
function veEnvBlock(array $env, array $rows): string
{
    $items = $env['items'] ?? null;
    $iters = $env['iterations_per_run'] ?? null;
    $runs  = $env['runs'] ?? null;

    $budget = ($iters !== null && $runs !== null)
        ? number_format((int) $iters) . ' renders × ' . (int) $runs . ' runs'
        : 'multiple runs';
    if ($items !== null) {
        $budget .= ', ' . (int) $items . ' items per render';
    }

    $lines      = [];
    $probeValue = veProbeValue($env);
    $lines[] = '**Environment** — PHP ' . (string) ($env['php_version'] ?? '?')
        . ' · ' . (string) ($env['os'] ?? '?')
        . ' · SAPI ' . (string) ($env['sapi'] ?? '?')
        . ' · OPcache (`opcache.enable_cli`): ' . (($env['opcache'] ?? false) ? 'yes' : 'no')
        . ($probeValue !== null ? ' · Memory probe: `' . $probeValue . '`' : '');
    $lines[] = '**Budget** — ' . $budget;

    // HOW the numbers were produced, not just what they measure. Three figures on
    // this page depend on it: the first render (a fresh process per engine), the
    // steady-state timings (a fresh process per CELL), and the memory columns (a
    // fresh process per reading). A reader comparing this page against an older
    // run needs all three, because a dataset from before the per-cell change is
    // not comparable with one from after it — a cell measured in a reused process
    // skips its own compile and its own cache write, which changes a per-render
    // cache check.
    $order      = (string) ($env['run_order'] ?? '');
    $steady     = (string) ($env['steady_probe_ms_basis'] ?? '');
    $firstBasis = (string) ($env['first_render_ms_basis'] ?? '');
    $parts      = [];
    if ($steady !== '') {
        // Composed as a NAMED sentence rather than "Each cell was measured by
        // <basis>", because the basis is already a full description of the loop —
        // prefixing it that way produced "measured by the render loop ... runs in
        // its own fresh process", which parses as nothing.
        //
        // No underscores in the basis text: this block is rendered as MARKDOWN, so
        // `iterations_per_run` came out as "iterations<em>per</em>run".
        $sentence = 'Steady-state timings: ' . $steady . '.';
        if ($order !== '') {
            $sentence .= ' ' . ucfirst($order) . '.';
        }
        $parts[] = $sentence;
    } elseif ($order !== '') {
        $parts[] = ucfirst($order) . '.';
    }
    if ($firstBasis !== '') {
        $parts[] = 'The first render was measured as ' . $firstBasis . '.';
    }
    if ($parts !== []) {
        $lines[] = '**Method** — ' . implode(' ', $parts);
    }

    $versions = veVersionsLine($env, $rows);
    if ($versions !== '') {
        $lines[] = '**Engines** — ' . $versions;
    }

    $measured = (string) ($env['timestamp'] ?? '');
    if ($measured !== '') {
        $lines[] = '_Measured ' . $measured . '_';
    }

    return implode("\n\n", $lines);
}

// -----------------------------------------------------------------------------
// Charts
// -----------------------------------------------------------------------------

/**
 * Headline: per-render time as a RANGE, not a mean.
 *
 * The retired table published mean and p95 as separate columns, which hides the
 * two facts a reader needs together: how fast an engine usually is (the dot)
 * and how steady it is (the width of the span). dotRange draws fastest -> p95
 * with the median marked, so a small value stays a visible dot instead of
 * collapsing into an invisible bar — the reason this primitive exists at all.
 *
 * The multiplier is anchored to the fastest engine's MEDIAN, never to the
 * trimmed mean, so the number agrees with the dot it sits beside.
 *
 * `$page` is null for the primary page, which keeps the chart's file name and
 * its title exactly as they were before the harness measured more than one page.
 */
function veRenderTimeChart(array $rows, array $env, string $dir, ?string $page = null): string
{
    $metrics = [];
    $factors = [];
    $colors  = [];
    $medians = [];

    foreach ($rows as $row) {
        $engine = (string) $row['engine'];
        $median = veNum($row, 'median_ms');
        if ($median === null) {
            continue;
        }
        // The low end must be a REAL measurement. A dataset without min_ms
        // would otherwise draw the leading cap exactly on the median and claim
        // every engine was perfectly stable — the degraded-range trap the
        // framework charts already paid for once.
        $low = veNum($row, 'min_ms') ?? $median;
        $metrics[$engine] = [
            'median' => $median,
            'low'    => min($low, $median),
            'high'   => veNum($row, 'p95_ms') ?? $median,
        ];
        $colors[$engine] = veEngineColor($engine);
        $medians[$engine] = $median;
    }
    if ($metrics === []) {
        return '';
    }

    $best = max(min($medians), 1e-9);
    foreach ($medians as $engine => $median) {
        $factors[$engine] = $median / $best;
    }

    $title = 'Render time per page';
    if ($page !== null) {
        $title .= ' — ' . vePageLabel($page);
    }

    $svg = SvgChart::dotRange(
        [''],
        ['' => $metrics],
        $colors,
        'ms',
        true,
        960,
        360,
        $title,
        'Lower is better · dot: median of every render · caps: fastest → p95',
        ['' => $factors],
        'x = median ÷ the fastest',
        false,
        'median',
        veVersionsLine($env, $rows)
    );

    $key = veChartKey('render-time', $page);
    file_put_contents($dir . '/' . $key . '.svg', $svg);

    return $key;
}

/**
 * The FIRST render, measured in a fresh process with a cold cache.
 *
 * This is the cost of the first request after a deploy: the engine's classes
 * load, the template compiles, the cache is written and the page renders once.
 *
 * Bars are sorted by their own value, fastest first, so `native` leads: it has
 * no compile step, so its "first render" is just a render. Ordering by engine
 * key used to bury that — the fastest bar sat wherever its name sorted.
 *
 * It used to be captioned "compile + cache fill, with the engine's own classes
 * already loaded", and that was what it measured for SOME engines — which was the
 * defect. The harness produced it in its own process as render -> flush ->
 * render, and a flush cannot un-declare a class, so engines guarded by
 * `class_exists()` skipped the compile and reported a warm render instead
 * (Twig by 28x, Stempler by 51x). The chart drew two non-compiling engines as the
 * fastest compilers. The figure is now measured in a fresh child process, so it
 * cannot be skipped, and the caption states what it includes rather than claiming
 * a narrower basis the measurement does not honour.
 *
 * `native` is therefore no longer at one end of this chart, and that is
 * correct: it has no compile step, so its "first render" is just a render. A
 * caching engine must cost more on the first request; what matters is by how
 * much, which is what the bars now show honestly.
 *
 * Drawn on a LINEAR axis, like the memory chart and unlike the steady-state
 * chart: zero is meaningful here, so a truncated baseline would exaggerate a
 * difference the reader is meant to weigh against the first request's real cost.
 * The field spans more than an order of magnitude (0.78 ms for `native` up to
 * 21 ms for the slowest compiling engine), which the log axis used to compress;
 * a linear axis instead lets the bars state that span literally. Every bar is
 * still labelled with its value and its unit, and the table below carries the
 * same figures, so a bar too short to read is never the only copy of a number.
 */
function veWarmCostChart(array $rows, array $env, string $dir, ?string $page = null): string
{
    $values = [];
    $colors = [];
    foreach ($rows as $row) {
        $first = veNum($row, 'first_render_ms');
        if ($first !== null) {
            $engine = (string) $row['engine'];
            $values[$engine] = $first;
            $colors[$engine] = veEngineColor($engine);
        }
    }
    if ($values === []) {
        return '';
    }
    // Fastest first, so `native` leads and every other bar reads as
    // "how much more than the fastest".
    $values = veSortByValue($values);

    $title = 'First render (fresh process, cold template cache)';
    if ($page !== null) {
        $title .= ' — ' . vePageLabel($page);
    }

    $svg = SvgChart::singleBars(
        $values,
        $colors,
        $title,
        'ms',
        false,
        960,
        340,
        true,
        'Measured once per engine, each in its own fresh process: engine boot, template compile, cache write and one render. OPcache is on, but the CLI segment is per-process, so the engine source is compiled in that process; the TEMPLATE cache is cold',
        veVersionsLine($env, $rows)
    );

    $key = veChartKey('warm-cost', $page);
    file_put_contents($dir . '/' . $key . '.svg', $svg);

    return $key;
}

/**
 * Memory per run: the heap floor, what is retained while serving, and the peak.
 *
 * THREE MARKS ON ONE AXIS, and they are three different MEASUREMENTS rather than
 * a spread of repeats. That distinction is the whole reason this is not the
 * render-time dot chart with different units: there the caps bound the fastest
 * observation and p95, i.e. where individual renders landed. Here every reading
 * is deterministic — seven independent fresh processes returned byte-identical
 * figures — so a repeat spread would be zero-length and its caption a lie.
 *
 *   left cap  `base_mem`      engine loaded and configured, nothing rendered
 *   dot       `retained_mem`  heap still held after a whole run, gc collected
 *   right cap `peak_mem`      the run's high-water mark
 *
 * WHY THE FLOOR IS DRAWN. Plotting the retained and peak figures alone anchors
 * every row at zero, and on that axis the engines look similar — but most of the
 * gap between them is not per-render cost at all, it is the one-time load of a
 * compiler and a compiled class. Stating the floor makes that visible: the range
 * starts at what the engine costs to HAVE LOADED, and its length is what the run
 * actually added. On the published run the range is a few kilobytes for the
 * engine that never compiles and a couple of megabytes for one that does.
 *
 * WHY NOT A BAR CHART. The old chart drew `peak_mem` as a bar from zero, which
 * hid the floor and made a 5-11% difference (the transient compile allocation)
 * carry the whole visual comparison. The range states all three figures on one
 * shared linear axis — linear because zero is meaningful for a footprint and a
 * truncated baseline would exaggerate a small difference.
 *
 * Rows are ordered by the dot, lightest first, like the other charts on the page.
 */
function veMemoryChart(array $rows, array $env, string $dir, ?string $page = null): string
{
    $series = [];
    $sort   = [];
    foreach ($rows as $row) {
        $base = veNum($row, 'base_mem');
        $ret  = veNum($row, 'retained_mem');
        $peak = veNum($row, 'peak_mem');
        // All three, or none. A row missing the floor cannot be drawn honestly
        // against one that has it: an absent left cap would read as "this engine
        // starts at zero", which is a claim about the engine rather than about
        // the dataset. Datasets recorded before this figure existed are
        // genuinely absent, and the chart says so by omitting the row.
        if ($base === null || $ret === null || $peak === null) {
            continue;
        }
        $engine = (string) $row['engine'];
        $mb     = static fn(float $bytes): float => $bytes / 1048576;
        $series[veEngineLabel($engine)] = [
            'color' => veEngineColor($engine),
            'low'   => $mb(min($base, $ret)),
            'mid'   => $mb($ret),
            'high'  => $mb(max($ret, $peak)),
        ];
        $sort[veEngineLabel($engine)] = $mb($ret);
    }
    if ($series === []) {
        return '';
    }
    // Ordered by the dot, so the picture reads as the ranking the table states.
    uasort($sort, static fn(float $a, float $b): int => $a <=> $b);
    $ordered = [];
    foreach (array_keys($sort) as $label) {
        $ordered[$label] = $series[$label];
    }

    $title = 'Memory per run';
    if ($page !== null) {
        $title .= ' — ' . vePageLabel($page);
    }

    $probeValue = veProbeValue($env);

    $svg = SvgChart::memoryRange(
        $ordered,
        $title,
        'bar spans the floor the engine costs to have loaded (left cap) → the run\'s peak (right cap) '
            . '· dot = heap retained after the run, gc collected · '
            . veMemoryBasis($env) . ($probeValue !== null ? ' · ' . $probeValue : ''),
        'engine loaded, nothing rendered',
        'peak during the run',
        'floor / retained / peak',
        ' · ',
        960,
        null,
        veVersionsLine($env, $rows),
        'MB of PHP heap, opcache on but per-process, so the engine compile is included'
    );

    $key = veChartKey('memory', $page);
    file_put_contents($dir . '/' . $key . '.svg', $svg);

    return $key;
}

// -----------------------------------------------------------------------------
// Table (generated — the only place these figures appear)
// -----------------------------------------------------------------------------

/**
 * The full result table.
 *
 * Generated from the same rows the charts read, so a cell and a dot can never
 * disagree, and so there is exactly ONE copy of these numbers in the world: the
 * consumer docs embed this fragment rather than restating it.
 */
function veTable(array $rows): string
{
    $l = [];
    $l[] = '| Engine | First render (ms) | Mean (ms) | Median (ms) | Min (ms) | p95 (ms) | Retained (MB) | Peak (MB) |';
    $l[] = '| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: |';

    // Ordered by the same key the headline chart draws: the median, fastest
    // first. A table in a different order than the chart beside it makes the
    // reader do the mapping themselves.
    $sorted = $rows;
    usort($sorted, static function (array $a, array $b): int {
        return (veNum($a, 'median_ms') ?? INF) <=> (veNum($b, 'median_ms') ?? INF);
    });

    foreach ($sorted as $row) {
        $engine   = (string) $row['engine'];
        $retained = veNum($row, 'retained_mem');
        $peak     = veNum($row, 'peak_mem');

        $cells = [
            veEngineLabel($engine),
            veFmt(veNum($row, 'first_render_ms')),
            veFmt(veNum($row, 'mean_ms')),
            veFmt(veNum($row, 'median_ms')),
            veFmt(veNum($row, 'min_ms')),
            veFmt(veNum($row, 'p95_ms')),
            $retained !== null ? number_format($retained / 1048576, 2) : '',
            $peak !== null ? number_format($peak / 1048576, 2) : '',
        ];
        $l[] = '| ' . implode(' | ', $cells) . ' |';
    }

    return implode("\n", $l);
}

/** A table cell: the figure at the precision the dataset actually carries. */
function veFmt(?float $v): string
{
    return $v === null ? '' : number_format($v, 3);
}

// -----------------------------------------------------------------------------
// Page
// -----------------------------------------------------------------------------

/** The public URL of the published page — absolute, so it resolves from any repo. */
function vePublicUrl(): string
{
    return 'https://sailantis.github.io/azera-competition/benchmarks/view-engine.html';
}

/**
 * The chart keys, in the order the page presents them.
 *
 * One list, so the Markdown and the HTML cannot present the same dataset in two
 * different orders — and so a chart added to the generator is placed in ONE
 * place rather than in every loop that happens to iterate charts.
 *
 * @return list<string>
 */
function veChartOrder(): array
{
    return ['render-time', 'warm-cost', 'memory'];
}

/**
 * How each chart is captioned, and what its footnote says.
 *
 * Hoisted out of veHtml() so the per-page sections can caption a chart the same
 * way the headline page does. The chart key a caller passes in is already
 * page-qualified (`mixed-render-time`), so lookups go through
 * veChartKind() — otherwise a second page's charts would caption as untitled.
 *
 * PAGE-QUALIFIED when the dataset covers more than one page: with three pages
 * on one document, three figures all captioned "Time per render" read as the
 * same chart repeated — reported exactly that way, which is the failure a caption
 * exists to prevent. The page name is what distinguishes them, and on a
 * single-page dataset it is omitted so the published wording does not change.
 *
 * @return array<string,array{0:string,1:string}>
 */
function veChartCaptions(bool $withPage = false, string $page = ''): array
{
    $suffix = $withPage && $page !== '' ? ' — ' . vePageLabel($page) : '';

    return [
        'render-time' => [
            'Time per render' . $suffix,
            'Lower is better. Span: fastest observation → p95; dot: median of every render.',
        ],
        'warm-cost' => [
            'First render (fresh process, cold template cache)' . $suffix,
            'The one-off cost the first request pays.',
        ],
        'memory' => [
            'Memory per run' . $suffix,
            'Range: the engine loaded with nothing rendered → the run\'s peak. Dot: heap retained after the run.',
        ],
    ];
}

/** The chart kind behind a possibly page-qualified key: `escaping-memory` -> `memory`. */
function veChartKind(string $key): string
{
    foreach (veChartOrder() as $kind) {
        if ($key === $kind || str_ends_with($key, '-' . $kind)) {
            return $kind;
        }
    }

    return $key;
}

/**
 * The Markdown fragment: the page this repository publishes, and the region the
 * consumer docs splice in.
 *
 * Prose here names NO measured figure. Every number lives in a chart or in the
 * generated table, both of which are recomputed from the dataset — a sentence
 * is not, so a figure typed into one silently outlives the run it describes.
 * The rule is enforced by tests/ViewEngineProseTest.php.
 *
 * ONE PAGE IS THE HEADLINE. When the dataset covers several rendering shapes the
 * primary page keeps the top of the document, its heading levels, and its chart
 * FILENAMES unchanged — so a published figure, a consumer README's chart and a
 * link into this page all keep working — and the other pages follow under their
 * own `##` heading, each with its own charts and its own table. That is a
 * correctness choice, not layout: a multi-page dataset can no longer be rendered
 * by accident as one page's numbers (veRows() refuses it), so the pages have to
 * be somewhere, and burying them would be the same failure with a quieter shape.
 *
 * @param list<string> $charts chart keys OF THE PRIMARY PAGE
 * @param array<string,array{charts:list<string>,rows:list<array<string,mixed>>}> $extra
 */
function veMarkdown(array $rows, array $env, array $charts, array $extra = []): string
{
    $l = [];
    $l[] = '# Template engine benchmark';
    $l[] = '';
    $entrants = veEntrantPhrase($rows);

    // The wording follows what the dataset actually covers. A single-page run
    // must render byte-identically to the way it did before this file knew about
    // pages at all — a report that rewords itself when nothing was re-measured
    // makes 'did this run change anything?' unanswerable from the diff.
    $l[] = $extra === []
        ? $entrants . ' render **the same page** — a layout, an included partial, a loop over '
            . 'the items and nested loops inside it — so the numbers compare engines rather than '
            . 'templates. `Clarity` and `Native` are engines inside the Azera framework; the others are '
            . 'the template languages Azera adapts via its view adapter layer.'
        : $entrants . ' render **the same pages** — a layout, an included partial, a loop over '
            . 'the items and nested loops inside it — so the numbers compare engines rather than '
            . 'templates. `Clarity` and `Native` are engines inside the Azera framework; the others are '
            . 'the template languages Azera adapts via its view adapter layer.';
    $l[] = '';
    $l[] = veEnvBlock($env, $rows);
    $l[] = '';

    // NAME THE HEADLINE PAGE — but only when other shapes follow.
    //
    // Every additional shape publishes under a named `##` heading, which left
    // the FIRST one anonymous: a reader met `## Time per render` with nothing
    // saying which page it measured, while every later section said. The name is
    // added conditionally rather than always because a single-page dataset has
    // no other heading to be confused with, and its rendered bytes are
    // deliberately frozen (see this function's docblock).
    if ($extra !== []) {
        $page  = veRowPage($rows);
        $about = vePageAbout($page);
        $l[] = '## ' . vePageLabel($page);
        $l[] = '';
        if ($about !== '') {
            $l[] = 'The first of the rendering shapes measured here, rendered on the same machine with '
                . 'the same engines as the rest: ' . $about . '. These are this page\'s own figures — a '
                . 'time here and a time further down are not the same measurement, so compare engines '
                . 'WITHIN a page rather than across pages.';
            $l[] = '';
        }
    }

    if (in_array('render-time', $charts, true)) {
        $l[] = '## Time per render';
        $l[] = '';
        $l[] = '![Render time per page](svg/view-engine/render-time.svg)';
        $l[] = '';
        $l[] = 'The dot is the median render and the caps bound the fastest observation and p95, so an '
            . 'engine that is usually fast but occasionally slow looks different from one that is '
            . 'uniformly slower. The multiplier beside each row is measured against the fastest median '
            . 'in the chart.';
        $l[] = '';
    }

    if (in_array('warm-cost', $charts, true)) {
        $l[] = '## First render';
        $l[] = '';
        $l[] = '![First render](svg/view-engine/warm-cost.svg)';
        $l[] = '';
        $l[] = 'The cost of the first request after a deploy: the engine\'s classes load, the template '
            . 'compiles, the cache is written and the page renders once. It is measured in a FRESH '
            . 'process per engine, so no engine can be measured against a template cache or a bootstrap another '
            . 'engine already paid for. OPcache is ON — as on a real deployment — but the CLI opcode segment ' 
            . 'is PER-PROCESS (not shared across shell_exec children), so the engine\'s own PHP files are ' 
            . 'compiled in that process; only the TEMPLATE cache is cold by construction. That makes it '
            . 'comparable across engines, and it is why the '
            . 'leading bar is a non-compiling engine: `native` has no compile step at all, so its first '
            . 'render is just a render. Engines that compile to a cached PHP class pay this once per '
            . 'deploy and nothing on later requests, which is what the render-time chart measures.';
        $l[] = '';
    }

    if (in_array('memory', $charts, true)) {
        $l[] = '## Memory per run';
        $l[] = '';
        $l[] = '![Memory per run](svg/view-engine/memory.svg)';
        $l[] = '';
        $l[] = 'Three marks, three measurements — not a spread of repeats. The left cap is the heap with '
            . 'the engine loaded and nothing rendered, so it is the floor of having that engine at all. '
            . 'The dot is what a whole run of renders still holds, with the garbage collector run '
            . 'first. The right cap is the run\'s high-water mark, which is where the transient '
            . 'allocation of compiling the template lives. Each engine is measured in its own fresh '
            . 'process, so none of the three inherits another engine\'s footprint. Rows are sorted by '
            . 'the dot, lightest first.';
        $l[] = '';
    }

    $l[] = '## Results';
    $l[] = '';
    $l[] = 'Generated from the run\'s JSON — the same rows the charts above are drawn from, so a cell '
        . 'and a dot cannot disagree. Rows are ordered by median, fastest first.';
    $l[] = '';
    $l[] = veTable($rows);
    $l[] = '';

    // The other pages, each with its own charts and table. Heading level `##`
    // rather than `###`: they are siblings of the headline page's sections, not
    // appendices to its results, and a reader scanning the headings should see
    // the extra shapes as measurements in their own right.
    foreach ($extra as $page => $section) {
        $l[] = '## ' . vePageLabel($page) . ' — another rendering shape';
        $l[] = '';
        $about = vePageAbout($page);
        if ($about !== '') {
            $l[] = 'Measured on the same machine with the same engines, rendering ' . $about . '. These '
                . 'are that page\'s own figures — a time here and a time above are not the same '
                . 'measurement, so compare engines WITHIN a page rather than across pages.';
            $l[] = '';
        }
        foreach (veChartOrder() as $kind) {
            foreach ($section['charts'] as $key) {
                if (veChartKind($key) !== $kind) {
                    continue;
                }
                // Page-qualified caption: three sections all headed "Time per
                // render" read as one chart repeated, which is exactly how the
                // multi-page page was first read.
                $caption = veChartCaptions(true, $page)[$kind] ?? [$key, ''];
                $l[] = '### ' . $caption[0];
                $l[] = '';
                $l[] = '![' . $caption[0] . '](' . 'svg/view-engine/' . $key . '.svg)';
                $l[] = '';
            }
        }
        $l[] = veTable($section['rows']);
        $l[] = '';
    }

    $l[] = '### What the columns are';
    $l[] = '';
    $l[] = '- **First render** — the first request after a deploy, measured in a fresh process per engine: engine boot, template compile, cache write and one render. The TEMPLATE cache is cold; OPcache is on but its CLI segment is per-process, so the engine source is compiled in that process. Comparable across engines because no engine inherits another\'s warm template cache or loaded classes.';
    $l[] = '- **Mean / Median / Min / p95** — computed over every individual render across all runs.';
    $l[] = '- **Retained** — PHP heap still held after a whole run of renders, with `gc_collect_cycles()` called before the reading, in a fresh process. This is what a process carries while serving.';
    $l[] = '- **Peak** — the same run\'s PHP heap high-water mark, where the transient allocation of the first compile lives. It sits above Retained and is not a second measurement of it.';
    $l[] = '';
    $l[] = '---';
    $l[] = '';
    $l[] = '> **Auto-generated.** Reproduce with:';
    $l[] = '> `php -d opcache.enable_cli=1 benchmarks/view-engine/run.php --engines=native,clarity,plates,blade,twig,stempler --iterations-per-run=10000 --runs=30 --items=200 --out=results/<date>`';
    $l[] = '> then `php scripts/view-engine-report.php --dataset=benchmarks/view-engine/results-<date>.json`. '
        . 'Do not edit by hand — re-run the harness to update it.';
    $l[] = '';
    $l[] = 'Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams '
        . 'can never disagree.';
    $l[] = '';

    return implode("\n", $l);
}

/**
 * The standalone HTML page.
 *
 * Deliberately mirrors the framework dashboard's own page shell — same card, same
 * ink, same inline <style> and no JS or CDN — so the two halves of the benchmark
 * site read as one publication rather than two. Links to the charts are
 * RELATIVE, because this file is served from beside the svg/ directory.
 *
 * Page-scoped sections (`$extra`) mirror the Markdown: the primary page is the
 * headline, and every other page gets its own `<section>` with the same figure
 * markup and its own table. Both renderings therefore agree about WHAT was
 * measured and in what order, which is the property the consumer docs depend on
 * when they embed the Markdown version of the same dataset.
 *
 * @param list<string> $charts chart keys OF THE PRIMARY PAGE
 * @param array<string,array{charts:list<string>,rows:list<array<string,mixed>>}> $extra
 */
function veHtml(array $rows, array $env, array $charts, array $extra = []): string
{
    $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

    $figures = veFigures($charts, $esc);

    // The table is rendered from the same rows, so it is emitted as HTML rather
    // than by converting Markdown — one source, two renderings.
    $thead = veTableHead();
    $tbody = veTableRows($rows, $esc);

    $envBlock = '';
    foreach (explode("\n\n", veEnvBlock($env, $rows)) as $para) {
        $envBlock .= '<p class="env">' . veInline($para, $esc) . '</p>' . "\n";
    }

    // Two things are emitted only when the dataset really covers more than one
    // rendering shape: the CSS the page sections need, and the plural wording of
    // the lead. A single-page dataset therefore produces the SAME bytes it did
    // before this file knew about pages, which is what makes "did the last run
    // change the page?" answerable by diffing the rendered file.
    $multi    = $extra !== [];
    $extraCss = $multi
        ? "h3{margin:28px 0 12px;font-size:17px}\nsection{margin-top:44px;border-top:1px solid var(--edge);padding-top:8px}\n"
        : '';

    // The headline page is named exactly when other shapes follow, mirroring the
    // Markdown renderer: without it the first charts are the only ones on the
    // page with no statement of what was rendered.
    $headline = '';
    if ($multi) {
        $page     = veRowPage($rows);
        $about    = vePageAbout($page);
        $headline = sprintf(
            "<h2>%s</h2>\n<p class=\"note\">%s</p>\n",
            $esc(vePageLabel($page)),
            $esc('The first of the rendering shapes measured here, rendered on the same machine with '
                . 'the same engines as the rest: ' . $about . '. These are this page\'s own figures — a '
                . 'time here and a time further down are not the same measurement, so compare engines '
                . 'WITHIN a page rather than across pages.')
        );
    }
    $entrants = veEntrantPhrase($rows);
    $lead     = $multi
        ? $entrants . ' rendering identical pages — a layout, an included partial, a loop over the items, and nested loops inside it.'
        : $entrants . ' rendering one identical page — a layout, an included partial, a loop over the items, and nested loops inside it.';

    // One section per additional page, each with its own charts and table. The
    // intro states the comparison rule explicitly, because a reader scrolling
    // past the headline could otherwise read a second page's rows as a ranking
    // against the first page's.
    $sections = '';
    foreach ($extra as $page => $section) {
        $about = vePageAbout($page);
        $note  = $about !== ''
            ? 'Measured on the same machine with the same engines, rendering ' . $about . '. '
                . 'A figure here and a figure above are different measurements — compare engines '
                . 'within a page, not across pages.'
            : 'A second rendering shape, measured on the same machine with the same engines.';

        $sections .= sprintf(
            "<section id=\"page-%s\">\n  <h2>%s</h2>\n  <p class=\"note\">%s</p>\n%s  <h3>Results</h3>\n  <table><thead>%s</thead><tbody>\n%s</tbody></table>\n</section>\n",
            $esc($page),
            $esc(vePageLabel($page)),
            $esc($note),
            veFigures($section['charts'], $esc, $page),
            $thead,
            veTableRows($section['rows'], $esc)
        );
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Template engine benchmark — Azera Competition</title>
<style>
:root{--bg:#f8fafc;--card:#fff;--ink:#1e293b;--ink-soft:#64748b;--edge:#e2e8f0;--accent:#3459e6}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif}
header,main,nav,footer{max-width:1040px;margin:0 auto;padding:0 20px}
nav{padding-top:24px}
nav a{color:var(--accent);text-decoration:none;font-weight:600;font-size:14px}
header h1{margin:16px 0 8px;font-size:30px;line-height:1.2}
p.lead{color:var(--ink-soft);margin:0 0 6px}
p.env{color:var(--ink-soft);font-size:13px;margin:2px 0}
p.env strong{color:var(--ink);font-weight:600}
h2{margin:40px 0 12px;font-size:20px}
{$extraCss}figure{margin:0 0 28px;background:var(--card);border:1px solid var(--edge);border-radius:10px;padding:14px}
figure img{display:block;width:100%;height:auto}
figcaption{font-weight:600;margin-bottom:8px}
p.note{color:var(--ink-soft);font-size:13px;margin:10px 0 0}
table{width:100%;border-collapse:collapse;background:var(--card);border:1px solid var(--edge);border-radius:10px;overflow:hidden;font-size:14px}
th,td{padding:9px 12px;border-bottom:1px solid var(--edge);text-align:right;white-space:nowrap}
thead th{background:#f1f5f9;font-size:13px;color:var(--ink-soft);text-align:right}
th[scope=row]{text-align:left;font-weight:600}
tbody tr:last-child th,tbody tr:last-child td{border-bottom:0}
.dot{display:inline-block;width:9px;height:9px;border-radius:50%;background:var(--c);margin-right:8px;vertical-align:1px}
footer{color:var(--ink-soft);font-size:13px;padding:32px 20px 48px}
footer code{background:#f1f5f9;padding:1px 5px;border-radius:4px}
</style>
</head>
<body>
<nav><a href="index.html">&larr; All benchmarks</a></nav>
<header>
  <h1>Template engine benchmark</h1>
  <p class="lead">{$lead} <strong>Clarity</strong> and <strong>Native</strong> are engines inside the Azera framework; the rest are the template languages Azera adapts.</p>
{$envBlock}</header>
<main>
{$headline}{$figures}<h3>Results</h3>
<table><thead>{$thead}</thead><tbody>
{$tbody}</tbody></table>
<p class="note">Memory is the peak PHP heap for one render, measured in a fresh process so each engine reports its own footprint rather than the high-water mark of everything measured before it.</p>
{$sections}</main>
<footer>
  Generated from the harness JSON by <code>scripts/view-engine-report.php</code>. Every chart is a plain SVG drawn from the same rows as the table, so the two cannot disagree.
</footer>
</body>
</html>
HTML;
}

/**
 * The `<figure>` cards for a chart-key list, in canonical chart order.
 *
 * `$page` is the page these charts belong to (empty for a single-page dataset),
 * so a multi-page document can caption each figure with the page it measured.
 */
function veFigures(array $keys, callable $esc, string $page = ''): string
{
    $captions = veChartCaptions($page !== '', $page);
    $out      = '';

    foreach (veChartOrder() as $kind) {
        foreach ($keys as $key) {
            if (veChartKind($key) !== $kind) {
                continue;
            }
            [$caption, $note] = $captions[$kind] ?? [$kind, ''];
            // NO `loading="lazy"`: on a page whose charts are the point, a
            // deferred image reads as a BROKEN one. The first render carried it
            // and the two below-the-fold sections looked empty in the browser —
            // reported as "the last two diagrams are not rendered, maybe the
            // links are broken" when the files were present and loading. The
            // charts are already plain SVGs beside the page; there is nothing to
            // save by deferring them.
            $out .= sprintf(
                "<figure>\n  <figcaption>%s</figcaption>\n  <img src=\"svg/view-engine/%s.svg\" alt=\"%s\">\n  <p class=\"note\">%s</p>\n</figure>\n",
                $esc($caption),
                $esc($key),
                $esc($caption),
                $esc($note)
            );
        }
    }

    return $out;
}

/** The result table's header row. */
function veTableHead(): string
{
    return '<tr><th>Engine</th><th>First render (ms)</th><th>Mean (ms)</th><th>Median (ms)</th>'
        . '<th>Min (ms)</th><th>p95 (ms)</th><th>Retained (MB)</th><th>Peak (MB)</th></tr>';
}

/**
 * The result table's body, ordered by median exactly as the Markdown table is.
 *
 * Shared by the headline table and every page section, so a page cannot be
 * rendered with a different column set or a different sort than the one beside
 * it — the drift a hand-copied table always eventually develops.
 */
function veTableRows(array $rows, callable $esc): string
{
    $sorted = $rows;
    usort($sorted, static fn(array $a, array $b): int =>
        (veNum($a, 'median_ms') ?? INF) <=> (veNum($b, 'median_ms') ?? INF));

    $out = '';
    foreach ($sorted as $row) {
        $engine   = (string) $row['engine'];
        $retained = veNum($row, 'retained_mem');
        $peak     = veNum($row, 'peak_mem');
        $out .= sprintf(
            "<tr><th scope=\"row\"><span class=\"dot\" style=\"--c:%s\"></span>%s</th><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>\n",
            $esc(veEngineColor($engine)),
            $esc(veEngineLabel($engine)),
            veFmt(veNum($row, 'first_render_ms')),
            veFmt(veNum($row, 'mean_ms')),
            veFmt(veNum($row, 'median_ms')),
            veFmt(veNum($row, 'min_ms')),
            veFmt(veNum($row, 'p95_ms')),
            $retained !== null ? number_format($retained / 1048576, 2) : '',
            $peak !== null ? number_format($peak / 1048576, 2) : ''
        );
    }

    return $out;
}

/**
 * Minimal inline-Markdown for the env block: `**bold**`, `*em*` and `` `code` ``,
 * escaped first so a dataset value can never inject markup. The env block is
 * generated, but its CONTENT comes from the dataset (a PHP version, an OS
 * string), so it is escaped rather than trusted.
 *
 * Code spans are extracted FIRST and restored LAST, so the emphasis rules cannot
 * reach inside them. That ordering is the fix for a defect this function shipped
 * with: the `_..._` rule ran over the whole string AFTER code spans had already
 * become `<code>...</code>`, so an identifier containing underscores was
 * italicised in the middle of the code element —
 * `opcache.enable<em>cli</em>`, which is not the identifier, while the Markdown
 * fragment beside it read `opcache.enable_cli` correctly. The two published
 * halves of the same run therefore disagreed. Markdown's own rule is that a code
 * span is literal, which is what the placeholders implement.
 */
function veInline(string $s, callable $esc): string
{
    $html = $esc($s);
    // Placeholders rather than a reorder: `**bold**` may legitimately contain a
    // code span, so code must be protected from every following rule.
    $spans = [];
    $html  = (string) preg_replace_callback(
        '/`(.+?)`/s',
        static function (array $m) use (&$spans): string {
            // The placeholder must contain NO underscores, or the `_..._` rule
            // below would italicise the placeholder itself (hit immediately:
            // `VE_CODE_0` came back as `VE<em>CODE</em>0`).
            $key = "\x00VECODESPAN" . count($spans) . "\x00";
            $spans[$key] = '<code>' . $m[1] . '</code>';
            return $key;
        },
        $html
    );
    $html = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    // Underscore emphasis, which the generated `_Measured ..._` line uses. Without
    // this the underscores are printed literally, so the line reads as markup
    // rather than as the footnote it is.
    $html = (string) preg_replace('/_(.+?)_/s', '<em>$1</em>', $html);

    return strtr($html, $spans);
}

// -----------------------------------------------------------------------------
// Publish
// -----------------------------------------------------------------------------

/**
 * Splice the generated Markdown into a consumer document between marker
 * comments, creating the region if it is not there yet.
 *
 * A MARKER region rather than "replace the section": the surrounding prose is
 * hand-written and belongs to the other repository, while everything between
 * the markers is generated here. Rewriting by heading would have to guess where
 * the hand-written part ends — which is how a generated table ends up
 * duplicating prose that also quotes numbers.
 *
 * The whole region is replaced, markers included, so a re-run is idempotent and
 * a second run cannot append a second copy.
 *
 * @return string the new file body, or the original when unchanged
 */
function veSpliceRegion(string $body, string $region, string $generated): string
{
    $begin = "<!-- view-engine:begin -->";
    $end   = "<!-- view-engine:end -->";
    $block = $begin . "\n" . $generated . "\n" . $end;

    $pattern = '/' . preg_quote($begin, '/') . '.*?' . preg_quote($end, '/') . '/s';
    if (preg_match($pattern, $body) === 1) {
        return (string) preg_replace($pattern, $block, $body, 1);
    }

    // First time: append the region, so the hand-written text above it stays
    // exactly as its author wrote it.
    return rtrim($body) . "\n\n" . $block . "\n";
}

/**
 * Publish to a target repository.
 *
 * Every target follows the same shape: copy the charts it needs, splice a
 * generated region into the target's own Markdown, and SWEEP the image
 * directory first where this generator owns it.
 *
 * The sweep is not housekeeping. Each target's image directory may live in
 * ANOTHER repository, where nobody can tell a stale chart from a current one,
 * so a chart this generator stops drawing would sit there forever illustrating
 * a section the page no longer has. (The framework publication learned this the
 * expensive way: four orphaned charts of a removed section.)
 *
 * TWO TARGETS, and the difference between them is WHICH SHAPE they carry and
 * WHETHER they own their Markdown: `framework` splices the headline shape into a
 * design doc, and `clarity-docs` writes a dedicated multi-shape page whole.
 *
 * THERE USED TO BE A THIRD, and what retired it is worth recording. `clarity`
 * copied two charts under FIXED names into clarity-engine's `docs/images/` — a
 * directory that repository also keeps its own logo in — so a publish there had
 * to be careful to touch its own two files and nothing else. It was retired on
 * 2026-09-28 because the README it served had already stopped referencing those
 * names: clarity-engine's README now embeds the charts `clarity-docs` publishes
 * into `docs/images/benchmarks/`, so the target was copying two files that
 * nothing linked to. A chart written where nothing reads it is an orphan with a
 * schedule. The fixed-name-and-unlink machinery went with it rather than being
 * kept as unused capability, because that capability is what allowed a publish
 * into a directory this generator does not own.
 *
 * @param list<string> $charts
 * @return list<string> log lines
 */
function vePublish(string $target, array $rows, array $env, array $charts, string $svgDir, string $mdBody, ?string $siblingsRoot = null, array $byPage = []): array
{
    // The directory holding the sibling repositories. Overridable so a test can
    // splice into a scratch tree instead of writing into two OTHER
    // repositories' README/docs — a test that edits a real repository is not a
    // test, and the alternative (not testing the splice at all) would leave the
    // one step that reaches across repositories unverified.
    $root = $siblingsRoot ?? dirname(__DIR__, 2);

    // WHERE each target publishes, WHICH SHAPES it publishes there, and whether
    // this generator owns its Markdown at all.
    //
    //  - `framework` splices a region into the design doc, which is a document
    //    about Clarity rather than Clarity's own front page, so a generated
    //    table there reads as evidence rather than as marketing. Its chart keeps
    //    the FIXED name `render-time.svg`, because that doc embeds it by that
    //    path and has done since long before this generator had a target table.
    //  - `clarity-docs` writes the dedicated multi-shape page: the shapes a
    //    reader would actually write (see the target's own `shapes` note), which
    //    is the full treatment on a page a reader opens on purpose. Clarity's
    //    README embeds the charts this target publishes and is otherwise
    //    untouched — the generator has no business in its Markdown.
    //
    // `sweep` is whether the destination directory exists only for this
    // benchmark. The distinction is load-bearing and was learned by damaging a
    // repository: clarity-engine/docs/images is SHARED — it holds
    // clarity-engine-logo.svg and clarity-engine-logo-src.svg beside whatever
    // charts live there — so clearing it wholesale DELETED TWO OF CLARITY'S OWN
    // ASSETS (2026-09-22). A sweep may only be wholesale in a directory this
    // generator created and owns. No target points at a shared directory at all
    // any more (the `clarity` target, which was the last one, is retired — see
    // the docblock above), so "may only be wholesale where we own it" is now a
    // property of the table rather than a rule a target has to remember.
    $targets = [
        'framework' => [
            'dir'    => $root . '/azera-framework/docs/images/benchmarks/view-engine',
            'mdFile' => $root . '/azera-framework/docs/03b-CLARITY-ENGINE.md',
            'sweep'  => true,
            'file'   => ['render-time' => 'render-time.svg'],
            'shapes' => ['sample'],
            'kinds'  => ['render-time'],
            'region' => static fn(array $shapes, array $e): string => veConsumerRegion(
                reset($shapes),
                $e,
                'images/benchmarks/view-engine/render-time.svg'
            ),
        ],
        'clarity-docs' => [
            'dir'    => $root . '/clarity-engine/docs/images/benchmarks',
            'mdFile' => $root . '/clarity-engine/docs/08-benchmark.md',
            'sweep'  => true,
            'file'   => [], // default: <page>-<kind>.svg
            // The shapes THIS page covers, stated by name rather than "all".
            //
            // The headline scalars page is deliberately NOT here: the README's
            // own diagrams show the heavy page and the published site shows every
            // shape, so a second copy of the trivial page on a page about the
            // interesting ones earns nothing. Objects-as-arrays is excluded for a
            // different reason — it isolates property-access cost rather than
            // showing a page a reader would write, so its figures belong beside
            // Objects where a reader can compare them, one link away.
            'shapes' => ['mixed', 'entities'],
            'kinds'  => 'all',
            // This target OWNS its Markdown file and writes it whole on first
            // publish. `framework` splices a region into a document it does not
            // own, which is the only other shape a target can take.
            'page'   => true,
            'region' => static fn(array $shapes, array $e): string => veShapesRegion($shapes, $e),
        ],
    ];

    if (!isset($targets[$target])) {
        return ["unknown publish target: {$target}"];
    }
    $t = $targets[$target];

    $dir    = $t['dir'];
    $mdFile = $t['mdFile'];

    if (!is_dir(dirname($dir))) {
        return ["target repository not found for '{$target}' at {$dir}"];
    }

    $log = [];
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        return ["cannot create {$dir}"];
    }

    // Only files this generator previously put here may be swept, and only where
    // the directory is ours. See the $sweep note above. Every target writes under
    // a directory this generator created, so a sweep is never a risk to another
    // repository's assets — that is exactly why no target carries a fixed-name
    // chart list any more.
    if ($t['sweep']) {
        foreach (glob($dir . '/*.svg') ?: [] as $stale) {
            @unlink($stale);
        }
    }

    // WHICH shapes this target publishes.
    //
    // Deriving it here means the charts copied and the tables rendered come from
    // ONE map, so a target can never show one shape's chart beside another
    // shape's numbers. A named shape is looked up through veShapeFor(), which
    // falls back to the headline and then to whatever the dataset has, so a
    // dataset that predates the preferred shape still publishes rather than
    // refusing.
    if ($t['shapes'] === 'all') {
        // Ordered by the SAME page table the harness measures in, so the page
        // opens with the headline shape and the caller's array order cannot
        // reorder the report. A shape the table does not know (a dataset from a
        // newer harness) is appended rather than dropped.
        $order  = array_keys(require __DIR__ . '/../benchmarks/view-engine/pages.php');
        $shapes = [];
        foreach ($order as $known) {
            if (isset($byPage[$known])) {
                $shapes[$known] = $byPage[$known];
            }
        }
        foreach ($byPage as $known => $shapeRows) {
            $shapes[$known] ??= $shapeRows;
        }
    } elseif ($byPage === []) {
        // A caller that passes no map at all holds only the rows it wants
        // published, so there is nothing to choose between and the choice must
        // not invent a key that is not there.
        $shapes = [veRowPage($rows) => $rows];
    } else {
        $shapes = [];
        foreach ($t['shapes'] as $wanted) {
            $pick = veShapeFor($byPage, $wanted);
            if (isset($byPage[$pick]) && !isset($shapes[$pick])) {
                $shapes[$pick] = $byPage[$pick];
            }
        }
    }
    if ($shapes === []) {
        return ["{$target}: the dataset carries no rows to publish"];
    }

    // The headline page's charts are stored UNPREFIXED — they are the published
    // API every consumer document embeds — so for exactly that page the source
    // name differs from the destination name.
    $headline = veRowPage($rows);
    $kinds    = $t['kinds'] === 'all' ? veChartOrder() : $t['kinds'];

    $copied = 0;
    foreach (array_keys($shapes) as $page) {
        foreach ($kinds as $kind) {
            $from = $svgDir . '/' . ($page === $headline ? $kind : $page . '-' . $kind) . '.svg';
            if (!is_file($from)) {
                continue;
            }
            // A fixed destination when the target named one (the consumer docs
            // embed by a stable path), otherwise the page-prefixed name.
            $to = $t['file'][$kind] ?? $page . '-' . $kind . '.svg';
            copy($from, $dir . '/' . $to);
            $copied++;
        }
    }
    $log[] = $copied === 1 ? 'copied 1 chart' : "copied {$copied} charts";

    $regionText = ($t['region'])($shapes, $env);

    // A target that OWNS its page does not exist yet on its first publish.
    // Writing it whole is the point of such a target: unlike a README there is
    // no hand-written prose of anyone else's to preserve, and a page that is
    // entirely generated is exactly what makes its prose safe to read.
    if (($t['page'] ?? false) && !is_file($mdFile)) {
        if (!is_dir(dirname($mdFile)) && !mkdir(dirname($mdFile), 0777, true) && !is_dir(dirname($mdFile))) {
            return ['cannot create ' . dirname($mdFile)];
        }
        file_put_contents($mdFile, veShapesPage($regionText));
        $log[] = 'created ' . basename($mdFile);

        return $log;
    }

    $body = (string) file_get_contents($mdFile);
    $next = veSpliceRegion($body, 'view-engine', $regionText);
    file_put_contents($mdFile, $next);
    $log[] = ($next === $body ? 'unchanged' : 'spliced') . ' ' . basename($mdFile);

    return $log;
}

/**
 * The region a consumer doc embeds.
 *
 * Two constraints shape it, and both come from where the text lands:
 *
 *  1. The image path is relative TO THAT FILE, so it differs per target and is
 *     passed in rather than assumed.
 *  2. The caption is built from the dataset — the budget and the engine
 *     versions are read from the env block, never typed. The retired caption
 *     here quoted a PHP version that this harness never recorded (it had been
 *     borrowed from the framework suite's dataset), which is precisely the
 *     failure a generated caption cannot have.
 *
 * The published-site URL is offered as well as the in-repository link: it is true
 * for every target and needs no per-repository knowledge, so a reader whose copy
 * of the docs is a release tarball still has somewhere to go.
 */
function veConsumerRegion(array $rows, array $env, string $imagePath): string
{
    $l = [];
    $l[] = 'Clarity is measured against other PHP template engines rendering the same page, on the same '
        . 'machine and PHP build. The chart and the table below are generated from the run\'s own JSON.';
    $l[] = '';
    $l[] = '![Template engine benchmark](' . $imagePath . ')';
    $l[] = '';
    $l[] = 'Rows are ordered by median, fastest first. Two engines sitting next to each other at the top '
        . 'of the table are not thereby ranked: a difference of a few percent is still within the spread '
        . 'of a single engine\'s own runs, and a gap that small is a tie, not a win.';
    $l[] = '';
    $l[] = veTable($rows);
    $l[] = '';
    $l[] = veEnvBlock($env, $rows);
    $l[] = '';
    $l[] = 'Full report — every chart, including the first render (measured in a fresh process per engine) '
        . 'and per-render memory: <' . vePublicUrl() . '>';

    return implode("\n", $l);
}

/**
 * The region of the dedicated multi-shape page: one section per rendering shape.
 *
 * WHY A PAGE OF ITS OWN. The consumer region above is a README's claim — one
 * chart and one table, at a glance. The other shapes answer different questions
 * (does the cost hold when the page is heavy? when the data is objects rather
 * than scalars?) and each needs three charts and a table, which is a report
 * rather than a README entry. So they get a page a reader opens deliberately.
 *
 * Prose here names NO measured figure. Every number is in a chart or in the
 * generated table, both recomputed from the dataset; a figure typed into a
 * sentence would silently outlive the run it describes. Same rule as the
 * published page, and enforced by tests/ViewEngineProseTest.php.
 *
 * @param array<string,list<array<string,mixed>>> $shapes page => that shape's rows
 */
function veShapesRegion(array $shapes, array $env): string
{
    $l = [];
    $l[] = 'Every engine was measured rendering the SAME template shape, on the same machine and PHP build '
        . 'and in the same session, so a difference between two engines is the engines and a difference '
        . 'between two sections is the work the page does.';
    $l[] = '';
    $l[] = 'Compare engines WITHIN a shape. The sections below are different templates doing different '
        . 'work with different data, so a time in one and a time in another are different measurements — '
        . 'a ranking read across sections would be a ranking of the pages, not of the engines.';
    $l[] = '';

    foreach ($shapes as $page => $rows) {
        $label = vePageLabel($page);
        $about = vePageAbout($page);

        $l[] = '## ' . $label;
        $l[] = '';
        if ($about !== '') {
            $l[] = 'What this page renders, and so what these numbers are the cost of: ' . $about . '.';
            $l[] = '';
        }

        // The per-render time first: it is the figure the engine comparison is
        // about. First render and memory follow, because each answers its own
        // question rather than repeating this one.
        $captions = [
            'render-time' => [
                'Time per render — ' . $label,
                'The dot is the median render and the caps bound the fastest observation and p95, so an '
                    . 'engine that is usually fast but occasionally slow looks different from one that is '
                    . 'uniformly slower. The multiplier beside each row is measured against the fastest '
                    . 'median in the chart.',
            ],
            'warm-cost' => [
                'First render — ' . $label,
                'The one-off cost the first request after a deploy pays: the engine\'s classes load, the '
                    . 'template compiles, the cache is written and the page renders once. Measured in a '
                    . 'fresh process per engine, so no engine is measured against a cache another engine '
                    . 'already paid for.',
            ],
            'memory' => [
                'Memory per run — ' . $label,
                'Three marks, three measurements: the left cap is the engine loaded with nothing rendered, '
                    . 'the dot is the heap retained after the run (gc collected) and the right cap is the '
                    . 'run\'s peak. Not a spread of repeats — every reading is deterministic.',
            ],
        ];
        foreach (veChartOrder() as $kind) {
            [$caption, $note] = $captions[$kind];
            $l[] = '### ' . $caption;
            $l[] = '';
            $l[] = '![' . $caption . '](images/benchmarks/' . $page . '-' . $kind . '.svg)';
            $l[] = '';
            $l[] = $note;
            $l[] = '';
        }

        $l[] = '### Results — ' . $label;
        $l[] = '';
        $l[] = 'Generated from the same rows the charts above are drawn from. Rows are ordered by median, '
            . 'fastest first.';
        $l[] = '';
        $l[] = veTable($rows);
        $l[] = '';
    }

    $l[] = '## What the columns are';
    $l[] = '';
    $l[] = '- **First render** — the first request after a deploy, measured in a fresh process per engine: '
        . 'engine boot, template compile, cache write and one render. The TEMPLATE cache is cold; OPcache is '
        . 'on but its CLI segment is per-process, so the engine source is compiled in that process. '
        . 'Comparable across engines because no '
        . 'engine inherits another\'s warm template cache or loaded classes.';
    $l[] = '- **Mean / Median / Min / p95** — computed over every individual render across all runs.';
    $l[] = '- **Retained** — PHP heap still held after a whole run of renders, with `gc_collect_cycles()` '
        . 'called before the reading, in a fresh process. This is what a process carries while serving.';
    $l[] = '- **Peak** — the same run\'s PHP heap high-water mark, which is where the transient allocation '
        . 'of the first compile lives. It sits above Retained and is not a second measurement of it.';
    $l[] = '';
    $l[] = 'Two engines sitting next to each other at the top of a table are not thereby ranked: a '
        . 'difference of a few percent is still within the spread of a single engine\'s own runs, and a '
        . 'gap that small is a tie, not a win.';
    $l[] = '';
    $l[] = veEnvBlock($env, reset($shapes));
    $l[] = '';
    $l[] = 'Every chart on this page is a plain SVG generated from the run\'s own JSON, so a number and a '
        . 'diagram cannot disagree. The full published report, including the headline page and each '
        . 'shape\'s live page: <' . vePublicUrl() . '>';

    return implode("\n", $l);
}

/**
 * The dedicated page as a whole: a title, the generated region, and a footer.
 *
 * The region is wrapped in the same `view-engine` markers the consumer docs use,
 * so a RE-publish splices rather than appends and a second run cannot leave two
 * copies of the report behind.
 */
function veShapesPage(string $region): string
{
    $l = [];
    $l[] = '# Benchmark';
    $l[] = '';
    $l[] = 'How Clarity compares with the other mainstream PHP template engines, across four rendering '
        . 'shapes: a page of scalars, a heavy page of escapes and loops, a page of objects, and the same '
        . 'objects held as nested arrays. The main README shows one of these at a glance; this page is '
        . 'the comparison in full.';
    $l[] = '';
    $l[] = '> **Generated — do not edit by hand.** Every figure below comes from a benchmark run\'s own '
        . 'JSON and is re-rendered from it, so the numbers cannot drift from the data they came from. To '
        . 'update this page, re-run the harness and re-publish; see the `azera-competition` repository.';
    $l[] = '';
    $l[] = '<!-- view-engine:begin -->';
    $l[] = '';
    $l[] = $region;
    $l[] = '';
    $l[] = '<!-- view-engine:end -->';

    return implode("\n", $l) . "\n";
}

// -----------------------------------------------------------------------------
// Driver
//
// Runs only when this file IS the entry point. The functions above are required
// by tests/ViewEnginePublishTest.php, and an unguarded driver would execute a
// whole render (and exit) the moment a test required the file to reach them —
// the same shape as the "never require run.php to test it" trap on the suite
// harness. $_SERVER['SCRIPT_FILENAME'] is the real entry point; under PHPUnit it
// is the phpunit binary, not this file.
// -----------------------------------------------------------------------------

$veIsEntryPoint = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if (!$veIsEntryPoint) {
    return;
}

$opts = getopt('', ['dataset::', 'out::', 'publish::', 'list', 'help']);

if (isset($opts['help'])) {
    echo <<<TXT
View-engine report generator

Options:
  --dataset=<file>       Harness JSON to render (required)
  --out=<dir>            Output directory (default docs/benchmarks)
  --publish=<targets>    Also write into consumer repos, comma-separated:
                         clarity-docs, framework
                         (NOT repeated flags: getopt keeps only the last one,
                          so --publish=a --publish=b would silently write to b only)
  --list                 List the charts and targets this build knows
  --help                 Show this help

A dataset may cover several rendering shapes (see benchmarks/view-engine/pages.php).
The PRIMARY page is the headline — its charts keep their unprefixed names, so no
published figure or consumer README breaks — and every other page follows in its
own section with its own charts (<page>-<chart>.svg) and its own table.

The publish targets differ in WHICH shapes they carry and WHETHER they touch the
target's Markdown at all:
  framework      the design doc — the headline shape, spliced as a region
  clarity-docs   a dedicated page for the rendering shapes a reader would write,
                 written whole; its charts are the ones Clarity's README embeds

TXT;
    exit(0);
}

if (isset($opts['list'])) {
    echo "Charts: render-time, warm-cost, memory\n";
    echo "Pages: " . implode(', ', array_keys(require __DIR__ . '/../benchmarks/view-engine/pages.php'))
        . " (first is the headline; other pages prefix their charts)\n";
    echo "Publish targets: clarity-docs (docs page + charts), framework (docs/03b + chart)\n";
    echo "Pass several as a comma list: --publish=clarity-docs,framework\n";
    exit(0);
}

$datasetArg = $opts['dataset'] ?? null;
if ($datasetArg === null) {
    fwrite(STDERR, "Missing --dataset=<file>. See --help.\n");
    exit(1);
}

$outDir  = rtrim($opts['out'] ?? dirname(__DIR__) . '/docs/benchmarks', '/');
$publish = $opts['publish'] ?? null;

[$env, $results] = veLoadDataset($datasetArg);

// The dataset may cover several rendering shapes. `$pages` is the canonical
// order (the primary page first, then whatever else the run measured), and the
// primary page is the one the top of the document and the consumer publications
// describe.
$pages = vePages($results);
if ($pages === []) {
    fwrite(STDERR, "Dataset contains no engine rows: {$datasetArg}\n");
    exit(1);
}

$primary     = $pages[0];
$primaryRows = veRowsForPage($results, $primary);
$extraPages  = array_slice($pages, 1);

if ($primaryRows === []) {
    fwrite(STDERR, "Dataset contains no engine rows for page '{$primary}': {$datasetArg}\n");
    exit(1);
}

echo "View-engine report\n";
echo "  dataset: {$datasetArg}\n";
echo "  engines: " . implode(', ', array_column($primaryRows, 'engine')) . "\n";
echo "  pages:   " . implode(', ', $pages)
    . (count($pages) > 1 ? "   (headline: {$primary})" : '') . "\n\n";

$svgDir = $outDir . '/svg/view-engine';
if (!is_dir($svgDir) && !mkdir($svgDir, 0777, true) && !is_dir($svgDir)) {
    fwrite(STDERR, "Cannot create output dir: {$svgDir}\n");
    exit(1);
}

// Sweep before writing, so a chart this generator stops drawing cannot sit in
// the published tree as an orphan. The chart list below is what the page
// embeds — it is built from what was actually WRITTEN, not from a listing.
foreach (glob($svgDir . '/*.svg') ?: [] as $stale) {
    @unlink($stale);
}

/**
 * Draw one page's three charts and return their keys.
 *
 * `$page` is null for the primary page, which is what keeps its chart FILE NAMES
 * unprefixed — the published API the two consumer repositories embed.
 *
 * @return list<string>
 */
$veChartsFor = static function (array $rowsForPage, ?string $page) use ($env, $svgDir): array {
    $keys = [];
    $keys[] = veRenderTimeChart($rowsForPage, $env, $svgDir, $page);
    $keys[] = veWarmCostChart($rowsForPage, $env, $svgDir, $page);
    $keys[] = veMemoryChart($rowsForPage, $env, $svgDir, $page);

    return array_values(array_filter($keys, static fn(string $c): bool => $c !== ''));
};

$charts = $veChartsFor($primaryRows, null);

// The additional pages. Their charts carry a page prefix, so a three-page run
// draws nine charts and no two can collide.
$extra = [];
foreach ($extraPages as $page) {
    $rowsForPage = veRowsForPage($results, $page);
    if ($rowsForPage === []) {
        continue;
    }
    $extra[$page] = ['charts' => $veChartsFor($rowsForPage, $page), 'rows' => $rowsForPage];
    echo "  page '{$page}': " . count($extra[$page]['charts']) . " charts\n";
}

$mdBody = veMarkdown($primaryRows, $env, $charts, $extra);
file_put_contents($outDir . '/view-engine.md', $mdBody);
file_put_contents($outDir . '/view-engine.html', veHtml($primaryRows, $env, $charts, $extra));

// The dataset is copied beside the page it describes, so a published figure can
// be traced to the exact file it came from without leaving the site.
copy($datasetArg, $outDir . '/view-engine.json');

// The CSV is the SAME run as the JSON, and the two must never disagree: a
// published pair where one half describes an older run is precisely the failure
// this generator exists to prevent — a figure nobody can trace. A plain copy()
// of a missing source only WARNS, which leaves the PREVIOUS run's CSV sitting
// beside the new JSON looking exactly like a current file. (Seen for real: a
// partially fetched dataset, .json only, left a 6-row CSV from another round
// under a fresh JSON.) So the copy is checked, and on failure the stale sibling
// is REMOVED rather than left to be misread.
$csvSource = preg_replace('/\.json$/', '.csv', $datasetArg) ?: $datasetArg;
$csvTarget = $outDir . '/view-engine.csv';
$csvCopied = false;
if (is_file($csvSource)) {
    if (copy($csvSource, $csvTarget)) {
        $csvCopied = true;
    } else {
        fwrite(STDERR, "  ! could not copy {$csvSource} to {$csvTarget}\n");
        @unlink($csvTarget);
    }
} else {
    fwrite(STDERR, "  ! no CSV beside {$datasetArg}; removing any stale {$outDir}/view-engine.csv\n");
    @unlink($csvTarget);
}

$chartCount = count($charts) + array_sum(array_map(
        static fn(array $s): int => count($s['charts']),
        $extra
    ));
echo '  ✓ view-engine.html, view-engine.md, ' . $chartCount . ' charts, view-engine.json'
    . ($csvCopied ? '/view-engine.csv' : ' (no csv: none beside the dataset)') . "\n";

if ($publish !== null) {
    // COMMA-SEPARATED, not repeated flags. getopt() returns only the LAST value
    // for a repeated option, so `--publish=clarity --publish=framework` would
    // publish to one repository and report success — a silent partial publish
    // into two other people's docs.
    //
    // The consumer targets publish the PRIMARY page — a design doc whose one
    // chart is an at-a-glance claim about Clarity. A
    // three-chart-per-page embed there would be a report inside a README, so the
    // other shapes go to their own page (`clarity-docs`) and the full set stays
    // one link away (vePublicUrl()).
    //
    // The other shapes' ROWS are passed alongside the headline's: the docs
    // target renders a table per shape, and it cannot render one from charts it
    // reads off disk. They are the shapes the generator just DREW, so a table
    // and its charts cannot describe two different pages. The headline is
    // included because the docs page is the full treatment — it is the one
    // place a reader sees every shape, in canonical order.
    $extraRows = [$primary => $primaryRows];
    foreach ($extra as $page => $section) {
        $extraRows[$page] = $section['rows'];
    }

    foreach (explode(',', (string) $publish) as $target) {
        $target = trim($target);
        if ($target === '') {
            continue;
        }
        foreach (vePublish($target, $primaryRows, $env, $charts, $svgDir, $mdBody, null, $extraRows) as $line) {
            echo "  → {$line}\n";
        }
    }
}

echo "\nWrote {$outDir}\n";