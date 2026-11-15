<?php

declare(strict_types=1);

/**
 * azera-competition — Clarity engine homepage benchmarks section generator.
 *
 * Draws the benchmark section of Clarity's product page on the Sailantis
 * homepage from the SAME view-engine dataset the published comparison uses:
 * three charts of the `mixed` page, the Clarity partial the homepage includes,
 * and the prose and alt text beside them.
 *
 * WHY THIS IS A SCRIPT OF ITS OWN, like scripts/homepage-report.php
 *
 * scripts/view-engine-report.php publishes a VIEW: a Markdown page, an HTML
 * dashboard and the snippets consumer repositories embed. What the homepage
 * needs is a Clarity *partial* — a fragment with no front matter, included
 * inside a page the homepage owns — plus charts in the homepage's public asset
 * tree. None of that is a view, and teaching the report generator a second
 * output format to serve one caller would put the homepage's markup inside it.
 * This script reads the same dataset and reuses the report's chart functions,
 * so a product-page figure and the published report figure cannot disagree
 * about a number.
 *
 * WHY ONLY THE `mixed` PAGE
 *
 * The comparison covers four rendering shapes. `mixed` is the one a product
 * page leads with: it is the heavy page, where every flat value is emitted twice
 * (text and attribute) and every row contributes six field reads, with every
 * value HTML-special so the escape path does real work. Against the trivial
 * `sample` page the shapes rank engines by render noise; this one ranks them by
 * the per-access cost that actually separates them. The other three shapes stay
 * on the published page, one link away — a product page is a summary, not the
 * report.
 *
 * WHY NO TAB SWITCH
 *
 * The Azera and Volucer sections switch between ROUTES: three questions, one
 * visible at a time. Here there is one page and three questions about it, and
 * all three are short — a switch would hide two thirds of a section whose whole
 * value is the ranking being visible at a glance. So the tiles are simply
 * stacked, and no `data-bench-panels` attribute is emitted: bench-switch.ts
 * drives only strips that opt in, and a panel it cannot see it cannot hide.
 *
 * Usage:
 *   php scripts/clarity-homepage-report.php
 *   php scripts/clarity-homepage-report.php --dataset=benchmarks/view-engine/<run>.json
 *   php scripts/clarity-homepage-report.php --homepage=../sailantis-homepage --dry-run
 *   php scripts/clarity-homepage-report.php --help
 */

// Reuse, not a second implementation: the chart primitives live in SvgChart, the
// page/chart functions and the provenance wording in view-engine-report.php. That
// file guards its own driver behind an entry-point check, so requiring it here
// defines the functions without rendering anything.
require_once __DIR__ . '/view-engine-report.php';

// -----------------------------------------------------------------------------
// What the page shows
// -----------------------------------------------------------------------------

/**
 * The one measured page this section publishes.
 *
 * Named rather than hardcoded into each call so a second page is one edit here
 * plus one row in chChartMeta()'s file names.
 */
const CH_PAGE = 'mixed';

/** Where the homepage serves the generated charts from (URL, not a path). */
const CH_CHART_URL = '/assets/images/clarity/benchmarks';

/**
 * The file inside the homepage that the partial is written to.
 *
 * Named for its route the way `partials/azera/bench-section` is, because the
 * include site — not this script — decides where a section goes.
 */
const CH_PARTIAL_FILE = 'views/partials/clarity/bench-section.clarity.html';

/**
 * The three charts, in reading order, and the words a reader gets instead of the
 * picture.
 *
 * `title` is the tile's heading and `mode` its qualifier, both LIVE TEXT: the
 * chart itself is an <img>, so a reader using a screen reader who reaches the
 * surrounding markup never sees the chart's own title. `note` says what the
 * figure measures, in words that carry no figure — every number on this section
 * is either inside a chart or in the footnote, both of which are recomputed from
 * the dataset, while a number typed into a sentence is recomputed by nothing.
 *
 * `file` carries the page in its name (`homepage-mixed-…`) so a second page
 * cannot collide with this one, and so the sweep in the driver can be narrow.
 *
 * @return array<string,array{file:string,title:string,mode:string,note:string}>
 */
