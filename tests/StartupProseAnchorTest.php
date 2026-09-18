<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The startup prose must not be anchored on a memoised re-bootstrap — and,
 * since the report was made figure-free, must not reprint a reading at all.
 *
 * Three frameworks re-bootstrap in microseconds because the compiled wiring
 * survives the recycle: azera returns its already-wired AppContext
 * (Bootstrap::boot() memoises per dbPath), CodeIgniter resets request-scoped
 * state only, CakePHP re-uses its loaded classes. Measured on the bench VM that
 * is 0.001-0.007 ms, against Symfony's 0.6 ms and Spiral's 15 ms.
 *
 * The CHART refuses to anchor its factor column on those rows (it requires a
 * median >= 0.1 ms) and leaves them unlabelled. The prose must do the same
 * thing in WORDS: state the no-op rule and leave the readings to the chart. It
 * used to print the winner's median, the loser's median and the factor between
 * them — numbers a re-measure leaves behind, because nothing regenerates a
 * sentence. These assertions pin both halves: the threshold is shared, and the
 * prose path carries no figure.
 */
final class StartupProseAnchorTest extends TestCase
{
    private static function source(): string
    {
        $path = dirname(__DIR__) . '/scripts/report/MarkdownReport.php';
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    public function testProseSeparatesMemoisedRebootsFromRealRebuilds(): void
    {
        $src = self::source();

        // The prose path must partition the race rather than take the raw
        // minimum, or the memoised rows silently become the scale.
        self::assertStringContainsString('$rebuilt = [];', $src, 'prose must partition the race into real rebuilds');
        self::assertStringContainsString('$noop    = [];', $src, 'prose must track the memoised re-bootstrap rows');
        self::assertStringContainsString(
            "\$m['median'] >= 0.1",
            $src,
            'the same 0.1 ms threshold the chart uses must gate the prose partition'
        );
    }

    public function testMemoisedRowsAreNamedInTheProseInsteadOfHidden(): void
    {
        $src = self::source();

        // Dropping the rows silently would misrepresent the band; they are
        // reported with the reason, so a reader sees the design difference —
        // and told why the chart gives them no factor.
        self::assertStringContainsString('keep their compiled wiring across a recycle', $src);
        self::assertStringContainsString("left out of the chart's factor column", $src);
    }

    public function testAllMemoisedBandStillRendersSomething(): void
    {
        $src = self::source();

        // If every framework memoises, $rebuilt is empty and there is no
        // factor to print — the branch must still produce a sentence rather
        // than an empty bullet.
        self::assertStringContainsString(
            'Every framework re-bootstraps in microseconds on this deployment',
            $src,
            'an all-memoised band must explain itself instead of rendering an empty factor'
        );
    }

    public function testChartAndProseShareTheThresholdRatherThanDriftingApart(): void
    {
        $src = self::source();

        // Two independent copies of the constant is exactly how the chart and
        // the prose drifted in the first place. Both sites must test 0.1.
        self::assertSame(
            2,
            substr_count($src, "\$m['median'] >= 0.1"),
            'the no-op threshold must appear in both the chart factor loop and the prose partition'
        );
    }

    /**
     * The probed startup branch names no reading.
     *
     * This is the whole point of the figure-free report: every measured value
     * lives in a chart or a table, both of which are regenerated from the
     * dataset. The prose states the measurement MODEL. A formatter call in the
     * probed branch is how a stale figure gets back in — it was SvgChart::fmt()
     * and SvgChart::fmtFactor() that used to print the winner and the factor.
     */
    public function testTheProbedStartupProsePrintsNoFigures(): void
    {
        // The repo is CRLF, so every multi-line needle must be matched against
        // a normalised copy — a "\n" literal never appears in the file itself.
        $src = str_replace("\r\n", "\n", self::source());

        // Isolate the probed branch: from its prose switch to the harness-band
        // fallback that follows it.
        $start = strpos($src, "if (\$probed) {\n            \$race =");
        self::assertNotFalse($start, 'the probed prose branch must still exist');
        $end = strpos($src, '// Prose: the cold race', $start);
        self::assertNotFalse($end, 'the harness-band prose must still follow the probed branch');

        $branch = substr($src, $start, $end - $start);

        self::assertStringNotContainsString(
            'SvgChart::fmt(',
            $branch,
            'the probed startup prose must state the model, not reprint a measurement'
        );
        self::assertStringNotContainsString(
            'SvgChart::fmtFactor(',
            $branch,
            'the factor belongs to the chart, which draws it beside the row it describes'
        );
    }
}
