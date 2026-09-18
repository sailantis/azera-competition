<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The worker-memory probe runs on BOTH real servers, and the two transports
 * must be interchangeable to every reader.
 *
 * Until 2026-09-17 the probe existed only in deploy/rr/worker.php, which
 * answers in response headers because a RoadRunner worker is resident in a
 * request loop. The real-fpm rows therefore carried mem_boot_heap = 0 and the
 * real-fpm view had no memory data at all — even though the FPM pool is
 * pm=static/max_children=1/max_requests=0, i.e. ONE worker resident for the
 * whole block that demonstrably retains what it built.
 *
 * FPM cannot answer in headers: its entry script runs, serves one request and
 * is torn down. It appends to temp/mem-fpm-<app>.jsonl from a shutdown hook
 * instead (mem_probe_arm() in boot-probe.php) and the harness reads the newest
 * line back.
 *
 * These tests pin the CONTRACT rather than the plumbing: the same four field
 * names on both sides, and report-side behaviour that does not depend on which
 * server produced the row. That is what stops the two transports from drifting
 * into two subtly different measurements.
 */
final class FpmMemoryProbeTest extends TestCase
{
    private static function fixturePath(): string
    {
        return dirname(__DIR__) . '/temp/' . 'tmp-fpm-mem-fixture.json';
    }

    /**
     * A minimal real-deployments dataset: two apps in both modes.
     *
     * Built explicitly rather than by copying results/real-deployments.json,
     * so the assertions below state their own premises instead of silently
     * depending on what the last VM run happened to produce.
     *
     * @param array{boot:int,peak:int,heap:int,rss:int,hwm:int} $fpm
     * @param array{boot:int,peak:int,heap:int,rss:int,hwm:int} $rr
     */
    private static function writeFixture(array $fpm, array $rr): string
    {
        $requests = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $base = [
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
            ];
            $requests[$req] = $base;
        }