function chChartMeta(): array
{
    return [
        // Steady state — the headline. Drawn as a RANGE rather than a bar
        // because the two facts a reader needs together are how fast an engine
        // usually is (the dot) and how steady it is (the caps).
        'render-time' => [
            'file'  => 'homepage-' . CH_PAGE . '-render-time.svg',
            'title' => 'Time per render',
            'mode'  => 'steady state',
            'note'  => 'Every render of the same page is measured: the dot is the median, the caps the '
                . 'fastest observation and the 95th percentile.',
        ],
        // The first request after a deploy. It is the one figure that separates
        // a compiling engine from an include, and Clarity's own claim — compile
        // once, then straight PHP — is only legible beside it.
        'warm-cost' => [
            'file'  => 'homepage-' . CH_PAGE . '-warm-cost.svg',
            'title' => 'First render',
            'mode'  => 'cold template cache',
            'note'  => 'The first request after a deploy: engine classes load, the template compiles, the '
                . 'cache is written and the page renders once.',
        ],
        'memory' => [
            'file'  => 'homepage-' . CH_PAGE . '-memory.svg',
            'title' => 'Memory per run',
            'mode'  => 'retained heap',
            'note'  => 'The heap a whole run still holds after a garbage collection, and the run\'s '
                . 'high-water mark, each read in its own fresh process.',
        ],
    ];
}

/** The chart kinds in reading order. */
function chChartOrder(): array
{
    return array_keys(chChartMeta());
}

/**
 * The dataset a bare `php scripts/clarity-homepage-report.php` renders.
 *
 * The newest `view-engine-*.json` beside the harness, ignoring the `-local-`
 * smoke runs: those are short local spot-checks (`--iterations-per-run=300`)
 * that are not comparable with a full run, and picking one by mtime would
 * publish a 300x budget on a page whose footnote says otherwise. A caller who
 * wants one says so with --dataset.
 *
 * Returns null when there is no dataset at all, so the driver can fail with the
 * paths it looked in rather than with a glob string.
 */
function chDefaultDataset(): ?string
{
    $candidates = glob(dirname(__DIR__) . '/benchmarks/view-engine/view-engine-*.json') ?: [];
    $candidates = array_values(array_filter(
        $candidates,
        static fn(string $path): bool => !str_contains(basename($path), '-local-')
    ));
    if ($candidates === []) {
        return null;
    }

    usort($candidates, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

    return $candidates[0];
}

// -----------------------------------------------------------------------------
// Rendering
// -----------------------------------------------------------------------------

/** Escape text for HTML text and attribute contexts. */
function chEscape(string $text): string
{
    return str_replace(
        ['&', '<', '>', '"'],
        ['&amp;', '&lt;', '&gt;', '&quot;'],
        $text
    );
}

/**
 * "Seven engines", with the verb that agrees with it.
 *
 * Counted from the rows rather than typed, for the reason the report gives: the
 * entrant count is a property of the RUN, and a sentence reading "Six engines"
 * above seven rows is the drift this whole generator exists to prevent.
 */
function chEntrantPhrase(array $rows): string
{
    $count = count($rows);

    return veEntrantPhrase($rows) . ($count === 1 ? ' renders' : ' render');
}

/**
 * Draw one page's three charts and return their SVG, keyed by chart kind.
 *
 * The charts are drawn by the report's own functions — `veRenderTimeChart()`,
 * `veWarmCostChart()`, `veMemoryChart()` — rather than by a second set of calls
 * assembled here. A product-page figure and the published figure for the same
 * run are therefore the same drawing, not two drawings of one dataset that can
 * drift apart in an axis, a colour or a caption.
 *
 * They write into a scratch directory because their contract is "draw to a
 * directory, return the file key"; the caller decides the homepage's file names.
 * The scratch directory is removed either way, so a failed run cannot leave
 * half-drawn figures behind in the asset tree.
 *
 * @param list<array<string,mixed>> $rows rows of ONE page (see veRowsForPage)
 * @return array<string,string> chart kind => SVG
 */
function chCharts(array $env, array $rows): array
{
    $tmp = sys_get_temp_dir() . '/clarity-homepage-' . bin2hex(random_bytes(6));
    if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
        throw new RuntimeException("cannot create scratch directory {$tmp}");
    }

    $out = [];
    try {
        $keys = [
            'render-time' => veRenderTimeChart($rows, $env, $tmp, CH_PAGE),
            'warm-cost'   => veWarmCostChart($rows, $env, $tmp, CH_PAGE),
            'memory'      => veMemoryChart($rows, $env, $tmp, CH_PAGE),
        ];
        foreach ($keys as $kind => $key) {
            // An empty key means the function declined to draw — a dataset that
            // lacks the columns for that figure. The function still writes (and
            // returns) a key when SvgChart yields nothing, so the CONTENT is what
            // decides: a tile is omitted rather than drawn from an empty figure.
            if ($key === '') {
                continue;
            }
            $path = $tmp . '/' . $key . '.svg';
            if (!is_file($path)) {
                continue;
            }
            $svg = (string) file_get_contents($path);
            if (trim($svg) !== '') {
                $out[$kind] = $svg;
            }
        }
    } finally {
        foreach (glob($tmp . '/*.svg') ?: [] as $drawn) {
            @unlink($drawn);
        }
        @rmdir($tmp);
    }

    return $out;
}

