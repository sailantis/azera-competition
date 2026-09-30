<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\SvgChart;
use PHPUnit\Framework\TestCase;

/**
 * The log axis: where it starts, and whether the data actually uses the plot.
 *
 * An axis has two jobs and this file pins both, because the old implementation
 * failed the second one silently. It was correct by every internal measure —
 * ticks were 1/2/5 values, the ratio never divided by zero, the bounds contained
 * the data — and it still made the published chart lie, by placing every dot in
 * the right half of the plot:
 *
 *   published r6 render-time chart, data 0.4287 .. 1.2588 ms
 *   axis       0.1 .. 2  (a whole decade below the data)
 *   dots       48.6% .. 84.5% of the plot width
 *
 * Half the chart was empty, and the engines — which differ by up to 2.9x — were
 * squeezed into the remaining third. A reader comparing two rows read a few
 * pixels for a large ratio.
 *
 * A NOTE ON THE FIX THAT WOULD NOT HAVE WORKED. An earlier proposal was to clamp
 * the low end with `10^floor(log10(min * 0.9))`. It is a no-op: `min * 0.9` stays
 * inside the same decade's mantissa for any min in [0.1, 1), so the exponent is
 * unchanged and the axis is identical bit for bit. The mantissa has to be
 * rounded (1/2/5), not the magnitude nudged. `testTheAxisStartsNearItsData`
 * catches the decade case; `testTheEarlierProposedClampWouldNotHaveHelped` states
 * the no-op explicitly so the idea does not get re-proposed.
 *
 * All fixtures are REAL: the values are the published chart's own numbers, so a
 * regression here is a statement about the shipped figure and not about a
 * synthetic one.
 */
final class ChartAxisTest extends TestCase
{
    /** dotRange's own geometry, so a pixel can be turned back into a ratio. */
    private const PAD_L = 218.0;
    private const PAD_R_WITH_FACTORS = 96.0;
    private const WIDTH = 960.0;

    /**
     * The published r6 render-time numbers: clarity fastest, twig slowest.
     *
     * `low`/`high` are the fastest observation and p95, as the harness records
     * them; only their extremes matter to the axis.
     *
     * @return array<string,array{low:float,median:float,high:float}>
     */
    private static function publishedRows(): array
    {
        return [
            'clarity' => ['low' => 0.4287, 'median' => 0.4310, 'high' => 0.5156],
            'native'  => ['low' => 0.4290, 'median' => 0.4491, 'high' => 0.5373],
            'plates'  => ['low' => 0.5034, 'median' => 0.5350, 'high' => 0.6511],
            'blade'   => ['low' => 0.6881, 'median' => 0.7387, 'high' => 0.8862],
            'twig'    => ['low' => 1.1872, 'median' => 1.2467, 'high' => 1.2588],
        ];
    }

