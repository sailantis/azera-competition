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
        //
        // Both sides now derive the kind from the SERVER through their own mapping
        // (probeKindFor() in run-http.php, a match in http-bench.php) because a third
        // arm exists and only the volucer spawn_cgi one differs from its neighbours.
        // The assertions therefore pin the two mappings AND the shared template they
        // both interpolate; pinning the old two-way ternary would have locked in the
        // bug that made a cold volucer arm read FPM's samples.
        self::assertStringContainsString(
            '$bootFile = "{$root}/temp/boot-{$bootKind}-{$appKey}.jsonl";',
            $runHttp,
            'run-http.php must clear the per-(server, app) probe file'
        );
        self::assertStringContainsString(
            '$bootKind = probeKindFor($server);',
            $runHttp,
            'run-http.php must derive the kind from the server, not from a two-way choice'
        );
        self::assertStringContainsString(
            '/temp/boot-{$bootKind}-{$appKey}.jsonl',
            $httpBench,
            'http-bench.php must read the per-(server, app) probe file'
        );
        self::assertStringContainsString(
            '$bootKind    = $probeKind;',
            $httpBench,
            'http-bench.php must read the SAME kind the orchestrator cleared'
        );
    }

    /**
     * Every public helper deploy-lib.php promises must actually be DEFINED.
     *
     * A function swallowed by an unterminated docblock is invisible to `php -l`
     * (the file is still valid PHP) and to every source-string test in this suite,
     * because they search for the text rather than for the definition. It surfaces
     * only at run time, on the VM, as "undefined function" part-way through a run —
     * which is exactly how `stampAllDeployConfigs()` was lost once. So the check is
     * `function_exists`, against the list of helpers run-http.php calls.
     */
    public function testEveryDeployHelperIsActuallyDefined(): void
    {
        require_once dirname(__DIR__) . '/scripts/deploy-lib.php';

        $required = [
            'stampPort',
            'stampAllDeployConfigs',
            'stampVolucerConfigs',
            'seedDatabase',
            'warmRouteCaches',
            'killAllBenchRoadRunners',
            'killAllBenchVolucers',
            'startRoadRunner',
            'stopRoadRunner',
            'startVolucer',
            'startVolucerSupervisor',
            'stopVolucer',
            'volucerRelayPath',
            'waitForRelay',
            'waitForPortFree',
            'waitForServer',
            'ensureFpmRunning',
            'measureFloors',
            'measureFloorApp',
            'runHttpBench',
            'phpCliForFpm',
            'phpSeriesFromFpmBin',
            'deploymentOpcache',
        ];

        foreach ($required as $fn) {
            self::assertTrue(
                function_exists($fn),
                "deploy-lib.php must define {$fn}() — run-http.php calls it, so a missing "
                    . 'definition is an undefined-function fatal in the middle of a run'
            );
        }
    }

    /**
     * Every arm the dataset measures has a probe kind, and both mappings agree.
     *
     * `--servers` is the authority on which arms exist: a name accepted there but
     * missing from probeKindFor() dies at block time, and a name mapped here but not
     * accepted there is dead code. Reading the list out of run-http.php keeps this test
     * honest when an arm is added or retired, instead of pinning a snapshot that has to
     * be edited by hand every time.
     */
    public function testEverySelectableArmHasAProbeKindInBothMappings(): void
    {
        $runHttp   = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');
        $httpBench = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');

        self::assertSame(
            1,
            preg_match('/\$validServers\s*=\s*\[(.*?)\];/s', $runHttp, $m),
            'run-http.php must declare $validServers'
        );
        preg_match_all("/'([a-z0-9-]+)'/", $m[1], $names);
        $servers = $names[1];
        self::assertNotEmpty($servers, '$validServers must list at least one server');

        foreach ($servers as $server) {
            foreach (['run-http.php' => $runHttp, 'http-bench.php' => $httpBench] as $label => $src) {
                self::assertMatchesRegularExpression(
                    "/'" . preg_quote($server, '/') . "'\s*=>\s*'(rr|fpm|volucer|volucer-cgi)'/",
                    $src,
                    "{$label} must map the selectable server '{$server}' to a probe kind"
                );
            }
        }
    }

    /**
     * The two Volucer arms must not collapse into one kind.
     *
     * This is the whole reason the mapping exists: the cold arm re-runs the FPM entry
     * scripts, so if it shared a kind with FPM or with the resident arm the block would
     * read another arm's samples — a wrong chart with a green run.
     */
    public function testTheVolucerArmsUseDistinctProbeKinds(): void
    {
        $runHttp   = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');
        $httpBench = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');

        foreach (['run-http.php' => $runHttp, 'http-bench.php' => $httpBench] as $label => $src) {
            self::assertMatchesRegularExpression(
                "/'volucer-cgi'\s*=>\s*'volucer-cgi'/",
                $src,
                "{$label}: the cold volucer arm must use its own probe kind, or it reads FPM's samples"
            );
            self::assertMatchesRegularExpression(
                "/'volucer-attach'\s*=>\s*'volucer'/",
                $src,
                "{$label}: the resident volucer arm's kind is 'volucer'"
            );
            self::assertStringNotContainsString(
                "'fpm'            => 'volucer'",
                $src,
                "{$label}: the cold volucer arm must not be mapped onto FPM's kind"
            );
        }
    }
}
