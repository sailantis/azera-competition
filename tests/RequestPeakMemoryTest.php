<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use AzeraCompetition\Report\SvgChart;
use PHPUnit\Framework\TestCase;

/**
 * The memory statistic: how much memory ONE REQUEST needs.
 *
 * Until 2026-09-17 the probe answered a different question in each mode, and
 * on a fresh-process server (nginx + php-fpm) the answer was meaningless. It
 * reported the heap left at shutdown, sampled in a register_shutdown_function
 * AFTER the response and after terminate() — allocator steady state, not
 * request cost. Measured on the bench VM it came out BELOW the boot footprint
 * on 14 of Laravel's 21 endpoints (0.885-0.918 MB against a 0.902 MB boot),
 * so a "request cost" could be negative and anti-correlated with framework
 * weight.
 *
 * The fix is a per-request high-water mark:
 *
 *   arm:      boot = memory_get_usage(false); memory_reset_peak_usage();
 *   shutdown: peak = memory_get_peak_usage(false);
 *
 * Resetting AT the boot-complete boundary anchors the mark at the boot heap,
 * so peak is absolute (boot <= peak), comparable across frameworks, and covers
 * exactly this request's work. It also makes the number independent of where in
 * a framework's lifecycle the call site sits — which matters, because Laravel
 * arms the probe after an explicit $kernel->bootstrap() and Spiral arms it
 * before $kernel->run().
 *
 * These tests pin the CONTRACT: the primitive is actually called, the field is
 * actually carried end to end, and the marks the chart draws are one statistic
 * rather than three unrelated requests.
 */
final class RequestPeakMemoryTest extends TestCase
{
    private static function fixturePath(): string
    {
        return dirname(__DIR__) . '/temp/tmp-peak-fixture.json';
    }

