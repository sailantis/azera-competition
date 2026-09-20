<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The framework-facing publication surface.
 *
 * `php scripts/report.php --publish=framework` copies every view that declares
 * `'publish' => ['framework']` into azera-framework VERBATIM — its Markdown and
 * all of its SVGs. That made "which views publish" the single decision that
 * controls how much of this repository lands in another one, and it was once
 * wrong in the expensive direction: all four full views declared it, so one run
 * wrote four markdown files and 59 SVGs into the framework docs. The framework
 * wants one page per deployment model carrying the claim, not a second
 * dashboard.
 *
 * These tests pin the three things that make the current arrangement safe:
 *
 *  1. exactly the two SUMMARY views publish, and their filenames are the stable
 *     ones the framework's own docs link to;
 *  2. each summary is a strict SUBSET of the full view it summarises, so
 *     publishing cannot quietly republish a whole view;
 *  3. the links a summary prints are ABSOLUTE, so the same .md reads correctly
 *     from this repository's docs and from the framework's.
 *
 * They are structural rather than byte-comparisons against committed output, so
 * they keep holding as the dataset is re-measured.
 */
final class FrameworkPublishTest extends TestCase
{
    /** How the framework docs refer to the two pages. */
    private const EXPECTED_FILES = [
        'summary-roadrunner' => '19-BENCHMARKS-SUMMARY-ROADRUNNER.md',
        'summary-fpm'        => '19-BENCHMARKS-SUMMARY-FPM.md',
    ];

    /** @return array<string,array<string,mixed>> */
    private static function manifest(): array
    {
        return require dirname(__DIR__) . '/scripts/report/views.php';
    }

    /** @return list<string> */
    private static function publishingViews(): array
    {
        $manifest = self::manifest();

        return array_keys(array_filter(
            $manifest['views'],
            static fn(array $v): bool => in_array('framework', $v['publish'] ?? [], true)
        ));
    }

    /**
     * The set of publishing views, asserted EXACTLY.
     *
     * A subset assertion would pass while a fifth view quietly joined the list;
     * that is precisely the regression this pins, so the comparison is strict
     * and names the two keys.
     */
    public function testOnlyTheSummaryViewsPublish(): void
    {
        self::assertSame(
            array_keys(self::EXPECTED_FILES),
            self::publishingViews(),
            'exactly the two summary views may publish into azera-framework'
        );
    }

    /**
     * The published filenames are a contract: azera-framework's own README and
     * docs index link to them by name.
     */
    public function testPublishedFilenamesAreTheOnesTheFrameworkLinksTo(): void
    {
        $manifest = self::manifest();

        foreach (self::EXPECTED_FILES as $key => $file) {
            self::assertSame(
                $file,
                $manifest['views'][$key]['publish_md'] ?? null,
                "{$key} must publish as {$file}"
            );
        }
    }

    /**
     * A full view must not publish. Stated separately from the exact-set test
     * so the failure names the offending view rather than only reporting that
     * the list changed.
     */
    public function testTheFullViewsPublishNothing(): void
    {
        $manifest = self::manifest();

        foreach (['warm-start', 'cold-start', 'real-roadrunner', 'real-fpm'] as $key) {
            self::assertNotContains(
                'framework',
                $manifest['views'][$key]['publish'] ?? [],
                "{$key} is a full view and must not be published into the framework"
            );
        }
    }

