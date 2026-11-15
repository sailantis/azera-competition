<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The Clarity homepage section is generated, published into another repository
 * and never read from this one.
 *
 * Same failure mode as HomepageSectionTest, for the other product page: a
 * mistake does not show up as a failing page here, it shows up as a wrong number
 * or a broken figure on the public site. What is pinned here is what differs
 * from the Azera section — the parts that made a second generator necessary
 * rather than a flag on the first:
 *
 *  1. ONE PAGE, THREE CHARTS. The section publishes the `mixed` page only, and
 *     refuses a dataset that did not measure it rather than falling back to a
 *     different shape's numbers under the same heading.
 *  2. NO SWITCH. There are no routes to switch between, so the partial must not
 *     emit `data-bench-panels`: bench-switch.ts would find one panel and one tab
 *     count of zero and leave the section inert — which is harmless today but
 *     would turn a future second panel into a silently broken strip.
 *  3. PROVENANCE AND ALT TEXT, from the dataset. Every chart names the page it
 *     measured, and the alt text carries the same medians the rows carry.
 */
final class ClarityHomepageSectionTest extends TestCase
{
    private const DATASET = __DIR__ . '/../benchmarks/view-engine/view-engine-2026-11-12-v0.4.2-php85-jit-reval60-10000x30-items200.json';