    /**
     * A dataset whose per-request peaks deliberately spread, so the min/median/
     * max selection has something to get right: the heaviest and lightest
     * endpoints are NOT the first and last in request order, so an
     * implementation that used array order instead of sorting would fail.
     *
     * mem_heap is deliberately DIFFERENT from mem_peak_heap, and cumulative
     * (it grows endpoint by endpoint, as a resident worker's heap does). A
     * fixture where the two are equal would let a reader that grabbed the wrong
     * field pass every assertion — the store exposes both, and they answer
     * different questions.
     *
     * @param array<string,int> $peaks request label => peak bytes (php-fpm)
     * @param bool $reverse write the request rows in reverse order, to prove
     *        the statistic does not depend on row order
     * @param int|null $defaultPeak peak for every request NOT named in $peaks.
     *        Null keeps the built-in spread. A test that needs to control the
     *        whole DISTRIBUTION (rather than a few points inside it) must be
     *        able to set every row, otherwise the remaining rows — and with
     *        them the median — are whatever the defaults happen to be.
     */
    private static function writeFixture(
        array $peaks,
        int $boot = 500000,
        bool $reverse = false,
        ?int $defaultPeak = null
    ): string {
        $order = BenchmarkConfig::requestOrder();
        if ($reverse) {
            $order = array_reverse($order);
        }

        $rows = [];
        foreach ($order as $i => $req) {
            // The default peak is keyed by REQUEST NAME, not by row position:
            // an index-derived default would itself be order-dependent, and the
            // order-independence test would then be measuring the fixture
            // rather than the statistic.
            $rowDefault = $defaultPeak ?? 260000 + (crc32($req) % 40) * 12000;
            $peak       = $peaks[$req] ?? $rowDefault;
            // Cumulative retained heap: a different quantity on a visibly
            // different scale, so a reader that grabbed the wrong field cannot
            // pass by coincidence.
            $heap = $boot + ($i + 1) * 60000;
            $rows[] = [
                'request'            => $req,
                'iterations_per_run' => 1000,
                'runs'               => 10,
                'trimmed_mean_ms'    => 1.0,
                'min_ms'             => 0.9,
                'mean_ms'            => 1.0,
                'median_ms'          => 1.0,
                'p95_ms'             => 1.2,
                'peak_mem'           => 0,
                'connect_ms'         => 0.05,
                'mem_boot_heap'      => $boot,
                'mem_peak_heap'      => $peak,
                'mem_heap'           => $heap,
                'mem_rss'            => 0,
                'mem_hwm'            => 0,
                'mem_samples'        => 10,
                'boot_ms'            => 0.5,
            ];
        }

        $payload = [
            'env'  => ['php_version' => '8.3.33', 'os' => 'Linux', 'timestamp' => '2026-09-17T00:00:00+00:00'],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'php-fpm' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $rows],
                    ],
                ],
                // A second app: the view needs two to draw a comparison, and a
                // one-app fixture would make every prose assertion vacuous (the
                // section returns '' and the test would "pass" on absence).
                //
                // Deliberately set to a SMALL constant peak so it is the
                // LIGHTEST, while it comes LAST in the view's app order. That
                // makes view order and rank order differ, which is the only way
                // a "the rows are ranked" assertion can fail — with the heaviest
                // app last they coincide and the assertion is vacuous.
                [
                    'app'   => 'spiral',
                    'modes' => [
                        'php-fpm' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            // array_merge, NOT `$r + [...]`: the union operator
                            // keeps the LEFT side's keys, and $r already carries
                            // mem_peak_heap — so `+` silently left this app with
                            // Azera's peaks, breaking the whole fixture.
                            'requests' => array_map(
                                static fn(array $r): array => array_merge(
                                    $r,
                                    ['mem_peak_heap' => 300000, 'mem_boot_heap' => 280000]
                                ),
                                $rows
                            ),
                        ],
                    ],
                ],
            ],
        ];

        $path = self::fixturePath();
        file_put_contents($path, json_encode($payload));
        return $path;
    }

    /**
     * The probe must reset the high-water mark, or the "per-request" peak is
     * really a cumulative one and the first endpoint's number leaks into every
     * later endpoint's.
     */
    public function testProbeResetsThePeakAtTheBootBoundary(): void
    {
        // Normalise line endings first: this repo is checked out CRLF
        // (core.autocrlf=true, no .gitattributes), so a strpos() for a needle
        // written with "\n" silently fails on every file.
        $probe = str_replace(
            "\r\n",
            "\n",
            (string) file_get_contents(dirname(__DIR__) . '/boot-probe.php')
        );

        $armPos = strpos($probe, 'function mem_probe_arm');
        $wriPos = strpos($probe, 'function mem_probe_write');
        self::assertNotFalse($armPos);
        self::assertNotFalse($wriPos);

        $arm = substr($probe, $armPos, $wriPos - $armPos);

        self::assertStringContainsString(
            'memory_reset_peak_usage()',
            $arm,
            'the reset must happen when the probe is armed, i.e. at the boot boundary'
        );
        // The reset must come AFTER the boot heap is read: resetting first
        // would let the boot heap itself be counted as the request's cost.
        // (assertGreaterThan takes ($expected, $actual) — the ARGUMENT ORDER is
        // the easy mistake here, and getting it backwards makes the assertion
        // vacuous rather than failing.)
        $resetPos = strpos($arm, 'memory_reset_peak_usage();');
        $bootPos  = strpos($arm, '$bootHeap = memory_get_usage(false);');
        self::assertNotFalse($resetPos);
        self::assertNotFalse($bootPos);
        self::assertGreaterThan(
            $bootPos,
            $resetPos,
            'boot must be sampled BEFORE the mark is reset'
        );

        // And the write must read the peak, not just the remaining heap.
        $write = substr($probe, $wriPos);
        self::assertStringContainsString('memory_get_peak_usage(false)', $write);
        self::assertMatchesRegularExpression(
            "/'peak'\s*=>\s*\\\$peak,/",
            $write,
            "the probe must record the peak under a 'peak' key"
        );
    }

    /**
     * peak must be an absolute number at or above boot. A probe that reports
     * the peak BELOW the boot heap is reporting a corrupted reading, and the
     * clamp is what keeps a runtime without memory_reset_peak_usage() from
     * emitting one.
     */
    public function testProbeClampsPeakToAtLeastTheBootHeap(): void
    {
        $probe = (string) file_get_contents(dirname(__DIR__) . '/boot-probe.php');

        self::assertMatchesRegularExpression(
            '/if\s*\(\$peak\s*<\s*\$bootHeap\)\s*\{\s*\$peak\s*=\s*\$bootHeap;/',
            $probe,
            'a peak below boot is not a measurement — it must be clamped'
        );
    }

    /**
     * The RR worker must answer the same field, or the two pages can no longer
     * be read side by side (which is the whole reason one code path serves
     * both).
     */
    public function testRoadRunnerWorkerAnswersTheSamePeakField(): void
    {
        // LF-normalised: the position comparison below spans lines, and this
        // repo is checked out CRLF.
        $w = str_replace(
            "\r\n",
            "\n",
            (string) file_get_contents(dirname(__DIR__) . '/deploy/rr/worker.php')
        );

        self::assertStringContainsString('X-Bench-Peak', $w);
        self::assertStringContainsString('memory_get_peak_usage(false)', $w);
        // Reset BEFORE dispatch. Resetting after would throw away the very
        // work the number is supposed to describe.
        $reset    = strpos($w, 'memory_reset_peak_usage()');
        $dispatch = strpos($w, '$adapter->dispatch(');
        self::assertNotFalse($reset);
        self::assertNotFalse($dispatch);
        self::assertLessThan(
            $dispatch,
            $reset,
            'the RR mark must be reset before the request is dispatched'
        );

        // The cumulative heap must SURVIVE: it is the leak trajectory the
        // roadrunner page is built around — it is what showed cakePHP's
        // 0.85 -> 40.6 MB climb before the adapter leak was fixed.
        self::assertStringContainsString('X-Bench-Heap', $w);
    }

    /**
     * Repeats are only worth taking because peak is per-request. The harness
     * must therefore probe an endpoint more than once by default, and reduce
     * each endpoint to one value before the cross-endpoint extremes.
     */
    public function testHarnessProbesEachEndpointRepeatedlyAndReducesPerEndpoint(): void
    {
        $harness = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');
        $lib     = (string) file_get_contents(dirname(__DIR__) . '/scripts/bench-lib.php');

        self::assertStringContainsString("'mem-repeats::'", $harness, 'the repeat count must be settable');
        self::assertStringContainsString('memProbeRepeated(', $harness);
        self::assertStringContainsString('memProbeAggregateEndpoint(', $harness);

        // Per-endpoint reduction must be a MEDIAN, and it must cover every
        // field — aggregating only some would leave a row mixing two statistics.
        self::assertStringContainsString('function memProbeAggregateEndpoint', $lib);
        self::assertMatchesRegularExpression(
            '/function memProbeAggregateEndpoint.*?foreach \(\[[^\]]*\'boot\'/s',
            $lib,
            'every probe field must be aggregated, not just the peak'
        );
        self::assertStringContainsString("\$out['samples'] = count(\$samples);", $lib);
    }

    /**
     * A sample that never lands must be MISSING, never a zero row. A zero
     * would render as "this endpoint needs no memory" — the same silent
     * partial-wrongness the write/read race produced before it was fixed.
     */
    public function testAMissingSampleIsSkippedRatherThanRecordedAsZero(): void
    {
        $lib = str_replace(
            "\r\n",
            "\n",
            (string) file_get_contents(dirname(__DIR__) . '/scripts/bench-lib.php')
        );

        $fn  = strpos($lib, 'function memProbeRepeated');
        $end = strpos($lib, 'function memProbeAggregateEndpoint');
        self::assertNotFalse($fn);
        self::assertNotFalse($end);

        $body = substr($lib, $fn, $end - $fn);
        self::assertStringContainsString("(\$sample['boot'] ?? 0) <= 0", $body);
        self::assertStringContainsString('continue;', $body);
        self::assertStringNotContainsString(
            "'boot' => 0, 'peak' => 0",
            $body,
            'a missing sample must be skipped, never synthesised as a zero row'
        );
    }

    /**
     * The per-endpoint reduction must be a MEDIAN of the samples, not the first
     * or the mean — a first-sample bug would silently publish the run-to-run
     * noise of one probe, which is exactly what the repeats exist to remove.
     */
    public function testPerEndpointReductionIsTheMedianOfTheSamples(): void
    {
        // Deliberately ordered so first, mean and median all differ:
        //   first = 100, mean = 300, median = 400
        $samples = [
            ['boot' => 10, 'peak' => 100, 'heap' => 100, 'rss' => 0, 'hwm' => 0],
            ['boot' => 10, 'peak' => 400, 'heap' => 400, 'rss' => 0, 'hwm' => 0],
            ['boot' => 10, 'peak' => 400, 'heap' => 400, 'rss' => 0, 'hwm' => 0],
        ];

        $agg = memProbeAggregateEndpoint($samples);
        self::assertNotNull($agg);
        self::assertSame(400, $agg['peak'], 'the median must win over the first sample and the mean');
        self::assertSame(3, $agg['samples']);
        // Every field is reduced, not just the peak.
        self::assertSame(10, $agg['boot']);
    }

    /**
     * Repeats are the point of the change, so the DEFAULT must be more than
     * one. A default of 1 would leave the report claiming a reduced median
     * while actually publishing single-shot noise.
     */
    public function testTheDefaultProbeCountIsMoreThanOne(): void
    {
        $harness = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');

        self::assertMatchesRegularExpression(
            "/\\\$memRepeats\s*=\s*max\(1,\s*\(int\)\s*\(\\\$opts\['mem-repeats'\]\s*\?\?\s*(\d+)\)\)/",
            $harness,
            'the --mem-repeats default must be settable and must be read from the options'
        );
        preg_match(
            "/\\\$memRepeats\s*=\s*max\(1,\s*\(int\)\s*\(\\\$opts\['mem-repeats'\]\s*\?\?\s*(\d+)\)\)/",
            $harness,
            $m
        );
        self::assertGreaterThan(1, (int) $m[1], 'probing an endpoint once defeats the repeats');

        // The report must not hard-code that figure — it is provenance, and a
        // dataset measured with a different --mem-repeats has to be described
        // by its own numbers.
        $report = str_replace(
            "\r\n",
            "\n",
            (string) file_get_contents(dirname(__DIR__) . '/scripts/report/MarkdownReport.php')
        );
        self::assertStringContainsString('requestPeakSamples(', $report);
        self::assertStringContainsString('if ($samples === 1) {', $report);
    }

    /**
     * The three marks the chart draws must come from ONE statistic, sorted —
     * and the prose must be able to name the endpoint each extreme belongs to.
     */
    public function testPeakRangeReturnsSortedRealEndpoints(): void
    {
        // Deliberately not in request order, and clear of every default value
        // in the fixture: the heaviest is early, the lightest is late. Array
        // order would return the wrong pair AND the wrong median.
        $heaviest = 'GET /items';
        $lightest = 'GET /features/config';

        $store = ResultStore::load(self::writeFixture([
            $heaviest => 900000,
            $lightest => 200000,
        ]));

        $range = $store->requestPeakRange('azera', 'php-fpm');
        self::assertNotNull($range);

        self::assertSame(200000, $range['low'], 'low must be the smallest peak');
        self::assertSame(900000, $range['high'], 'high must be the largest peak');
        self::assertSame($lightest, $range['lowRequest'], 'the lightest endpoint must be named');
        self::assertSame($heaviest, $range['highRequest'], 'the heaviest endpoint must be named');
        self::assertSame(
            21,
            $range['count'],
            'every endpoint with a peak must take part, not just the extremes'
        );

        // The median must be the value at the MIDDLE OF THE SORTED SERIES —
        // asserted against an independently computed expectation, so a median
        // taken from the unsorted array cannot pass by luck.
        $series = $store->requestPeakSeries('azera', 'php-fpm');
        $values = array_column($series, 'peak');
        $sorted = $values;
        sort($sorted);
        $expectedMedian = $sorted[(int) floor((count($sorted) - 1) / 2)];

        self::assertSame($expectedMedian, $range['median']);
        self::assertContains($range['median'], $values, 'the median must be a real endpoint reading');
        self::assertNotSame(
            $series[0]['peak'],
            $range['median'],
            'the fixture must not let the unsorted first entry masquerade as the median'
        );
        self::assertLessThanOrEqual($range['high'], $range['median']);
        self::assertGreaterThanOrEqual($range['low'], $range['median']);

        // And the series must read the PEAK field, not the cumulative heap.
        // The fixture makes them different on purpose, so a reader that grabbed
        // mem_heap would produce a completely different set of numbers.
        $heapValues = array_column($store->residentTrajectory('azera', 'php-fpm'), 'heap');
        self::assertNotSame(
            $heapValues,
            $values,
            'the peak series must not be reading the cumulative heap'
        );
        self::assertSame(200000, min($values), 'the low must come from the peak field');
        self::assertSame(900000, max($values), 'the high must come from the peak field');
        self::assertNotSame($heapValues, $values);
    }

    /**
     * The order-independence that makes peak rankable: an endpoint's peak must
     * not depend on which endpoints came before it. This is the property the
     * cumulative heap does NOT have.
     */
    public function testPeakIsIndependentOfEndpointOrder(): void
    {
        $peaks = [
            'GET /'        => 400000,
            'GET /items'   => 900000,
            'GET /items/1' => 600000,
        ];

        $a       = ResultStore::load(self::writeFixture($peaks, 500000, false));
        $seriesA = array_column($a->requestPeakSeries('azera', 'php-fpm'), 'peak', 'request');

        // Same measurements, rows written in the reverse order. Every peak must
        // be identical — whereas the cumulative heap answers this by changing
        // every later value.
        $b       = ResultStore::load(self::writeFixture($peaks, 500000, true));
        $seriesB = array_column($b->requestPeakSeries('azera', 'php-fpm'), 'peak', 'request');

        self::assertSame($seriesA, $seriesB);
        // And the cross-endpoint selection must be unchanged too.
        self::assertEquals(
            $a->requestPeakRange('azera', 'php-fpm'),
            $b->requestPeakRange('azera', 'php-fpm')
        );
    }

    /**
     * The chart's three marks are one statistic, and ALL THREE are printed.
     *
     * The bug this pins: rows used to print only two values, so the right cap's
     * number appeared nowhere in the text. A reader comparing a row against the
     * drawn caps could not reconcile them (Symfony printed 0.456 → 0.574 while
     * its cap sat at 0.752) and reasonably concluded the chart was wrong.
     */
    public function testChartPrintsAllThreeMarksAndTheRightCapMatchesTheLast(): void
    {
        $series = [
            'Azera' => ['color' => '#3459e6', 'low' => 0.30, 'mid' => 0.55, 'high' => 0.90],
        ];

        $svg = SvgChart::memoryRange(
            $series,
            'T',
            'C',
            'lightest request',
            'heaviest request',
            'low / median / high',
            ' · '
        );

        self::assertNotSame('', $svg);
        // Three values printed, in the order the marks appear. (SvgChart::fmt()
        // pins 3 decimals for a value under 1 MB; the fixture is deliberately
        // all sub-MB so the assertion states the real output.)
        self::assertStringContainsString('0.300 · 0.550 · 0.900', $svg);
        self::assertSame(
            1,
            substr_count($svg, '0.550'),
            'each of the three marks is printed exactly once'
        );

        // The right cap must be drawn at the x the printed high value maps to —
        // the reconciliation a reader does by eye, and the one that failed when
        // the text carried a different number from the cap.
        preg_match_all('/>([\d.]+) MB</', $svg, $all);
        $axisTop = (float) end($all[1]);
        self::assertGreaterThan(0.0, $axisTop, 'the axis must be labelled with its top tick');

        // Locate the plot's left edge from the drawing itself rather than from
        // a constant: padL is content-sized now, so a hard-coded 218 would test
        // the number this change exists to remove. The axis baseline is the
        // full-width slate line at the bottom of the grid. Groups are
        // (x1, y1, x2) — y2 is a backreference to y1, so x2 is group 3.
        preg_match(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
            $svg,
            $base
        );
        self::assertNotEmpty($base, 'the axis baseline must be drawn');
        $padL  = (float) $base[1];
        $plotR = (float) $base[3];
        self::assertGreaterThan(0.0, $padL);
        self::assertGreaterThan($padL, $plotR);

        $expectedX = $padL + ($plotR - $padL) * (0.90 / $axisTop);

        // End caps are the zero-length spans in the SERIES colour. Filtering on
        // the colour matters: the grid lines and the two dashed reference lines
        // are also zero-length spans, and an unfiltered match returns the axis
        // top (920) as the rightmost "cap".
        $capXs = [];
        preg_match_all(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="([\d.]+)" stroke="#3459e6" stroke-width="2\.5" stroke-linecap="round"\/>/',
            $svg,
            $cm
        );
        foreach ($cm[1] as $i => $x1) {
            if ($x1 === $cm[3][$i]) {
                $capXs[] = (float) $x1;
            }
        }
        self::assertNotEmpty($capXs, 'the end caps must be drawn');

        // The DOT must sit at the median, not at either cap. The fixture puts
        // the median (0.55) strictly between them, so a dot drawn at the high
        // cap would be caught here rather than passing as "somewhere on the bar".
        preg_match(
            '/<circle cx="([\d.]+)" cy="[\d.]+" r="5\.2" fill="#3459e6"/',
            $svg,
            $dm
        );
        self::assertNotEmpty($dm, 'the dot must be drawn');
        $expectedMidX = $padL + ($plotR - $padL) * (0.55 / $axisTop);
        self::assertEqualsWithDelta(
            $expectedMidX,
            (float) $dm[1],
            1.0,
            'the dot must mark the median, not an end cap'
        );
        self::assertNotEqualsWithDelta(
            max($capXs),
            (float) $dm[1],
            1.0,
            'the dot must not coincide with the right cap'
        );

        self::assertEqualsWithDelta(
            $expectedX,
            max($capXs),
            1.0,
            'the right cap must sit at the value printed as the highest reading'
        );
    }

    /**
     * The two deployment models must be described by their OWN statistics, and
     * the FPM page must not carry the resident worker's narrative.
     */
    public function testFpmProseDescribesPerRequestMemoryNotResidentGrowth(): void
    {
        $store = ResultStore::load(self::writeFixture([
            'GET /items' => 900000,
        ]));

        $dir = dirname(__DIR__) . '/temp/tmp-peak-svg';
        $md  = (new MarkdownReport($store, 'real-fpm', [
            'title'  => 'Real FPM',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'php-fpm',
            'charts' => ['resident-memory'],
        ]))->render($dir, 'svg/real-fpm');

        self::assertStringContainsString('## Per-request memory', $md);
        self::assertStringContainsString('high-water mark', $md);
        // Since the report was made figure-free, the heaviest endpoint is named
        // by the CHART (which prints each row's own readings), not by a
        // sentence. What the prose must still carry is the model: the marks are
        // one statistic per framework, ranked on the median.
        self::assertStringNotContainsString('MB** serving', $md, 'the prose must not reprint a reading');
        // The resident-worker wording must not leak onto a fresh-process page.
        self::assertStringNotContainsString('Resident worker memory', $md);
        self::assertStringNotContainsString('end state', $md);

        // The DRAWN rows must be ranked on the MEDIAN reading (lightest first),
        // so the picture agrees with the prose. Read the order out of the SVG
        // rather than trusting the prose, which is ordered by construction and
        // would pass even if the chart were not sorted.
        $svg = (string) file_get_contents($dir . '/resident-memory.svg');
        self::assertNotSame('', $svg);

        $azeraRow  = strpos($svg, '>Azera</text>');
        $spiralRow = strpos($svg, '>Spiral</text>');
        self::assertNotFalse($azeraRow, 'Azera must be drawn');
        self::assertNotFalse($spiralRow, 'Spiral must be drawn');

        // Spiral is a small constant here (300000) and comes LAST in the view's
        // app order, while Azera is first and reaches 900000 — so view order,
        // rank order and the direction of the sort all differ, and an unsorted
        // (or reverse-sorted) chart fails below.
        //
        // This does NOT by itself distinguish median-ranking from high-ranking:
        // both keys put Spiral first on these numbers. That distinction is the
        // subject of testRowsAreOrderedByMedianNotByTheHeaviestEndpoint(), which
        // builds a distribution where the two keys disagree on purpose. Keeping
        // the two concerns in separate tests means neither assertion is doing
        // two jobs.
        self::assertGreaterThan(
            $spiralRow,
            $azeraRow,
            'rows must be ranked (lightest first), not left in the view\'s app order'
        );
    }

    /**
     * The row ordering key is the MEDIAN, not the heaviest endpoint.
     *
     * The bug this pins: ranking on the high end lets a single heavy route
     * reorder the whole table, so a framework that is cheap on every typical
     * request but expensive on one outlier sorts above one that is uniformly
     * heavier. The two keys must genuinely disagree for the test to be able to
     * fail for the right reason, which the fixture below arranges.
     */
    public function testRowsAreOrderedByMedianNotByTheHeaviestEndpoint(): void
    {
        // A distribution chosen so the two candidate keys DISAGREE, which is the
        // only shape in which this test can fail for the right reason:
        //
        //   20 rows at 200000 + one outlier at 950000
        //     -> median 200000 (the middle of the sorted series), high 950000
        //
        // Spiral is a constant 300000 in this fixture, so:
        //   by MEDIAN: Azera 200000 < Spiral 300000  -> Azera first
        //   by HIGH:   Azera 950000 > Spiral 300000  -> Spiral first
        //
        // A chart ranked on the high end therefore inverts this ordering, and
        // the assertion below catches it.
        $store = ResultStore::load(self::writeFixture(['GET /items' => 950000], 500000, false, 200000));
        $range = $store->requestPeakRange('azera', 'php-fpm');
        self::assertNotNull($range);
        self::assertSame(200000, $range['median'], 'the median must be the middle of the distribution');
        self::assertSame(950000, $range['high'], 'the high must be the outlier, not the median');

        $dir = dirname(__DIR__) . '/temp/tmp-peak-order';
        $md  = (new MarkdownReport($store, 'real-fpm', [
            'title'  => 'Real FPM',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'php-fpm',
            'charts' => ['resident-memory'],
        ]))->render($dir, 'svg/real-fpm');

        $svg = (string) file_get_contents($dir . '/resident-memory.svg');
        self::assertNotSame('', $svg);

        $azeraRow  = strpos($svg, '>Azera</text>');
        $spiralRow = strpos($svg, '>Spiral</text>');
        self::assertNotFalse($azeraRow);
        self::assertNotFalse($spiralRow);

        // Azera's median is the lower of the two, so Azera must be drawn FIRST
        // even though Azera owns the heaviest endpoint. Ranking on the high end
        // would put Spiral first and fail here — and this assertion is the
        // WHOLE contract now, because the prose prints no order-dependent
        // reading: the drawn order is what a reader ranks on.
        self::assertLessThan(
            $spiralRow,
            $azeraRow,
            'rows must be ranked by the median: Azera has the lower median but the heavier worst endpoint'
        );
    }

    /**
     * The faint zero-anchored bar must stop at the MEDIAN dot, and be drawn
     * behind the range so the exact readings stay on top.
     *
     * The bug this prevents: a bar drawn to the right cap would be overpainted
     * by the shorter bar of whichever framework has a smaller median, so the
     * two rows would visually merge and the reader could not tell which length
     * belonged to which framework. Stopping at the dot also makes the bar and
     * the dot the same reading, so there is only one number to reconcile.
     */
    public function testTheFaintBarRunsFromZeroToTheMedianDotOnly(): void
    {
        $series = [
            // Two frameworks deliberately close together: the bug only shows
            // when one bar is long enough to reach over the other.
            'Azera'  => ['color' => '#3459e6', 'low' => 0.20, 'mid' => 0.90, 'high' => 1.20],
            'Spiral' => ['color' => '#0ca678', 'low' => 0.10, 'mid' => 0.30, 'high' => 1.10],
        ];

        $svg = SvgChart::memoryRange($series, 'T', 'C', 'l', 'h', 'low / median / high', ' · ');
        self::assertNotSame('', $svg);

        // Same three-group baseline match the reconciliation test uses:
        // (x1, y1, x2), so group 3 is the plot's right edge.
        preg_match(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
            $svg,
            $base
        );
        self::assertNotEmpty($base, 'the axis baseline must be drawn so the plot edge can be located');
        $padL  = (float) $base[1];
        $plotR = (float) $base[3];
        preg_match_all('/>([\d.]+) MB</', $svg, $all);
        $axisTop = (float) end($all[1]);

        // One rect per series, in the series colour.
        preg_match_all(
            '/<rect x="([\d.]+)" y="([\d.]+)" width="([\d.]+)" height="([\d.]+)" fill="(#[0-9a-f]{6})" fill-opacity="0\.12"\/>/',
            $svg,
            $rm,
            PREG_SET_ORDER
        );
        self::assertCount(2, $rm, 'every row must carry a zero-anchored bar');

        $toX = static fn(float $v): float => $padL + ($plotR - $padL) * ($v / $axisTop);
        foreach ($rm as $i => $r) {
            self::assertEqualsWithDelta(
                $padL,
                (float) $r[1],
                1.0,
                'the bar must start at the zero baseline'
            );
            // Width must equal (median - 0), which is (xMid - padL).
            $expectedW = $toX($i === 0 ? 0.90 : 0.30) - $padL;
            self::assertEqualsWithDelta(
                $expectedW,
                (float) $r[3],
                1.0,
                'the bar must end at the median dot, not at the right cap'
            );
            // And it must NOT reach the high cap.
            self::assertLessThan(
                $toX($i === 0 ? 1.20 : 1.10) - $padL,
                (float) $r[3],
                'the bar must stop short of the right cap'
            );
        }

        // Drawn FIRST, so the range line, the caps and the dot all paint over
        // it. A bar emitted after the dot would hide the marker it anchors.
        $firstBar   = strpos($svg, 'fill-opacity="0.12"');
        $firstRange = strpos($svg, 'stroke-linecap="round" opacity="0.45"');
        self::assertNotFalse($firstBar);
        self::assertNotFalse($firstRange);
        self::assertLessThan($firstRange, $firstBar, 'the bar must be drawn behind the range');
    }

    /**
     * Labels and values must never overlap, however long the strings get.
     *
     * The bug this pins: the two left-hand columns were derived from one
     * constant with a fixed 94px gap, and the per-request rows print three
     * values ('0.629 · 0.643 · 0.693', ~117px rendered) in a column narrower
     * than that gap — so on the published charts every value was drawn on top
     * of a framework name. Sizing each column from its own longest string
     * cannot collide.
     *
     * Asserted on the GEOMETRY, not on a magic pixel count: the x the value
     * column is anchored at must be far enough right that the longest name
     * still ends before the longest value begins.
     */
    public function testLongValuesCannotOverlapTheNameColumn(): void
    {
        // Names long enough to be the widest thing in the chart, plus values
        // that are wide too: both columns must be sized independently, so
        // whichever is longer wins its own column.
        $series = [
            'CodeIgniter'     => ['color' => '#e8590c', 'low' => 0.629, 'mid' => 0.643, 'high' => 0.693],
            'A Very Long One' => ['color' => '#3459e6', 'low' => 0.581, 'mid' => 0.662, 'high' => 0.728],
        ];

        $svg = SvgChart::memoryRange($series, 'T', 'C', 'l', 'h', 'low / median / high', ' · ');
        self::assertNotSame('', $svg);

        // Read the two drawn columns' anchor points and the two longest strings.
        preg_match('/<text x="([\d.]+)" y="[\d.]+"[^>]*text-anchor="end">CodeIgniter<\/text>/', $svg, $nm);
        preg_match('/<text x="([\d.]+)" y="[\d.]+"[^>]*text-anchor="end">0.629 · 0.643 · 0.693<\/text>/', $svg, $vm);
        self::assertNotEmpty($nm, 'the name must be drawn');
        self::assertNotEmpty($vm, 'the value must be drawn');
        $nameRight = (float) $nm[1];
        $valRight  = (float) $vm[1];

        // The name starts at x=16 (it is the widest column entry), and must end
        // before the value begins. Value width is approximated the same way the
        // chart does it, but the CONTRACT asserted here is the ordering of the
        // two anchors, which holds whatever the estimator says.
        self::assertGreaterThan(
            $nameRight,
            $valRight,
            'the value column must be anchored right of the name column'
        );
        // '0.629 · 0.643 · 0.693' is ~117px at 12.5px/700. If the gutter were
        // the old fixed 94px gap, valRight would sit within 94px of nameRight
        // and the value would start left of the name's end.
        self::assertGreaterThan(
            100.0,
            $valRight - $nameRight,
            'the value column must clear the widest value string, not a fixed gap'
        );
    }

    /**
     * The multiplier column: each row's median against the lightest median,
     * printed directly behind that row's own right cap.
     *
     * The numbers that must not drift: the factor is computed from the MEDIAN
     * (the dot), not from the cap — a column derived from the high end would
     * still "look plausible" while describing a different quantity from the one
     * the chart emphasises.
     */
    public function testMedianFactorColumnAnchorsRightOfTheHeaviestCap(): void
    {
        $series = [
            'Light' => ['color' => '#0ca678', 'low' => 0.20, 'mid' => 0.20, 'high' => 0.30],
            'Mid'   => ['color' => '#3459e6', 'low' => 0.20, 'mid' => 0.40, 'high' => 1.00],
            'Heavy' => ['color' => '#e5484d', 'low' => 0.20, 'mid' => 0.60, 'high' => 0.80],
        ];

        $svg = SvgChart::memoryRange(
            $series,
            'T',
            'C',
            'l',
            'h',
            'low / median / high',
            ' · ',
            960,
            'x = median ÷ the lightest median'
        );
        self::assertNotSame('', $svg);

        // Medians 0.20 / 0.40 / 0.60 against a lightest median of 0.20:
        // x 1.0, x 2.0, x 3.0 — but the REFERENCE row (the one holding the
        // lightest median) prints NOTHING, so the chart carries exactly two
        // labels. Note 'Heavy' has the highest median of the two labelled rows
        // but NOT the highest cap ('Mid' does, at 1.00), so a high-derived
        // column would print x 3.3 / x 5.0 instead.
        self::assertStringContainsString('>x 2.0</text>', $svg);
        self::assertStringContainsString('>x 3.0</text>', $svg);
        self::assertStringNotContainsString('>x 1.0</text>', $svg, 'the reference row needs no multiplier label');
        self::assertStringNotContainsString('>x 3.3</text>', $svg, 'the factor must come from the median, not the cap');
        self::assertStringNotContainsString('>x 5.0</text>', $svg, 'the factor must come from the median, not the cap');

        preg_match_all(
            '/<text x="([\d.]+)" y="([\d.]+)"[^>]*text-anchor="start"[^>]*>x [\d.]+<\/text>/',
            $svg,
            $fx,
            PREG_SET_ORDER
        );
        self::assertCount(2, $fx, 'only the non-baseline rows carry a multiplier');
        self::assertSame(2, substr_count($svg, '>x '), 'no row emits an empty label element');

        // Pair each label with the RIGHT cap of the row it sits on, by y. This
        // is the property that matters — the label travels with its own row
        // rather than lining up with a shared column — and pairing by y is what
        // makes the assertion independent of row order.
        preg_match_all(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="([\d.]+)" stroke="#[0-9a-f]{6}" stroke-width="2\.5" stroke-linecap="round"\/>/',
            $svg,
            $cm,
            PREG_SET_ORDER
        );
        $capXByY = [];
        foreach ($cm as $m) {
            // A zero-length span is an end cap. It is drawn from cy-capH to
            // cy+capH, so its own y1 is 7px above the row centre — key on the
            // MIDPOINT, which is the row centre the label is aligned to.
            if ($m[1] === $m[3]) {
                $key = $m[2] === $m[4]
                    ? (string) $m[2]
                    : (string) (((float) $m[2] + (float) $m[4]) / 2);
                $capXByY[$key] = max($capXByY[$key] ?? 0.0, (float) $m[1]);
            }
        }
        self::assertNotEmpty($capXByY);

        $checked = 0;
        foreach ($fx as $label) {
            $lx   = (float) $label[1];
            $ly   = (float) $label[2] - 4.5; // the label is drawn 4.5px below the row centre
            $capX = (float) $capXByY[self::matchY($capXByY, $ly)];
            self::assertEqualsWithDelta(
                $capX + 9.0,
                $lx,
                1.0,
                'the label must sit 9px behind its own row\'s right cap, as dotRange() places its factors'
            );
            $checked++;
        }
        self::assertSame(2, $checked);

        // And the two labels must NOT share an x: if they did, the placement
        // would be a column and this test would be asserting the wrong shape.
        $labelXs = array_map(static fn(array $m): float => (float) $m[1], $fx);
        self::assertCount(2, array_unique($labelXs), 'each label follows its own cap, not a shared column');

        // The subtitle/caption note explains what the multiplier is.
        self::assertStringContainsString('x = median ÷ the lightest median', $svg);
    }

    /**
     * The cap-line y key nearest to $y, as the string the cap map used. The
     * chart emits y with trailing zeros trimmed, so the lookup must compare
     * numerically rather than string-matching the formatted float.
     *
     * @param array<string,float> $byY
     */
    private static function matchY(array $byY, float $y): string
    {
        $best     = null;
        $bestDist = PHP_FLOAT_MAX;
        foreach (array_keys($byY) as $k) {
            $d = abs((float) $k - $y);
            if ($d < $bestDist) {
                $bestDist = $d;
                $best     = (string) $k;
            }
        }
        self::assertNotNull($best);
        self::assertLessThan(0.2, $bestDist, 'every label must sit on a row that has a cap');
        return $best;
    }

    /**
     * The multiplier column is OPT-IN, and its WORDING is the caller's, because
     * the dot is not a median on every memory chart. On the per-request page
     * the dot is a per-request median; on the resident-worker page the very
     * same mark is the heap left after the last endpoint. A hardcoded
     * 'x = median ...' sentence would therefore be a false description of one
     * of the two published pages, which is why the primitive takes a NOTE and
     * the note is what the reader sees beside the chart.
     *
     * The renderers are checked too, not just the primitive: each page's call
     * site has to pass its own sentence, and getting them the wrong way round
     * would leave every unit test of the primitive green.
     */
    public function testFactorColumnIsOptInAndAbsentByDefault(): void
    {
        $series = [
            'Azera'  => ['color' => '#3459e6', 'low' => 0.20, 'mid' => 0.40, 'high' => 0.80],
            'Spiral' => ['color' => '#0ca678', 'low' => 0.10, 'mid' => 0.60, 'high' => 1.20],
        ];

        // No note = no column at all, and no sentence describing one.
        $off = SvgChart::memoryRange($series, 'T', 'C', 'l', 'h', 'boot / end / worst', ' → ');
        self::assertNotSame('', $off);
        self::assertStringNotContainsString('x 1.0', $off);
        self::assertStringNotContainsString('>x ', $off);
        self::assertStringNotContainsString('x =', $off);

        // And the default geometry is untouched: the plot still ends 40px from
        // the card's right edge, exactly as it did before the column existed.
        preg_match(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
            $off,
            $baseOff
        );
        self::assertEqualsWithDelta(920.0, (float) $baseOff[3], 0.01, 'the default right gutter must not change');

        $on = SvgChart::memoryRange(
            $series,
            'T',
            'C',
            'l',
            'h',
            'boot / end / worst',
            ' → ',
            960,
            'x = end state ÷ the lightest end state'
        );
        preg_match(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
            $on,
            $baseOn
        );
        self::assertLessThan(
            (float) $baseOff[3],
            (float) $baseOn[3],
            'only the factor-bearing chart gives up room for the right gutter'
        );
        self::assertStringContainsString('x 1.5', $on);
        // The note printed is the one that was handed over, not a built-in.
        self::assertStringContainsString('x = end state ÷ the lightest end state', $on);

        // The RENDERER: both memory pages now carry multipliers, and each states
        // the ratio in its OWN terms. Read the SVGs — the note and the labels
        // are drawn, not written into the Markdown.
        $store  = ResultStore::load(self::writeFixture(['GET /items' => 900000]));
        $fpmDir = dirname(__DIR__) . '/temp/tmp-peak-optin-fpm';
        (new MarkdownReport($store, 'real-fpm', [
            'title'  => 'Real FPM',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'php-fpm',
            'charts' => ['resident-memory'],
        ]))->render($fpmDir, 'svg/real-fpm');
        $fpmSvg = (string) file_get_contents($fpmDir . '/resident-memory.svg');
        self::assertNotSame('', $fpmSvg);
        self::assertStringContainsString('x = median ÷ the lightest median', $fpmSvg);

        $rrDir = dirname(__DIR__) . '/temp/tmp-peak-optin-rr';
        (new MarkdownReport(ResultStore::load(self::withRoadrunner()), 'real-roadrunner', [
            'title'  => 'Real RR',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'roadrunner',
            'charts' => ['resident-memory'],
        ]))->render($rrDir, 'svg/real-roadrunner');
        $rrSvg = (string) file_get_contents($rrDir . '/resident-memory.svg');
        self::assertNotSame('', $rrSvg);
        // The resident worker now DOES carry a multiplier column — and it must
        // describe itself as an end state, never as a median.
        self::assertStringContainsString('>x ', $rrSvg, 'the resident worker now carries a multiplier column');
        self::assertStringContainsString('x = end state ÷ the lightest end state', $rrSvg);
        self::assertStringNotContainsString(
            'median',
            $rrSvg,
            'the dot on this page is an end state; calling it a median would be false'
        );
    }

    /**
     * The standard fixture plus a roadrunner block, so the resident-worker
     * renderer has something to draw. Its own writer keeps this out of the FPM
     * fixtures, where a second mode would be dead weight.
     */
    private static function withRoadrunner(): string
    {
        $payload = json_decode((string) file_get_contents(self::writeFixture(['GET /items' => 900000])), true);
        self::assertIsArray($payload);

        foreach ($payload['apps'] as $i => $app) {
            // A resident worker's heap RISES across the block — that is the
            // trajectory this view exists to show — and its boot is fixed.
            $boot = 500000 + $i * 100000;
            $rows = [];
            foreach ($app['modes']['php-fpm']['requests'] as $j => $r) {
                $rows[] = array_merge($r, [
                    'mem_boot_heap' => $boot,
                    'mem_heap'      => $boot + ($j + 1) * 40000,
                    'mem_peak_heap' => $boot + ($j + 1) * 40000,
                ]);
            }
            $payload['apps'][$i]['modes']['roadrunner'] = [
                'iterations_per_run' => 1000,
                'runs'               => 10,
                'requests'           => $rows,
            ];
        }

        $path = dirname(__DIR__) . '/temp/tmp-peak-rr-fixture.json';
        file_put_contents($path, json_encode($payload));
        return $path;
    }

    /**
     * A single-framework chart has nothing to compare against: no multiplier is
     * printed AND no right gutter is given up for one. Skipping the label while
     * still narrowing the plot would waste axis length for an annotation that
     * does not exist.
     */
    public function testFactorColumnIsNotDrawnForASingleFramework(): void
    {
        $svg = SvgChart::memoryRange(
            ['Azera' => ['color' => '#3459e6', 'low' => 0.20, 'mid' => 0.40, 'high' => 0.80]],
            'T',
            'C',
            'l',
            'h',
            'low / median / high',
            ' · ',
            960,
            'x = median ÷ the lightest median'
        );
        self::assertNotSame('', $svg);
        self::assertStringNotContainsString('>x ', $svg, 'a lone framework has no multiplier');

        // ...and the plot keeps its full default width, so the absent column
        // costs nothing. Asserted on the drawn geometry, because "no labels
        // printed" would also be true of a chart that reserved the room anyway.
        preg_match(
            '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
            $svg,
            $base
        );
        self::assertNotEmpty($base);
        self::assertEqualsWithDelta(
            920.0,
            (float) $base[3],
            0.01,
            'a lone framework must not reserve a gutter for a multiplier it never prints'
        );
    }

    /**
     * A framework only a few percent heavier than the reference still gets a
     * label — it rounds to 'x 1.0' rather than being suppressed.
     *
     * The bug this pins: suppressing every row that FORMATS as 'x 1.0' is not
     * the same as suppressing the reference. Two of the six frameworks on the
     * published run sit 1.4% and 2.9% above the lightest, so they formatted to
     * 'x 1.0' and vanished — leaving a blank that a reader cannot tell apart
     * from the deliberately unlabelled reference. The rule is therefore
     * identity ("is this the row carrying the lightest median?"), not the
     * formatted string.
     */
    public function testANearReferenceRowIsLabelledRatherThanBlank(): void
    {
        // 0.29 / 0.30 / 0.31 MB: the two heavier rows are 3.4% and 6.9% above
        // the lightest, so both format to 'x 1.0' — and must both still print.
        $series = [
            'Light'   => ['color' => '#0ca678', 'low' => 0.28, 'mid' => 0.29, 'high' => 0.30],
            'Mid'     => ['color' => '#3459e6', 'low' => 0.29, 'mid' => 0.30, 'high' => 0.31],
            'Heavier' => ['color' => '#e5484d', 'low' => 0.29, 'mid' => 0.31, 'high' => 0.32],
        ];

        $svg = SvgChart::memoryRange($series, 'T', 'C', 'l', 'h', 'low / median / high', ' · ', 960, 'x = median ÷ the lightest median');
        self::assertNotSame('', $svg);

        preg_match_all('/>x ([\d.]+)<\/text>/', $svg, $fx);
        self::assertCount(
            2,
            $fx[1],
            'only the reference row is blank; the near-reference rows still print'
        );
        self::assertSame(['1.0', '1.1'], $fx[1], 'the printed values are the rounded multipliers');

        // Count the LABELS drawn, not just the ones carrying digits: a guard
        // that stopped checking for the empty string would emit 'x ' for the
        // reference row, which the digit-matching pattern above cannot see.
        self::assertSame(
            2,
            substr_count($svg, '>x '),
            'the reference row must emit no label element at all'
        );
    }

    /**
     * The right gutter is sized from the widest label that is actually printed,
     * not from a constant.
     *
     * Pinned behaviourally: two charts whose widest multipliers differ get
     * different gutters, the longer label giving up more room. A fixed reserve
     * would draw the same plot width for both — and would clip the column as
     * soon as a multiplier grew past whatever the constant was chosen for.
     */
    public function testTheRightGutterIsSizedFromThePrintedLabel(): void
    {
        $short = [
            'Light' => ['color' => '#0ca678', 'low' => 0.10, 'mid' => 0.20, 'high' => 0.30],
            'Heavy' => ['color' => '#e5484d', 'low' => 0.10, 'mid' => 0.60, 'high' => 0.80],
        ];
        // 3.04 / 0.20 = 15.2 -> the widest label is 'x 15.2', not 'x 3.0'.
        $long = [
            'Light' => ['color' => '#0ca678', 'low' => 0.10, 'mid' => 0.20, 'high' => 0.30],
            'Heavy' => ['color' => '#e5484d', 'low' => 0.10, 'mid' => 3.04, 'high' => 3.20],
        ];

        $edge = static function (array $series): float {
            $svg = SvgChart::memoryRange($series, 'T', 'C', 'l', 'h', 'low / median / high', ' · ', 960, 'x = median ÷ the lightest median');
            preg_match(
                '/<line x1="([\d.]+)" y1="([\d.]+)" x2="([\d.]+)" y2="\2" stroke="#cbd5e1" stroke-width="1"\/>/',
                $svg,
                $base
            );
            self::assertNotEmpty($base);
            return (float) $base[3];
        };

        $edgeShort = $edge($short);
        $edgeLong  = $edge($long);

        self::assertStringContainsString('x 3.0', SvgChart::memoryRange($short, 'T', 'C', 'l', 'h', 'low / median / high', ' · ', 960, 'x = median ÷ the lightest median'));
        self::assertStringContainsString('x 15.2', SvgChart::memoryRange($long, 'T', 'C', 'l', 'h', 'low / median / high', ' · ', 960, 'x = median ÷ the lightest median'));

        self::assertLessThan(
            $edgeShort,
            $edgeLong,
            'the wider label must give up more of the plot width than the narrower one'
        );
        // And both stay inside the card.
        self::assertGreaterThan(860.0, $edgeLong);
    }

    /**
     * Rows are ordered by the DOT, and the row order must agree with the
     * multiplier printed beside each row.
     *
     * The bug this pins: the resident-worker chart ranked on the LEFT CAP. That
     * is a footprint measured before any request — a real property of the worker
     * — but it is not the reading the page is about, and it put the row order at
     * odds with its own x-factor column. A framework could be drawn at the top
     * for being cheapest to EXIST while carrying a larger multiplier than the
     * row beneath it, so the table read as two different rankings at once.
     *
     * The fixture is built so the two keys DISAGREE: the app with the lightest
     * boot is not the app with the lightest end state. Ranking on the left cap
     * and ranking on the dot then produce different orders, which is the only
     * way this assertion can fail.
     */
    public function testResidentWorkerRowsAreOrderedByTheDot(): void
    {
        $payload = json_decode((string) file_get_contents(self::withRoadrunner()), true);
        self::assertIsArray($payload);

        // Drive the two marks apart deliberately. With the fixture's own
        // numbers the app that boots lightest also ends lightest, so the order
        // would be the same under either key and the test could not fail.
        //   azera  : lightest BOOT, heaviest END  -> last under the dot, first
        //            under the old left-cap order.
        //   spiral : heaviest BOOT, lightest END  -> first now, last before.
        foreach ($payload['apps'] as $i => $app) {
            $isAzera = ($app['app'] ?? '') === 'azera';
            $boot    = $isAzera ? 400000 : 900000;
            $end     = $isAzera ? 4000000 : 500000;
            foreach ($payload['apps'][$i]['modes']['roadrunner']['requests'] as $j => $r) {
                // The LAST row decides the dot (residentHeap returns the last
                // probed row's heap), so only that one needs to differ.
                $last = $j === count($app['modes']['roadrunner']['requests']) - 1;
                $payload['apps'][$i]['modes']['roadrunner']['requests'][$j] = array_merge($r, [
                    'mem_boot_heap' => $boot,
                    'mem_heap'      => $last ? $end : $boot + 1000,
                ]);
            }
        }

        $path = dirname(__DIR__) . '/temp/tmp-order-rr-fixture.json';
        file_put_contents($path, json_encode($payload));

        $dir = dirname(__DIR__) . '/temp/tmp-order-rr';
        (new MarkdownReport(ResultStore::load($path), 'real-roadrunner', [
            'title'  => 'Real RR',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'roadrunner',
            'charts' => ['resident-memory'],
        ]))->render($dir, 'svg/real-roadrunner');

        $svg = (string) file_get_contents($dir . '/resident-memory.svg');
        self::assertNotSame('', $svg);

        // Row order is read from the drawn y positions, so this asserts what the
        // reader sees rather than the array the renderer happened to build.
        preg_match(
            '/<text x="[\d.]+" y="([\d.]+)"[^>]*text-anchor="end">Azera<\/text>/',
            $svg,
            $azera
        );
        preg_match(
            '/<text x="[\d.]+" y="([\d.]+)"[^>]*text-anchor="end">Spiral<\/text>/',
            $svg,
            $spiral
        );
        self::assertNotEmpty($azera, 'Azera must be drawn');
        self::assertNotEmpty($spiral, 'Spiral must be drawn');

        // Spiral ends LIGHTER (0.5 MB vs 4 MB), so it belongs ABOVE Azera on a
        // lighter-first ranking — even though Azera has the lighter boot.
        self::assertLessThan(
            (float) $azera[1],
            (float) $spiral[1],
            'the lighter END STATE must be drawn above the heavier one'
        );

        // And the reference row (the lightest dot) carries no multiplier, while
        // the heavier row does — so the column and the order speak the same
        // ranking. Under the old left-cap order this row would be the reference
        // and Azera would be labelled.
        // Numeric labels only: the note line also begins with 'x ' ('x = end
        // state ÷ …'), so a looser pattern picks the sentence up as if it were a
        // row's multiplier.
        preg_match_all('/>x ([\d.]+)<\/text>/', $svg, $fx);
        self::assertSame(
            ['8.0'],
            $fx[1],
            'Azera ends at 4.0 MB against Spiral\'s 0.5 MB, and Spiral is the unlabelled reference'
        );

        @unlink($path);
    }

    /**
     * A chart's note lines must fit inside the card, and a wrapped note must not
     * open with a stray separator.
     *
     * The bug this pins: the note lines are single <text> elements and SVG does
     * not wrap them, so a line wider than the card is silently CLIPPED at the
     * card's edge — the sentence just loses its ending. The resident-worker
     * caption grew a multiplier note and measured 11.7px past the edge at 960px;
     * nothing in the renderer objected, and the chart still "looked fine" until
     * the last words were gone.
     */
    public function testNoteLinesAreWrappedToFitInsideTheCard(): void
    {
        // A caption long enough to overflow even with the default geometry, so
        // the assertion is about wrapping rather than about one known string.
        // The multiplier note is spelled out in full — 'the lightest end state'
        // rather than 'lightest end state' — because the wrapping has to still
        // fit the REAL sentence the resident-worker page prints, and a shorter
        // stand-in would pass while the published chart clipped.
        $svg = SvgChart::memoryRange(
            [
                'Azera'  => ['color' => '#3459e6', 'low' => 0.20, 'mid' => 0.40, 'high' => 0.80],
                'Spiral' => ['color' => '#0ca678', 'low' => 0.10, 'mid' => 0.60, 'high' => 1.20],
            ],
            'Resident worker memory — footprint, end state and worst endpoint',
            'bar spans boot (left cap) → largest endpoint (right cap) · dot = heap after the last endpoint',
            'l',
            'h',
            'boot / end / worst',
            ' → ',
            960,
            'x = end state ÷ the lightest end state'
        );
        self::assertNotSame('', $svg);

        preg_match('/viewBox="0 0 ([\d.]+) [\d.]+"/', $svg, $vb);
        $width = (float) $vb[1];

        // The estimator is private; the point is to measure with the SAME one
        // the layout used, so reflection is the honest route to it.
        $m = new \ReflectionMethod(SvgChart::class, 'approxTextWidth');
        $m->setAccessible(true);
        $approx = static fn(string $s, float $size): float => (float) $m->invoke(null, $s, $size);

        preg_match_all(
            '/<text x="([\d.]+)" y="([\d.]+)"[^>]*font-size="([\d.]+)"[^>]*font-style="italic"[^>]*>([^<]*)<\/text>/',
            $svg,
            $lines,
            PREG_SET_ORDER
        );
        self::assertNotEmpty($lines, 'the note lines must be drawn');

        foreach ($lines as $l) {
            $end = (float) $l[1] + $approx(html_entity_decode($l[4], ENT_QUOTES | ENT_HTML5), (float) $l[3]);
            self::assertLessThanOrEqual(
                $width,
                $end,
                sprintf('a note line runs past the card edge: "%s"', $l[4])
            );
            // Ordinary wrapping typography: a continuation never opens with the
            // separator that was already consumed at the break, and never
            // dangles a trailing one either.
            self::assertStringStartsNotWith('·', $l[4]);
            self::assertStringEndsNotWith('·', $l[4]);
        }

        // The caption really did wrap — otherwise every assertion above is
        // satisfied by a single line that happened to fit, and the test would
        // pass with wrapping removed entirely.
        self::assertGreaterThan(
            1,
            count(array_filter(
                $lines,
                static fn(array $l): bool => str_contains($l[4], 'bar spans')
                    || str_contains($l[4], 'x = end state')
            )),
            'the caption and its multiplier note must occupy more than one line'
        );

        // The whole sentence survives — wrapping must not silently drop a
        // clause. Read back from the drawn text alone.
        $drawn = preg_replace('/\s+/', ' ', implode(' · ', array_map(
            static fn(array $l): string =>
                trim(html_entity_decode($l[4], ENT_QUOTES | ENT_HTML5)),
            $lines
        )));
        self::assertStringContainsString('x = end state ÷ the lightest end state', (string) $drawn);
        self::assertStringContainsString('bar spans boot (left cap)', (string) $drawn);
    }

    /**
     * The published prose must name the DOT as the ranking key, and must not
     * describe the left cap as the only rankable mark.
     *
     * The bug this pins is a documentation one, and it was live: the code sorted
     * the rows on the dot while two docblocks (residentMemory() in the report
     * and residentHeap() in the store) still asserted that only the left cap
     * ranks frameworks. A reader — or the next person to touch the sort — would
     * have found two contradictory statements and no way to tell which was the
     * intent. The rendered prose is asserted too, because that is the part a
     * reader actually sees.
     */
    public function testResidentWorkerProseNamesTheDotAsTheRankingKey(): void
    {
        $store = ResultStore::load(self::withRoadrunner());
        $md    = (new MarkdownReport($store, 'real-roadrunner', [
            'title'  => 'Real RR',
            'apps'   => ['azera', 'spiral'],
            'mode'   => 'roadrunner',
            'charts' => ['resident-memory'],
        ]))->render(dirname(__DIR__) . '/temp/tmp-rankkey-rr', 'svg/real-roadrunner');

        self::assertStringContainsString(
            'Rows are ordered by the **dot**',
            $md,
            'the published prose must state the ranking key'
        );
        self::assertStringNotContainsString(
            'Only the left cap ranks frameworks',
            $md,
            'the left cap is no longer the ranking key; the old sentence now contradicts the sort'
        );

        // The order-dependence caveat has to remain stated rather than quietly
        // dropped now that it is no longer used to justify the ordering — the
        // dot IS order-dependent, and a page that stopped saying so would be
        // hiding the one real weakness of the key it uses.
        self::assertStringContainsString('endpoint-order dependent', $md);

        // And the source of the reasoning must not regress to the old claim.
        $report = (string) file_get_contents(dirname(__DIR__) . '/scripts/report/MarkdownReport.php');
        self::assertStringNotContainsString(
            'only the left cap ranks frameworks',
            $report,
            'the code must not reassert the superseded rule'
        );
        $storeSrc = (string) file_get_contents(dirname(__DIR__) . '/scripts/report/ResultStore.php');
        self::assertStringNotContainsString(
            'Use residentBootHeap() for the footprint comparison',
            $storeSrc,
            'residentHeap() must not point readers at the boot heap as the ranking key'
        );
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::fixturePath());
        @unlink(dirname(__DIR__) . '/temp/tmp-peak-rr-fixture.json');
        @unlink(dirname(__DIR__) . '/temp/tmp-order-rr-fixture.json');
    }
}
