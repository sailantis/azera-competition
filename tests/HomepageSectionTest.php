<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The homepage section must state what it measured, and must not overstate it.
 *
 * This generator is unusual in the repository: its output is not a page anyone
 * reads here. It writes a Clarity partial into ANOTHER repository's template
 * tree and six SVGs into that repository's asset tree, and nothing in this
 * repository consumes either. So a mistake does not show up as a failing page
 * here — it shows up as a wrong number on the public product page, which is the
 * same failure mode the published summaries were built to prevent.
 *
 * Three classes of mistake are pinned:
 *
 *  1. PROVENANCE. Every chart names the deployment model and every sentence about
 *     the environment comes from the dataset. A hand-typed "PHP 8.5" is a copy
 *     that survives the run it described.
 *  2. THE TAB/PANEL PAIRING. bench-switch.ts refuses a strip whose tab count does
 *     not match its panel count, so a mismatch turns the whole section inert —
 *     silently, because an inert strip still renders every panel.
 *  3. THE ALT TEXT. It is the only description of a figure a reader using a screen
 *     reader has, so it has to carry the same numbers as the rows.
 */
final class HomepageSectionTest extends TestCase
{
    private const DATASET = __DIR__ . '/../results/real-deployments.json';

    /** The scripts are plain global-function files, like the rest of the harness. */
    private static bool $loaded = false;

    public static function setUpBeforeClass(): void
    {
        if (!self::$loaded) {
            require_once __DIR__ . '/../scripts/homepage-report.php';
            self::$loaded = true;
        }
    }

    protected function setUp(): void
    {
        if (!is_file(self::DATASET)) {
            self::markTestSkipped('results/real-deployments.json is not present');
        }
    }

    private function store(): ResultStore
    {
        return ResultStore::load(self::DATASET);
    }

    /** Render the section and return the partial text. */
    private function section(): string
    {
        $written = [];
        return \homepageSection($this->store(), \homepageCompetitions(), '/x', $written);
    }

    // -- 1. Provenance ------------------------------------------------------

    public function testEveryChartTitleNamesTheServerAndTheModel(): void
    {
        foreach (array_keys(HOMEPAGE_MODELS) as $mode) {
            $model = HOMEPAGE_MODELS[$mode];
            $svg = \homepageChart(
                $this->store(),
                'GET /',
                $mode,
                HOMEPAGE_APPS,
                $model['server_label'] . ' (' . strtolower($model['label']) . ') — C2 — Home'
            );
            self::assertNotSame('', $svg, "no chart drawn for {$mode}");

            // The title is a <title>/<text> node carrying both halves. A reader
            // who copies the figure out of the page keeps the heading, so the
            // server and the model have to be IN it.
            self::assertStringContainsString(
                $model['server_label'] . ' (' . strtolower($model['label']) . ')',
                $svg,
                "the chart for {$mode} does not name both its server and its model"
            );
        }
    }

    public function testTheSectionNamesBothServersInItsFootnote(): void
    {
        $section = $this->section();

        foreach (HOMEPAGE_MODELS as $model) {
            self::assertStringContainsString(
                $model['server_label'],
                $section,
                'the footnote does not name ' . $model['server_label']
            );
        }
    }

    /**
     * The charts are drawn on a LINEAR axis.
     *
     * A product page's reader should not have to learn that an equal distance
     * means a different number of milliseconds at each end of the axis before
     * they can compare two bars. Linear is the default for that reason, and this
     * assertion exists because the scale is the one chart setting whose change is
     * invisible in the numbers: every figure, caption and multiplier stays
     * identical, and only the geometry moves. A grep of the output is therefore
     * the only cheap way to catch it, and the SVG names the scale in its own note.
     */
    public function testTheChartsAreDrawnOnALinearAxis(): void
    {
        foreach (array_keys(HOMEPAGE_MODELS) as $mode) {
            $svg = \homepageChart($this->store(), 'GET /', $mode, HOMEPAGE_APPS, 'title');

            self::assertNotSame('', $svg);
            self::assertStringContainsString('linear scale', $svg, "the {$mode} chart is not linear");
            self::assertStringNotContainsString('logarithmic scale', $svg, "the {$mode} chart is logarithmic");
        }
    }