/**
 * The alternative text of one chart, rebuilt from the facts it was drawn from.
 *
 * Generated rather than typed, and for a sharper reason than the prose: this is
 * the ONLY description of the figure a reader using a screen reader gets, so it
 * has to carry the same numbers the rows carry rather than a transcription that
 * outlives the run it describes.
 *
 * Each kind states the figures THAT chart draws, ordered the way the chart draws
 * them, so the sentence and the bars rank identically.
 *
 * @param list<array<string,mixed>> $rows
 */
function chAltText(string $kind, array $rows): string
{
    // Sorted on the FIGURE, not on the formatted string: "10.00 MB" sorts before
    // "9.00 MB" lexicographically, which would state a ranking the chart does not
    // draw the moment a figure reaches five digits.
    $sortKey = [];
    $values  = [];
    foreach ($rows as $row) {
        if (!isset($row['engine'])) {
            continue;
        }
        $label = veEngineLabel((string) $row['engine']);

        if ($kind === 'render-time') {
            $v = veNum($row, 'median_ms');
            if ($v !== null) {
                $values[$label]  = number_format($v, 3) . ' ms median';
                $sortKey[$label] = $v;
            }
        } elseif ($kind === 'warm-cost') {
            $v = veNum($row, 'first_render_ms');
            if ($v !== null) {
                $values[$label]  = number_format($v, 3) . ' ms';
                $sortKey[$label] = $v;
            }
        } elseif ($kind === 'memory') {
            $retained = veNum($row, 'retained_mem');
            $floor    = veNum($row, 'base_mem');
            $peak     = veNum($row, 'peak_mem');
            if ($retained === null || $floor === null || $peak === null) {
                continue;
            }
            $mb = static fn(float $bytes): string => number_format($bytes / 1048576, 2) . ' MB';
            $values[$label]  = $mb($retained) . ' retained, ' . $mb($floor) . ' floor, ' . $mb($peak) . ' peak';
            $sortKey[$label] = $retained;
        }
    }
    if ($values === []) {
        return '';
    }
    // The charts order their rows by their own figure, ascending — the memory
    // chart by the retained dot, the others by their single value — so the
    // sentence must too, or it states a different ranking than the picture.
    uksort($values, static function (string $a, string $b) use ($sortKey): int {
        $byValue = ($sortKey[$a] ?? INF) <=> ($sortKey[$b] ?? INF);

        return $byValue !== 0 ? $byValue : strcasecmp($a, $b);
    });

    $parts = [];
    foreach ($values as $label => $reading) {
        $parts[] = $label . ' ' . $reading;
    }

    $lead = match ($kind) {
        'render-time' => 'Render time for the same page, median of every render, fastest first',
        'warm-cost'   => 'First render in a fresh process with a cold template cache, fastest first',
        'memory'      => 'Heap after a whole run, lightest retained first',
        default       => 'Measured figures, lowest first',
    };

    return $lead . ': ' . implode(', ', $parts) . '.';
}

/**
 * The conditions line under the section: how the numbers were obtained.
 *
 * Every clause is read from the dataset's `env` block, so the caption cannot
 * describe a run other than the one behind it — the same rule the report's own
 * environment block follows. A clause whose field is missing is omitted rather
 * than guessed: an older dataset genuinely did not record it.
 */
