<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The startup section is called "Framework startup" — no endpoint qualifier.
 *
 * It used to read "Framework startup GET /", because GET / was the request
 * whose startup the harness happened to time first. That qualifier became
 * wrong once the number was measured directly rather than inferred from a
 * request: the boot probe times "PHP start → framework ready" in the running
 * deployment (see boot-probe.php), and the CLI harness times bootstrap()
 * itself. Neither measurement is a property of GET / — the endpoint was only
 * ever where the sample was taken.
 *
 * The qualifier was also actively misleading next to the chart: the chart
 * draws THREE boot bands on a harness dataset (cold / FPM rebuild / warm
 * recycle), none of which belongs to one endpoint, so a heading naming GET /
 * described a different scope from the figure under it.
 *
 * MarkdownReport emits this heading from THREE mutually exclusive paths, and
 * all three must agree:
 *
 *   bootChart() probed branch   — a real deployment (RoadRunner / PHP-FPM),
 *                                 one band measured by the boot probe.
 *   bootChart() harness branch  — the CLI harness's three bands
 *                                 (cold + FPM rebuild + warm recycle).
 *   legacyStartup()             — the pre-boot-timing fallback, which plots
 *                                 warm GET / dispatch directly, and whose
 *                                 CHART is captioned with the endpoint it
 *                                 really does measure.
 *
 * The legacy path keeps the endpoint in its chart because that path genuinely
 * plots a request; its section heading still uses the shared name, so the
 * page's anchors do not depend on which path ran.
 */
final class StartupHeadingTest extends TestCase
{
    private const HEADING = '## Framework startup';

    private static function source(): string
    {
        $path = dirname(__DIR__) . '/scripts/report/MarkdownReport.php';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testEveryStartupSectionEmitterUsesTheSharedHeading(): void
    {
        $src = self::source();

        // One emitter per report path; a new path that invents its own heading
        // fails this count rather than silently rendering a second name.
        self::assertSame(
            3,
            substr_count($src, self::HEADING . '\n\n"'),
            'all three startup emitters (probed, harness bands, legacy) must use the same heading'
        );
    }

    /**
     * The endpoint qualifier must not come back.
     *
     * It described the sampling site as if it were the measurement, and it
     * contradicted the multi-band chart drawn under it. Pinned as an absence
     * rather than by banning one exact string, so a future rename to another
     * endpoint ("GET /items") fails here too — the problem is the qualifier,
     * not the specific route.
     */
    public function testTheStartupHeadingNamesNoEndpoint(): void
    {
        $src = str_replace("\r\n", "\n", self::source());

        preg_match_all('/"## Framework startup[^"]*"/', $src, $m);
        self::assertNotSame([], $m[0], 'the startup heading must still be emitted as a literal');

        foreach ($m[0] as $literal) {
            self::assertSame(
                '"## Framework startup\n\n"',
                $literal,
                'the startup heading must not carry an endpoint qualifier — the boot is not a property of one route'
            );
        }
    }

    public function testHeadingStillMatchesTheSectionTheTestsAndDocsGrepFor(): void
    {
        $src = self::source();

        // The heading is also a stable anchor for tooling (temp/ helpers and
        // task definitions grep for the bare prefix). It is now the exact
        // heading, which is what those bare-prefix greps were reaching for.
        self::assertStringContainsString('## Framework startup', $src);
    }
}