        $app = static function (string $key, array $fpm, array $rr) use ($requests): array {
            $rrRows = [];
            $fpRows = [];
            foreach ($requests as $req => $base) {
                $rrRows[] = $base + [
                        'mem_boot_heap' => $rr['boot'],
                        'mem_peak_heap' => $rr['peak'],
                        'mem_heap'      => $rr['heap'],
                        'mem_rss'       => $rr['rss'],
                        'mem_hwm'       => $rr['hwm'],
                    ];
                $fpRows[] = $base + [
                        'mem_boot_heap' => $fpm['boot'],
                        'mem_peak_heap' => $fpm['peak'],
                        'mem_heap'      => $fpm['heap'],
                        'mem_rss'       => $fpm['rss'],
                        'mem_hwm'       => $fpm['hwm'],
                        'boot_ms'       => 0.5,
                    ];
            }
            return [
                'app'   => $key,
                'modes' => [
                    'roadrunner' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $rrRows],
                    'php-fpm'    => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $fpRows],
                ],
            ];
        };

        $payload = [
            'env'  => ['php_version' => '8.3.33', 'os' => 'Linux', 'timestamp' => '2026-09-17T00:00:00+00:00'],
            'apps' => [
                $app('azera', $fpm, $rr),
                $app('spiral', ['boot' => 7000000, 'peak' => 7200000, 'heap' => 8000000, 'rss' => 0, 'hwm' => 0],
                    ['boot' => 7422584, 'peak' => 7500000, 'heap' => 8516056, 'rss' => 0, 'hwm' => 0]
                ),
            ],
        ];

        $path = self::fixturePath();
        file_put_contents($path, json_encode($payload));
        return $path;
    }

    public function testPhpFpmRowsExposeResidentMemoryLikeRoadRunnerRows(): void
    {
        $store = ResultStore::load(self::writeFixture(
            ['boot' => 990880, 'peak' => 1394568, 'heap' => 1394568, 'rss' => 50000000, 'hwm' => 51000000],
            ['boot' => 990880, 'peak' => 1394568, 'heap' => 1394568, 'rss' => 50000000, 'hwm' => 51000000]
        ));

        // The whole point: FPM can answer the same question RR answers.
        self::assertTrue($store->hasResidentMem('php-fpm'), 'FPM rows must carry the probe');
        self::assertTrue($store->hasResidentMem('roadrunner'));
        self::assertSame(990880, $store->residentBootHeap('azera', 'php-fpm'));
        self::assertSame(990880, $store->residentBootHeap('azera', 'roadrunner'));
    }

    public function testFpmSampleFieldsMatchTheRoadRunnerHeaderFields(): void
    {
        // boot-probe.php must emit exactly the five keys deploy/rr/worker.php
        // answers with, or the harness's mapping becomes a translation that
        // can drift. Read both sources rather than trusting the docblocks.
        //
        // The field set is FIVE since 2026-09-17: peak joined boot/heap/rss/hwm
        // when the probe moved from a post-request heap snapshot to a
        // per-request high-water mark. The two sides must still be identical
        // sets, which is the property this test exists to pin.
        $probe = (string) file_get_contents(dirname(__DIR__) . '/boot-probe.php');
        $rrW   = (string) file_get_contents(dirname(__DIR__) . '/deploy/rr/worker.php');

        $fields = ['boot', 'peak', 'heap', 'rss', 'hwm'];
        foreach ($fields as $field) {
            self::assertMatchesRegularExpression(
                "/'" . $field . "'\s+=>/",
                $probe,
                "boot-probe.php must write a '{$field}' key"
            );
        }
        // The RR worker answers through X-Bench-<Field> headers; those names
        // are the contract the harness reads back.
        $headers = ['X-Bench-Boot', 'X-Bench-Peak', 'X-Bench-Heap', 'X-Bench-Rss', 'X-Bench-Hwm'];
        foreach ($headers as $header) {
            self::assertStringContainsString($header, $rrW, "{$header} must still be answered");
        }

        // Neither side may grow a field the other lacks — that is exactly the
        // drift this test guards, and the loops above only catch MISSING
        // fields, never an extra one. Extracting both sets and comparing them
        // as sets catches either direction.
        //
        // Scoped to the MEMORY probe's own names on both sides: the RR worker
        // also answers X-Bench-Split/Handle-Us/Cleanup-Us for a different
        // measurement entirely, and counting those would compare field sets of
        // two unrelated probes.
        preg_match_all("/'(boot|peak|heap|rss|hwm)'\s+=>/", $probe, $pm);
        preg_match_all('/X-Bench-(Boot|Peak|Heap|Rss|Hwm)\b/', $rrW, $rm);

        $probeFields = array_values(array_unique(array_map('strtolower', $pm[1])));
        $rrFields    = array_values(array_unique(array_map('strtolower', $rm[1])));
        sort($probeFields);
        sort($rrFields);

        self::assertSame(
            $probeFields,
            $rrFields,
            'both probes must report the same memory field set, in either direction'
        );

        // And the client-side reader must normalise to those same keys.
        $lib = (string) file_get_contents(dirname(__DIR__) . '/scripts/bench-lib.php');
        self::assertStringContainsString("'hwm'  => (int) (\$row['hwm'] ?? 0)", $lib);
        self::assertStringContainsString("'peak' => \$peak,", $lib);
    }

    public function testFpmProbeIsGatedOnAHeaderTimedRequestsNeverSend(): void
    {
        // An ungated 3.124 us/op append would charge the fastest framework
        // ~0.39% and the slowest ~0.016%: a systematic bias, not noise. The
        // gate is the whole reason the probe is admissible.
        $probe = (string) file_get_contents(dirname(__DIR__) . '/boot-probe.php');

        self::assertStringContainsString('function mem_probe_wanted(): bool', $probe);
        self::assertStringContainsString("const MEM_PROBE_HEADER = 'X-Mem-Probe';", $probe);
        self::assertStringContainsString(
            'if (!mem_probe_wanted()) {',
            $probe,
            'mem_probe_arm() must return before registering anything when ungated'
        );
        // The shutdown hook must live INSIDE mem_probe_arm(), after the gate
        // and after the boot heap is read — not at file scope, where it would
        // arm the probe on every request regardless of the header.
        //
        // Scoped to the function BODY: the docblock above mem_probe_arm()
        // mentions register_shutdown_function too, so a whole-file strpos()
        // would match prose and pass no matter where the real call sat.
        $armPos = strpos($probe, 'function mem_probe_arm');
        $wriPos = strpos($probe, 'function mem_probe_write');
        self::assertNotFalse($armPos);
        self::assertNotFalse($wriPos);

        $body = substr($probe, $armPos, $wriPos - $armPos);
        self::assertStringContainsString(
            'register_shutdown_function',
            $body,
            'the shutdown hook must be registered inside mem_probe_arm()'
        );
        self::assertStringContainsString(
            'mem_probe_wanted()',
            $body,
            'mem_probe_arm() must gate on the header before arming anything'
        );
    }

    public function testEveryEntryScriptArmsTheFpmProbe(): void
    {
        // A framework that forgets to arm it silently reports no memory, which
        // looks like "this framework uses nothing" rather than "no data".
        $apps = ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
        foreach ($apps as $app) {
            $src = (string) file_get_contents(dirname(__DIR__) . "/public/index-{$app}.php");
            self::assertStringContainsString(
                "mem_probe_arm('{$app}')",
                $src,
                "public/index-{$app}.php must arm the FPM memory probe"
            );
        }
    }

    /**
     * The page must not claim a transport it did not use, and must describe
     * the statistic the server can actually support.
     *
     * Before this change the prose said "Read from inside the live RoadRunner
     * worker" unconditionally, so the real-fpm view asked a reader to believe
     * numbers came from a worker loop FPM does not have. The two transports
     * now also carry DIFFERENT statistics (a resident worker's cumulative
     * trajectory vs a fresh process's per-request peak), so the section titles
     * differ as well: "Resident worker memory" is only true where a worker is
     * resident.
     */
    public function testResidentMemoryProseDoesNotAttributeFpmNumbersToRoadRunner(): void
    {
        $store = ResultStore::load(self::writeFixture(
            ['boot' => 990880, 'peak' => 1394568, 'heap' => 1394568, 'rss' => 0, 'hwm' => 0],
            ['boot' => 990880, 'peak' => 1394568, 'heap' => 1394568, 'rss' => 0, 'hwm' => 0]
        ));
        $apps = ['azera', 'spiral'];
        $dir  = dirname(__DIR__) . '/temp/tmp-fpm-mem-svg';

        $fpmMd = (new MarkdownReport($store, 'real-fpm', [
            'title'  => 'Real FPM',
            'apps'   => $apps,
            'mode'   => 'php-fpm',
            'charts' => ['resident-memory'],
        ]))->render($dir . '-fpm', 'svg/real-fpm');

        $rrMd = (new MarkdownReport($store, 'real-roadrunner', [
            'title'  => 'Real RR',
            'apps'   => $apps,
            'mode'   => 'roadrunner',
            'charts' => ['resident-memory'],
        ]))->render($dir . '-rr', 'svg/real-roadrunner');

        // FPM: a per-request page that never mentions a resident worker.
        self::assertStringContainsString('## Per-request memory', $fpmMd);
        self::assertStringNotContainsString(
            'RoadRunner worker',
            $fpmMd,
            'the FPM page must not claim its numbers came from a RoadRunner worker'
        );
        self::assertStringNotContainsString(
            'Resident worker memory',
            $fpmMd,
            'a fresh-process server has no resident worker to describe'
        );
        self::assertStringContainsString('max_requests = 0', $fpmMd);
        // The per-request question, stated as such.
        self::assertStringContainsString('high-water mark', $fpmMd);

        // RR: the resident-worker page keeps its own section and transport.
        self::assertStringContainsString('RoadRunner worker', $rrMd);
        self::assertStringContainsString('## Resident worker memory', $rrMd);
        self::assertStringNotContainsString('Per-request memory', $rrMd);
    }

    /**
     * The read must WAIT for the shutdown hook's write.
     *
     * PHP runs shutdown functions after the response has been flushed, so curl
     * returns before the probe's file append happens. Reading straight away
     * raced that write on the bench VM (2026-09-17) and recorded a zero row
     * for the FIRST endpoint of laravel, cakephp and symfony — a partial
     * wrongness that renders fine and is easy to miss.
     *
     * The contract is tested deterministically rather than with a real child
     * process: what matters is that a line that appeared AFTER the caller took
     * its baseline is returned in preference to one that was already there.
     * The genuine race is verified by re-running the probe on the VM, where
     * the fix's effect is visible in the first endpoint's row.
     */
    public function testMemoProbeReadReturnsTheSampleThatAppearedAfterTheBaseline(): void
    {
        $file = dirname(__DIR__) . '/temp/tmp-fpm-race-' . bin2hex(random_bytes(4)) . '.jsonl';
        file_put_contents($file, json_encode(['boot' => 111, 'heap' => 222, 'rss' => 0, 'hwm' => 0]) . "\n");

        // Baseline taken BEFORE the "probe request".
        $before = memProbeLineCount($file);
        self::assertSame(1, $before);

        // The shutdown hook lands a second sample.
        file_put_contents(
            $file,
            json_encode(['boot' => 999, 'heap' => 1000, 'rss' => 0, 'hwm' => 0]) . "\n",
            FILE_APPEND
        );

        $sample = memProbeWaitForNewSample($file, $before, 500);
        @unlink($file);

        self::assertNotNull($sample, 'a new sample must be picked up');
        self::assertSame(999, $sample['boot'], 'the NEW sample must win over the pre-existing one');
        self::assertSame(1000, $sample['heap']);
    }

    public function testWaitGivesUpInsteadOfHangingWhenNoSampleArrives(): void
    {
        // A framework that forgot to arm the probe must fail fast. If this
        // blocks, one missing arm site turns into a hung benchmark run.
        $file = dirname(__DIR__) . '/temp/tmp-fpm-nosample-' . bin2hex(random_bytes(4)) . '.jsonl';
        file_put_contents($file, json_encode(['boot' => 1, 'heap' => 2, 'rss' => 0, 'hwm' => 0]) . "\n");

        $t0     = microtime(true);
        $sample = memProbeWaitForNewSample($file, memProbeLineCount($file), 200);
        $took   = microtime(true) - $t0;

        @unlink($file);

        self::assertNull($sample, 'no new sample must return null, not stale data');
        self::assertLessThan(2.0, $took, 'the wait must be bounded');
    }

    /**
     * Every FPM memory read must go through the baseline-then-wait dance.
     *
     * There are two read paths — the --mem-only pass and the full run's
     * per-endpoint probe — and fixing only one would leave the other racy,
     * with a symptom (one wrong row per app) that is easy to overlook. Since
     * 2026-09-17 the --mem-only path calls memProbeRepeated(), so the
     * assertions moved a level down: neither path may read the sample file
     * without first taking a line count.
     *
     * Pinned as a CONTRACT (no direct read in the harness; the shared helper
     * baselines before every wait) rather than as a raw count of call
     * occurrences, which just breaks whenever the code is refactored — as it
     * did here, while the invariant was in fact preserved.
     */
    public function testBothFpmMemoryReadSitesWaitForTheSample(): void
    {
        $harness = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');
        $lib     = (string) file_get_contents(dirname(__DIR__) . '/scripts/bench-lib.php');

        // 1. The full-run probe still baselines, then waits.
        self::assertStringContainsString(
            '$before = memProbeLineCount($memFile);',
            $harness,
            'the full-run probe needs its own pre-probe baseline'
        );
        self::assertStringContainsString(
            '$sample = memProbeWaitForNewSample($memFile, $before);',
            $harness
        );

        // 2. The --mem-only pass must reuse the repeating helper, which
        //    baselines internally — not read the file itself.
        self::assertStringContainsString(
            'memProbeRepeated(',
            $harness,
            'the --mem-only pass must probe through memProbeRepeated()'
        );
        self::assertStringNotContainsString(
            'memProbeReadLast(',
            $harness,
            'reading the sample file without a baseline is the race this pins'
        );

        // 3. And that helper must baseline before EVERY wait, so the Nth
        //    repeat cannot return the (N-1)th sample.
        self::assertStringContainsString('$before = memProbeLineCount($file);', $lib);
        self::assertStringContainsString('memProbeWaitForNewSample($file, $before)', $lib);
    }

    public static function tearDownAfterClass(): void
    {
        foreach ([
            self::fixturePath(),
            dirname(__DIR__) . '/temp/tmp-fpm-mem-svg-fpm',
            dirname(__DIR__) . '/temp/tmp-fpm-mem-svg-rr',
        ] as $path) {
            if (is_dir($path)) {
                foreach (glob($path . '/**/*') ?: [] as $f) {
                    @unlink($f);
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        // Race-test leftovers.
        foreach (glob(dirname(__DIR__) . '/temp/tmp-fpm-race-*.jsonl') ?: [] as $f) {
            @unlink($f);
        }
        foreach (glob(dirname(__DIR__) . '/temp/tmp-fpm-nosample-*.jsonl') ?: [] as $f) {
            @unlink($f);
        }
    }
}