function chConditions(array $env): string
{
    $budget = [];
    $iters  = $env['iterations_per_run'] ?? null;
    $runs   = $env['runs'] ?? null;
    if (is_numeric($iters) && is_numeric($runs)) {
        $budget[] = 'Median of ' . number_format((int) $iters) . ' renders per run over '
            . (int) $runs . ' runs';
    } elseif (is_numeric($iters)) {
        $budget[] = 'Median of ' . number_format((int) $iters) . ' renders per run';
    }
    if (is_numeric($env['items'] ?? null)) {
        $budget[] = (int) $env['items'] . ' items per render';
    }

    $sentence = $budget === [] ? 'Aggregated over multiple runs' : implode(', ', $budget);
    // In-process is not a detail here: these figures compare template engines,
    // not web servers, and a reader who compared them with the Azera section's
    // end-to-end HTTP numbers would otherwise be comparing two different things.
    $sentence .= ', rendered in-process on the PHP CLI.';

    $env_parts = [];
    if (is_string($env['php_version'] ?? null) && $env['php_version'] !== '') {
        $env_parts[] = 'PHP ' . $env['php_version'];
    }
    if (array_key_exists('opcache', $env)) {
        $env_parts[] = 'OPcache (opcache.enable_cli): ' . (($env['opcache'] ?? false) ? 'yes' : 'no');
    }
    // The JIT clause comes from the SHARED renderer, so this footnote and the
    // published report cannot word one run two ways. It is empty for a dataset
    // that did not record the JIT — "not recorded" is not "off", and inventing
    // either would misdescribe the run behind the numbers above.
    $jit = \AzeraCompetition\Report\Provenance::jitClause($env);
    if ($jit !== '') {
        $env_parts[] = $jit;
    }
    // The Clarity revalidate window, from the same shared renderer. It moves the
    // Clarity bar and cannot be seen from the other engines' rows, so it belongs
    // in the caption rather than only in the dataset.
    $reval = \AzeraCompetition\Report\Provenance::clarityRevalidateClause($env);
    if ($reval !== '') {
        $env_parts[] = $reval;
    }
    if ($env_parts !== []) {
        $sentence .= ' ' . implode(' · ', $env_parts) . '.';
    }

    return $sentence;
}

/**
 * The whole section as a Clarity partial.
 *
 * The markup hides nothing and applies no switch: all three charts are rendered
 * visible, in order, and no `data-bench-panels` attribute is emitted, so
 * bench-switch.ts — which only drives strips that opt in — leaves the section
 * alone. Each tile is a full-width row rather than a half-width pair, because
 * the range charts reserve a fixed name/value gutter that would set the text
 * inside a half-width figure smaller than the text around it.
 *
 * @param array<string,string> $charts chart kind => SVG (see chCharts())
 */
function chSection(array $env, array $rows, string $chartUrl, array $charts): string
{
    $intro = chEntrantPhrase($rows)
        . ' the same page from the same template and the same data: a layout, an included partial, a block of'
        . ' flat values each emitted twice — once as text, once in an attribute — and a table of rows with six'
        . ' fields each, with every value HTML-special so the escaping path does real work in both contexts.'
        . ' Each engine renders in-process, so a difference here is the engine rather than the server, and'
        . ' inside every chart each row is relative to the fastest.';

    $tiles = [];
    foreach (chChartOrder() as $kind) {
        if (!isset($charts[$kind])) {
            continue;
        }
        $meta = chChartMeta()[$kind];
        $alt  = chAltText($kind, $rows);

        $tiles[] = sprintf(
            "        <div class=\"col-12 bench-tile\">\n"
            . "          <p class=\"bench-tile-title\">%s <span class=\"bench-tile-mode\">%s</span></p>\n"
            . "          <img src=\"%s/%s\"\n"
            . "            class=\"img-fluid w-100\"\n"
            . "            alt=\"%s\"/>\n"
            . "          <p class=\"bench-tile-note\">%s</p>\n"
            . "        </div>",
            chEscape($meta['title']),
            chEscape($meta['mode']),
            chEscape(rtrim($chartUrl, '/')),
            chEscape($meta['file']),
            chEscape($alt),
            chEscape($meta['note'])
        );
    }

    if ($tiles === []) {
        throw new RuntimeException(
            'the dataset produced no chart for the ' . CH_PAGE . ' page; nothing to write'
        );
    }

    return "{# Generated by azera-competition scripts/clarity-homepage-report.php. #}\n"
        . "{# Do not edit: the numbers and conditions come from the run. #}\n"
        . "      <p class=\"text-center text-muted mb-4 pb-2 mx-auto\" style=\"max-width: 760px;\">\n"
        . '        ' . chEscape($intro) . "\n"
        . "      </p>\n"
        . "      <div class=\"row g-4 justify-content-center align-items-start\">\n"
        . implode("\n", $tiles) . "\n"
        . "      </div>\n"
        . "      <p class=\"text-center text-muted small mt-4 mb-0\">\n"
        . '        ' . chEscape(chConditions($env)) . "\n"
        . "      </p>\n";
}

// -----------------------------------------------------------------------------
// CLI
// -----------------------------------------------------------------------------
//
// Guarded so the pure helpers above can be REQUIRED by the test suite. Without
// the guard, including this file from a test would parse $argv, render a section
// and exit() the test process — which is not a test failure but a test run that
// silently stops. The script is still a plain `php scripts/x.php` for every
// normal caller: only a `require` from the tests takes the other branch.
if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath(__FILE__)) {
    return;
}

