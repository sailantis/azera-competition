<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every server a row can be measured on must also have a FLOOR probe.
 *
 * WHY THIS IS A TEST AND NOT A COMMENT. A floor is one `GET /` to the same server
 * with no framework behind it, so it is the only thing in the dataset that says how
 * much of a row is the SERVER. Without it a reader has no way to know that a 149 us
 * `/echo` row on volucer-attach contains 4 us of framework — and on 2026-11-24 that
 * is exactly the mistake that was made: `/echo` was compared across servers as if the
 * numbers were the frameworks, when they were almost entirely two different floors.
 *
 * The two Volucer arms went a full release with no floor. `floor-http`, `floor-php` and
 * `floor-rr` covered the servers that had them since the first real run, so the gap was
 * invisible: the dataset HAD a floors block, it was just short by two entries, and
 * nothing consumed the floors block in a way that noticed.
 *
 * A floor must also be attached to the RIGHT mode. `measureFloorApp()` used to send
 * every non-RoadRunner server down an `'fpm'` shim, so both Volucer floors came back
 * labelled `php-fpm` — a probe that exists, is plausible, and describes the wrong
 * server. The mode is the whole point, so it is asserted.
 */
final class FloorCoverageTest extends TestCase
{
    /** Every mode a measured row can carry. */
    private const MODES = ['roadrunner', 'php-fpm', 'volucer-attach', 'volucer-cgi'];

    /** The floor app key the dataset uses for each mode. */
    private const FLOOR_FOR_MODE = [
        'roadrunner'     => 'floor-rr',
        'php-fpm'        => 'floor-php',
        'volucer-attach' => 'floor-volucer-attach',
        'volucer-cgi'    => 'floor-volucer-cgi',
    ];

    private static function dataset(): array
    {
        $path = dirname(__DIR__) . '/results/real-deployments.json';
        self::assertFileExists($path, 'the canonical dataset must be present');

        return (array) json_decode((string) file_get_contents($path), true);
    }

    /**
     * The canonical dataset carries one floor per mode it measures.
     */
    public function testTheDatasetFloorsCoverEveryMeasuredMode(): void
    {
        $dataset = self::dataset();

        $modes = [];
        foreach (($dataset['apps'] ?? []) as $app) {
            foreach (array_keys((array) ($app['modes'] ?? [])) as $mode) {
                $modes[$mode] = true;
            }
        }
        self::assertNotSame([], $modes, 'the dataset must carry at least one measured mode');

        // Mode => floor app key actually present.
        $floorMode = [];
        foreach (($dataset['floors'] ?? []) as $floor) {
            $keys = array_keys((array) ($floor['modes'] ?? []));
            self::assertCount(
                1,
                $keys,
                "floor '{$floor['app']}' must carry exactly one mode — it is one server"
            );
            $floorMode[$keys[0]] = $floor['app'];
        }

        foreach (array_keys($modes) as $mode) {
            self::assertArrayHasKey(
                $mode,
                $floorMode,
                "no floor probe for mode '{$mode}': its rows carry server overhead with nothing to hold them against"
            );

            self::assertSame(
                self::FLOOR_FOR_MODE[$mode],
                $floorMode[$mode],
                "mode '{$mode}' must be floored by " . self::FLOOR_FOR_MODE[$mode]
                    . ', not by ' . $floorMode[$mode]
            );
        }
    }

    /**
     * A floor must be a real measurement, not a placeholder.
     *
     * A zero or absent `GET /` row would satisfy a keys-only check while giving a
     * reader nothing to subtract, and the failure would look like a fast server.
     */
    public function testEveryFloorCarriesARealMeasurement(): void
    {
        $dataset = self::dataset();

        foreach (($dataset['floors'] ?? []) as $floor) {
            $mode = array_key_first((array) ($floor['modes'] ?? []));
            $row  = null;
            foreach ((array) ($floor['modes'][$mode]['requests'] ?? []) as $candidate) {
                if (($candidate['request'] ?? null) === 'GET /') {
                    $row = $candidate;
                }
            }

            self::assertNotNull($row, "floor '{$floor['app']}' has no GET / row");
            self::assertGreaterThan(
                0.0,
                (float) ($row['median_ms'] ?? 0.0),
                "floor '{$floor['app']}' has a non-positive median"
            );
            self::assertGreaterThan(
                0.0,
                (float) ($row['min_ms'] ?? 0.0),
                "floor '{$floor['app']}' has a non-positive minimum"
            );
        }
    }

    /**
     * The floor sources exist and are framework-free.
     *
     * The point of a floor is that no framework is in it. An entry script that grew a
     * `require` of an autoloader would still run, still answer, and silently turn the
     * floor into a second measurement of an app — with no error anywhere.
     */
    public function testTheFloorSourcesDoNotBootAFramework(): void
    {
        $root = dirname(__DIR__);

        $entryScripts = [
            $root . '/public/index-floor-volucer-attach.php',
            $root . '/public/index-floor-volucer-cgi.php',
        ];
        foreach ($entryScripts as $path) {
            self::assertFileExists($path, basename($path) . ' must exist for the stampVolucerConfigs() front_controller');

            $src = (string) file_get_contents($path);
            foreach (['vendor/autoload.php', 'WebAppAdapter.php', 'bootstrap()', 'Bootstrap::boot'] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $src,
                    basename($path) . " must not load a framework ({$forbidden})"
                );
            }
        }

