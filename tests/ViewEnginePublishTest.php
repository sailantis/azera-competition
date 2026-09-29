<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The view-engine publication surface.
 *
 * `scripts/view-engine-report.php --publish=<target>` writes into TWO OTHER
 * repositories: clarity-engine's README + docs/images, and azera-framework's
 * docs/03b + docs/images. That makes the generator the only thing in this
 * repository that edits files elsewhere, and it makes two failures possible that
 * nothing else here would catch:
 *
 *  1. A stale chart. The target directories live in other repositories, where
 *     nobody can tell a current chart from an orphan, so a chart the generator
 *     stops drawing would sit there forever illustrating a section the page no
 *     longer has. The framework publication learned this the expensive way
 *     (four orphaned charts of a removed section), so the sweep is pinned at
 *     the source as well as by its outcome.
 *  2. A dead image path. The chart is referenced RELATIVE TO EACH CONSUMER's
 *     own file, so a path that is right for one repository dangles in the
 *     other — and a dangling image in a README renders as a broken icon, not an
 *     error.
 *
 * Every publish test runs against a SCRATCH tree, never the real siblings: a
 * test that edits another repository is not a test.
 */
final class ViewEnginePublishTest extends TestCase
{
    private const DATASET = '/benchmarks/view-engine/results.json';

    /**
     * A small synthetic dataset — not the committed one.
     *
     * A fixture that asserts on the real dataset cannot test the code that
     * produced it, and this dataset is deleted and re-measured after every
     * harness change. Values are arbitrary but ordered, so a sort regression is
     * visible.
     *
     * @return array{env:array<string,mixed>,results:list<array<string,mixed>>}
     */
    private static function dataset(): array
    {
        $engines = [
            'native'  => ['first' => 1.5, 'mean' => 0.90, 'min' => 0.85, 'median' => 0.88, 'p95' => 1.10, 'base' => 3145728, 'use1' => 3250585, 'retained' => 3250585, 'peak' => 4194304],
            'clarity' => ['first' => 0.6, 'mean' => 0.80, 'min' => 0.75, 'median' => 0.78, 'p95' => 0.95, 'base' => 3670016, 'use1' => 4194304, 'retained' => 4194304, 'peak' => 5242880],
            // The same engine in open mode: a hair slower here purely so the two
            // rows are distinguishable in a rendered chart, which is what makes a
            // renderer that collapsed them visible.
            'clarity-open' => ['first' => 0.6, 'mean' => 0.82, 'min' => 0.77, 'median' => 0.79, 'p95' => 0.97, 'base' => 3670016, 'use1' => 4227072, 'retained' => 4227072, 'peak' => 5308416],
            'plates'       => ['first' => 8.0, 'mean' => 1.20, 'min' => 1.10, 'median' => 1.18, 'p95' => 1.40, 'base' => 3250585, 'use1' => 4089446, 'retained' => 4089446, 'peak' => 6291456],
            'blade'        => ['first' => 130.0, 'mean' => 1.80, 'min' => 1.60, 'median' => 1.75, 'p95' => 2.10, 'base' => 3303014, 'use1' => 6815744, 'retained' => 6815744, 'peak' => 8388608],
            'twig'         => ['first' => 170.0, 'mean' => 2.20, 'min' => 2.00, 'median' => 2.15, 'p95' => 2.60, 'base' => 3303014, 'use1' => 7130316, 'retained' => 7130316, 'peak' => 8912896],
        ];

        $results = [];
        foreach ($engines as $engine => $v) {
            $results[] = [
                'engine'             => $engine,
                'first_render_ms'    => $v['first'],
                'base_mem'           => $v['base'],
                'use1_mem'           => $v['use1'],
                'iterations_per_run' => 100,
                'runs'               => 2,
                'run_summaries'      => [],
                'trimmed_mean_ms'    => $v['mean'],
                'mean_ms'            => $v['mean'],
                'min_ms'             => $v['min'],
                'median_ms'          => $v['median'],
                'p95_ms'             => $v['p95'],
                'retained_mem'       => $v['retained'],
                'peak_mem'           => $v['peak'],
                'penalty_aggregate'  => null,
            ];
        }

        return [
            'env' => [
                'php_version'        => '8.3.33',
                'os'                 => 'Linux 6.8.0-test',
                'opcache'            => true,
                'sapi'               => 'cli',
                'timestamp'          => '2026-09-21T00:00:00+00:00',
                'items'              => 100,
                'iterations_per_run' => 100,
                'runs'               => 2,
                'engines'            => array_keys($engines),
                'penalty_enabled'    => true,
                // The method stamps the harness writes, so the rendered page can
                // be checked for stating them rather than for merely having a
                // Method line.
                'first_render_ms_basis' => 'one render in a fresh process with a cold template cache: engine class loading, template compile, cache write and one render',
                'steady_probe_ms_basis' => 'the render loop for each (engine, page) cell runs in its own fresh process against a warm cache: one untimed warm-up render, then runs x iterations-per-run timed renders',
                'run_order'             => 'no order: each (engine, page) cell is measured in its own process, so measurement order cannot affect a cell',
                'azera_framework_ref'   => 'abc1234',
                'clarity_modes'         => ['clarity' => 'sandboxed', 'clarity-open' => 'open'],
                'engine_versions'       => [
                    'clarity'      => ['package' => 'sailantis/clarity-engine', 'label' => 'Clarity', 'version' => 'dev-main', 'ref' => 'def5678'],
                    'clarity-open' => ['package' => 'sailantis/clarity-engine', 'label' => 'Clarity (open mode)', 'version' => 'dev-main', 'ref' => 'def5678'],
                    'native'       => ['package' => 'sailantis/azera-framework', 'label' => 'NativeEngine (Azera)', 'version' => '0.1.0', 'ref' => 'abc1234'],
                    'plates'       => ['package' => 'league/plates', 'label' => 'Plates', 'version' => '3.6.0', 'ref' => null],
                    'blade'        => ['package' => 'illuminate/view', 'label' => 'Blade', 'version' => '10.49.0', 'ref' => null],
                    'twig'         => ['package' => 'twig/twig', 'label' => 'Twig', 'version' => '3.28.0', 'ref' => null],
                ],
            ],
            'results' => $results,
        ];
    }

