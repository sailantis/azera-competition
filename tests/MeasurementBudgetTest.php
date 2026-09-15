<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The measurement budget is the harness' most load-bearing invariant.
 *
 * RoadRunner and PHP-FPM are two ways of running the SAME request, so the
 * comparison between them is only meaningful when both were measured with the
 * same sample. The published dataset violated that (a single-app RoadRunner
 * refresh at 1000x10 while the FPM block stayed at 300x5, because the FPM rows
 * are expensive to re-measure) and every downstream caption had to branch
 * around the mismatch.
 *
 * These tests pin the two halves of the fix: budget DETECTION must read what a
 * dataset actually recorded (including the older row-level shape), and budget
 * ENFORCEMENT must reject an asymmetric one while tolerating the unknown.
 */
final class MeasurementBudgetTest extends TestCase
{
    /**
     * One measured mode, shaped like http-bench.php writes it: the budget sits
     * at mode level AND on every request row.
     */
    private static function mode(int $iters, int $runs, bool $modeLevel = true): array
    {
        $rows = [];
        foreach (['GET /', 'GET /items'] as $label) {
            $rows[] = [
                'request'            => $label,
                'iterations_per_run' => $iters,
                'runs'               => $runs,
                'trimmed_mean_ms'    => 1.0,
            ];
        }

        $mode = ['requests' => $rows];
        if ($modeLevel) {
            $mode = ['iterations_per_run' => $iters, 'runs' => $runs] + $mode;
        }

        return $mode;
    }

    /** One app block with the given "<mode> => [iters, runs]" map. */
    private static function app(string $name, array $modes, bool $modeLevel = true): array
    {
        $out = [];
        foreach ($modes as $mode => [$iters, $runs]) {
            $out[$mode] = self::mode($iters, $runs, $modeLevel);
        }

        return ['app' => $name, 'modes' => $out];
    }

    /** A dataset with real apps and optional floor probes. */
    private static function dataset(array $apps, array $floors = [], bool $modeLevel = true): array
    {
        return [
            'apps'   => array_map(static fn(array $a): array => self::app($a[0], $a[1], $modeLevel), $apps),
            'floors' => array_map(static fn(array $f): array => self::app($f[0], $f[1], $modeLevel), $floors),
        ];
    }

    // --- detection ---------------------------------------------------------

    public function testReadsTheBudgetAtModeLevel(): void
    {
        $budget = budgetOfMode(self::mode(1000, 10));

        self::assertSame(['iters' => 1000, 'runs' => 10], $budget);
    }

    public function testFallsBackToTheFirstRowForOlderDatasets(): void
    {
        // Datasets written before the mode-level fields existed carry the
        // budget only on each request row.
        $budget = budgetOfMode(self::mode(300, 5, modeLevel: false));

        self::assertSame(['iters' => 300, 'runs' => 5], $budget, 'row-level budget must still be found');
    }

    public function testModeLevelWinsOverARowThatDisagrees(): void
    {
        // The mode-level value is what the whole block ran at; a stray row must
        // not be able to redefine it.
        $mode = self::mode(1000, 10);
        $mode['requests'][0]['iterations_per_run'] = 7;

        self::assertSame(['iters' => 1000, 'runs' => 10], budgetOfMode($mode));
    }

    public function testModeWithoutABudgetIsNull(): void
    {
        self::assertNull(budgetOfMode(['requests' => []]));
        self::assertNull(budgetOfMode([]));

        $rows = self::mode(1000, 10, modeLevel: false);
        unset($rows['requests'][0]['iterations_per_run'], $rows['requests'][0]['runs']);
        $rows['requests'] = [$rows['requests'][0]];
        self::assertNull(budgetOfMode($rows), 'a row without the fields is not a budget');
    }

    public function testCollectsBudgetsKeyedByAppAndMode(): void
    {
        $budgets = budgetsByAppMode(self::dataset(
            [
                ['azera', ['roadrunner' => [1000, 10], 'php-fpm' => [1000, 10]]],
                ['laravel', ['roadrunner' => [1000, 10]]]
            ],
            [['floor-php', ['php-fpm' => [1000, 10]]]]
        ));

        self::assertSame([
            'azera/roadrunner'   => ['iters' => 1000, 'runs' => 10],
            'azera/php-fpm'      => ['iters' => 1000, 'runs' => 10],
            'laravel/roadrunner' => ['iters' => 1000, 'runs' => 10],
            'floor-php/php-fpm'  => ['iters' => 1000, 'runs' => 10],
        ], $budgets, 'floor probes are part of the same invocation and must be checked too');
    }

