<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\HtmlReport;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * Where a feature's workload sentence is stated, and how often.
 *
 * The Feature benchmarks section used to state each feature's workload as a
 * BULLET LIST under the section intro, and then draw twelve charts under their
 * own `###` headings further down. A reader measuring one feature therefore met
 * its subject in one place and its race in another: the heading "### Cache"
 * named nothing, and the sentence explaining what the cache race does sat a
 * screen above it.
 *
 * The fix moved the sentence under the feature's OWN heading, above its chart —
 * in both renderers, so the Markdown (a plain line) and the HTML (a figure
 * caption line) say the same thing in the same place.
 *
 * The tests below pin the two halves that make that safe:
 *
 *  1. each heading is followed by its own sentence, anchored on the request the
 *     feature is measured with — a hardcoded map, NOT read back out of
 *     BenchmarkConfig, because an expectation derived from the code under test
 *     is echoed straight back by a mutation;
 *  2. each sentence appears EXACTLY ONCE, which is what catches the real
 *     regression: a future edit that re-adds the up-front list would print
 *     every one of them twice, and "the heading is followed by the sentence"
 *     would still pass.
 *
 * PUNCTUATION IS NOT PINNED, and that is deliberate: the first version of these
 * tests hardcoded the exact separators (`### H\n\n> (`GET /`) — text`), so a
 * purely cosmetic edit — dropping the `>` quote marker and the parentheses —
 * failed them while the page was entirely correct. They now assert the SHAPE: a
 * heading line, then the very next line carrying the feature's request in code
 * ticks and its sentence. How that line is decorated is a presentation choice
 * the renderer may change without these tests objecting.
 *
 * The sentences themselves are static text by design — the report's contract is
 * that a figure lives only in a chart or a table, never in a sentence (see
 * ChartManifestTest::testTheReportProseNamesNoMeasuredFigure), so nothing here
 * asserts a reading.
 */
final class FeatureDescriptionTest extends TestCase
{
    /**
     * The request each feature is anchored on.
     *
     * Deliberately a literal map rather than BenchmarkConfig::featurePrimaryRequest():
     * a test that reads its expectation from the implementation cannot detect
     * that the implementation moved. These are the canonical endpoints the
     * harness measures the feature with.
     *
     * @var array<string,string>
     */
    private const ANCHOR = [
        'routing'        => 'GET /',
        'orm'            => 'GET /items',
        'query-builder'  => 'GET /items-qb',
        'rest-api'       => 'GET /api/items',
        'aop'            => 'GET /features/aop',
        'cache'          => 'GET /features/cache',
        'db-events'      => 'GET /features/db-events',
        'events'         => 'GET /features/events',
        'validation'     => 'GET /features/validation',
        'config'         => 'GET /features/config',
        'request-scoped' => 'GET /features/request-scoped',
        'rate-limiter'   => 'GET /features/rate-limit',
    ];

    /** @return array<string,array<string,mixed>> */
    private static function manifest(): array
    {
        return require dirname(__DIR__) . '/scripts/report/views.php';
    }

    private static function store(): ResultStore
    {
        return ResultStore::load(dirname(__DIR__) . '/results/free-for-all-opcache-iso.json');
    }

    /** The full-coverage view: it draws every feature race. */
    private static function markdown(): string
    {
        $manifest = self::manifest();

        return (new MarkdownReport(self::store(), 'warm-start', $manifest['views']['warm-start']))
            ->render(sys_get_temp_dir() . '/tmp-featdesc-md', 'svg/warm-start');
    }

    private static function html(): string
    {
        $manifest = self::manifest();
        $view     = $manifest['views']['warm-start'];
        $store    = self::store();

        // The HTML only draws the charts it is HANDED, exactly as report.php
        // hands it the renderer's own writtenCharts() list — so the helper goes
        // through the same funnel instead of naming one chart by hand (which
        // would leave eleven features unrendered and the assertions vacuous).
        $md = new MarkdownReport($store, 'warm-start', $view);
        $md->render(sys_get_temp_dir() . '/tmp-featdesc-html', 'svg/warm-start');
        $files = [];
        foreach ($md->writtenCharts() as $chart) {
            $files[$chart] = 'svg/warm-start/' . $chart . '.svg';
        }

        return (new HtmlReport($store, $manifest))->view('warm-start', $view, $files);
    }

    /**
     * The feature map and the anchor list must stay in step.
     *
     * Without this, a new feature added to BenchmarkConfig would be silently
     * absent from every assertion below (they iterate ANCHOR), so the tests
     * would keep passing while one race was unchecked.
     */
    public function testTheAnchorListCoversEveryFeatureTheHarnessMeasures(): void
    {
        self::assertSame(
            BenchmarkConfig::featureOrder(),
            array_keys(self::ANCHOR),
            'every measured feature must have an expected anchor request'
        );

        foreach (self::ANCHOR as $feature => $request) {
            self::assertSame(
                $request,
                BenchmarkConfig::featurePrimaryRequest($feature),
                "the {$feature} race is anchored on {$request}"
            );
            self::assertNotSame('', BenchmarkConfig::featureDescriptionFor($feature));
        }
    }

