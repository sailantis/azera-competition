<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The startup section heading names the endpoint it is anchored on.
 *
 * The section is called "Framework startup GET /" because GET / is the request
 * whose startup the number describes — the same endpoint every other chart in
 * the report keys its baseline to. Without the endpoint in the heading a
 * reader cannot tell whether the boot shown belongs to the bare route, to a
 * database-backed endpoint, or to the harness as a whole.
 *
 * MarkdownReport emits this heading from THREE mutually exclusive paths, and
 * all three must agree:
 *
 *   bootChart() probed branch   — a real deployment (RoadRunner / PHP-FPM),
 *                                 one band measured by the boot probe.
 *   bootChart() harness branch  — the CLI harness's three bands
 *                                 (cold + FPM rebuild + warm recycle).
 *   legacyStartup()             — the pre-boot-timing fallback, which plots
 *                                 warm GET / dispatch directly (and its chart
 *                                 title already said "GET /").
 *
 * The legacy path already carried the endpoint in its chart title while the
 * heading did not, so the two disagreed on the same page. These assertions
 * pin the heading text and the fact that every emitter uses it.
 */
final class StartupHeadingTest extends TestCase
{
    private const HEADING = '## Framework startup GET /';

    private static function source(): string
    {
        $path = dirname(__DIR__) . '/scripts/report/MarkdownReport.php';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testEveryStartupSectionEmitterUsesTheEndpointQualifiedHeading(): void
    {
        $src = self::source();

        // One emitter per report path; a new path that forgets the endpoint
        // fails this count rather than silently rendering a bare heading.
        self::assertSame(
            3,
            substr_count($src, self::HEADING . '\n\n"'),
            'all three startup emitters (probed, harness bands, legacy) must use the GET / heading'
        );
    }

    public function testNoStartupSectionStillUsesTheBareHeading(): void
    {
        $src = self::source();

        // The unqualified form must not survive anywhere — it is the string
        // that made the legacy path contradict its own chart title.
        self::assertStringNotContainsString(
            '"## Framework startup\n\n"',
            $src,
            'no emitter may still print the bare "Framework startup" heading'
        );
    }

    public function testHeadingStillMatchesTheSectionTheTestsAndDocsGrepFor(): void
    {
        $src = self::source();

        // The heading is also a stable anchor for tooling (temp/ helpers and
        // task definitions grep for it). Keep the prefix intact so those
        // lookups keep resolving.
        self::assertStringContainsString('## Framework startup GET /', $src);
        self::assertStringContainsString('## Framework startup', $src);
    }
}
