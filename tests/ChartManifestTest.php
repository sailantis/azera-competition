<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * A view's HTML must reference exactly the charts its Markdown was given.
 *
 * The bug this pins (found 2026-09-17): docs/benchmarks/view-cold-start.html
 * embedded a "Peak memory footprint" <figure> pointing at
 * svg/cold-start/memory.svg, while cold-start.md had no such section at all —
 * the two halves of ONE view disagreed about what the view contains.
 *
 * The cause was report.php discovering charts by LISTING the output
 * directory instead of asking the renderer what it had written. The SVG
 * directory survives between runs, so a chart a view has stopped drawing
 * keeps sitting in it; listing picked it up again as if it were current.
 * svg/cold-start/memory.svg was exactly that — an orphan written by an older
 * render (707d495), resurrected into the HTML on every regeneration.
 *
 * The assertions below are structural rather than byte-comparisons against
 * committed output, so they keep holding as the datasets are re-measured:
 * whatever charts render() reports writing are the ones that may appear.
 */
final class ChartManifestTest extends TestCase
{
    private const DATASET = '/results/free-for-all-opcache-iso.json';

    /**
     * A measured figure as it would appear in a sentence.
     *
     * Two shapes: a decimal number, and an integer carrying a unit. The
     * trailing boundary is deliberately ABSENT — a word boundary cannot follow
     * `%` (both it and the space after it are non-word characters), so a
     * `\b`-terminated pattern silently missed every percentage. The mutation
     * harness found that: injecting "stays under 1%" into a published page
     * left this test green.
     */
    private const FIGURE = '/\b\d+\.\d+\b|\b\d+\s*(?:ms|MB|%)/';

    private static function dataset(): string
    {
        return dirname(__DIR__) . self::DATASET;
    }

    private static function scratch(string $name): string
    {
        $dir = dirname(__DIR__) . '/temp/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir;
    }

    /**
     * Render one view of the manifest into a scratch dir.
     *
     * @return array{md:string, charts:list<string>, dir:string}
     */
    private function renderView(string $viewKey, string $outName): array
    {
        $manifest = require dirname(__DIR__) . '/scripts/report/views.php';
        self::assertArrayHasKey($viewKey, $manifest['views'], "no view {$viewKey} in the manifest");
        $view = $manifest['views'][$viewKey];

        $store = ResultStore::load(self::dataset());
        $dir   = self::scratch($outName) . '/svg/' . $viewKey;

        $md   = new MarkdownReport($store, $viewKey, $view);
        $body = $md->render($dir, 'svg/' . $viewKey);

        return ['md' => $body, 'charts' => $md->writtenCharts(), 'dir' => $dir];
    }

    public function testWrittenChartsNamesEverySvgTheRendererCreated(): void
    {
        $r = $this->renderView('warm-start', 'tmp-chartman-warm');

        self::assertNotSame([], $r['charts'], 'warm-start must write charts');
        foreach ($r['charts'] as $chart) {
            self::assertFileExists(
                $r['dir'] . '/' . $chart . '.svg',
                "{$chart} was reported as written but is not on disk"
            );
        }
    }

    public function testWrittenChartsIsTheFunnelForEverySvgOnDisk(): void
    {
        $r = $this->renderView('warm-start', 'tmp-chartman-funnel');

        $onDisk = [];
        foreach (scandir($r['dir']) ?: [] as $f) {
            if (str_ends_with($f, '.svg')) {
                $onDisk[] = basename($f, '.svg');
            }
        }
        sort($onDisk);
        $reported = $r['charts'];
        sort($reported);

        // Equality both ways is the point: nothing on disk is un-reported
        // (which would mean a write bypassing writeSvg() and therefore able to
        // go stale unnoticed), and nothing reported is missing.
        self::assertSame($onDisk, $reported, 'writtenCharts() must match the SVGs actually produced');
    }

    /**
     * The regression itself: a stale SVG left in the view's directory must not
     * turn into a chart reference.
     *
     * This reproduces the original failure in the way that matters — the
     * orphan is planted BEFORE the render, exactly as it survives in a real
     * docs/benchmarks/svg/<view>/ tree between regenerations.
     */
    public function testStaleSvgInViewDirectoryIsNotReportedAsAChart(): void
    {
        $r = $this->renderView('cold-start', 'tmp-chartman-stale');

        // Plant the orphan the real tree carried: cold-start draws no memory
        // chart, so its name can only come from a stale file.
        $orphan = $r['dir'] . '/memory.svg';
        file_put_contents($orphan, '<svg xmlns="http://www.w3.org/2000/svg"><!-- orphan --></svg>');

        $r2 = $this->renderView('cold-start', 'tmp-chartman-stale');

        self::assertNotContains(
            'memory',
            $r2['charts'],
            'a stale memory.svg in the directory must not be reported as a chart of cold-start'
        );
        self::assertContains('startup', $r2['charts'], 'the real charts must still be reported');
    }

    public function testColdStartMarkdownAndHtmlAgreeAboutTheCharts(): void
    {
        $r = $this->renderView('cold-start', 'tmp-chartman-agree');

        self::assertStringNotContainsString(
            'memory.svg',
            $r['md'],
            'cold-start.md must not reference a memory chart it does not draw'
        );
        self::assertNotContains('memory', $r['charts']);
    }

