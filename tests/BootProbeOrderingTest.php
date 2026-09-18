<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Ordering invariant for the boot probe inside scripts/run-http.php.
 *
 * The probe file must be CLEARED BEFORE the server starts, never after it is
 * ready. The two servers record their sample at different moments:
 *
 *   roadrunner -> deploy/rr/worker.php records the worker boot while
 *                 `rr serve` comes up, i.e. BEFORE waitForServer() returns.
 *   php-fpm    -> the entry script re-runs per request, so samples only start
 *                 arriving after the probe requests in http-bench.php.
 *
 * The original code unlinked the file AFTER waitForServer(), which deleted the
 * RoadRunner sample that had just been produced. Every RR block then logged
 * "boot probe: no samples (file temp/boot-rr-<app>.jsonl missing)" while FPM
 * worked, and the boot chart silently drew only half the frameworks
 * (2026-09-16). This is a source-level assertion: it cannot run the benchmark,
 * but it pins the ordering that the bug depended on.
 */
final class BootProbeOrderingTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    public function testProbeFileIsClearedBeforeTheServerStarts(): void
    {
        $src = (string) file_get_contents(self::repoRoot() . '/scripts/run-http.php');

        $unlink = strpos($src, '@unlink($bootFile);');
        $start  = strpos($src, '$proc = startRoadRunner(');
        $ready  = strpos($src, 'waitForServer($baseUrl, $server, $appKey');

        self::assertNotFalse($unlink, 'run-http.php must clear the probe file');
        self::assertNotFalse($start, 'run-http.php must start RoadRunner');
        self::assertNotFalse($ready, 'run-http.php must wait for the server');

        // The whole bug in one comparison: clearing after the server is ready
        // destroys the RoadRunner worker's sample.
        self::assertLessThan(
            $start,
            $unlink,
            'the probe file must be cleared BEFORE the server starts — the RoadRunner '
                . 'worker records its boot while `rr serve` comes up, so clearing it after '
                . 'waitForServer() deletes the sample'
        );
        self::assertLessThan(
            $ready,
            $start,
            'sanity: the server is started before it is waited for'
        );
    }

    public function testWorkerRecordsBootBeforeItsRequestLoop(): void
    {
        $src = (string) file_get_contents(self::repoRoot() . '/deploy/rr/worker.php');

        $boot   = strpos($src, '$adapter->bootstrap();');
        $record = strpos($src, "boot_probe_record('rr', \$benchApp, true);");
        $loop   = strpos($src, 'while (true) {');

        self::assertNotFalse($boot, 'the worker must bootstrap the adapter');
        self::assertNotFalse($record, 'the worker must record its boot sample');
        self::assertNotFalse($loop, 'the worker must have a request loop');

        // Recording inside the loop would append once per request — the exact
        // hot-path write the header gate exists to avoid — and would time
        // request handling rather than worker startup.
        self::assertGreaterThan($boot, $record, 'the sample must be taken after bootstrap()');
        self::assertLessThan($loop, $record, 'the sample must be taken BEFORE the request loop');
    }

    public function testHttpBenchReadsTheSamePathRunHttpClears(): void
    {
        $runHttp   = (string) file_get_contents(self::repoRoot() . '/scripts/run-http.php');
        $httpBench = (string) file_get_contents(self::repoRoot() . '/scripts/http-bench.php');

        // A writer/reader path mismatch is silent: the probe reports "no
        // samples" and the chart falls back to the legacy proxy with no error.
        self::assertStringContainsString(
            "\$bootFile = \"{\$root}/temp/boot-\" . (\$server === 'rr' ? 'rr' : 'fpm') . \"-{\$appKey}.jsonl\";",
            $runHttp,
            'run-http.php must clear the per-(server, app) probe file'
        );
        self::assertStringContainsString(
            '/temp/boot-{$bootKind}-{$appKey}.jsonl',
            $httpBench,
            'http-bench.php must read the per-(server, app) probe file'
        );
    }
}
