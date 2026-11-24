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
}