    /**
     * Each `###` heading is immediately followed by its own workload sentence.
     *
     * Matched as a SHAPE (heading line, then the request in code ticks and the
     * sentence on the next line), not as a literal, so the separators around
     * them can be restyled without a false failure — see the class docblock.
     */
    public function testEachHeadingCarriesItsOwnWorkloadSentence(): void
    {
        $md = self::markdown();

        foreach (self::ANCHOR as $feature => $request) {
            $label = BenchmarkConfig::featureLabel($feature);
            $what  = BenchmarkConfig::featureDescriptionFor($feature);

            self::assertMatchesRegularExpression(
                '/^### ' . preg_quote($label, '/')
                    . '\n[^\n]*`' . preg_quote($request, '/') . '`[^\n]*' . preg_quote($what, '/') . '$/m',
                $md,
                "the {$label} heading must state its request and workload directly beneath it"
            );
        }
    }

    /**
     * ...and the sentence precedes the chart it explains.
     *
     * The ORDER is the point of the change: the caption was correct before as
     * well, it was just somewhere else on the page. Compared with
     * assertGreaterThan on strpos, and both positions asserted first — the
     * reversed-argument form silently passes for both orderings.
     */
    public function testTheSentencePrecedesTheChartItExplains(): void
    {
        $md = self::markdown();

        $sentence = strpos($md, '`GET /features/cache`');
        $chart    = strpos($md, '![Cache](');

        self::assertNotFalse($sentence, 'the cache workload sentence must be on the page');
        self::assertNotFalse($chart, 'the cache chart must be on the page');
        self::assertGreaterThan($sentence, $chart, 'the workload sentence must come before its chart');
    }

    /**
     * Every sentence appears EXACTLY ONCE, in both renderers.
     *
     * This is the assertion that catches re-adding the up-front list: a
     * duplicated description leaves every other test in this file green,
     * because the heading form is still present further down.
     */
    public function testEachSentenceIsPrintedExactlyOnce(): void
    {
        $md   = self::markdown();
        $html = self::html();

        foreach (self::ANCHOR as $feature => $_) {
            $what = BenchmarkConfig::featureDescriptionFor($feature);

            self::assertSame(
                1,
                substr_count($md, $what),
                "the {$feature} workload sentence must be stated exactly once (a second copy means a list was re-added)"
            );
            // The HTML escapes apostrophes, so compare on a fragment that
            // cannot contain one — the sentence is printed, not the escaping.
            self::assertSame(
                1,
                substr_count($html, self::plainFragment($what)),
                "the {$feature} workload sentence must appear exactly once in the HTML too"
            );
        }
    }

    /**
     * The HTML carries the same sentence in the same place: under the caption
     * of the feature's own figure.
     *
     * A shape again: the figure's caption, then a line naming the request and
     * the sentence, before the `<img>`. The class name and the exact separator
     * are NOT pinned — they are presentation, and the first version of this
     * test failed on a `<p class>` rename (which is precisely what a guard like
     * this should not be sensitive to).
     */
    public function testTheHtmlFigureCarriesTheSameSentence(): void
    {
        $html = self::html();

        foreach (self::ANCHOR as $feature => $request) {
            $label = BenchmarkConfig::featureLabel($feature);
            $what  = self::plainFragment(BenchmarkConfig::featureDescriptionFor($feature));

            self::assertMatchesRegularExpression(
                '/<figcaption>' . preg_quote($label, '/') . '<\/figcaption>'
                    . '\\s*<p[^>]*><code>' . preg_quote($request, '/') . '<\/code>[^<]*'
                    . preg_quote($what, '/') . '/',
                $html,
                "the {$label} figure must carry its request and workload under the caption"
            );
        }
    }

    /**
     * The section intro no longer lists the features.
     *
     * A structural check rather than a string search for a bullet: every line
     * between the section heading and the first `###` must be plain prose, so
     * any list — bullet, numbered or table — reappearing there fails.
     */
    public function testTheSectionIntroCarriesNoFeatureList(): void
    {
        $md = self::markdown();

        $start = strpos($md, "## Feature benchmarks\n");
        self::assertNotFalse($start, 'the feature section must exist');
        $end = strpos($md, "\n### ", $start);
        self::assertNotFalse($end, 'the feature section must contain feature headings');

        $intro = substr($md, $start, $end - $start);
        foreach (explode("\n", $intro) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '## ')) {
                continue;
            }
            self::assertDoesNotMatchRegularExpression(
                '/^([-*+]|\d+\.|\|)\s|^\|/',
                $line,
                "the feature section intro must not list the features (found: {$line})"
            );
        }

        // The descriptions live under the headings, and nowhere else — the
        // intro names no endpoint at all.
        foreach (self::ANCHOR as $feature => $request) {
            self::assertStringNotContainsString(
                $request,
                $intro,
                'the intro must not name an endpoint; the sentence under the heading does that'
            );
            unset($feature);
        }
    }

    /**
     * A fragment of a sentence that survives HTML escaping.
     *
     * The renderer escapes the text, so an apostrophe becomes `&#039;` — the
     * comparison has to be made on a substring that contains none. The first
     * three words are always safe: no description begins with a punctuation
     * mark or an abbreviation.
     */
    private static function plainFragment(string $what): string
    {
        return implode(' ', array_slice(explode(' ', $what), 0, 3));
    }
}