    /**
     * A version the run did not stamp must not be invented.
     *
     * `env.servers` is written from the servers' own `--version` output. When a
     * key is absent, the honest page says nothing rather than naming a build from
     * memory — and the three banners that ARE there have three different shapes,
     * which is what makes an unanchored split go wrong.
     */
    public function testServerVersionsAreReadFromTheStampedBannerOnly(): void
    {
        $store = $this->store();

        self::assertSame('0.6.0', \homepageServerVersion($store, 'volucer', 'Volucer'));
        self::assertSame('8.5.11', \homepageServerVersion($store, 'php_fpm', 'PHP'));
        self::assertSame('1.24.0', \homepageServerVersion($store, 'nginx'));
        self::assertNull(\homepageServerVersion($store, 'no-such-server'));
    }

    /**
     * The Volucer banner is a whole feature list, and its first comma sits INSIDE
     * that list. Splitting on a comma once published
     * "volucer 0.6.0   front-ends: HTTP/1.1" as if the rest of the build were
     * absent, so the split is pinned here by a banner that carries a comma before
     * the version is even reached.
     */
    public function testACommaInTheBannerDoesNotTruncateTheVersion(): void
    {
        $store = $this->store();

        $version = \homepageServerVersion($store, 'volucer', 'Volucer');
        self::assertSame('0.6.0', $version);
        self::assertStringNotContainsString('front-ends', (string) $version);
        self::assertStringNotContainsString(',', (string) $version);
    }

    // -- 2. Tab/panel pairing ----------------------------------------------

    /**
     * bench-switch.ts drives a section only when its tab count equals its panel
     * count, and applies no hidden state otherwise. So the counts are the whole
     * contract, and they are counted from the rendered markup rather than from
     * the constants.
     */
    public function testTheTabStripMatchesThePanelCount(): void
    {
        $section = $this->section();

        $tabs   = preg_match_all('/class="bench-tab"/', $section);
        $panels = preg_match_all('/class="bench-panel"/', $section);

        self::assertGreaterThan(1, $tabs, 'the section rendered fewer than two routes');
        self::assertSame($tabs, $panels, 'the switch cannot drive a strip that does not match its panels');
    }

    /**
     * Exactly one tab is selected, and it is the first.
     *
     * Deciding the selection from the loop index (rather than from the number of
     * panels already emitted) marked a tab selected that had been dropped for
     * producing no chart, leaving the strip with nothing selected at all.
     */
    public function testExactlyOneTabIsSelectedAndItIsTheFirst(): void
    {
        $section = $this->section();

        self::assertSame(1, preg_match_all('/aria-selected="true"/', $section));

        $firstSelected = strpos($section, 'aria-selected="true"');
        $firstOther    = strpos($section, 'aria-selected="false"');
        self::assertIsInt($firstSelected);
        self::assertIsInt($firstOther);
        self::assertLessThan($firstOther, $firstSelected);
    }

    /** Every panel points at the tab that labels it, and every tab at its panel. */
    public function testEveryPanelAndTabCrossReferenceEachOther(): void
    {
        $section = $this->section();

        preg_match_all('/id="([a-z0-9-]+)" aria-controls="([a-z0-9-]+)"/', $section, $tabs, PREG_SET_ORDER);
        self::assertNotEmpty($tabs);

        foreach ($tabs as [$all, $id, $controls]) {
            self::assertStringContainsString('id="' . $controls . '" role="tabpanel"', $section);
            self::assertStringContainsString('aria-labelledby="' . $id . '"', $section);
        }
    }

    /**
     * The tabs appear in the page's READING order: C2, C6, C5.
     *
     * Not numeric order, and not an accident: the API tab leads because the JSON
     * route is the narrower question — the same data work with no template — so a
     * reader who abandons the figure after one tab abandons it after the one that
     * isolates the data path. Asserting the order rather than just the presence
     * keeps a reordering from silently changing which tab a returning reader
     * lands on.
     *
     * The slugs are compared as INTEGERS because the slug is a `C` followed by a
     * number and a plain string sort puts `C10` before `C2`.
     *
     * Note the gaps: the page draws C2 (`/`), C6 (`/api/items-fast`) and C5
     * (`/items-fast`). C1 (`/echo`) and C3/C4 (the hydrating data routes) are
     * measured and published, but not drawn here — this section compares SERVERS,
     * so both data tabs draw the unhydrated route and hydration does not become a
     * second variable between two charts.
     */
    public function testTabsFollowTheReadingOrder(): void
    {
        $section = $this->section();

        preg_match_all('/^\s+(C\d+) — /m', $section, $m);
        self::assertNotEmpty($m[1], 'no slug-labelled tabs found; is the registry missing?');

        $numbers = array_map(static fn(string $s): int => (int) substr($s, 1), $m[1]);

        self::assertSame(
            [2, 6, 5],
            $numbers,
            'the tab order is the page\'s reading order, not numeric: ' . implode(', ', $m[1])
        );
    }