        // The attach floor needs its own supervisor: app.php exits on an unknown
        // BENCH_APP by design, so a floor cannot travel through it.
        $supervisor = $root . '/deploy/volucer/attach-floor.php';
        self::assertFileExists($supervisor);
        $src = (string) file_get_contents($supervisor);
        self::assertStringNotContainsString(
            "require __DIR__ . '/app.php'",
            $src,
            'the floor supervisor must not require app.php — that is the path that selects a framework adapter'
        );
        self::assertStringContainsString('Supervisor::serve(', $src);
    }

    // --- a floor the rows can actually be held against ----------------------

    /** One floor probe carrying a single `GET /` row. */
    private static function floor(string $app, string $mode, float $trimmedMs): array
    {
        return [
            'app'   => $app,
            'modes' => [
                $mode => [
                    'iterations_per_run' => 1000,
                    'runs'               => 10,
                    'requests'           => [[
                        'request'         => 'GET /',
                        'trimmed_mean_ms' => $trimmedMs,
                        'median_ms'       => $trimmedMs,
                    ]],
                ],
            ],
        ];
    }

    /** One app block whose rows are the given "<request> => ms" map on one mode. */
    private static function appOn(string $app, string $mode, array $rows): array
    {
        $requests = [];
        foreach ($rows as $label => $ms) {
            $requests[] = ['request' => $label, 'trimmed_mean_ms' => $ms];
        }

        return [
            'app'   => $app,
            'modes' => [$mode => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => $requests]],
        ];
    }

    /**
     * The exact 2026-11-23 shape is rejected.
     *
     * These are the published figures: a floor of 0.1734 ms on volucer-attach and a
     * Phalcon `/echo` row 13.9% below it. The row is not fast, it is impossible — and
     * it was read as a framework win for a release.
     */
    public function testARowBelowItsOwnFloorIsRejected(): void
    {
        $result = assertRowsNotBelowFloor([
            'apps'   => [self::appOn('phalcon', 'volucer-attach', ['GET /echo' => 0.1494, 'GET /' => 0.1767])],
            'floors' => [self::floor('floor-volucer-attach', 'volucer-attach', 0.1734)],
        ]);

        self::assertFalse($result['ok'], 'a row cannot beat a request that boots nothing');
        self::assertCount(1, $result['violations'], 'only the sub-floor row is a violation');
        self::assertSame('GET /echo', $result['violations'][0]['request']);
        self::assertSame('phalcon', $result['violations'][0]['app']);
    }

    /**
     * Sampling error is NOT a violation.
     *
     * A row and a floor are two independent samples — often from DIFFERENT runs — so
     * a row a little under its floor is ordinary drift. Measured on the bench VM, two
     * volucer-attach `/echo` runs of the same code differed by 3.0% and 5.0%, so the
     * default tolerance is 8%. This case is the largest such drift the real data
     * contains (Phalcon `/echo` against the correctly-sampled floor), and it must
     * pass or the check would fail every honest run.
     */
    public function testASmallDeficitInsideTheNoiseBandPasses(): void
    {
        $result = assertRowsNotBelowFloor([
            'apps'   => [self::appOn('phalcon', 'volucer-attach', ['GET /echo' => 0.1494])],
            'floors' => [self::floor('floor-volucer-attach', 'volucer-attach', 0.1588)],
        ]);

        self::assertTrue($result['ok'], '5.9% under the floor is cross-run drift, not a mismatch');
        self::assertSame(1, $result['checked'], 'the row must still be counted as checked');
    }

    /**
     * A missing floor is skipped, not failed.
     *
     * "No reference point" and "inconsistent with the reference point" are different
     * states, and only the second is this check's business —
     * testTheDatasetFloorsCoverEveryMeasuredMode() is what asserts the first.
     */
    public function testAModeWithNoFloorIsSkippedRatherThanFailed(): void
    {
        $result = assertRowsNotBelowFloor([
            'apps'   => [self::appOn('azera', 'volucer-attach', ['GET /echo' => 0.1])],
            'floors' => [],
        ]);

        self::assertTrue($result['ok']);
        self::assertSame(0, $result['checked'], 'nothing to hold the row against');
    }

    /**
     * The tolerance is a real knob, not decoration.
     *
     * The 13.9% case sits far outside the default, so a caller with a noisier server
     * has to widen it explicitly. Asserting the boundary keeps the default from
     * silently growing into "no check at all".
     */
    public function testTheToleranceIsHonoured(): void
    {
        $dataset = [
            'apps'   => [self::appOn('phalcon', 'volucer-attach', ['GET /echo' => 0.1494])],
            'floors' => [self::floor('floor-volucer-attach', 'volucer-attach', 0.1734)],
        ];

        self::assertFalse(assertRowsNotBelowFloor($dataset, 0.02)['ok']);
        self::assertFalse(assertRowsNotBelowFloor($dataset, 0.05)['ok']);
        self::assertTrue(
            assertRowsNotBelowFloor($dataset, 1.00)['ok'],
            'a 100% tolerance must accept any deficit — the knob is honoured'
        );
        // And the DEFAULT must be the calibrated 8%, not something that would have
        // passed the 13.9% regression: that is the whole point of the number.
        self::assertFalse(
            assertRowsNotBelowFloor($dataset)['ok'],
            'the default tolerance must still reject a 13.9% deficit'
        );
    }

    /**
     * Every writer that can put a floor beside a row fails loudly.
     *
     * The 2026-11-23 floors arrived through `merge-floors.php`, which had no check at
     * all — that is why the impossible row reached the report. The guard is only
     * worth anything if the paths that write a dataset call it, so the SOURCE is
     * asserted rather than a re-implementation of it.
     */
    public function testEveryDatasetWriterAppliesTheFloorGuard(): void
    {
        $root = dirname(__DIR__);

        foreach (['scripts/assemble-real.php', 'scripts/merge-floors.php'] as $relative) {
            $src = (string) file_get_contents("{$root}/{$relative}");
            self::assertStringContainsString(
                'assertRowsNotBelowFloor(',
                $src,
                "{$relative} can write floors next to measured rows and must reject a row below its floor"
            );
        }

        // merge-floors.php is the specific path the bad probe took, and it did not
        // even load the library that owns the check.
        $merge = (string) file_get_contents($root . '/scripts/merge-floors.php');
        self::assertStringContainsString("require_once __DIR__ . '/bench-lib.php';", $merge);
    }

    /**
     * A floor probe must WAIT for its server before timing it.
     *
     * `measureFloors()` reloads FPM immediately before it calls `measureFloorApp()`, and
     * a reload replaces workers ASYNCHRONOUSLY: the new pool's socket exists while it is
     * still connectionless, so nginx answers 502. The app path always waited for
     * readiness; the floor path did not, and on 2026-11-24 that aborted a run at its
     * very last step — after all 28 app blocks had been measured — so the whole
     * 4-hour latency dataset was discarded. abortIfBroken() is right to treat a 502 as
     * a broken body: a gateway error inside a timed block means the number describes a
     * restart, not a request.
     *
     * Asserted on the SOURCE because the failure is an ABSENT call: there is nothing to
     * observe at runtime without a live FPM master whose workers are mid-replacement.
     */
    public function testAFloorProbeWaitsForItsServerBeforeTimingIt(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__) . '/scripts/deploy-lib.php');

        $start = strpos($source, 'function measureFloorApp(');
        self::assertNotFalse($start, 'measureFloorApp() must exist');

        $end = strpos($source, '/**', $start + 1);

        $body = substr($source, $start, $end === false ? null : $end - $start);

        $wait = strpos($body, 'waitForServer($baseUrl, $server, $name);');
        $bench = strpos($body, 'runHttpBench(');

        self::assertNotFalse($wait, 'a floor must wait for readiness — the FPM reload is asynchronous');
        self::assertNotFalse($bench);
        self::assertLessThan(
            $bench,
            $wait,
            'the readiness wait must come BEFORE the timed block, or a 502 is timed and then aborts the run'
        );
    }

    /**
     * `--floors-only` must skip the app loop, not run it first.
     *
     * THIS IS WHY THE BAD PROBE WAS A `--quick` ONE. The flag was only consulted
     * after the per-app loop, so `--floors-only` still benchmarked every selected
     * app — hours of work — before reaching the floors block. An operator who wanted
     * just the probes therefore reached for `--quick` to make it finish, and that
     * capped the sample at 100x3 while the dataset it was grafted into sat at
     * 1000x10. The floors were then inconsistent with the rows, and the guard above
     * is what now refuses that graft.
     */
    public function testFloorsOnlySkipsTheAppLoop(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');

        // The guard has to be INSIDE the loop and before the first per-app echo.
        $guard = strpos($src, 'if ($floorsOnly) {');
        self::assertNotFalse($guard, 'run-http.php must consult --floors-only');

        $loop = strpos($src, 'foreach ($apps as $appKey) {');
        self::assertNotFalse($loop);

        $firstAppEcho = strpos($src, '=== App: {$appKey}');
        self::assertNotFalse($firstAppEcho);

        self::assertGreaterThan($loop, $guard, 'the guard must be inside the app loop');
        self::assertLessThan(
            $firstAppEcho,
            $guard,
            'the guard must run BEFORE any app block, or a floors-only pass still benchmarks the apps'
        );
    }
}