    public function testRerenderDoesNotAccumulateChartKeys(): void
    {
        $manifest = require dirname(__DIR__) . '/scripts/report/views.php';
        $view     = $manifest['views']['warm-start'];
        $store    = ResultStore::load(self::dataset());
        $dir      = self::scratch('tmp-chartman-rerender') . '/svg/warm-start';

        $md = new MarkdownReport($store, 'warm-start', $view);
        $md->render($dir, 'svg/warm-start');
        $first = $md->writtenCharts();
        $md->render($dir, 'svg/warm-start');
        $second = $md->writtenCharts();

        self::assertNotSame([], $first);
        self::assertSame($first, $second, 'a second render() must not append to the previous chart list');
        self::assertSame(count($first), count(array_unique($second)), 'writtenCharts() must not contain duplicates');
    }

    /**
     * The two redundant views must not come back.
     *
     * 'memory' and 'relative' drew byte-identical charts and tables to
     * warm-start (verified by SHA256 before removal), so they were three pages
     * asserting the same numbers. A future edit that re-adds either key would
     * restore that duplication silently — nothing else in the suite would
     * notice, because both rendered successfully.
     */
    public function testTheRedundantViewsAreNotReintroduced(): void
    {
        $manifest = require dirname(__DIR__) . '/scripts/report/views.php';

        self::assertArrayNotHasKey('memory', $manifest['views'], 'the memory view duplicated warm-start');
        self::assertArrayNotHasKey('relative', $manifest['views'], 'the relative view duplicated warm-start');

        // The memory CHART is a different thing and must survive: warm-start
        // still draws it.
        self::assertContains('memory', $manifest['views']['warm-start']['charts']);
    }

    /**
     * The orchestration half of the fix, pinned at the source.
     *
     * The tests above prove the RENDERER knows which charts it wrote; that is
     * only useful if report.php asks it. Reverting the orchestrator to
     * directory listing would leave every assertion above passing while the
     * published HTML went back to embedding orphans, so the caller's choice is
     * asserted directly.
     */
    public function testReportOrchestratorAsksTheRendererInsteadOfListingTheDirectory(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/report.php');

        self::assertStringContainsString(
            '$md->writtenCharts()',
            $src,
            'report.php must take the chart list from the renderer'
        );
        self::assertStringNotContainsString(
            'scandir($svgDir)',
            $src,
            'report.php must not discover charts by listing the output dir — that resurrects orphans'
        );
    }

    /**
     * The report PROSE carries no measured figure.
     *
     * This is the report's central contract: a chart and a table are regenerated
     * from the dataset, so their numbers can never disagree with it. A SENTENCE
     * is not regenerated — nothing recomputes it — so a reading printed in prose
     * is a reading that silently outlives the dataset it came from. That is
     * exactly what happened here: the published pages claimed "Spiral takes
     * 7.88 ms — x 17.8 slower" while the chart beside them, drawn from the same
     * JSON, showed a different spread.
     *
     * The check is structural, not a string list: strip what legitimately holds
     * numbers (tables, inline code, chart references, the environment stamp) and
     * assert that no decimal or unit-bearing integer survives in what is left.
     * A new sentence that names a figure therefore fails, whichever figure it
     * names.
     */
    public function testTheReportProseNamesNoMeasuredFigure(): void
    {
        $offenders = [];

        foreach ($this->publishedMarkdown() as $file => $markdown) {
            $prose = self::proseOnly($markdown);
            // Inline code legitimately carries endpoint paths and settings such
            // as `pm.max_requests=0`; a framework name in backticks is not a
            // measurement either.
            $prose = preg_replace('/`[^`]*`/', ' ', $prose) ?? $prose;

            if (preg_match_all(self::FIGURE, $prose, $m) !== 0) {
                $offenders[$file] = array_values(array_unique($m[0]));
            }
        }

        self::assertSame(
            [],
            $offenders,
            "measured figures found in report prose (they belong in a chart or a table):\n"
                . print_r($offenders, true)
        );
    }

    /**
     * Published Markdown files, keyed by name. Only the .md is inspected: the
     * HTML is generated from the same renderer, so it inherits the contract.
     *
     * @return array<string,string>
     */
    private function publishedMarkdown(): array
    {
        $out  = [];
        $dir  = dirname(__DIR__) . '/docs/benchmarks';
        $keys = array_keys((require dirname(__DIR__) . '/scripts/report/views.php')['views']);

        foreach ($keys as $key) {
            $path = $dir . '/' . $key . '.md';
            self::assertFileExists($path, "{$key}.md must be published");
            $out[$key . '.md'] = (string) file_get_contents($path);
        }

        return $out;
    }

    /**
     * Everything in a rendered page that is NOT one of the number-bearing
     * structures: tables, chart references, inline code, and the dataset
     * provenance lines.
     */
    private static function proseOnly(string $markdown): string
    {
        $keep    = [];
        $inTable = false;
        $inFoot  = false;

        foreach (explode("\n", $markdown) as $line) {
            if (str_starts_with($line, '> **Auto-generated.**')) {
                $inFoot = true;
            }
            if ($inFoot) {
                continue;
            }
            if (preg_match('/^\s*\|/', $line) === 1) {
                $inTable = true;
                continue;
            }
            if ($inTable) {
                if (trim($line) === '') {
                    $inTable = false;
                }
                continue;
            }
            // Dataset provenance: the environment stamp, the measurement date,
            // and the version of every framework that was measured. All three
            // are regenerated from the dataset on every render, so a figure in
            // them cannot outlive the run — which is the whole reason the guard
            // exists. `**Frameworks**` belongs here for exactly that reason: it
            // is a stamp, not a sentence about a number.
            if (
                str_starts_with($line, '**Environment**')
                    || str_starts_with($line, '**Frameworks**')
                    || str_starts_with($line, '_Measured ')
            ) {
                continue;
            }
            // A markdown image is a chart reference, not a sentence.
            if (preg_match('/^!\[/', $line) === 1) {
                continue;
            }
            $keep[] = $line;
        }

        return implode("\n", $keep);
    }
}