    /**
     * A summary must be a SUBSET of the view it summarises.
     *
     * The subset lives in the FEATURE RACES, not in the chart keys: both draw
     * the same three charts, but the summary narrows the feature chart to three
     * races instead of twelve, so it writes far fewer SVGs. (An earlier version
     * compared `count($charts)` and failed — the chart keys were equal precisely
     * BECAUSE the narrowing is expressed one level down, which is worth stating
     * rather than papering over. They are no longer equal: the summaries also
     * drop the startup chart, see testTheSummariesDropTheStartupChart.)
     *
     * Without this, "summary" is a label rather than a property: a future edit
     * could give it the full feature list and the framework would receive the
     * dashboard again, which is what moving the publish target was for.
     */
    public function testASummaryIsAStrictSubsetOfItsFullView(): void
    {
        $manifest  = self::manifest();
        $pairs     = ['summary-roadrunner' => 'real-roadrunner', 'summary-fpm' => 'real-fpm'];
        $canonical = \AzeraCompetition\Report\BenchmarkConfig::featureOrder();

        foreach ($pairs as $summary => $full) {
            $summaryCharts = $manifest['views'][$summary]['charts'] ?? [];
            $fullCharts    = $manifest['views'][$full]['charts'] ?? [];

            self::assertSame(
                [],
                array_diff($summaryCharts, $fullCharts),
                "{$summary} must not draw a chart {$full} does not draw"
            );

            // The narrowing: strictly fewer feature races than the full view
            // draws (the full view names none, i.e. all of them).
            $keys = $manifest['views'][$summary]['feature_keys'] ?? [];
            self::assertNotSame([], $keys, "{$summary} must name its races");
            self::assertLessThan(
                count($canonical),
                count($keys),
                "{$summary} must draw fewer feature races than the canonical {$full} set"
            );

            // Observable form of the same claim: fewer SVGs on disk.
            $store  = ResultStore::load(dirname(__DIR__) . '/results/real-deployments.json');
            $fullMd = (new MarkdownReport($store, $full, $manifest['views'][$full]))
                ->render(sys_get_temp_dir() . "/tmp-fwpub-subset-{$full}", "svg/{$full}");
            $sumMd = (new MarkdownReport($store, $summary, $manifest['views'][$summary]))
                ->render(sys_get_temp_dir() . "/tmp-fwpub-subset-{$summary}", "svg/{$summary}");
            self::assertNotSame('', $fullMd);
            self::assertNotSame('', $sumMd);

            $fullCount = count(glob(sys_get_temp_dir() . "/tmp-fwpub-subset-{$full}/*.svg") ?: []);
            $sumCount  = count(glob(sys_get_temp_dir() . "/tmp-fwpub-subset-{$summary}/*.svg") ?: []);
            self::assertGreaterThan(
                $sumCount,
                $fullCount,
                "{$full} must publish more charts than {$summary}"
            );

            // The two deployment models are never blended: a summary carries the
            // same mode and dataset as the full view it summarises.
            self::assertSame($manifest['views'][$full]['mode'], $manifest['views'][$summary]['mode']);
            self::assertSame($manifest['views'][$full]['dataset'], $manifest['views'][$summary]['dataset']);
        }
    }

    /**
     * The feature narrowing is a real restriction, not decoration: the summary
     * asks for a subset of the canonical feature order.
     */
    public function testSummaryFeatureRacesAreANarrowedSubset(): void
    {
        $manifest = self::manifest();

        foreach (array_keys(self::EXPECTED_FILES) as $key) {
            $keys = $manifest['views'][$key]['feature_keys'] ?? [];
            self::assertNotSame([], $keys, "{$key} must name the races it draws");
            self::assertSame(
                [],
                array_diff($keys, \AzeraCompetition\Report\BenchmarkConfig::featureOrder()),
                "{$key} names a feature the harness does not measure"
            );
            self::assertContains(
                'features',
                $manifest['views'][$key]['charts'] ?? [],
                "{$key} narrows the feature list but does not draw the feature chart — "
                    . "the narrowing would be dead configuration"
            );
        }
    }

    /**
     * The summaries carry no startup chart, and the `GET /` race instead.
     *
     * Both halves matter, and they are ONE decision: the boot chart was removed
     * because it cannot be read on its own — its note block carries the no-op
     * rule ("several frameworks memoise their re-bootstrap") and its numbers
     * only mean something beside the deployment model — so what keeps the boot
     * question on the page is the routing race, drawn on the shared feature
     * axis at the same endpoint the probe was measured on.
     *
     * Asserted as an equivalence rather than two separate absences: a summary
     * that dropped the chart AND the race would be a legal-looking page with the
     * startup story missing entirely, and two independent assertions would both
     * pass for it.
     */
    public function testTheSummariesDropTheStartupChartAndDrawTheRoutingRace(): void
    {
        $manifest = self::manifest();

        foreach (array_keys(self::EXPECTED_FILES) as $key) {
            $charts = $manifest['views'][$key]['charts'] ?? [];
            $keys   = $manifest['views'][$key]['feature_keys'] ?? [];

            self::assertNotContains(
                'hero',
                $charts,
                "{$key} must not draw the startup chart — it was removed from the summaries on 2026-09-18"
            );
            self::assertContains(
                'routing',
                $keys,
                "{$key} must draw the GET / routing race, which is what carries the startup story now"
            );
        }
    }

