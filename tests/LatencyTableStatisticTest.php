<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use AzeraCompetition\Report\Tables;
use PHPUnit\Framework\TestCase;

/**
 * The latency table and the chart beside it must publish the SAME number for a cell.
 *
 * WHY THIS IS A TEST. They did not. The table printed the trimmed mean and the charts
 * drew the median, under two different legends on the same page, so `GET /` on
 * volucer-attach read 0.198 in the table and 0.191 in the chart directly above it.
 * Reported 2026-11-25 by a reader who compared the two and could not tell which was
 * the measurement.
 *
 * The table now prints the median, because the median is the statistic a chart can
 * SHOW: it is one of the plotted points, so the dot on a bar IS the published number,
 * and the whisker brackets it with values that also exist in the sample. See
 * ResultStore::medianWithBoot().
 *
 * The second half of the contract is the tie band. A resident arm puts a few
 * microseconds of framework inside a ~160 us row that is almost entirely the server,
 * so a small gap there is inside the spread of two runs of the SAME code and its sign
 * flips. Bolding one entrant as the winner on such a row is how an 8 us difference
 * with a ~180 us span was read as a defeat.
 */
final class LatencyTableStatisticTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';
        require_once dirname(__DIR__) . '/scripts/report/ResultStore.php';
        require_once dirname(__DIR__) . '/scripts/report/Tables.php';
    }

    /**
     * The table prints the median, not the trimmed mean.
     *
     * Both figures are in the dataset and they differ by a few percent, so a table
     * that prints one while the chart draws the other is not detectable by eyeballing
     * a single number — it needs the pair.
     */
    public function testTheTablePrintsTheMedianThatTheChartDraws(): void
    {
        $store = $this->store([
            'azera'  => ['trim' => 0.1977, 'median' => 0.1910],
            'phalcon' => ['trim' => 0.2057, 'median' => 0.1991],
        ]);

        $tables = new Tables($store);
        $matrix = $tables->latencyMatrix('volucer-attach', ['azera', 'phalcon']);

        $row = $this->rowFor($matrix, 'GET /');
        self::assertEqualsWithDelta(0.1910, (float) $row['cells']['azera']['ms'], 1e-9, 'the cell must be the median');
        self::assertEqualsWithDelta(0.1991, (float) $row['cells']['phalcon']['ms'], 1e-9);

        // And the rendered cell agrees with the store's chart input.
        $store2 = $store;
        self::assertEqualsWithDelta(
            (float) $store2->medianWithBoot('azera', 'volucer-attach', 'GET /'),
            (float) $row['cells']['azera']['ms'],
            1e-9,
            'the table must read the same accessor the charts read'
        );

        $md = $tables->latencyMarkdown('volucer-attach', ['azera', 'phalcon']);
        self::assertStringContainsString('0.191', $md, 'the rendered table carries the median');
        self::assertStringNotContainsString('0.198', $md, 'the rendered table must not carry the trimmed mean');
    }

    /**
     * A runner-up inside the band is flagged as a tie rather than presented as a loss.
     *
     * Every gap here is well inside the measured same-cell spread, and the Azera /
     * Phalcon pair is the one that actually flipped sign across interleaved runs.
     */
    public function testACloseRunnerUpIsMarkedAsATie(): void
    {
        $store = $this->store([
            'azera'   => ['trim' => 0.1727, 'median' => 0.1654],
            'phalcon' => ['trim' => 0.1726, 'median' => 0.1665],
        ]);

        $row = $this->rowFor((new Tables($store))->latencyMatrix('volucer-attach', ['azera', 'phalcon']), 'GET /');

        self::assertTrue($row['cells']['azera']['winner'], 'the fastest is still the winner');
        self::assertFalse($row['cells']['azera']['tie'], 'the winner is not marked as tied to itself');
        self::assertFalse($row['cells']['phalcon']['winner']);
        self::assertTrue($row['cells']['phalcon']['tie'], 'a runner-up inside the band is a tie');

        $md = (new Tables($store))->latencyMarkdown('volucer-attach', ['azera', 'phalcon']);
        self::assertStringContainsString('≈', $md, 'the tie has to be visible in the rendered cell');
    }

    /**
     * A gap WIDER than the band stays an unmarked, unambiguous result.
     *
     * The band must not turn into "everything is a tie": on the per-request arms the
     * differences are multiples, and calling those ties would delete the finding.
     */
    public function testAClearWinnerIsNotMarkedAsATie(): void
    {
        $store = $this->store([
            'azera'   => ['trim' => 3.86, 'median' => 3.78],
            'phalcon' => ['trim' => 0.68, 'median' => 0.66],
        ]);

        $row = $this->rowFor((new Tables($store))->latencyMatrix('php-fpm', ['azera', 'phalcon']), 'GET /');

        self::assertTrue($row['cells']['phalcon']['winner']);
        self::assertFalse($row['cells']['azera']['tie'], 'a 5x gap is not a tie');

        $md = (new Tables($store))->latencyMarkdown('php-fpm', ['azera', 'phalcon']);
        self::assertStringNotContainsString('≈', $md);
    }

    /**
     * The band is 3%, and that number is load-bearing.
     *
     * It was calibrated from two full runs of the same code: the median |delta| of one
     * cell's median between them was 3.6%. Asserting the boundary keeps the band from
     * silently growing into "no winner is ever real" — and from shrinking below the
     * noise, which would bold a coin flip again.
     */
    public function testTheTieBandBoundaryIsWhereItIsDocumented(): void
    {
        // Just inside 3%: a tie.
        $inside = $this->store([
            'azera'   => ['trim' => 0.10, 'median' => 0.1000],
            'phalcon' => ['trim' => 0.10, 'median' => 0.1029],
        ]);
        $row = $this->rowFor((new Tables($inside))->latencyMatrix('volucer-attach', ['azera', 'phalcon']), 'GET /');
        self::assertTrue($row['cells']['phalcon']['tie'], '2.9% is inside the band');

        // Just outside: not a tie.
        $outside = $this->store([
            'azera'   => ['trim' => 0.10, 'median' => 0.1000],
            'phalcon' => ['trim' => 0.10, 'median' => 0.1031],
        ]);
        $row = $this->rowFor((new Tables($outside))->latencyMatrix('volucer-attach', ['azera', 'phalcon']), 'GET /');
        self::assertFalse($row['cells']['phalcon']['tie'], '3.1% is outside the band');
    }

    /**
     * A resident page states the run-to-run spread; a per-request page does not.
     *
     * The note explains why a small gap on a resident arm is not a result. It is
     * deliberately qualitative — a frozen percentage would be stale after the next
     * run, and ChartManifestTest forbids measured figures in prose.
     */
    public function testTheResidentPageExplainsTheRunToRunSpread(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/report/MarkdownReport.php');

        self::assertStringContainsString('Run-to-run spread', $src);

        // Gated on the resident model: a per-request row is dominated by a boot that
        // costs hundreds of microseconds, so its share of noise is much smaller and the
        // note would mislead there.
        self::assertMatchesRegularExpression(
            '/private function runToRunNote\(string \$mode\): string\s*\{\s*if \(!\$this->store->isResidentMode\(\$mode\)\) \{\s*return \'\';/',
            $src,
            'the spread note must be resident-only'
        );
    }

    /**
     * An endpoint-level exclusion blanks ONE cell, not the whole feature.
     *
     * This is the 2026-11-25 shape: Phalcon cannot be raced on `GET /features/aop`
     * (no interceptor layer, so its row would compare hand-written code against
     * generated proxies), but it must still appear on `GET /features/aop-direct` —
     * the hand-written control that prices exactly the abstraction in question, and
     * the one row Phalcon reads best because its `/features/aop` is already that code.
     *
     * The feature-level gate alone cannot express this: `featureMap()` maps both
     * endpoints to `aop`, so dropping the feature hid both.
     */
    public function testAnExcludedEndpointBlanksOnlyThatCell(): void
    {
        $store = $this->store([
            'azera'   => ['trim' => 1.80, 'median' => 1.78],
            'phalcon' => ['trim' => 2.04, 'median' => 2.00],
        ], [
            'GET /features/aop'        => ['azera' => ['trim' => 1.80, 'median' => 1.78], 'phalcon' => ['trim' => 2.04, 'median' => 2.00]],
            'GET /features/aop-direct' => ['azera' => ['trim' => 1.78, 'median' => 1.76], 'phalcon' => ['trim' => 1.89, 'median' => 1.85]],
        ]);

        $matrix = (new Tables($store))->latencyMatrix('php-fpm', ['azera', 'phalcon']);

        $aop = $this->rowFor($matrix, 'GET /features/aop');
        self::assertNotNull($aop['cells']['azera']['ms'], 'the other entrants still race it');
        self::assertNull($aop['cells']['phalcon']['ms'], 'the excluded cell is blank, not zero');

        $direct = $this->rowFor($matrix, 'GET /features/aop-direct');
        self::assertNotNull(
            $direct['cells']['phalcon']['ms'],
            'the CONTROL endpoint must be published for every entrant, Phalcon included'
        );
        self::assertEqualsWithDelta(
            1.85,
            (float) $direct['cells']['phalcon']['ms'],
            1e-9,
            'and it carries Phalcon\'s own measurement'
        );
    }

    /**
     * An excluded endpoint stays in the TOTALS even when the entrant has no
     * measurement for it at all.
     *
     * The totals are compared over `commonRequests()`, and if an excluded app counted
     * as "missing" there, the request would drop out for EVERYONE — six frameworks
     * would be summed over a smaller workload than the seventh's feature page draws,
     * and the totals would silently answer a different question than the tables above
     * them.
     *
     * The fixture deliberately gives Phalcon NO row for `/features/aop`, which is the
     * real situation: it is excluded from that endpoint, so nothing measured it there.
     */
    public function testAnExcludedEndpointStaysInTheCommonTotals(): void
    {
        $store = $this->store([
            'azera'   => ['trim' => 1.80, 'median' => 1.78],
            'phalcon' => ['trim' => 2.04, 'median' => 2.00],
        ], [
            'GET /'                    => ['azera' => ['trim' => 0.20, 'median' => 0.19], 'phalcon' => ['trim' => 0.21, 'median' => 0.20]],
            'GET /features/aop'        => ['azera' => ['trim' => 1.80, 'median' => 1.78]],
            'GET /features/aop-direct' => ['azera' => ['trim' => 1.78, 'median' => 1.76], 'phalcon' => ['trim' => 1.89, 'median' => 1.85]],
        ]);

        $common = $store->commonRequests('php-fpm', ['azera', 'phalcon']);

        self::assertContains('GET /features/aop', $common, 'an endpoint one entrant sits out is still common');
        self::assertContains('GET /features/aop-direct', $common);
        self::assertContains('GET /', $common);
    }

    /** @param list<array{request:string,feature:string,cells:array<string,mixed>}> $matrix */
    private function rowFor(array $matrix, string $request): array
    {
        foreach ($matrix as $row) {
            if ($row['request'] === $request) {
                return $row;
            }
        }
        self::fail("no row for {$request}");
    }    /**
     * A store whose mode carries the given statistics per app and per request.
     *
     * Both statistics are supplied because the whole point is that they DIFFER: a
     * fixture carrying one value would pass whichever accessor the table used.
     *
     * When $requests is given it overrides the single `GET /` row, so a test can build
     * a matrix with several endpoints (needed by the endpoint-exclusion cases, which
     * have to show one cell blank and another populated in the same mode).
     *
     * @param array<string,array{trim:float,median:float}> $cells fallback per-app values
     * @param array<string,array<string,array{trim:float,median:float}>>|null $requests request => app => stats
     */
    private function store(array $cells, ?array $requests = null): ResultStore
    {
        $apps = [];
        $all  = $cells;
        foreach ($requests ?? [] as $byApp) {
            $all += $byApp;
        }
        $keys = array_keys($all);

        foreach ($keys as $app) {
            $rows = [];
            if ($requests === null) {
                $rows[] = $this->row('GET /', $cells[$app]['trim'], $cells[$app]['median']);
            } else {
                foreach ([
                    'GET /'                    => 'routing',
                    'GET /features/aop'        => 'aop',
                    'GET /features/aop-direct' => 'aop',
                ] as $request => $_feature) {
                    // NO fallback to $cells when $requests is given: the whole point of
                    // that form is that some (request, app) pairs have NO measurement,
                    // which is the situation an endpoint exclusion creates. Falling back
                    // made Phalcon carry a `/features/aop` row it should not have, and
                    // the totals test then passed with the gate removed.
                    $spec = $requests[$request][$app] ?? null;
                    if ($spec === null) {
                        continue;
                    }
                    $rows[] = $this->row($request, $spec['trim'], $spec['median']);
                }
            }

            $apps[] = [
                'app'   => $app,
                'modes' => [
                    'volucer-attach' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $rows],
                    'php-fpm'        => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $rows],
                ],
            ];
        }

        $dir = dirname(__DIR__) . '/temp/latency-stat-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/probe.json";
        file_put_contents($path, json_encode([
            'env'  => ['php_version' => '8.3.33', 'deployment' => 'real', 'budget' => '1000x10'],
            'apps' => $apps,
        ], JSON_PRETTY_PRINT));

        try {
            return ResultStore::load($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    private function row(string $request, float $trim, float $median): array
    {
        return [
            'request'            => $request,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => $trim,
            'min_ms'             => $median,
            'mean_ms'            => $trim,
            'median_ms'          => $median,
            'p95_ms'             => $median,
        ];
    }
}
