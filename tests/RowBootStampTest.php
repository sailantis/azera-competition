<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A per-request row's `boot_ms` and the boot probe for its mode are ONE measurement
 * recorded in two places, and they must agree.
 *
 * WHY THIS IS A TEST. On 2026-11-24 the boot band was re-measured against pruned
 * autoloaders (Azera 0.2754 -> 0.0680 ms) and the refreshed mode-level block reached
 * the dataset while every per-request row kept the stamp from the original run. All
 * seven apps carried a `boot_ms` that disagreed with the probe it claimed to come
 * from — Azera's by 305%. Nothing noticed, because the report reads the mode-level
 * block on the probed path while ResultStore's lifecycle sub-line and
 * fpmBootMedian() read the row. The same dataset would have printed two different
 * boots for one framework, in two places, and called both of them measured.
 *
 * The invariant is per-request only. A resident arm boots before it serves, so its
 * rows must carry no stamp at all — ProbeTransportTest::testBootMillisIsGatedOnThe
 * ProbeKindAndNotTheServerName() is what pins that half.
 */
final class RowBootStampTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/scripts/bench-lib.php';
    }

    /**
     * The exact 2026-11-24 shape is rejected: a refreshed probe beside stale stamps.
     *
     * These are the dataset's real numbers for two apps, one where the merge refreshed
     * the probe and one where it happened to move less. Both are wrong by the same
     * criterion, which is the point — the check is about agreement, not magnitude.
     */
    public function testARowStampThatDisagreesWithItsProbeIsRejected(): void
    {
        $result = assertRowBootStampsMatchProbe([
            'apps' => [
                self::app('azera', 'php-fpm', 0.2754, 0.0680),
                self::app('symfony', 'php-fpm', 1.1452, 0.8979),
            ],
        ]);

        self::assertFalse($result['ok'], 'a row stamp must equal the probe median it came from');
        self::assertCount(2, $result['violations']);
        self::assertSame(2, $result['checked'], 'both stamps must be counted as checked');

        // Worst offender first, so the operator reads the real breakage first.
        self::assertSame('azera', $result['violations'][0]['app']);
        self::assertSame(0.2754, $result['violations'][0]['stamp']);
        self::assertSame(0.0680, $result['violations'][0]['probe']);
    }

    /**
     * An agreeing pair passes — including the rounding a re-merge could introduce.
     *
     * The two numbers are the same statistic from the same run, so they should be
     * identical. The 2% band exists only so a future merge that recomputes a median
     * from a slightly different subset does not fail on the last digit.
     */
    public function testAnAgreeingStampInsideRoundingPasses(): void
    {
        $result = assertRowBootStampsMatchProbe([
            'apps' => [self::app('azera', 'php-fpm', 0.0680, 0.0680)],
        ]);
        self::assertTrue($result['ok']);

        // 1% apart: inside the default band, because these are one measurement.
        $result = assertRowBootStampsMatchProbe([
            'apps' => [self::app('azera', 'php-fpm', 0.0687, 0.0680)],
        ]);
        self::assertTrue($result['ok'], 'a 1% difference is rounding, not a stale merge');
    }

    /**
     * The tolerance is a knob, and the default must still catch the real regression.
     *
     * A caller may widen it for a noisier probe, but widening must be explicit — the
     * default is what the 2026-11-24 dataset had to fail.
     */
    public function testTheToleranceIsHonoured(): void
    {
        $dataset = ['apps' => [self::app('symfony', 'php-fpm', 1.1452, 0.8979)]];

        self::assertFalse(assertRowBootStampsMatchProbe($dataset, 0.20)['ok'], '27.5% is outside a 20% band');
        self::assertTrue(assertRowBootStampsMatchProbe($dataset, 0.30)['ok'], 'a wider band is honoured');
        self::assertFalse(assertRowBootStampsMatchProbe($dataset)['ok'], 'the default must still reject it');
    }

    /**
     * A resident arm carries no stamp, so there is nothing to contradict.
     *
     * This is the half of the contract ProbeTransportTest does not cover: the check
     * must SKIP these rows rather than compare them to a warm-recycle boot that was
     * never inside their clock.
     */
    public function testAStamplessResidentArmIsSkipped(): void
    {
        $dataset = [
            'apps' => [[
                'app'                 => 'azera',
                'modes'               => ['roadrunner' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => [
                    ['request' => 'GET /', 'trimmed_mean_ms' => 0.18],
                ]]],
                'boot_samples_by_mode' => ['roadrunner' => ['median' => 0.0017]],
            ]],
        ];

        $result = assertRowBootStampsMatchProbe($dataset);
        self::assertTrue($result['ok']);
        self::assertSame(0, $result['checked'], 'no stamp means no claim to check');
    }

    /**
     * A dataset with no boot probe is skipped, not failed.
     *
     * "No probe" and "probe contradicts the row" are different states, and only the
     * second is this check's business — the same distinction the floor guard draws
     * about a missing floor.
     */
    public function testAModeWithoutAProbeIsSkipped(): void
    {
        $dataset = [
            'apps' => [[
                'app'   => 'azera',
                'modes' => ['php-fpm' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => [
                    ['request' => 'GET /', 'trimmed_mean_ms' => 0.5, 'boot_ms' => 0.4],
                ]]],
            ]],
        ];

        $result = assertRowBootStampsMatchProbe($dataset);
        self::assertTrue($result['ok']);
        self::assertSame(0, $result['checked'], 'nothing to hold the stamp against');
    }

    /**
     * Rows of one mode sharing a stamp is the normal shape; a mode whose rows DISAGREE
     * is reported per distinct value rather than silently taking the first.
     *
     * http-bench.php stamps one value across every row of the mode, so a mixed mode is
     * already corrupt. Reporting each value keeps it visible.
     */
    public function testADisagreeingModeReportsEveryDistinctStamp(): void
    {
        $result = assertRowBootStampsMatchProbe([
            'apps' => [[
                'app'                 => 'azera',
                'modes'               => ['php-fpm' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => [
                    ['request' => 'GET /', 'trimmed_mean_ms' => 0.5, 'boot_ms' => 0.2754],
                    ['request' => 'GET /echo', 'trimmed_mean_ms' => 0.4, 'boot_ms' => 0.3000],
                ]]],
                'boot_samples_by_mode' => ['php-fpm' => ['median' => 0.0680]],
            ]],
        ]);

        self::assertFalse($result['ok']);
        self::assertCount(2, $result['violations'], 'both distinct stamps are violations');
        self::assertSame(2, $result['checked']);
    }

    /**
     * Every writer that can move a probe and a stamp independently must check them.
     *
     * The guard is only worth anything if the paths that write a dataset call it, so
     * the SOURCE is asserted rather than a re-implementation. `merge-boot.php` is the
     * specific path that produced the divergence, and `assemble-real.php` is the
     * assembler that combines per-app fragments into the canonical file.
     */
    public function testEveryBootWriterAppliesTheStampGuard(): void
    {
        $root = dirname(__DIR__);

        foreach (['scripts/merge-boot.php', 'scripts/merge-modes.php', 'scripts/assemble-real.php', 'scripts/run-http.php'] as $relative) {
            $src = (string) file_get_contents("{$root}/{$relative}");
            self::assertStringContainsString(
                'assertRowBootStampsMatchProbe(',
                $src,
                "{$relative} can write a boot probe beside measured rows and must reject a stale stamp"
            );
        }

        // merge-boot.php did not even load the library that owns the check.
        $merge = (string) file_get_contents($root . '/scripts/merge-boot.php');
        self::assertStringContainsString("require_once __DIR__ . '/bench-lib.php';", $merge);
    }

    /**
     * The canonical dataset satisfies the invariant.
     *
     * This is the assertion that goes red until the stale stamps are re-measured, and
     * it is deliberately in the suite rather than left to the next operator: the
     * dataset on disk is what the report renders, so a dataset that fails here renders
     * two different boots for one framework.
     */
    public function testTheCanonicalDatasetStampsAgreeWithItsProbes(): void
    {
        $path = dirname(__DIR__) . '/results/real-deployments.json';
        self::assertFileExists($path);

        $dataset = (array) json_decode((string) file_get_contents($path), true);
        $result  = assertRowBootStampsMatchProbe($dataset);

        self::assertTrue(
            $result['ok'],
            "the canonical dataset reports a row boot its probe contradicts:\n"
                . describeRowBootStampViolations($result)
        );
    }

    /** One app block with a stamped mode and the probe it should have come from. */
    private static function app(string $key, string $mode, float $stamp, float $probeMedian): array
    {
        return [
            'app'                  => $key,
            'modes'                => [
                $mode => [
                    'iterations_per_run' => 1000,
                    'runs'               => 10,
                    'requests'           => [
                        ['request' => 'GET /', 'trimmed_mean_ms' => 0.5, 'boot_ms' => $stamp],
                        ['request' => 'GET /echo', 'trimmed_mean_ms' => 0.4, 'boot_ms' => $stamp],
                    ],
                ],
            ],
            'boot_samples_by_mode' => [$mode => ['count' => 50, 'median' => $probeMedian]],
        ];
    }
}