    // --- enforcement -------------------------------------------------------

    public function testUniformBudgetPassesAndReportsTheSample(): void
    {
        $result = assertUniformBudget(budgetsByAppMode(self::dataset([
            ['azera', ['roadrunner' => [1000, 10], 'php-fpm' => [1000, 10]]],
            ['laravel', ['roadrunner' => [1000, 10], 'php-fpm' => [1000, 10]]],
        ])));

        self::assertTrue($result['ok']);
        self::assertSame('1000x10', $result['budget']);
    }

    public function testMixedBudgetIsRejected(): void
    {
        // The historical asymmetry: RR refreshed at 1000x10, FPM left at 300x5.
        $result = assertUniformBudget(budgetsByAppMode(self::dataset([
            ['azera', ['roadrunner' => [1000, 10], 'php-fpm' => [300, 5]]],
            ['laravel', ['roadrunner' => [1000, 10], 'php-fpm' => [300, 5]]],
        ])));

        self::assertFalse($result['ok'], 'two deployment models at different samples must not compare');
    }

    public function testReportsEveryOffendingLabelOnRejection(): void
    {
        // The operator has to see WHICH rows are off, not just that something is.
        $result = assertUniformBudget(budgetsByAppMode(self::dataset([
            ['azera', ['roadrunner' => [1000, 10], 'php-fpm' => [300, 5]]],
        ])));

        self::assertSame([
            'azera/roadrunner' => '1000x10',
            'azera/php-fpm'    => '300x5',
        ], $result['byAppMode']);
    }

    public function testDifferentRunCountsAtTheSameIterationsAreStillAMismatch(): void
    {
        // 1000x10 vs 1000x20 is still two different samples.
        $result = assertUniformBudget(budgetsByAppMode(self::dataset([
            ['azera', ['roadrunner' => [1000, 20]]],
            ['laravel', ['roadrunner' => [1000, 10]]],
        ])));

        self::assertFalse($result['ok']);
    }

    public function testUniformityIsOrderIndependent(): void
    {
        $result = assertUniformBudget(budgetsByAppMode(self::dataset([
            ['azera', ['php-fpm' => [1000, 10], 'roadrunner' => [1000, 10]]],
        ])));

        self::assertTrue($result['ok']);
        self::assertSame('1000x10', $result['budget']);
    }

    public function testUnknownBudgetsAreIgnoredRatherThanTreatedAsAMismatch(): void
    {
        // "unknown" is not "different": a dataset whose rows predate the field
        // must not be rejected for it, or every legacy file becomes unusable.
        $result = assertUniformBudget(budgetsByAppMode([
            'apps' => [
                self::app('azera', ['roadrunner' => [1000, 10]]),
                ['app' => 'legacy', 'modes' => ['roadrunner' => ['requests' => [['request' => 'GET /']]]]],
            ],
        ]));

        self::assertTrue($result['ok']);
        self::assertSame('1000x10', $result['budget'], 'the known rows still describe the sample');
    }

    public function testEmptyBudgetSetIsUniformButHasNoBudget(): void
    {
        $result = assertUniformBudget([]);

        self::assertTrue($result['ok'], 'nothing to disagree');
        self::assertNull($result['budget'], 'and no sample to claim');
    }

    // --- the caption the report prints --------------------------------------

    public static function budgetLabelShape(): array
    {
        return [
            'iters and runs' => ['1000x10', '1000 iterations per run over 10 runs'],
            'single run'     => ['1000x1', '1000 iterations per run over 1 runs'],
        ];
    }

    #[DataProvider('budgetLabelShape')]
    public function testLabelSpellsOutBothDimensions(string $stamp, string $expected): void
    {
        // The caption is built by ResultStore from the same "iters x runs" stamp
        // the guards produce, so the two can never drift.
        self::assertSame($expected, \AzeraCompetition\Report\ResultStore::budgetLabelFromStamp($stamp));
    }

    public function testLabelRejectsAnUnparsableStamp(): void
    {
        // A stamp the report cannot read must yield no caption rather than a
        // half-parsed number.
        self::assertNull(\AzeraCompetition\Report\ResultStore::budgetLabelFromStamp('1000x'));
        self::assertNull(\AzeraCompetition\Report\ResultStore::budgetLabelFromStamp('1000'));
        self::assertNull(\AzeraCompetition\Report\ResultStore::budgetLabelFromStamp(''));
    }
}