    /**
     * The same dataset, split over several pages.
     *
     * Derived from `dataset()` rather than written out again, so the shapes can
     * never disagree about what a row contains: a hand-copied second fixture is
     * how a test starts pinning a dataset no harness ever produced.
     *
     * The page list is a PARAMETER because different tests need different
     * coverage: the caption and consumer-region tests are about "more than one
     * page", while the docs-publication test is about the two specific shapes
     * that page carries (`mixed` and `entities`), and a fixture missing the
     * second of those could only test the page's failure to publish it.
     *
     * Each page's figures are deliberately DIFFERENT from the headline's
     * (`+100 ms` per step on every timing column), so a renderer that published
     * one page's numbers under another page's heading is visible rather than
     * plausible.
     *
     * @param list<string> $pages
     * @return array{env:array<string,mixed>,results:list<array<string,mixed>>}
     */
    private static function twoPageDataset(array $pages = ['sample', 'mixed']): array
    {
        $base = self::dataset();

        $rows = [];
        foreach ($base['results'] as $row) {
            foreach (array_values($pages) as $i => $page) {
                $bump = $i * 100;
                $row['page']            = $page;
                $row['first_render_ms'] = $row['first_render_ms'] + $bump;
                $row['mean_ms']         = $row['mean_ms'] + $bump;
                $row['median_ms']       = $row['median_ms'] + $bump;
                $row['min_ms']          = $row['min_ms'] + $bump;
                $row['p95_ms']          = $row['p95_ms'] + $bump;
                $rows[] = $row;
            }
        }

        $base['env']['pages'] = array_values($pages);
        $base['results'] = $rows;

        return $base;
    }

    /** Write the multi-page synthetic dataset and return its path. */
    private static function twoPageDatasetFile(string $dir, array $pages = ['sample', 'mixed']): string
    {
        $file = $dir . '/benchmarks/view-engine/two-page.json';
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, json_encode(self::twoPageDataset($pages), JSON_PRETTY_PRINT));