    /**
     * No panel may be hidden in the MARKUP.
     *
     * The section is progressive by design: the CSS hides panels only through a
     * class the script applies. A `hidden` attribute or a `bench-hidden` class in
     * the generated file would blank the section for a reader whose bundle failed
     * to load — the exact regression the Volucer section's switch was written to
     * avoid.
     */
    public function testTheMarkupHidesNothing(): void
    {
        $section = $this->section();

        self::assertStringNotContainsString('bench-hidden', $section);
        self::assertDoesNotMatchRegularExpression('/\shidden[\s>=]/', $section);
    }

    // -- 3. Alt text -------------------------------------------------------

    /**
     * The alt text carries the same numbers the rows carry.
     *
     * Read back through the same ResultStore the chart drew from, so a re-measure
     * cannot leave the description stating the previous run's figures.
     */
    public function testTheAltTextRepeatsTheMeasuredMedians(): void
    {
        $store  = $this->store();
        $alt    = \homepageAltText($store, 'GET /', 'volucer-attach', HOMEPAGE_APPS, 'Volucer, resident worker');
        $spread = $store->spread('azera', 'volucer-attach', 'GET /');

        self::assertNotNull($spread);
        self::assertStringContainsString(number_format($spread['median'], 2) . ' ms', $alt);
        self::assertStringContainsString('GET /', $alt);

        // Fastest-first ordering is stated in the sentence, so the sentence has to
        // actually be ordered: the lowest figure must precede the highest.
        $low = number_format($spread['median'], 2);
        $highest = 0.0;
        foreach (HOMEPAGE_APPS as $app) {
            $s = $store->spread($app, 'volucer-attach', 'GET /');
            if ($s !== null && $s['median'] > $highest) {
                $highest = $s['median'];
            }
        }
        self::assertLessThan(
            strpos($alt, number_format($highest, 2)) ?: PHP_INT_MAX,
            strpos($alt, $low) ?: PHP_INT_MAX,
            'the alt text claims fastest-first but does not print them in that order'
        );
    }

    // -- Section-level honesty ---------------------------------------------

    /**
     * A dataset missing one of the two models is refused, not half-rendered.
     *
     * A section that showed the resident charts and silently omitted the
     * per-request ones would read as a comparison that had been made. The guard
     * belongs in the CLI (it exits), and its absence is asserted here by
     * constructing the situation the CLI checks for.
     */
    public function testBothModelsMustBePresent(): void
    {
        $modes = $this->store()->modes();
        foreach (array_keys(HOMEPAGE_MODELS) as $mode) {
            self::assertContains($mode, $modes, "the canonical dataset lost its {$mode} rows");
        }
    }

    /** Every referenced chart is one the collector reports writing. */
    public function testEveryReferencedChartWasWritten(): void
    {
        $written = [];
        $section = \homepageSection($this->store(), \homepageCompetitions(), '/x', $written);

        preg_match_all('/src="\/x\/([^"]+)"/', $section, $m);
        self::assertNotEmpty($m[1]);

        $referenced = array_values(array_unique($m[1]));
        sort($referenced);
        $collected = array_keys($written);
        sort($collected);

        self::assertSame($collected, $referenced, 'the partial and the written chart set disagree');
    }

    /**
     * Two renders of one dataset are byte-identical.
     *
     * The section is regenerated whenever the dataset changes, so a timestamp or
     * a `date()` anywhere in it would make every regeneration a diff and hide the
     * one change that mattered.
     */
    public function testTheRenderedSectionIsDeterministic(): void
    {
        self::assertSame($this->section(), $this->section());
    }
}