    /** The scripts are plain global-function files, like the rest of the harness. */
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            require_once __DIR__ . '/../scripts/clarity-homepage-report.php';
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        if (!is_file(self::DATASET)) {
            self::markTestSkipped('the view-engine dataset is not present');
        }
    }

    /** @return array{0:array<string,mixed>,1:list<array<string,mixed>>} */
    private function dataset(): array
    {
        return \veLoadDataset(self::DATASET);
    }

    /** @return list<array<string,mixed>> */
    private function rows(): array
    {
        return \veRowsForPage($this->dataset()[1], \CH_PAGE);
    }

    private function section(): string
    {
        [$env, $results] = $this->dataset();
        $rows = \veRowsForPage($results, \CH_PAGE);

        return \chSection($env, $rows, '/x', \chCharts($env, $rows));
    }

    // -- 1. One page, three charts ------------------------------------------

    public function testTheSectionDrawsTheMixedPage(): void
    {
        self::assertSame('mixed', \CH_PAGE);
        self::assertNotSame([], $this->rows(), 'the canonical dataset lost its mixed rows');
    }

    public function testEveryChartIsNamedForThePageItMeasured(): void
    {
        [$env, $results] = $this->dataset();
        $charts = \chCharts($env, \veRowsForPage($results, \CH_PAGE));

        self::assertSame(
            ['render-time', 'warm-cost', 'memory'],
            array_keys($charts),
            'the three figures the section publishes'
        );
        foreach ($charts as $kind => $svg) {
            self::assertStringContainsString(
                \vePageLabel(\CH_PAGE),
                $svg,
                "the {$kind} chart does not name the page it measured"
            );
        }
    }

    public function testAChartFileIsNamedForThePageSoASecondPageCannotCollide(): void
    {
        foreach (\chChartMeta() as $kind => $meta) {
            self::assertStringContainsString(
                \CH_PAGE,
                $meta['file'],
                "the {$kind} chart's filename does not carry the page"
            );
            self::assertStringStartsWith('homepage-', $meta['file']);
        }
    }

    // -- 2. No switch -------------------------------------------------------

    public function testTheSectionEmitsNoSwitchMarkup(): void
    {
        $section = $this->section();

        self::assertStringNotContainsString(
            'data-bench-panels',
            $section,
            'the Clarity section has no routes to switch between; a strip here would never be driven'
        );
        self::assertStringNotContainsString('bench-tab', $section);
        self::assertStringNotContainsString('role="tablist"', $section);
    }

    public function testTheSectionHidesNothing(): void
    {
        $section = $this->section();

        self::assertStringNotContainsString('bench-hidden', $section);
        self::assertDoesNotMatchRegularExpression('/\shidden[\s>=]/', $section);
    }

    // -- 3. Provenance and alt text -----------------------------------------

    public function testEveryTileNamesItsFigureAndSaysWhatItMeasures(): void
    {
        [$env, $results] = $this->dataset();
        $rows   = \veRowsForPage($results, \CH_PAGE);
        $charts = \chCharts($env, $rows);
        $section = \chSection($env, $rows, '/x', $charts);

        foreach (\chChartMeta() as $kind => $meta) {
            self::assertStringContainsString(\chEscape($meta['title']), $section, "no tile for {$kind}");
            // The note is LIVE TEXT beside an <img>, so a figure in it would be a
            // second copy of a number that nothing recomputes.
            self::assertDoesNotMatchRegularExpression(
                '/\d+(?:[.,]\d+)?\s*(?:ms|MB)\b/',
                $meta['note'],
                "the {$kind} tile's note states a measured figure"
            );
        }
    }

    public function testTheAltTextRepeatsTheMeasuredMedians(): void
    {
        $rows = $this->rows();
        $clarity = null;
        foreach ($rows as $row) {
            if (($row['engine'] ?? '') === 'clarity') {
                $clarity = $row;
                break;
            }
        }
        self::assertNotNull($clarity, 'the dataset no longer measures clarity');

        $alt = \chAltText('render-time', $rows);
        self::assertStringContainsString(
            number_format((float) $clarity['median_ms'], 3) . ' ms',
            $alt,
            'the alt text must carry the median the chart draws'
        );
        self::assertStringContainsString('fastest first', $alt);
    }

    public function testTheAltTextIsOrderedByTheFigureItQuotes(): void
    {
        $rows = $this->rows();
        $alt  = \chAltText('render-time', $rows);

        // Pull "Label 1.234 ms median" pairs in the order the sentence states
        // them, then check the figures ascend: the chart orders its rows by the
        // median, so a sentence in another order ranks them differently.
        preg_match_all('/([A-Za-z][A-Za-z0-9 ()]*?) (\d[\d,]*\.\d+) ms median/', $alt, $m);
        self::assertNotEmpty($m[2], 'no readings found in the alt text');

        $values = array_map(static fn(string $v): float => (float) str_replace(',', '', $v), $m[2]);
        $sorted = $values;
        sort($sorted);

        self::assertSame($sorted, $values, 'the alt text does not print the medians fastest first');
    }

    public function testTheConditionsLineComesFromTheDataset(): void
    {
        [$env] = $this->dataset();
        $line  = \chConditions($env);

        self::assertStringContainsString(number_format((int) $env['iterations_per_run']), $line);
        self::assertStringContainsString((string) (int) $env['runs'], $line);
        self::assertStringContainsString((string) $env['php_version'], $line);
        self::assertStringContainsString('OPcache', $line);
    }

    public function testTheConditionsLineOmitsWhatTheDatasetDidNotRecord(): void
    {
        $line = \chConditions(['iterations_per_run' => 1000, 'runs' => 10]);

        self::assertStringContainsString('1,000', $line);
        // No version clause and no OPcache clause. "PHP CLI" is the render
        // channel, not a provenance claim, so the assertion is on the VERSION
        // that a dataset which did not record one must not be given.
        self::assertDoesNotMatchRegularExpression('/PHP \d/', $line);
        self::assertStringNotContainsString('OPcache', $line);
    }

    /**
     * The footnote states the JIT setting, and only when the dataset recorded it.
     *
     * This is the claim that made the whole exercise worth doing: a JIT-on run
     * and a JIT-off run are not comparable, so a section that publishes figures
     * without saying which it measured cannot be compared with anything. The
     * clause comes from the SHARED renderer, so it is worded identically to the
     * published report's — and an older dataset without the keys must render
     * nothing rather than "off", which would be inventing an observation.
     */
    public function testTheConditionsLineStatesTheJitWhenTheDatasetRecordedIt(): void
    {
        [$env] = $this->dataset();

        // The committed dataset for this release was measured with the JIT on;
        // if it was not, the figures behind the homepage section were not taken
        // under the conditions they claim.
        self::assertTrue(
            $env['opcache_jit_enabled'] ?? null,
            'the dataset behind this section must have been measured with the JIT enabled'
        );
        self::assertStringContainsString('JIT: ' . $env['opcache_jit'], \chConditions($env));
        self::assertStringContainsString((string) $env['opcache_jit_buffer_size'], \chConditions($env));

        // Recorded-as-off still renders (it is a fact about the run), but a
        // dataset that says nothing gets no clause at all.
        $jitOff = \chConditions(['iterations_per_run' => 10, 'runs' => 1, 'opcache_jit' => 'disable', 'opcache_jit_buffer_size' => '64M', 'opcache_jit_enabled' => false]);
        self::assertStringContainsString('JIT: off', $jitOff);

        $silent = \chConditions(['iterations_per_run' => 10, 'runs' => 1]);
        self::assertStringNotContainsString('JIT', $silent, '"not recorded" must not be published as "off"');
    }

    /**
     * The footnote states the Clarity revalidate window, when the dataset has it.
     *
     * The window changes the Clarity bar and is invisible in the other engines'
     * rows, so a caption that omits it lets a reader compare two different
     * configurations without knowing it. A dataset measured before the key
     * existed must render nothing rather than "0", which would claim a
     * per-render check nobody performed.
     */
    public function testTheConditionsLineStatesTheClarityRevalidateWindow(): void
    {
        $withWindow = \chConditions([
            'iterations_per_run'       => 10,
            'runs'                     => 1,
            'clarity_revalidate_freq'  => 60,
        ]);
        self::assertStringContainsString('Clarity template revalidate: 60 s', $withWindow);

        // 0 is a distinct, meaningful configuration — spell it out, so it cannot
        // read as "recorded nothing".
        $everyRender = \AzeraCompetition\Report\Provenance::clarityRevalidateClause(['clarity_revalidate_freq' => 0]);
        self::assertStringContainsString('checked every render', $everyRender);

        // Not recorded => no clause at all.
        $silent = \chConditions(['iterations_per_run' => 10, 'runs' => 1]);
        self::assertStringNotContainsString('revalidate', $silent, '"not recorded" must not be published as a value');
    }

    public function testTheRenderedSectionIsDeterministic(): void
    {
        self::assertSame($this->section(), $this->section());
    }

    /**
     * The default dataset must be a FULL run.
     *
     * The harness writes `-local-` smoke datasets next to the real ones, with a
     * budget two orders of magnitude smaller. Picking one by mtime alone would
     * publish a 300x run under a footnote claiming 10,000x, so the selector
     * excludes them by name.
     */
    public function testTheDefaultDatasetSkipsLocalSmokeRuns(): void
    {
        $chosen = \chDefaultDataset();
        if ($chosen === null) {
            self::markTestSkipped('no view-engine dataset present');
        }

        self::assertStringNotContainsString('-local-', basename($chosen));
    }
}