    /**
     * The published pages and the published image directory agree.
     *
     * A chart file the page no longer references is worse than clutter: it is a
     * diagram of a section the page does not contain, sitting in another
     * repository where nobody can tell it is stale. Dropping the startup chart
     * left four such files in azera-framework (two per view) until the publish
     * step learned to sweep the directory before copying — see report.php.
     *
     * Asserted against the FRAMEWORK tree rather than this repository's own
     * svg/ directory, because that is the copy which persists: the local one is
     * swept by the renderer on every run.
     */
    public function testThePublishedImagesAreExactlyTheOnesThePagesReference(): void
    {
        $framework = dirname(__DIR__, 2) . '/azera-framework';
        if (!is_dir($framework)) {
            self::markTestSkipped('azera-framework is not checked out beside this repository');
        }

        foreach (array_keys(self::EXPECTED_FILES) as $key) {
            $md = (string) file_get_contents($framework . '/docs/' . self::EXPECTED_FILES[$key]);
            preg_match_all(
                '/!\[[^\]]*\]\([^)]*images\/benchmarks\/' . preg_quote($key, '/') . '\/([^)]+)\)/',
                $md,
                $m
            );
            $referenced = $m[1];
            sort($referenced);

            $onDisk = [];
            foreach (glob($framework . '/docs/images/benchmarks/' . $key . '/*.svg') ?: [] as $f) {
                $onDisk[] = basename($f);
            }
            sort($onDisk);

            self::assertNotSame([], $referenced, "{$key}'s published page must reference charts");
            self::assertSame(
                $referenced,
                $onDisk,
                "{$key}'s published image directory must hold exactly the files its page references — "
                    . 'an extra one is a stale chart of a section that no longer exists'
            );
        }
    }

    /**
     * The publish step SWEEPS the framework's image directory before copying.
     *
     * The test above asserts the OUTCOME, which is not enough on its own: it
     * passes whenever no stale file happens to be lying around, so deleting the
     * sweep would leave it green until the next time a view drops a chart —
     * exactly the situation that produced four orphaned startup.svg files in
     * azera-framework. Pinned at the source for the same reason
     * ChartManifestTest pins report.php's writtenCharts() call: the guard has to
     * be shown to exist, not merely to have worked once.
     *
     * Asserted as "an unlink inside the publish function", not by matching the
     * loop's exact spelling, so a refactor that keeps the behaviour still passes.
     */
    public function testThePublishStepSweepsTheTargetDirectoryBeforeCopying(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/report.php');

        $start = strpos($src, 'function publish(');
        self::assertNotFalse($start, 'the publish function must still exist');

        $body = substr($src, $start);
        self::assertStringContainsString(
            '@unlink(',
            $body,
            'the publish step must delete the target directory\'s stale charts before copying — '
                . 'nothing else removes them, and the directory lives in another repository'
        );
        self::assertStringContainsString(
            'glob($imagesDir',
            $body,
            'the sweep must be scoped to the view\'s own image directory'
        );
    }

    /**
     * A page never cross-references a chart it does not draw.
     *
     * Both renderers explain where the worker's recycle is measured, and both
     * used to name "the startup chart" unconditionally. That is a dead
     * reference on the two summaries, which draw no startup chart — the reader
     * is sent to a figure that is not on the page, and the sentence reads as a
     * leftover from a longer document.
     *
     * Asserted on the RENDERED pages of every view, so it holds whether the
     * naming is fixed in MarkdownReport (page prose + chart captions) or in
     * HtmlReport (the boot-model line). A source-level assertion would only
     * cover the half it looked at.
     */
    public function testNoPageNamesAChartItDoesNotDraw(): void
    {
        $manifest = self::manifest();
        $store    = ResultStore::load(dirname(__DIR__) . '/results/real-deployments.json');
        $checked  = 0;

        foreach ($manifest['views'] as $key => $view) {
            $charts = $view['charts'] ?? [];

            $md = (new MarkdownReport($store, $key, $view))
                ->render(sys_get_temp_dir() . "/tmp-fwpub-xref-{$key}", "svg/{$key}");
            $html = (new \AzeraCompetition\Report\HtmlReport($store, $manifest))
                ->view($key, $view, ['startup' => 'svg/' . $key . '/startup.svg']);

            if (!in_array('hero', $charts, true)) {
                self::assertStringNotContainsString(
                    'startup chart',
                    $md,
                    "{$key} draws no startup chart, so its page must not send the reader to one"
                );
                self::assertStringNotContainsString(
                    'startup chart',
                    $html,
                    "{$key} draws no startup chart, but its HTML boot note names one"
                );
            }
            $checked++;
        }

        self::assertSame(count($manifest['views']), $checked, 'every view must be exercised');
    }