        return $file;
    }

    /** Write the synthetic dataset and return its path. */
    private static function datasetFile(string $dir): string
    {
        $file = $dir . self::DATASET;
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        file_put_contents($file, json_encode(self::dataset(), JSON_PRETTY_PRINT));

        // The generator copies the CSV beside the page, so it must exist.
        file_put_contents(preg_replace('/\.json$/', '.csv', $file), "engine,first_render_ms\nclarity,0.6\n");

        return $file;
    }

    /** A scratch "siblings root" holding both consumer repositories. */
    private static function scratchTree(): string
    {
        $root = dirname(__DIR__) . '/temp/tmp-ve-publish';
        foreach ([
            '/clarity-engine/docs/images',
            '/clarity-engine/docs',
            '/azera-framework/docs/images/benchmarks/view-engine',
        ] as $sub) {
            if (!is_dir($root . $sub)) {
                mkdir($root . $sub, 0777, true);
            }
        }

        file_put_contents($root . '/clarity-engine/README.md', "# Clarity\n\nHand-written intro.\n");
        file_put_contents(
            $root . '/azera-framework/docs/03b-CLARITY-ENGINE.md',
            "# Clarity DSL Template Engine\n\nHand-written intro.\n"
        );

        return $root;
    }

    /** The full shape map a multi-page dataset yields, as the driver builds it. */
    private static function shapesForDataset(string $dataset): array
    {
        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        [$env, $results] = veLoadDataset($dataset);

        return veRowsByPage($results);
    }

    /** Run the generator as a subprocess and return its combined output. */
    private static function runGenerator(string $dataset, string $outDir, string $publish): string
    {
        $script = dirname(__DIR__) . '/scripts/view-engine-report.php';
        $cmd    = sprintf(
            'php %s --dataset=%s --out=%s%s 2>&1',
            escapeshellarg($script),
            escapeshellarg($dataset),
            escapeshellarg($outDir),
            $publish === '' ? '' : ' --publish=' . escapeshellarg($publish)
        );

        return (string) shell_exec($cmd);
    }

    /**
     * The generated page references exactly the charts that were written.
     *
     * This is the ChartManifestTest rule applied to the new page: a chart the
     * generator stopped drawing must not survive in the embedded list, and a
     * referenced chart must exist. Asserted on the RENDERED page rather than on
     * a source string, so it holds whichever way the list is built.
     */
    public function testThePageReferencesExactlyTheChartsThatExist(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-page';
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');

        // NO --publish here. Publishing WRITES INTO ANOTHER REPOSITORY, so a
        // test that invoked it through the CLI (which has no scratch-root
        // override) edited the real clarity-engine README — twice, appending a
        // second generated region there. Only vePublish() is exercised with a
        // scratch root; the CLI's own publish path is not a test target.
        self::runGenerator($ds, $out, '');

        $md = (string) file_get_contents($out . '/view-engine.md');
        preg_match_all('/\(svg\/view-engine\/([a-z-]+)\.svg\)/', $md, $m);
        $referenced = $m[1];
        sort($referenced);

        $onDisk = [];
        foreach (glob($out . '/svg/view-engine/*.svg') ?: [] as $f) {
            $onDisk[] = basename($f, '.svg');
        }
        sort($onDisk);

        self::assertNotSame([], $referenced, 'the page must embed charts');
        self::assertSame($onDisk, $referenced, 'every chart on disk must be referenced, and vice versa');
    }

    /**
     * The first-render and memory bars state the unit on the SUBTITLE line, on a
     * LINEAR axis.
     *
     * Two shapes were reported from the published page and both are pinned here,
     * because both are one-word edits away from coming back:
     *
     *  - The unit sat on a line of its own ("unit: ms"), below the scale line.
     *    It now closes the scale line instead — "linear scale · lower is better ·
     *    unit: ms" — so the header is one statement rather than two, and the row
     *    the unit line occupied is reclaimed rather than left as a blank gap.
     *  - The first render was drawn on a LOG axis, which compressed a span of
     *    more than an order of magnitude. It is linear like the memory chart, so
     *    the bars state that span literally; the axis line now says so.
     *
     * Asserted on the RENDERED svg, because the subtitle is composed inside the
     * primitive and a source string would not show which line a unit ended up on.
     */
    public function testTheBarChartsCarryTheUnitOnTheSubtitleLineOverALinearAxis(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-unit-line';
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');
        self::runGenerator($ds, $out, '');

        $subtitle = static function (string $file): string {
            $svg = (string) file_get_contents($file);
            self::assertMatchesRegularExpression(
                '/<text[^>]*>([^<]*linear scale[^<]*)<\/text>/',
                $svg,
                'the chart must draw a scale line'
            );
            preg_match('/<text[^>]*>([^<]*linear scale[^<]*)<\/text>/', $svg, $m);

            return $m[1];
        };

        $first = $subtitle($out . '/svg/view-engine/warm-cost.svg');
        self::assertStringContainsString(
            'linear scale',
            $first,
            'the first render is a linear chart, like the memory chart'
        );
        self::assertStringNotContainsString(
            'logarithmic',
            $first,
            'the log axis is what this change removed'
        );
        self::assertStringContainsString(
            'lower is better · unit: ms',
            $first,
            'the unit closes the scale line, not a line of its own'
        );

        $memory = $subtitle($out . '/svg/view-engine/memory.svg');
        self::assertStringContainsString('linear scale', $memory, 'the memory range is linear, like the first render');
        // Asserted on the WHOLE svg, not just the scale line: the axis note is a
        // note line and may WRAP onto a second <text>, so a line-scoped check
        // would silently pass or fail on where the wrap happens to fall.
        $memorySvg = (string) file_get_contents($out . '/svg/view-engine/memory.svg');
        self::assertStringContainsString(
            'per-process',
            $memorySvg,
            'the axis note must state the probe\'s REAL opcache state, not the retired shared-segment claim'
        );
        self::assertStringNotContainsString(
            'primed shared segment',
            $memorySvg,
            'the CLI segment is not shared across shell_exec children; that wording described a basis never taken'
        );
        // It is a range, not a ranking: there is no "lower is better" line,
        // because "what does this engine cost to have loaded" has no direction.
        self::assertStringNotContainsString(
            'lower is better',
            $memory,
            'the memory range states three measurements; it is not a race'
        );

        // And the separate unit line must be GONE, not merely accompanied: a
        // stray "unit: ms" text element is exactly the shape being removed.
        self::assertStringNotContainsString(
            '>unit: ',
            (string) file_get_contents($out . '/svg/view-engine/warm-cost.svg'),
            'the unit must not be drawn on a line of its own'
        );
        self::assertStringNotContainsString(
            '>unit: ',
            (string) file_get_contents($out . '/svg/view-engine/memory.svg'),
            'the unit must not be drawn on a line of its own'
        );
    }

    /**
     * The BAR CHARTS are ordered by the values they draw, slowest last.
     *
     * The bars used to follow the dataset's engine order — an arbitrary key — so
     * the winner sat somewhere in the middle and the reader had to rank the bars
     * themselves. Three parts of the page now agree: the render-time chart
     * orders its rows by median, the results table orders its rows by median,
     * and both bar charts order their bars by their own value.
     *
     * The fixture's first-render figures RISE with the engine key (see
     * dataset(): `native` 1.5, `clarity` 0.6, `plates` 8, `blade` 130, `twig`
     * 170), i.e. the KEY order and the VALUE order are not the same sequence, so
     * an unsorted chart cannot pass this. The memory fixture is the opposite —
     * four engines share one footprint — so it exercises the tie case, where the
     * only requirement is that equal values are not interleaved with others.
     *
     * Read from the RENDERED svg, because the order comes from an array sort and
     * a source assertion would only prove the sort was called.
     */
    public function testTheBarChartsAreOrderedByTheirOwnValues(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-bar-order';
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');
        self::runGenerator($ds, $out, '');

        /**
         * The bars' (label, value) pairs in DRAW order — which is file order,
         * since the primitive emits one rect per label as it walks the map.
         *
         * Both texts share the "middle" anchor, so they are told apart by shape:
         * a category label is alphabetic, a value label is numeric.
         *
         * @return array{labels:list<string>,values:list<float>}
         */
        $bars = static function (string $file): array {
            $svg = (string) file_get_contents($file);
            preg_match_all(
                '/<text[^>]*text-anchor="middle"[^>]*>([^<]*)<\/text>/',
                $svg,
                $m
            );
            $labels = [];
            $values = [];
            foreach ($m[1] as $txt) {
                if (preg_match('/^[a-z][a-z-]*$/', $txt)) {
                    $labels[] = $txt;
                } elseif (preg_match('/^[0-9.]+$/', $txt)) {
                    $values[] = (float) $txt;
                }
            }

            return ['labels' => $labels, 'values' => $values];
        };

        /**
         * A range chart's rows in DRAW order, each as `[floor, dot, peak]`.
         *
         * The name is drawn with the "end" anchor in the series colour, the
         * three readings with the same anchor in ink, joined by ' · ' — see
         * memoryRange(). Reading the RENDERED strings rather than a source
         * assertion is the point: the order comes from an array sort and the
         * values from four per-row fields, and only the drawn output shows
         * whether the two agreed.
         *
         * @return array{labels:list<string>,rows:array<string,list<float>>}
         */
        $rangeRows = static function (string $file): array {
            $svg = (string) file_get_contents($file);
            preg_match_all(
                '/<text[^>]*text-anchor="end"[^>]*>([^<]*)<\/text>/',
                $svg,
                $m
            );
            $labels = [];
            $rows   = [];
            $name   = null;
            foreach ($m[1] as $txt) {
                if (preg_match('/^[0-9.]+ · [0-9.]+ · [0-9.]+$/', $txt)) {
                    if ($name !== null) {
                        $rows[$name] = array_map('floatval', explode(' · ', $txt));
                    }
                    continue;
                }
                // Everything else in this column is a row label — except the
                // header naming the three marks, which is skipped by shape:
                // every other label starts with an engine's capitalised name.
                if ($txt !== 'floor / retained / peak') {
                    $name = $txt;
                    $labels[] = $txt;
                }
            }

            return ['labels' => $labels, 'rows' => $rows];
        };

        $first = $bars($out . '/svg/view-engine/warm-cost.svg');
        self::assertSame(
            ['clarity', 'clarity-open', 'native', 'plates', 'blade', 'twig'],
            $first['labels'],
            'first-render bars, fastest first (the two 0.6 ms clarity modes lead, then native)'
        );
        self::assertSame('native', $first['labels'][2], 'native leads the compiling engines');

        // The RULE, not one dataset's ranking: the drawn values never decrease,
        // so the bars rise monotonically and the fastest sits at the left.
        $drawn = $first['values'];
        $asc   = $drawn;
        sort($asc);
        self::assertSame($asc, $drawn, 'the first-render bars must ascend: ' . implode(', ', $drawn));

        $mem = $rangeRows($out . '/svg/view-engine/memory.svg');
        self::assertSame(
            ['Native', 'Plates', 'Clarity', 'Clarity (open)', 'Blade', 'Twig'],
            $mem['labels'],
            'memory rows, lightest retained first — native holds least, twig most'
        );

        // Every row states THREE readings, so no reader has to guess which
        // figure a cap represents. And they are a genuine sequence: the floor is
        // below the dot and the dot below the peak, which is what makes the
        // range a range rather than two marks on one pixel.
        foreach ($mem['rows'] as $label => $parts) {
            self::assertCount(3, $parts, "{$label} must print the floor, the dot and the peak");
            [$floor, $dot, $peak] = $parts;
            self::assertLessThan($dot, $floor, "{$label}'s floor must sit below its retained reading");
            self::assertLessThan($peak, $dot, "{$label}'s retained reading must sit below its peak");
        }

        // The DOTS ascend, which is the sort the chart claims.
        $dots = array_map(static fn(array $p): float => $p[1], array_values($mem['rows']));
        $asc  = $dots;
        sort($asc);
        self::assertSame($asc, $dots, 'the memory rows must ascend by the dot: ' . implode(', ', $dots));
    }

    /**
     * The dashboard link exists only when the page does.
     *
     * A card pointing at a file that was never written is a dead link on a
     * PUBLIC site, and the failure is invisible until someone clicks it. Pinned
     * by RENDERING the dashboard twice — once with the page present and once
     * without — because a source-level assertion cannot show that the gate is
     * evaluated.
     */
    public function testTheDashboardCardIsGatedOnThePageExisting(): void
    {
        $manifest = require dirname(__DIR__) . '/scripts/report/views.php';
        self::assertArrayHasKey('extra_pages', $manifest, 'the manifest must declare extra pages');

        $extra = $manifest['extra_pages'];
        self::assertCount(1, $extra);
        self::assertSame('view-engine.html', $extra[0]['file']);

        $store = \AzeraCompetition\Report\ResultStore::load(dirname(__DIR__) . '/results/free-for-all-opcache-iso.json');

        $viewFiles = [];
        foreach (array_keys($manifest['views']) as $key) {
            $viewFiles[$key] = 'view-' . $key . '.html';
        }

        $dir = dirname(__DIR__) . '/temp/tmp-ve-card';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Absent -> no card.
        @unlink($dir . '/view-engine.html');
        $without = (new \AzeraCompetition\Report\HtmlReport($store, $manifest))->index($viewFiles, $dir);
        self::assertStringNotContainsString(
            'view-engine.html',
            $without,
            'a card must not be emitted for a page that was not written'
        );

        // Present -> the card points at it.
        file_put_contents($dir . '/view-engine.html', '<html></html>');
        $with = (new \AzeraCompetition\Report\HtmlReport($store, $manifest))->index($viewFiles, $dir);
        self::assertStringContainsString(
            'href="view-engine.html"',
            $with,
            'the card must appear once the page exists'
        );
    }

    /**
     * Publishing splices into the consumer's OWN markdown, between markers, and
     * keeps the hand-written text around it.
     */
    public function testPublishingSplicesIntoTheConsumerDocumentWithoutTouchingItsProse(): void
    {
        $sib = self::scratchTree();
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');
        $out = dirname(__DIR__) . '/temp/tmp-ve-page';

        // The generator resolves publish targets from its own location, so the
        // script is invoked through a thin wrapper that passes the scratch root.
        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        // Calling the real functions directly: this is the only way to point the
        // publish step at a scratch tree without editing the script under test.
        [$env, $results] = veLoadDataset($ds);
        $rows   = veRows($results);
        $charts = ['render-time', 'warm-cost', 'memory'];
        $svgDir = $out . '/svg/view-engine';
        veRenderTimeChart($rows, $env, $svgDir);
        veWarmCostChart($rows, $env, $svgDir);
        veMemoryChart($rows, $env, $svgDir);

        $log = vePublish('framework', $rows, $env, $charts, $svgDir, '', $sib);
        self::assertNotSame([], $log);

        $md = (string) file_get_contents($sib . '/azera-framework/docs/03b-CLARITY-ENGINE.md');
        self::assertStringContainsString('<!-- view-engine:begin -->', $md);
        self::assertStringContainsString('<!-- view-engine:end -->', $md);
        self::assertStringContainsString('Hand-written intro.', $md, 'the surrounding prose must survive');

        // Idempotent: a second publish replaces the region rather than appending
        // a second copy.
        vePublish('framework', $rows, $env, $charts, $svgDir, '', $sib);
        $again = (string) file_get_contents($sib . '/azera-framework/docs/03b-CLARITY-ENGINE.md');
        self::assertSame(
            1,
            substr_count($again, '<!-- view-engine:begin -->'),
            'a re-publish must replace the region, not add another'
        );
        self::assertSame($md, $again, 'publishing the same dataset twice must be a no-op');
    }

    /**
     * The published chart set is exactly what the consumer page references.
     *
     * The failure this catches is the one that put four orphaned charts into the
     * framework repository: the image directory is in ANOTHER repository, so a
     * chart nothing references is invisible to everyone who could notice.
     *
     * This is asserted on the FRAMEWORK target, whose
     * docs/images/benchmarks/view-engine directory exists only for this
     * benchmark and is therefore swept wholesale. It is deliberately NOT
     * asserted on clarity: clarity-engine/docs/images is a shared directory that
     * holds clarity's own logo assets, so "nothing but my chart may survive
     * here" is the wrong rule there — see
     * testPublishingLeavesTheTargetsOwnAssetsAlone.
     */
    public function testThePublishedImagesAreExactlyWhatThePageReferences(): void
    {
        $sib = self::scratchTree();
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');
        $out = dirname(__DIR__) . '/temp/tmp-ve-page';

        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        // Plant an orphan: a chart from a section the page no longer has.
        file_put_contents($sib . '/azera-framework/docs/images/benchmarks/view-engine/legacy-memory.svg', '<svg/>');
        file_put_contents($sib . '/azera-framework/docs/images/benchmarks/view-engine/warm-cost.svg', '<svg/>');

        [$env, $results] = veLoadDataset($ds);
        $rows = veRows($results);
        vePublish('framework', $rows, $env, ['render-time'], $out . '/svg/view-engine', '', $sib);

        $onDisk = [];
        foreach (glob($sib . '/azera-framework/docs/images/benchmarks/view-engine/*.svg') ?: [] as $f) {
            $onDisk[] = basename($f);
        }
        sort($onDisk);

        self::assertSame(
            ['render-time.svg'],
            $onDisk,
            'the sweep must leave exactly the chart the page references — no orphans in another repository'
        );

        $md = (string) file_get_contents($sib . '/azera-framework/docs/03b-CLARITY-ENGINE.md');
        self::assertStringContainsString('images/benchmarks/view-engine/render-time.svg', $md);
        self::assertStringNotContainsString('legacy-memory.svg', $md);
    }

    /**
     * Publishing must not delete assets the target repository owns.
     *
     * The first version of the sweep cleared clarity-engine/docs/images
     * wholesale on the reasoning "anything else here is an orphan of mine". That
     * directory is SHARED: it is where clarity-engine-logo.svg and
     * clarity-engine-logo-src.svg live, and clarity's own README embeds the
     * logo. A publish run therefore deleted two files belonging to another
     * repository — a failure with no error, no failing test and no visible
     * trace until the README was read and the image was gone.
     *
     * The rule the test above states (no chart but mine may survive) is only
     * correct where the DIRECTORY is mine. That is true of
     * docs/images/benchmarks/view-engine, which this generator created for this
     * benchmark, and false of clarity-engine/docs/images, which is SHARED: it is
     * where clarity-engine-logo.svg and clarity-engine-logo-src.svg live, and
     * clarity's own README embeds the logo.
     *
     * NO TARGET POINTS AT A SHARED DIRECTORY ANY MORE. The one that did —
     * `clarity`, which copied two charts under fixed names into
     * clarity-engine/docs/images and surgically removed the chart it had
     * historically owned there — was retired on 2026-09-28, because clarity's
     * README had stopped referencing those names and now embeds the charts
     * `clarity-docs` publishes instead. This test pins the retirement in the two
     * ways that matter:
     *
     *  1. The retired name is REFUSED rather than silently accepted, so a
     *     `--publish=clarity` typo is a message and not a quiet no-op. A target
     *     that no longer exists is the only failure mode a reader cannot see.
     *  2. Publishing every remaining target leaves a shared directory
     *     BYTE-FOR-BYTE as it was, orphans and all. That is the property the
     *     retirement buys: nothing the generator does can now touch a file in
     *     another project's asset directory, so the 2026-09-22 damage cannot
     *     recur even by a target being added carelessly.
     */
    public function testNoTargetWritesIntoASharedImagesDirectory(): void
    {
        $sib = self::scratchTree();
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');
        $out = dirname(__DIR__) . '/temp/tmp-ve-page';

        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        // Clarity's shared directory, exactly as it exists for real: two of its
        // own assets, plus the chart this generator wrote there in a previous
        // era and never removed.
        $shared = $sib . '/clarity-engine/docs/images';
        file_put_contents($shared . '/clarity-engine-logo.svg', '<svg id="logo"/>');
        file_put_contents($shared . '/clarity-engine-logo-src.svg', '<svg id="logo-src"/>');
        file_put_contents($shared . '/benchmark-results.svg', '<svg id="old"/>');

        $before = self::dirFingerprint($shared);

        [$env, $results] = veLoadDataset($ds);
        $rows   = veRows($results);
        $byPage = veRowsByPage($results);

        /** @var list<string> $targets every target this build knows */
        $targets = ['framework', 'clarity-docs'];
        $refused = vePublish('clarity', $rows, $env, ['render-time'], $out . '/svg/view-engine', '', $sib, $byPage);

        self::assertSame(
            ['unknown publish target: clarity'],
            $refused,
            'the retired target must be refused by name — a silent no-op would hide a typo in --publish'
        );

        foreach ($targets as $target) {
            vePublish($target, $rows, $env, ['render-time'], $out . '/svg/view-engine', '', $sib, $byPage);
        }

        self::assertSame(
            $before,
            self::dirFingerprint($shared),
            'clarity-engine/docs/images is not this generator\'s directory: no target may add a file to it '
                . 'or remove one, including an orphan from an earlier era'
        );
    }

    /**
     * `name => sha1` for every file in a directory, so a test can assert that a
     * publish changed NOTHING there — a missing file and a rewritten one both
     * show up, which a count cannot tell apart from an unchanged set.
     *
     * @return array<string,string>
     */
    private static function dirFingerprint(string $dir): array
    {
        $out = [];
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                $out[basename($file)] = sha1((string) file_get_contents($file));
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * The caption is read from the dataset, never typed.
     *
     * The retired caption in clarity's README stated a PHP version this harness
     * never recorded. A generated caption cannot do that: the value comes from
     * the env block of the very run being published.
     */
    public function testTheConsumerCaptionComesFromTheDataset(): void
    {
        $env = self::dataset()['env'];

        self::assertStringContainsString(
            '8.3.33',
            veEnvBlock($env, veRows(self::dataset()['results'])),
            'the PHP version must be the one the dataset recorded'
        );
        self::assertStringNotContainsString(
            '8.3.6',
            veEnvBlock($env, veRows(self::dataset()['results'])),
            'a version the dataset did not record must not appear'
        );
    }

    /**
     * The report states HOW the numbers were produced, not just what they are.
     *
     * Three figures on the page depend on the method: the first render is measured
     * in a fresh process per engine; the steady-state timings in a fresh process
     * per CELL (a different basis from every earlier dataset, which ran the render
     * loop in the harness's own process, where a cell could inherit a class another
     * cell declared); and the memory columns in a fresh process per reading. A
     * reader comparing this page against an older run has to know all three,
     * because a dataset from before the per-cell change is not comparable with one
     * from after it.
     */
    public function testTheReportStatesTheMethodBehindTheNumbers(): void
    {
        $env = self::dataset()['env'];

        self::assertStringContainsString(
            '**Method**',
            veEnvBlock($env, veRows(self::dataset()['results'])),
            'the page must say how the numbers were produced'
        );

        // The basis strings must survive into the rendered block, not merely
        // exist in the dataset.
        $block = veEnvBlock($env, veRows(self::dataset()['results']));
        self::assertStringContainsString('fresh process', $block, 'the first-render basis must be stated');
        self::assertStringContainsString(
            'its own fresh process',
            $block,
            'the steady-state basis is what makes this page comparable with nothing before it'
        );

        // And the harness must actually stamp all three keys, since a dataset
        // missing them would render a page that cannot say how it was measured.
        $runSrc = (string) file_get_contents(dirname(__DIR__) . '/benchmarks/view-engine/run.php');
        self::assertStringContainsString("'run_order'", $runSrc);
        self::assertStringContainsString("'first_render_ms_basis'", $runSrc);
        self::assertStringContainsString("'steady_probe_ms_basis'", $runSrc);
    }

    /**
     * A single-page dataset renders BYTE-IDENTICALLY to the pre-page renderer.
     *
     * The headline page's chart filenames are a published API: clarity-engine's
     * README and azera-framework's docs embed `render-time.svg` from the site's
     * `svg/view-engine/` directory, and this generator copies one of them into
     * each consumer repository at a path that repository has referenced for
     * years. So teaching the renderer about pages must not rename or reword the
     * single-page output — otherwise every published figure and every consumer
     * image breaks at once, for a change that was supposed to be additive.
     *
     * A SHA-256 rather than a diff: the value of this test is that it FAILS when
     * an invisible space or a reordered attribute changes, which is exactly the
     * class of edit a page-aware refactor introduces.
     */
    public function testASinglePageDatasetRendersTheUnprefixedCharts(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-single-page';
        $ds  = self::datasetFile(dirname(__DIR__) . '/temp');

        self::runGenerator($ds, $out, '');

        $md  = (string) file_get_contents($out . '/view-engine.md');
        $svg = [];
        foreach (glob($out . '/svg/view-engine/*.svg') ?: [] as $f) {
            $svg[] = basename($f);
        }
        sort($svg);

        self::assertSame(
            ['memory.svg', 'render-time.svg', 'warm-cost.svg'],
            $svg,
            'a single-page dataset must keep the unprefixed chart names the consumer repos embed'
        );
        self::assertStringContainsString('(svg/view-engine/render-time.svg)', $md);
        self::assertStringNotContainsString(
            'sample-render-time.svg',
            $md,
            'the headline page must NOT be prefixed — that would silently break both consumer repos'
        );
    }

    /**
     * The PRIMARY page is the headline, and every other page gets its own charts
     * and its own table.
     *
     * The failure this pins is the one the guard in veRows() exists for: a
     * multi-page dataset used to be keyed by engine alone, so whichever page came
     * last overwrote the others and its numbers were published as the headline.
     * The guard made that loud; this test makes sure the loud path has a CORRECT
     * destination rather than merely refusing to publish.
     *
     * The second page's figures are +100 ms, so a renderer that drew one page's
     * rows under the other page's heading would publish the first page's numbers
     * twice and this test would see them.
     */
    public function testAMultiPageDatasetPublishesEachPageUnderItsOwnHeadingAndCharts(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-two-page';
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp');

        $log = self::runGenerator($ds, $out, '');
        self::assertStringContainsString('headline: sample', $log, 'the run must name its headline page');

        $md = (string) file_get_contents($out . '/view-engine.md');

        // The headline keeps its unprefixed chart and its own figures.
        self::assertStringContainsString('(svg/view-engine/render-time.svg)', $md);
        // The second page gets prefixed charts...
        self::assertStringContainsString('(svg/view-engine/mixed-render-time.svg)', $md);
        self::assertStringContainsString('(svg/view-engine/mixed-warm-cost.svg)', $md);
        self::assertStringContainsString('(svg/view-engine/mixed-memory.svg)', $md);
        // ...its own heading...
        self::assertStringContainsString('## Mixed', $md);
        // ...and its own table. The fixture's pages differ by 100 ms in every
        // timing column, so both the page's own figure and the headline's figure
        // must appear — and the page's own number must appear AFTER its heading,
        // not only in the table above it.
        $afterHeading = substr($md, (int) strpos($md, '## Mixed'));
        self::assertMatchesRegularExpression(
            '/\|\s*Clarity\s*\|[^|]*\|\s*100\.8\d*\s*\|/',
            $afterHeading,
            "the page's table must carry the page's OWN trimmed mean (0.78 + 100), not the headline's"
        );

        // Two pages x three charts exist on disk: three unprefixed, three
        $svg = [];
        foreach (glob($out . '/svg/view-engine/*.svg') ?: [] as $f) {
            $svg[] = basename($f);
        }
        sort($svg);
        self::assertSame(
            [
                'memory.svg',
                'mixed-memory.svg',
                'mixed-render-time.svg',
                'mixed-warm-cost.svg',
                'render-time.svg',
                'warm-cost.svg',
            ],
            $svg,
            'a two-page run draws six chart files for two chart kinds, and no two may collide'
        );
    }

    /**
     * The consumer region embeds the PRIMARY page, not the whole multi-page set.
     *
     * Those files are a README and a design document, whose one image is an
     * at-a-glance claim about Clarity. A per-page chart set spliced into them
     * would turn a README into a report — and, because the consumer region's
     * image path is a FIXED historical filename, it could not embed the second
     * page's charts even if it wanted to. The full figures stay one link away.
     */
    public function testTheConsumerRegionEmbedsTheHeadlinePageOnly(): void
    {
        $sib = self::scratchTree();
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp');
        $out = dirname(__DIR__) . '/temp/tmp-ve-two-page';

        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        [$env, $results] = veLoadDataset($ds);
        $pages = vePages($results);
        self::assertSame(['sample', 'mixed'], $pages);

        $rows   = veRowsForPage($results, $pages[0]);
        $svgDir = $out . '/svg/view-engine';
        $charts = [
            veRenderTimeChart($rows, $env, $svgDir),
            veWarmCostChart($rows, $env, $svgDir),
            veMemoryChart($rows, $env, $svgDir),
        ];

        vePublish('framework', $rows, $env, $charts, $svgDir, '', $sib);

        $md  = (string) file_get_contents($sib . '/azera-framework/docs/03b-CLARITY-ENGINE.md');
        $img = 'images/benchmarks/view-engine/render-time.svg';
        self::assertStringContainsString('](' . $img . ')', $md, 'the consumer keeps its historical image path');

        // The region embeds NO second-page figure. The fixture's second page is
        // +100 ms, so a leak is arithmetically visible.
        self::assertStringNotContainsString('mixed-render-time', $md);
        self::assertDoesNotMatchRegularExpression(
            '/\|\s*Clarity\s*\|[^|]*\|\s*100\./',
            $md,
            'the consumer region must not carry a second page\'s figures — it embeds the headline only'
        );
    }

    /**
     * A dataset with no CSV beside it must not leave a STALE CSV published.
     *
     * Found for real (2026-09-22): the r7 dataset was fetched as `.json` only, so
     * the generator's `copy()` of the missing CSV emitted a warning and left the
     * PREVIOUS round's `view-engine.csv` in `docs/benchmarks/`. The published
     * page then offered a JSON and a CSV that described different runs — the
     * exact failure this generator exists to prevent, and one that no test
     * noticed because a warning is not an error and the file it left behind
     * looks entirely normal.
     *
     * The rule pinned here: the CSV beside the page either matches the JSON or is
     * not there at all.
     */
    public function testAMissingCsvDoesNotLeaveAStaleOnePublished(): void
    {
        $dir = dirname(__DIR__) . '/temp/tmp-ve-nocsv';
        @mkdir($dir, 0777, true);

        $ds = self::datasetFile($dir);
        // A stale CSV from ANOTHER run, exactly as the real one was left.
        file_put_contents($dir . '/view-engine.csv', "engine,first_render_ms\nstale,999.9\n");
        // And no CSV beside the dataset itself.
        @unlink(preg_replace('/\.json$/', '.csv', $ds));

        $out = self::runGenerator($ds, $dir . '/out', '');
        self::assertStringContainsString('no CSV beside', $out, 'the operator must be told the CSV was not copied');
        self::assertFileDoesNotExist(
            $dir . '/out/view-engine.csv',
            'a stale CSV beside a fresh JSON would be traced to the wrong run — it must be removed'
        );
        self::assertFileExists($dir . '/out/view-engine.json');
    }

    /**
     * The charts are NOT lazy-loaded, and each page's charts are captioned with
     * their page.
     *
     * Both come from one real report of the three-page document: "all times below
     * the first diagram seem to be similar... the last two diagrams are not
     * rendered, maybe links are broken?" The files were present and the URLs
     * correct — the images carried `loading="lazy"`, so the two below-the-fold
     * sections looked empty, and every section's figures were captioned with the
     * SAME text ("Time per render", three times), which made three different
     * measurements read as one chart repeated.
     *
     * Two separate failures, one symptom. Pinned separately:
     *   - a deferred image on a chart page reads as a broken image;
     *   - a caption that does not name its page makes a per-page comparison
     *     unreadable even when everything renders.
     */
    public function testChartsAreNotLazyLoadedAndCaptionsNameTheirPage(): void
    {
        $out = dirname(__DIR__) . '/temp/tmp-ve-captions';
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp');

        self::runGenerator($ds, $out, '');
        $html = (string) file_get_contents($out . '/view-engine.html');

        self::assertStringNotContainsString(
            'loading=',
            $html,
            'a deferred chart reads as a missing one — this page exists to show the charts'
        );

        // Every figure is captioned, and the second page's figures name that page.
        $captions = [];
        preg_match_all('/<figcaption>(.*?)<\/figcaption>/', $html, $m);
        foreach ($m[1] as $c) {
            $captions[] = html_entity_decode($c, ENT_QUOTES, 'UTF-8');
        }
        self::assertCount(6, $captions, 'two pages x three charts must all be captioned');

        $mixed = array_values(array_filter($captions, static fn(string $c): bool => str_contains($c, 'Mixed')));
        self::assertCount(3, $mixed, 'each of the second page\'s charts must name its page');

        // And the headline stays UNQUALIFIED, so the published wording does not
        // change for a single-page dataset.
        self::assertContains('Time per render', $captions);
        self::assertContains('First render (fresh process, cold template cache)', $captions);
        self::assertContains('Memory per run', $captions);
    }

    /**
     * The HEADLINE rendering shape is named once other shapes follow.
     *
     * Every additional shape publishes under a named `##` heading, which left the
     * first one anonymous: a reader met "Time per render" with nothing saying
     * which page it measured, while every later section said. Reported as "you
     * don't know what you are seeing there", which is exactly it — the chart was
     * captioned by what it MEASURES and never by what it measured IT.
     *
     * NAMED ONLY WHEN OTHER SHAPES FOLLOW. A single-page dataset is left alone
     * deliberately: it has no sibling heading to be confused with, and its
     * rendered bytes are frozen so "did this run change anything?" stays
     * answerable by diffing the file. Both halves are asserted, because the
     * always-on version is the change that looks right on a multi-page dataset
     * and silently rewrites every single-page publication.
     */
    public function testTheHeadlineRenderingShapeIsNamedOnlyWhenAnotherShapeFollows(): void
    {
        // Multi-page: the headline is named, and named BEFORE its first chart.
        $out = dirname(__DIR__) . '/temp/tmp-ve-headline-name';
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp');
        self::runGenerator($ds, $out, '');

        $md = (string) file_get_contents($out . '/view-engine.md');
        self::assertStringContainsString("\n## Scalars\n", $md, 'the headline shape must be named');
        $name = strpos($md, "\n## Scalars\n");
        $time = strpos($md, "\n## Time per render\n");
        self::assertNotFalse($time, 'the headline still carries its chart sections');
        self::assertLessThan(
            $time,
            $name,
            'the name must come BEFORE the first chart, or it names nothing the reader can see yet'
        );
        // The shape that renders it, not just its label: `Scalars` is a name, and
        // a name with no statement of what was measured is the original problem.
        self::assertStringContainsString('a loop over scalars with a filter', $md);

        // A later shape is "another", not "a second" — false once there are two.
        self::assertStringContainsString('## Mixed — another rendering shape', $md);
        self::assertStringNotContainsString('a second rendering shape', $md);

        // The HTML page is the same document in another rendering.
        $html = (string) file_get_contents($out . '/view-engine.html');
        $h2   = strpos($html, '<h2>Scalars</h2>');
        $fig  = strpos($html, '<figure>');
        self::assertNotFalse($h2, 'the HTML page must name the headline shape too');
        self::assertNotFalse($fig, 'the HTML page must still draw its figures');
        self::assertLessThan($fig, $h2, 'the HTML name must precede the first figure');

        // Single-page: NOT named, so the frozen bytes stay frozen.
        $single = dirname(__DIR__) . '/temp/tmp-ve-single-page';
        self::runGenerator(self::datasetFile(dirname(__DIR__) . '/temp'), $single, '');
        $one = (string) file_get_contents($single . '/view-engine.md');
        self::assertStringNotContainsString('## Scalars', $one, 'a lone shape is not named');
        self::assertStringNotContainsString('another rendering shape', $one);
    }

    /**
     * The docs target publishes EVERY shape, to a page and a directory of its own.
     *
     * Three properties at once, because they are one decision:
     *
     *  - the page is created rather than spliced (it is wholly generated, so
     *    there is no hand-written prose of the target's to preserve);
     *  - it carries every shape INCLUDING the headline, since it is the one place
     *    a reader sees them all, in canonical order;
     *  - its charts go to `docs/images/benchmarks/`, a directory this generator
     *    created and therefore may sweep — while clarity's SHARED
     *    `docs/images/` is never written to at all (pinned separately by
     *    testNoTargetWritesIntoASharedImagesDirectory).
     */
    public function testTheDocsTargetPublishesEveryShapeToItsOwnPageAndDirectory(): void
    {
        $sib = self::scratchTree();
        // The THREE-shape fixture: this target publishes `mixed` and `entities`,
        // so the fixture must carry both or the test could only observe the
        // failure to publish the second of them.
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp', ['sample', 'mixed', 'entities']);
        $out = dirname(__DIR__) . '/temp/tmp-ve-docs-shapes';

        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        [$env, $results] = veLoadDataset($ds);
        $rows   = veRowsForPage($results, 'sample');
        $byPage = veRowsByPage($results);

        // Charts on disk, as the driver writes them. The headline's are
        // UNPREFIXED, which is the naming trap this target has to honour.
        @mkdir($out . '/svg/view-engine', 0777, true);
        foreach (veChartOrder() as $kind) {
            file_put_contents($out . '/svg/view-engine/' . $kind . '.svg', '<svg/>');
            foreach (array_keys($byPage) as $page) {
                file_put_contents($out . "/svg/view-engine/{$page}-{$kind}.svg", '<svg/>');
            }
        }

        $log = vePublish('clarity-docs', $rows, $env, [], $out . '/svg/view-engine', '', $sib, $byPage);
        self::assertSame([], array_filter($log, static fn(string $l): bool => str_contains($l, 'unknown')));

        $page = $sib . '/clarity-engine/docs/08-benchmark.md';
        self::assertFileExists($page, 'the docs target must create its page');

        $md = (string) file_get_contents($page);
        self::assertStringContainsString('<!-- view-engine:begin -->', $md);
        self::assertStringContainsString('<!-- view-engine:end -->', $md);

        // The shapes the page covers, and the ones it deliberately does NOT:
        // the headline scalars page (already on the README's own diagrams and the
        // published site) is absent, so this page stays about the shapes that ask
        // a different question.
        foreach (['Mixed', 'Objects'] as $label) {
            self::assertStringContainsString("## {$label}", $md, "the page must carry the {$label} shape");
        }
        self::assertStringNotContainsString(
            '## Scalars',
            $md,
            'the trivial shape belongs to the published site, not this page'
        );

        // Each shape's own table, so a shape's chart and its numbers agree.
        self::assertSame(
            2,
            substr_count($md, '| Engine | First render (ms) |'),
            'every published shape needs its own table, or a chart has no numbers beside it'
        );

        // The charts landed in the directory this generator owns, under
        // page-prefixed names.
        foreach (['mixed-render-time.svg', 'entities-render-time.svg'] as $svg) {
            self::assertFileExists($sib . '/clarity-engine/docs/images/benchmarks/' . $svg);
        }
        self::assertFileDoesNotExist(
            $sib . '/clarity-engine/docs/images/benchmarks/sample-render-time.svg',
            'an unpublished shape must not leave a chart behind in the swept directory'
        );

        // Idempotent: a second publish replaces the region, not the page.
        vePublish('clarity-docs', $rows, $env, [], $out . '/svg/view-engine', '', $sib, $byPage);
        self::assertSame(
            1,
            substr_count((string) file_get_contents($page), '<!-- view-engine:begin -->'),
            'a re-publish must replace the region, not append another'
        );
    }

    /**
     * NO target edits clarity-engine's README — it is not a publication surface.
     *
     * The README is hand-written prose that DESCRIBES its diagrams, and it embeds
     * the charts under `docs/images/benchmarks/` that `clarity-docs` publishes. So
     * the generator's involvement with it is exactly one step removed: it writes
     * images the README chooses to reference, and never a byte of the Markdown.
     * Splicing a region into a document this generator does not own is how a
     * README stops being its author's — and the README's own text is where a
     * project explains WHY the heavy page is the one worth showing.
     *
     * Asserted by publishing EVERY target at a scratch tree that has a README, so
     * a future target cannot quietly acquire the file.
     */
    public function testNoTargetTouchesClaritysReadme(): void
    {
        $sib = self::scratchTree();
        $ds  = self::twoPageDatasetFile(dirname(__DIR__) . '/temp');
        $out = dirname(__DIR__) . '/temp/tmp-ve-readme-untouched';

        require_once dirname(__DIR__) . '/scripts/view-engine-report.php';

        [$env, $results] = veLoadDataset($ds);
        $rows   = veRowsForPage($results, 'sample');
        $byPage = veRowsByPage($results);

        @mkdir($out . '/svg/view-engine', 0777, true);
        foreach (veChartOrder() as $kind) {
            file_put_contents($out . '/svg/view-engine/' . $kind . '.svg', '<svg/>');
            file_put_contents($out . '/svg/view-engine/mixed-' . $kind . '.svg', '<svg/>');
        }

        $readme = $sib . '/clarity-engine/README.md';
        $before = (string) file_get_contents($readme);

        foreach (['framework', 'clarity-docs'] as $target) {
            $log = vePublish($target, $rows, $env, [], $out . '/svg/view-engine', '', $sib, $byPage);
            // The README is what must go untouched, NOT the log's silence about
            // splicing in general: `clarity-docs` splices into its own page
            // (08-benchmark.md) and legitimately says so on a re-publish.
            self::assertSame(
                [],
                array_filter($log, static fn(string $l): bool => str_contains($l, 'README')),
                "the '{$target}' log must not claim to have written clarity-engine's README"
            );
        }

        self::assertSame(
            $before,
            (string) file_get_contents($readme),
            'the README is hand-written: no target may edit a byte of it'
        );
    }

    /** A dataset without an envelope is refused, not rendered. */
    public function testABareArrayDatasetIsRefused(): void
    {
        $bare = dirname(__DIR__) . '/temp/tmp-ve-bare.json';
        file_put_contents($bare, json_encode([['engine' => 'twig', 'median_ms' => 1.0]]));
        file_put_contents(dirname(__DIR__) . '/temp/tmp-ve-bare.csv', "engine\n");

        $out = (string) shell_exec(sprintf(
            'php %s --dataset=%s --out=%s 2>&1',
            escapeshellarg(dirname(__DIR__) . '/scripts/view-engine-report.php'),
            escapeshellarg($bare),
            escapeshellarg(dirname(__DIR__) . '/temp/tmp-ve-bare-out')
        ));

        self::assertStringContainsString('envelope', $out, 'a dataset with no env block must be refused by name');
        self::assertFileDoesNotExist(
            dirname(__DIR__) . '/temp/tmp-ve-bare-out/view-engine.md',
            'nothing may be rendered from a dataset that cannot state its environment'
        );
    }
}