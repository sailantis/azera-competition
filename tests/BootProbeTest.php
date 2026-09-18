<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Boot probe — the "PHP start → framework ready" measurement used by the real
 * deployment views.
 *
 * The behaviour worth pinning is the GATE, not the arithmetic. Measured cost
 * on the bench VM: an ungated append is 3.12 us/op, which is 0.39% of the
 * fastest FPM row (azera 794 us) but only 0.016% of the slowest (spiral
 * 18984 us). Charging every request would therefore penalise the fastest
 * framework hardest — the same failure mode as the 50 ms cache sleep removed
 * on 2026-09-16. So a request WITHOUT the probe header must not write at all,
 * and that is what this test locks down.
 */
final class BootProbeTest extends TestCase
{
    private string $probeFile;

    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/boot-probe.php';
        $this->probeFile = dirname(__DIR__) . '/temp/boot-fpm-__test__.jsonl';
        @unlink($this->probeFile);
        @unlink(dirname(__DIR__) . '/temp/boot-rr-__test__.jsonl');
        unset($_SERVER['HTTP_X_BOOT_PROBE']);
        // In production the clock is per-request (FPM resets globals between
        // requests) or set once per worker lifetime. In one PHPUnit process
        // the global would leak from test to test, so reset it explicitly —
        // otherwise "record without start" sees the previous test's clock.
        unset($GLOBALS['__boot_probe_t0']);
    }

    protected function tearDown(): void
    {
        @unlink($this->probeFile);
        @unlink(dirname(__DIR__) . '/temp/boot-rr-__test__.jsonl');
        unset($_SERVER['HTTP_X_BOOT_PROBE']);
        unset($GLOBALS['__boot_probe_t0']);
    }

    public function testSampleFileIsInTempAndNamespacedByKindAndApp(): void
    {
        $path = boot_probe_file('fpm', 'azera');
        $this->assertStringEndsWith('/temp/boot-fpm-azera.jsonl', str_replace('\\', '/', $path));
        $this->assertStringEndsWith('/temp/boot-rr-spiral.jsonl', str_replace('\\', '/', boot_probe_file('rr', 'spiral')));
    }

    /**
     * The sample path must be INSIDE the repo, and must be the exact path the
     * client-side reader looks at.
     *
     * This is the assertion that earns its keep: an earlier version derived the
     * path from `dirname(__DIR__)`, which pointed one directory ABOVE the repo.
     * Every write then failed silently, the probe returned zero samples, and
     * the report quietly fell back to the legacy startup proxy — the bug this
     * whole feature exists to fix. A weaker "ends with /temp/..." assertion
     * passes for both the right and the wrong path, so it must be checked
     * against the repo root explicitly.
     */
    public function testSamplePathIsInsideTheRepoAndMatchesTheReader(): void
    {
        $repo = str_replace('\\', '/', dirname(__DIR__));
        $path = str_replace('\\', '/', boot_probe_file('fpm', 'azera'));

        $this->assertStringStartsWith($repo . '/', $path, 'sample path escaped the repo root');
        $this->assertStringNotContainsString('..', $path);

        // scripts/http-bench.php builds the read path as dirname(__DIR__) from
        // scripts/ — i.e. the repo root. Writer and reader must agree exactly,
        // or samples land in a file nothing ever reads.
        $reader = $repo . '/temp/boot-fpm-azera.jsonl';
        $this->assertSame($reader, $path, 'writer path and reader path disagree');
    }

    public function testRequestWithoutProbeHeaderWritesNothing(): void
    {
        boot_probe_start();
        $this->assertFalse(boot_probe_record('fpm', '__test__'), 'record() must refuse when the header is absent');
        $this->assertFileDoesNotExist($this->probeFile, 'a gated request must not touch the filesystem');
    }

    public function testRequestWithProbeHeaderWritesOneSample(): void
    {
        $_SERVER['HTTP_X_BOOT_PROBE'] = '1';
        boot_probe_start();
        $this->assertTrue(boot_probe_record('fpm', '__test__'));

        $samples = boot_probe_samples('fpm', '__test__');
        $this->assertCount(1, $samples);
        $this->assertGreaterThan(0.0, $samples[0]);
        $this->assertLessThan(60000.0, $samples[0]);
    }

    public function testSamplesAccumulateSoTheMedianIsAvailable(): void
    {
        $_SERVER['HTTP_X_BOOT_PROBE'] = '1';
        // Independent of whatever earlier tests left behind: the point is that
        // N probe requests produce N samples, which is what makes a median
        // meaningful instead of a single lucky first request.
        @unlink($this->probeFile);
        for ($i = 0; $i < 25; $i++) {
            boot_probe_start();
            $this->assertTrue(boot_probe_record('fpm', '__test__'));
        }
        $this->assertCount(25, boot_probe_samples('fpm', '__test__'), 'every probe request must contribute a sample');
    }

    public function testForceBypassesTheHeaderForTheWorkerBoot(): void
    {
        // The RoadRunner worker boots BEFORE it can see a request, so there is
        // no header to gate on; its call site forces the write. This runs once
        // per worker lifetime, never in a hot path.
        boot_probe_start();
        $this->assertTrue(boot_probe_record('rr', '__test__', true));
        $this->assertCount(1, boot_probe_samples('rr', '__test__'));
    }

    public function testRecordWithoutStartReportsNoSampleInsteadOfGarbage(): void
    {
        // A miswired entry script must not append a nonsense number.
        $_SERVER['HTTP_X_BOOT_PROBE'] = '1';
        $this->assertFalse(boot_probe_record('fpm', '__test__'));
        $this->assertSame([], boot_probe_samples('fpm', '__test__'));
    }

    public function testSamplesAreReadableBackAndSurviveGarbageLines(): void
    {
        $file = boot_probe_file('fpm', '__test__');
        file_put_contents($file, "1.5\nnot-a-number\n2.5\n\n3.5\n");
        $this->assertSame([1.5, 2.5, 3.5], boot_probe_samples('fpm', '__test__'));
    }
}