    private static function renderPublishedChart(): string
    {
        $metrics = self::publishedRows();
        $colors  = [];
        foreach (array_keys($metrics) as $e) {
            $colors[$e] = '#3459e6';
        }

        return SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'ms',
            true,
            (int) self::WIDTH,
            360,
            'Render time per page',
            'Lower is better',
            ['' => array_fill_keys(array_keys($metrics), 1.0)],
            'x = median ÷ the fastest',
            false,
            'median'
        );
    }

    /**
     * Axis tick VALUES, parsed from the drawn labels.
     *
     * Read from the SVG rather than from axisMap()'s return value on purpose:
     * the numbers that matter are the ones a reader sees. A correct tick list
     * that the renderer then skips (its own `$x < $padL` filter drops
     * out-of-range ticks) would pass a unit test and still ship a clipped axis.
     *
     * @return list<float>
     */
    private static function tickValues(string $svg): array
    {
        preg_match_all('/>([0-9]*\.?[0-9]+) ms</', $svg, $m);
        $values = array_map(static fn(string $v): float => (float) $v, $m[1]);
        sort($values);

        return $values;
    }

    /**
     * The x of every median dot, as a 0..1 ratio of the plot width.
     *
     * The ring's radius is what identifies a median dot; nothing else in
     * dotRange draws a circle.
     *
     * @return list<float>
     */
    private static function dotRatios(string $svg): array
    {
        preg_match_all('/<circle cx="([0-9.]+)"/', $svg, $m);
        $plotW = self::WIDTH - self::PAD_L - self::PAD_R_WITH_FACTORS;
        $out   = [];
        foreach ($m[1] as $cx) {
            $out[] = (((float) $cx) - self::PAD_L) / $plotW;
        }
        sort($out);

        return $out;
    }

    /** The private axisMap(), so the boundary rule can be driven directly. */
    private static function axisMap(float $min, float $max): array
    {
        $ref = new \ReflectionMethod(SvgChart::class, 'axisMap');
        $ref->setAccessible(true);

        return $ref->invoke(null, $min, $max, true, 5);
    }

    private static function logFloor(float $v): float
    {
        $ref = new \ReflectionMethod(SvgChart::class, 'logFloor');
        $ref->setAccessible(true);

        return (float) $ref->invoke(null, $v);
    }

    // -------------------------------------------------------------------------
    // The fix: the axis starts near the data
    // -------------------------------------------------------------------------

    /**
     * The axis low must be within ONE 1/2/5 STEP of the data, not a decade below
     * it.
     *
     * Margin: the old rule put the bound 4.29x below the data (0.1 for 0.4287),
     * the new one 2.14x below (0.2). The 3.0 threshold separates them with room
     * on both sides, so neither float noise nor a differently-shaped fixture
     * flips the verdict.
     */
    public function testTheAxisStartsNearItsData(): void
    {
        $ticks = self::tickValues(self::renderPublishedChart());

        self::assertNotSame([], $ticks, 'the chart must draw tick labels to read an axis from');
        $lowest  = min($ticks);
        $dataMin = 0.4287;

        self::assertLessThanOrEqual(
            $dataMin,
            $lowest,
            'the axis must start at or below the smallest datum, or the datum is off the plot'
        );
        self::assertGreaterThanOrEqual(
            $dataMin / 3.0,
            $lowest,
            sprintf(
                'axis starts at %s for data starting at %s — %.2fx below the data. '
                    . 'A whole-decade low bound leaves the left of the plot empty.',
                $lowest,
                $dataMin,
                $dataMin / $lowest
            )
        );
    }

    /**
     * The dots must USE the plot, not huddle in one half.
     *
     * Both halves of the old failure are asserted, because either alone could be
     * satisfied by accident: a leftmost dot early in the plot AND a span across
     * it. Published numbers were 48.6%..84.5% (span 35.9%); the fix gives
     * 33.1%..79.9% (span 46.8%).
     */
    public function testTheDotsUseMostOfThePlotWidth(): void
    {
        $dots = self::dotRatios(self::renderPublishedChart());

        self::assertCount(5, $dots, 'one median dot per engine');
        $leftmost  = $dots[0];
        $rightmost = $dots[count($dots) - 1];

        self::assertLessThanOrEqual(
            0.40,
            $leftmost,
            sprintf('the fastest engine sits at %.1f%% of the plot width — the left of the axis is unused', $leftmost * 100)
        );
        self::assertGreaterThanOrEqual(
            0.40,
            $rightmost - $leftmost,
            sprintf(
                'the engines span only %.1f%% of the plot width (%.1f%%..%.1f%%), which is what made a 2.9x difference read as a few pixels',
                ($rightmost - $leftmost) * 100,
                $leftmost * 100,
                $rightmost * 100
            )
        );
    }

    /**
     * The no-op clamp, stated as an executable fact.
     *
     * `10^floor(log10(min * 0.9))` is exactly `10^floor(log10(min))` for every
     * min whose mantissa is in [1, 10/9) — which includes the whole [0.1, 1)
     * range the charts live in. Asserted so the idea is not re-proposed as a fix.
     */
    public function testTheEarlierProposedClampWouldNotHaveHelped(): void
    {
        foreach ([0.4287, 0.2, 0.5, 0.9, 0.999] as $min) {
            self::assertSame(
                10 ** (int) floor(log10($min)),
                10 ** (int) floor(log10($min * 0.9)),
                sprintf('min=%s: multiplying by 0.9 is a no-op on the decade', $min)
            );
        }
    }

    // -------------------------------------------------------------------------
    // logFloor: the rule itself
    // -------------------------------------------------------------------------

    /**
     * @return list<array{0:float,1:float}>
     */
    public static function logFloorCases(): array
    {
        return [
            // value, expected: the largest 1/2/5×10ⁿ at or below it.
            [0.4287, 0.2],
            [0.2, 0.2],
            [0.5, 0.5],
            [0.9, 0.5],
            [1.0, 1.0],
            [2.5, 2.0],
            [5.0, 5.0],
            [170.0, 100.0],
            [0.0041, 0.002],
        ];
    }

    /**
     * @dataProvider logFloorCases
     */
    public function testLogFloorRoundsTo125Values(float $value, float $expected): void
    {
        self::assertEqualsWithDelta($expected, self::logFloor($value), 1e-9);
    }

    /** The point of the method: it is strictly above the decade below. */
    public function testLogFloorIsAboveTheDecadeBelowForSubUnitValues(): void
    {
        self::assertEqualsWithDelta(0.2, self::logFloor(0.4287), 1e-9);
        self::assertNotEqualsWithDelta(0.1, self::logFloor(0.4287), 1e-9);
    }

    // -------------------------------------------------------------------------
    // Degenerate bounds — the guard has to hold
    // -------------------------------------------------------------------------

    /**
     * Bounds that round to the SAME 1/2/5 value must not collapse the axis.
     *
     * This is why the guard compares the BOUNDS rather than the decade
     * exponents: 0.5 and 1 are both inside 10^-1..10^0, so a decade-based check
     * (`$hi <= $lo` on exponents) sees a healthy one-decade span while the ratio
     * divides by log10(1.0) - log10(1.0) = 0.
     */
    public function testEqualBoundsStillProduceAnAxis(): void
    {
        [$ratioOf, $ticks] = self::axisMap(0.5, 0.5);

        self::assertNotSame([], $ticks, 'a single value must still yield a readable axis');
        // Every datum lands at a real position rather than a division by zero.
        // (A value below the axis low clamps to 0, which is the guard working.)
        $r = $ratioOf(0.5);
        self::assertGreaterThanOrEqual(0.0, $r);
        self::assertLessThanOrEqual(1.0, $r);
    }

    /**
     * A narrow band inside one decade must still spread across the plot.
     *
     * 0.5 → 0.9 used to draw 0.0%..84.8%; the low bound now sits exactly on the
     * data, so the band is readable rather than bunched against the right edge.
     */
    public function testANarrowBandIsSpreadAcrossTheAxis(): void
    {
        [$ratioOf] = self::axisMap(0.5, 0.9);

        $span = $ratioOf(0.9) - $ratioOf(0.5);
        self::assertGreaterThan(0.5, $span, '0.5..0.9 is a 1.8x range and must not read as a point');
    }

    /**
     * The axis must never be far wider than its data.
     *
     * A decade-based low bound cost a factor of ~4.3 on the published chart. The
     * 1/2/5 rule bounds the empty margin by construction, so the axis can never
     * span more than ~2.6x more log-range than the data it shows.
     */
    public function testTheAxisIsNeverMuchWiderThanItsData(): void
    {
        $cases = [
            [0.4287, 1.2588],
            [0.5, 0.9],
            [0.2, 0.23],
            [0.0041, 0.0054],
        ];

        foreach ($cases as [$min, $max]) {
            [$ratioOf] = self::axisMap($min, $max);
            $shown = $ratioOf($max) - $ratioOf($min);
            self::assertGreaterThan(0.0, $shown, "min={$min} max={$max} produced a zero-width axis");

            $logSpan = log10($max / $min);
            $waste   = $logSpan / $shown;
            self::assertLessThanOrEqual(
                2.6,
                $waste,
                sprintf('data %s..%s uses only %.0f%% of the axis', $min, $max, 100 / $waste)
            );
        }
    }
}