$opts = getopt('', ['dataset::', 'homepage::', 'chart-url::', 'out::', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo <<<TXT
azera-competition Clarity homepage benchmarks section

Options:
  --dataset=<path>   view-engine dataset to render (default: the newest
                     benchmarks/view-engine/view-engine-*.json, ignoring
                     the -local- smoke runs)
  --homepage=<dir>   The sailantis-homepage checkout to write into
                     (default: ../sailantis-homepage beside this repository)
  --chart-url=<url>  URL prefix the partial's <img> tags use
                     (default: /assets/images/clarity/benchmarks)
  --out=<dir>        Write the charts here instead of the homepage's asset tree
  --dry-run          Render and report, write nothing
  --help             Show this help

Writes:
  <homepage>/views/partials/clarity/bench-section.clarity.html
  <homepage>/public/assets/images/clarity/benchmarks/homepage-mixed-*.svg

Renders the 'mixed' chart page only — the heavy page. The other rendering shapes
stay on the published report (docs/benchmarks/view-engine.html).

TXT;
    exit(0);
}

$root     = dirname(__DIR__);
$home     = rtrim($opts['homepage'] ?? dirname($root) . '/sailantis-homepage', '/\\');
$chartUrl = rtrim($opts['chart-url'] ?? CH_CHART_URL, '/');
$dryRun   = isset($opts['dry-run']);

$dataset = $opts['dataset'] ?? chDefaultDataset();
if ($dataset === null || !is_file($dataset)) {
    fwrite(STDERR, "No dataset to render.\n");
    fwrite(STDERR, "Pass --dataset=<file>, or put a view-engine-*.json in "
        . $root . "/benchmarks/view-engine/.\n");
    exit(1);
}

[$env, $results] = veLoadDataset($dataset);

// Fail loudly rather than render a subset: a section drawn from a dataset that
// never measured this page would silently publish the wrong page's numbers.
$rows = veRowsForPage($results, CH_PAGE);
if ($rows === []) {
    fwrite(STDERR, "Dataset {$dataset} has no '" . CH_PAGE . "' rows.\n");
    fwrite(STDERR, "Pages present: " . implode(', ', vePages($results)) . "\n");
    exit(1);
}
if (count($rows) < 2) {
    fwrite(STDERR, "Dataset {$dataset} measured only one engine on '" . CH_PAGE
        . "'; a comparison needs at least two.\n");
    exit(1);
}

$charts = chCharts($env, $rows);
$partial = chSection($env, $rows, $chartUrl, $charts);

$chartFiles = [];
foreach (chChartOrder() as $kind) {
    if (isset($charts[$kind])) {
        $chartFiles[$kind] = chChartMeta()[$kind]['file'];
    }
}

if ($dryRun) {
    echo "Dry run — nothing written.\n";
    echo "  dataset:  {$dataset}\n";
    echo "  homepage: {$home}\n";
    echo "  page:     " . CH_PAGE . ' (' . count($rows) . " engines)\n";
    echo "  partial:  " . CH_PARTIAL_FILE . " (" . strlen($partial) . " bytes)\n";
    foreach ($chartFiles as $kind => $file) {
        echo "  chart:    {$file} (" . strlen($charts[$kind]) . " bytes)\n";
    }
    exit(0);
}

$partialPath = $home . '/' . CH_PARTIAL_FILE;
$chartsDir   = rtrim($opts['out'] ?? $home . '/public' . $chartUrl, '/\\');

foreach ([dirname($partialPath), $chartsDir] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
        fwrite(STDERR, "Cannot create {$dir}\n");
        exit(1);
    }
}

// The chart directory persists between renders, so a chart this script has
// STOPPED drawing would keep sitting in the homepage's asset tree and keep being
// served — a figure no page embeds any more, still reachable by URL. The glob is
// narrow on purpose: it covers only this page's charts, so a hand-drawn
// illustration dropped in beside them (or another page's section) is not deleted
// by a re-render.
foreach (glob($chartsDir . '/homepage-' . CH_PAGE . '-*.svg') ?: [] as $stale) {
    @unlink($stale);
}

foreach ($chartFiles as $kind => $file) {
    file_put_contents($chartsDir . '/' . $file, $charts[$kind]);
}
file_put_contents($partialPath, $partial);

echo "Clarity homepage section\n";
echo "  dataset: {$dataset}\n";
echo "  partial: {$partialPath}\n";
echo "  charts:  {$chartsDir} (" . count($chartFiles) . " files)\n";
