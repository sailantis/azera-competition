<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/merge-mem.php — grafting a mem-only probe onto an existing
 * real-deployment dataset.
 *
 * The counterpart of MergeBootTest, and it guards the same class of mistake.
 * A mem-only pass takes seconds, the full 1000x10 latency run takes hours, and
 * the merge is what lets us add worker-memory numbers WITHOUT re-measuring
 * latency. Three ways that could go wrong silently, all pinned here:
 *
 *   1. It could put a PHP-FPM reading on the RoadRunner side (or the reverse),
 *      because the two probes use different transports in the same dataset.
 *   2. It could drop an endpoint the probe measured but the dataset does not
 *      have, leaving a chart drawn from a partial series. Memory is
 *      CUMULATIVE and order-dependent, so a missing endpoint does not just
 *      lose a point — it makes every later reading mean something different.
 *   3. It could overwrite timing fields while writing memory ones.
 *
 * Runs the real script in a child process against temp files, like
 * MergeBootTest does.
 */
final class MergeMemTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__) . '/temp/mergemem-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    /** @param array<string,mixed> $data */
    private function write(string $name, array $data): string
    {
        $path = "{$this->dir}/{$name}";
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }

    /** @return array<string,mixed> */
    private function dataset(): array
    {
        $row = static fn(string $r, float $ms): array => [
            'request'            => $r,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => $ms,
            'min_ms'             => 0.9,
            'mean_ms'            => 1.1,
            'median_ms'          => 1.0,
            'p95_ms'             => 1.3,
            'peak_mem'           => 0,
            'connect_ms'         => 0.1,
            'mem_boot_heap'      => 0,
            'mem_peak_heap'      => 0,
            'mem_heap'           => 0,
            'mem_rss'            => 0,
            'mem_hwm'            => 0,
            'mem_samples'        => 0,
        ];
        return [
            'env'  => ['php_version' => '8.3.33', 'os' => 'Linux', 'timestamp' => '2026-09-17T00:00:00+00:00'],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'roadrunner' => ['requests' => [$row('GET /', 0.267), $row('GET /items', 0.487)]],
                        'php-fpm'    => ['requests' => [$row('GET /', 0.782), $row('GET /items', 1.50)]],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string,array<string,int>> $rows request label => mem fields
     * @return array<string,mixed>
     */
    private function memPayload(string $app, string $server, array $rows): array
    {
        return ['app' => $app, 'server' => $server, 'mem_only' => true, 'mem_rows' => $rows];
    }

    /**
     * @return array{code:int,out:string}
     */
    private function merge(string $datasetPath, string ...$probePaths): array
    {
        $script = dirname(__DIR__) . '/scripts/merge-mem.php';
        $cmd    = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script)
            . ' ' . escapeshellarg($datasetPath);
        foreach ($probePaths as $p) {
            $cmd .= ' ' . escapeshellarg($p);
        }
        $out  = [];
        $code = 0;
        exec($cmd . ' 2>&1', $out, $code);
        return ['code' => $code, 'out' => implode("\n", $out)];
    }

    /** @return array<string,mixed> */
    private function read(string $path): array
    {
        return (array) json_decode((string) file_get_contents($path), true);
    }

    public function testMemoryIsWrittenOntoTheMatchingServerOnly(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', [
            'GET /'      => ['mem_boot_heap' => 990880, 'mem_peak_heap' => 1394568, 'mem_samples' => 10],
            'GET /items' => ['mem_boot_heap' => 990880, 'mem_peak_heap' => 1614552, 'mem_samples' => 10],
        ]));

        $r = $this->merge($ds, $fp);
        self::assertSame(0, $r['code'], $r['out']);

        $data   = $this->read($ds);
        $modes  = $data['apps'][0]['modes'];
        $fpmRow = $modes['php-fpm']['requests'][0];

        self::assertSame(1394568, $fpmRow['mem_peak_heap'], 'the FPM probe must land on php-fpm');
        self::assertSame(990880, $fpmRow['mem_boot_heap']);
        self::assertSame(10, $fpmRow['mem_samples']);

        // RoadRunner rows had zeros and must STILL have zeros — the two servers
        // were measured separately and a cross-write would silently mix them.
        self::assertSame(0, $modes['roadrunner']['requests'][0]['mem_peak_heap']);
        self::assertSame(0, $modes['roadrunner']['requests'][0]['mem_samples']);
    }

    /**
     * RoadRunner must be SKIPPED, and the reason stated.
     *
     * A resident worker's memory is ONE run's cumulative trajectory, and its
     * boot heap is not reproducible between runs — it is read warm, after the
     * worker's re-boot cycles. Measured on the bench VM 2026-09-17, that drift
     * was +0.071, +0.416, +1.925, -1.736, -0.002 and +0.071 MB across the six
     * apps. Grafting a probe pass's peak onto a dataset whose boot came from
     * the latency run therefore produced rows with peak BELOW boot —
     * arithmetically impossible for one worker, and a symptom of mixing two
     * runs rather than of a broken probe.
     */
    public function testRoadRunnerIsSkippedWithAReasonRatherThanMerged(): void
    {
        $ds = $this->write('ds.json', $this->dataset());

        // The full run's cumulative state, which a merge must not disturb.
        $data = $this->read($ds);
        $data['apps'][0]['modes']['roadrunner']['requests'][0]['mem_heap']      = 5555555;
        $data['apps'][0]['modes']['roadrunner']['requests'][0]['mem_rss']       = 6666666;
        $data['apps'][0]['modes']['roadrunner']['requests'][0]['mem_boot_heap'] = 4444444;
        file_put_contents($ds, json_encode($data, JSON_PRETTY_PRINT));

        $rr = $this->write('rr.json', $this->memPayload('azera', 'rr', [
            'GET /' => [
                'mem_boot_heap' => 111,
                'mem_peak_heap' => 999,
                'mem_heap'      => 222,
                'mem_samples'   => 10,
            ],
            'GET /items' => ['mem_boot_heap' => 111, 'mem_peak_heap' => 999, 'mem_samples' => 10],
        ]));

        $r = $this->merge($ds, $rr);
        // Skipping is not an error: the run probed both servers, so the caller
        // must not have to filter.
        self::assertSame(0, $r['code'], $r['out']);
        self::assertStringContainsString('Skipped', $r['out'], 'a skip must be reported');
        self::assertStringContainsString('roadrunner', $r['out']);
        self::assertStringContainsString('not reproducible', $r['out'], 'the reason must be stated');

        $row = $this->read($ds)['apps'][0]['modes']['roadrunner']['requests'][0];
        self::assertSame(0, $row['mem_peak_heap'], 'no peak may be grafted onto roadrunner');
        self::assertSame(0, $row['mem_samples']);
        // Untouched: everything the full run owns.
        self::assertSame(5555555, $row['mem_heap'], 'the cumulative trajectory belongs to the full run');
        self::assertSame(6666666, $row['mem_rss']);
        self::assertSame(4444444, $row['mem_boot_heap'], 'the warm boot must not be replaced');
    }

    /**
     * The merge must never write the cumulative fields, for either mode.
     *
     * mem_heap is cumulative, and the mem-only pass serves N probes per
     * endpoint where the full run serves one — so grafting it REPLACES a real
     * resident trajectory with a different quantity. On 2026-09-17 that turned
     * cakePHP's then-genuine 5.3 -> 40.6 MB climb (an adapter leak, since
     * fixed) into a flat 3.5 MB line.
     */
    public function testCumulativeHeapIsNeverOverwrittenEvenForFpm(): void
    {
        $ds = $this->write('ds.json', $this->dataset());

        $data = $this->read($ds);
        $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_heap'] = 7777777;
        $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_rss']  = 8888888;
        $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_hwm']  = 9999999;
        file_put_contents($ds, json_encode($data, JSON_PRETTY_PRINT));

        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', [
            'GET /' => [
                'mem_boot_heap' => 990880,
                'mem_peak_heap' => 1394568,
                'mem_heap'      => 1111,
                'mem_rss'       => 2222,
                'mem_hwm'       => 3333,
                'mem_samples'   => 10,
            ],
            'GET /items' => ['mem_boot_heap' => 990880, 'mem_peak_heap' => 1614552, 'mem_samples' => 10],
        ]));

        self::assertSame(0, $this->merge($ds, $fp)['code']);
        $row = $this->read($ds)['apps'][0]['modes']['php-fpm']['requests'][0];

        self::assertSame(1394568, $row['mem_peak_heap'], 'the per-request statistic is grafted');
        self::assertSame(7777777, $row['mem_heap'], 'the cumulative heap belongs to the full run');
        self::assertSame(8888888, $row['mem_rss']);
        self::assertSame(9999999, $row['mem_hwm']);
    }

    public function testTimingFieldsAreNotTouched(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', [
            'GET /'      => ['mem_peak_heap' => 1394568, 'mem_samples' => 10],
            'GET /items' => ['mem_peak_heap' => 1614552, 'mem_samples' => 10],
        ]));

        self::assertSame(0, $this->merge($ds, $fp)['code']);
        $data = $this->read($ds);

        self::assertSame(0.782, $data['apps'][0]['modes']['php-fpm']['requests'][0]['trimmed_mean_ms']);
        self::assertSame(1.50, $data['apps'][0]['modes']['php-fpm']['requests'][1]['trimmed_mean_ms']);
        self::assertSame(0.267, $data['apps'][0]['modes']['roadrunner']['requests'][0]['trimmed_mean_ms']);
    }

    public function testUnmatchedEndpointIsRefusedRatherThanSilentlyDropped(): void
    {
        // The probe measured an endpoint the dataset never timed, so the two
        // were taken against different endpoint sets — the merge must fail
        // loudly instead of grafting the subset that happened to match.
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', [
            'GET /'         => ['mem_peak_heap' => 1394568, 'mem_samples' => 10],
            'GET /features' => ['mem_peak_heap' => 1500000, 'mem_samples' => 10],
        ]));

        $r = $this->merge($ds, $fp);
        self::assertNotSame(0, $r['code'], 'an unmatched endpoint must abort the merge');
        self::assertStringContainsString('GET /features', $r['out']);

        // And it must have aborted BEFORE writing anything.
        $data = $this->read($ds);
        self::assertSame(0, $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_peak_heap']);
    }

    /**
     * A payload with no per-request peak must be REFUSED, not merged as zeros.
     *
     * Two ways it happens, and both used to be silent:
     *   - the payload predates the statistic (measured before 2026-09-17);
     *   - the payload is a STALE file left on the VM by an earlier run, which
     *     is exactly what a fetch-then-merge does not otherwise notice.
     * Zeros render as "this framework needs no memory", so silence here is
     * worse than an error.
     */
    public function testPayloadWithoutAPerRequestPeakIsRefused(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', [
            'GET /'      => ['mem_boot_heap' => 990880, 'mem_heap' => 1394568, 'mem_rss' => 0, 'mem_hwm' => 0],
            'GET /items' => ['mem_boot_heap' => 990880, 'mem_heap' => 1614552, 'mem_rss' => 0, 'mem_hwm' => 0],
        ]));

        $r = $this->merge($ds, $fp);
        self::assertNotSame(0, $r['code'], 'a payload without mem_peak_heap must be refused');
        self::assertStringContainsString('mem_peak_heap', $r['out']);

        // Nothing may have been written.
        $data = $this->read($ds);
        self::assertSame(0, $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_peak_heap']);
    }

    public function testPayloadThatIsNotAMemOnlyResultIsRefused(): void
    {
        $ds   = $this->write('ds.json', $this->dataset());
        $boot = $this->write('boot.json', [
            'app'       => 'azera',
            'server'    => 'fpm',
            'boot_only' => true,
            'boot'      => ['cold_ms' => 1.0, 'warm_ms' => 1.0],
        ]);

        $r = $this->merge($ds, $boot);
        self::assertNotSame(0, $r['code']);
        self::assertStringContainsString('mem_only', $r['out']);
    }

    public function testEmptyProbeRowsAreRefused(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('azera', 'fpm', []));

        $r = $this->merge($ds, $fp);
        self::assertNotSame(0, $r['code'], 'a probe with no rows must not be written');
    }

    public function testUnknownAppIsRefused(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fp = $this->write('fpm.json', $this->memPayload('nosuchapp', 'fpm', [
            'GET /' => ['mem_peak_heap' => 1, 'mem_samples' => 10],
        ]));

        $r = $this->merge($ds, $fp);
        self::assertNotSame(0, $r['code']);
        self::assertStringContainsString('nosuchapp', $r['out']);
    }

    public function testRoadRunnerProbeIsSkippedNotMappedOntoAnotherMode(): void
    {
        // The same script must ACCEPT an rr payload (--mem-only works for rr
        // too: it answers in headers) and then skip it — never map it onto
        // php-fpm, which would silently publish worker numbers as request
        // numbers on the wrong page.
        $ds = $this->write('ds.json', $this->dataset());
        $rr = $this->write('rr.json', $this->memPayload('azera', 'rr', [
            'GET /'      => ['mem_boot_heap' => 111, 'mem_peak_heap' => 222, 'mem_samples' => 10],
            'GET /items' => ['mem_boot_heap' => 111, 'mem_peak_heap' => 333, 'mem_samples' => 10],
        ]));

        $r = $this->merge($ds, $rr);
        self::assertSame(0, $r['code'], $r['out']);

        $data = $this->read($ds);
        self::assertSame(
            0,
            $data['apps'][0]['modes']['php-fpm']['requests'][0]['mem_peak_heap'],
            'an rr payload must never land on php-fpm'
        );
        self::assertSame(0, $data['apps'][0]['modes']['roadrunner']['requests'][0]['mem_peak_heap']);
        self::assertStringContainsString('Skipped', $r['out']);
    }
}