    /**
     * The rendered page prints its outbound links, and every one is ABSOLUTE.
     *
     * A relative link would resolve in this repository's docs/benchmarks and
     * dangle in azera-framework/docs, which is where a published page is read.
     */
    public function testTheSummaryPagePrintsAbsoluteLinks(): void
    {
        $manifest = self::manifest();
        $view     = $manifest['views']['summary-fpm'];
        $store    = ResultStore::load(dirname(__DIR__) . '/results/real-deployments.json');
        $dir      = sys_get_temp_dir() . '/tmp-fwpub-fpm';

        $md = (new MarkdownReport($store, 'summary-fpm', $view))->render($dir, 'svg/summary-fpm');

        self::assertStringContainsString('**Full comparison**', $md, 'the page must state that it is a summary');

        foreach ($view['links'] as $label => $url) {
            self::assertStringContainsString(
                '<' . $url . '>',
                $md,
                "the page must link to {$label}"
            );
        }

        preg_match_all('/<([^>]+)>/', $md, $m);
        $urls = array_values(array_unique($m[1]));
        self::assertNotSame([], $urls, 'the links must be printed as urls');

        // Every printed target must be absolute — asserted on the RENDERED text
        // rather than against the manifest's own value, because reading the
        // expectation out of the view under test makes the check vacuous: a
        // mutation that turned the manifest's url relative was echoed straight
        // back by the first version of this test and passed.
        foreach ($urls as $url) {
            self::assertMatchesRegularExpression(
                '#^https://#',
                $url,
                "{$url} must be absolute — a relative link dangles in the framework repo"
            );
        }
        self::assertCount(
            count($view['links']),
            $urls,
            'every declared link must be printed, and nothing else'
        );

        // ...and the cross-link between the two models exists, so neither page
        // is a dead end.
        self::assertStringContainsString('view-summary-roadrunner.html', $md);
    }

    /**
     * A view's footer names the publish step exactly when that view publishes.
     *
     * The footer used to tell EVERY reader to run `report.php --publish=framework`,
     * which was both stale (the full views publish nothing now) and misleading:
     * it read as an instruction to publish the page being looked at.
     *
     * Asserted as an equivalence over ALL views rather than on one pair. The
     * first version rendered summary-fpm and a full view, so a mutation that
     * stripped `'publish'` from summary-ROADRUNNER only was never exercised and
     * survived — the harness caught that, which is the whole reason it exists.
     */
    public function testAPageNamesThePublishStepExactlyWhenItPublishes(): void
    {
        $manifest = self::manifest();
        $store    = ResultStore::load(dirname(__DIR__) . '/results/real-deployments.json');
        $checked  = 0;

        foreach ($manifest['views'] as $key => $view) {
            $md = (new MarkdownReport($store, $key, $view))
                ->render(sys_get_temp_dir() . "/tmp-fwpub-step-{$key}", "svg/{$key}");
            $publishes = in_array('framework', $view['publish'] ?? [], true);
            $mentions  = str_contains($md, '--publish=framework');

            self::assertSame(
                $publishes,
                $mentions,
                $publishes
                    ? "{$key} publishes, so its footer must state the --publish=framework step"
                    : "{$key} publishes nothing, so its footer must not instruct the reader to publish"
            );
            $checked++;
        }

        self::assertSame(count($manifest['views']), $checked, 'every view must be exercised');
    }
}
