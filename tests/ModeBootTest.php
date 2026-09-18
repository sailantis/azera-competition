<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The boot a mode's request rows carry must come from THAT mode.
 *
 * On a real deployment one app is measured on two servers, and run-http.php
 * writes each boot twice: under `boot_by_mode[<mode>]` (authoritative) and
 * under the legacy scalar `boot`. The server loop runs [rr, fpm], so an
 * unconditional legacy assignment leaves the scalar holding the PHP-FPM boot.
 *
 * ResultStore::modeBootMs() read only the scalar for the warm/roadrunner
 * branch, so msWithBoot() added the PHP-FPM boot to every RoadRunner row — a
 * roughly constant per-framework offset (+16.0 ms Spiral, +3.2 Laravel, +0.88
 * CodeIgniter) that reordered the field and inverted the win counts. The
 * published "every framework lands within 5% / server-bound" prose was an
 * artifact of that offset, not a fact about the servers.
 *
 * These assertions fail if the accessor ever prefers the scalar again, or if
 * the harness goes back to clobbering it.
 */
final class ModeBootTest extends TestCase
{
    /**
     * The exact shape run-http.php writes for one app: a PHP-FPM boot of
     * 0.5125 ms in both the mode map and (wrongly) the scalar — the state the
     * buggy run produced — with a genuine RoadRunner recycle of 0.0015 ms.
     *
     * @return array<string,mixed>
     */
    private function probeDataset(): array
    {
        $row = static fn(string $r): array => [
            'request'            => $r,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => 0.266821469125,
            'min_ms'             => 0.185247,
            'mean_ms'            => 0.2675333271,
            'median_ms'          => 0.251957,
            'p95_ms'             => 0.355545,
            'peak_mem'           => 0,
            'connect_ms'         => 5.0e-5,
            'mem_boot_heap'      => 990880,
            'mem_heap'           => 1394568,
            'mem_rss'            => 50827264,
            'mem_hwm'            => 50827264,
        ];

        return [
            'env' => [
                'php_version' => '8.3.33',
                'os'          => 'Linux 6.8.0-139-generic',
                'sapi'        => 'cli',
                'deployment'  => 'real',
                'boot_probe'  => 50,
                'budget'      => '1000x10',
            ],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'roadrunner' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [$row('GET /'), $row('GET /items')],
                        ],
                        'php-fpm' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [
                                $row('GET /') + ['boot_ms' => 0.5125],
                                $row('GET /items') + ['boot_ms' => 0.5125],
                            ],
                        ],
                    ],
                    // Written LAST by the [rr, fpm] loop => holds the FPM boot.
                    'boot'         => ['cold_ms' => 0.5125, 'warm_ms' => 0.5125],
                    'boot_by_mode' => [
                        'roadrunner' => ['cold_ms' => 0.0015, 'warm_ms' => 0.0015],
                        'php-fpm'    => ['cold_ms' => 0.5125, 'warm_ms' => 0.5125],
                    ],
                    'boot_kind_by_mode' => [
                        'roadrunner' => 'warm_recycle',
                        'php-fpm'    => 'per_request_boot',
                    ],
                ]
            ],
        ];
    }

    private function store(): ResultStore
    {
        $dir = dirname(__DIR__) . '/temp/modeboot-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/probe.json";
        file_put_contents($path, json_encode($this->probeDataset(), JSON_PRETTY_PRINT));

        try {
            return ResultStore::load($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    public function testRoadRunnerBootComesFromTheModeMapNotTheLegacyScalar(): void
    {
        $store = $this->store();

        self::assertTrue($store->isProbedBoot());
        self::assertSame(
            0.0015,
            $store->modeBootMs('azera', 'roadrunner'),
            'the RoadRunner recycle must come from boot_by_mode, never the legacy scalar'
        );
        self::assertSame(
            0.5125,
            $store->modeBootMs('azera', 'php-fpm'),
            'the FPM boot is the per-request boot_ms share'
        );
    }

    public function testOccupancyAddsOnlyTheModesOwnBoot(): void
    {
        $store = $this->store();

        $measured = $store->ms('azera', 'roadrunner', 'GET /');
        self::assertNotNull($measured);

        self::assertEqualsWithDelta(
            $measured + 0.0015,
            (float) $store->msWithBoot('azera', 'roadrunner', 'GET /'),
            1e-9,
            'a RoadRunner row carries the warm recycle, not the FPM boot'
        );

        // The FPM rows already contain their boot in trimmed_mean_ms, so
        // nothing may be added on top — adding would double-count.
        $fpm = $store->ms('azera', 'php-fpm', 'GET /');
        self::assertEqualsWithDelta(
            $fpm,
            (float) $store->msWithBoot('azera', 'php-fpm', 'GET /'),
            1e-9
        );
    }

    public function testTheInflatedValueWouldBeTheFpmBootDifference(): void
    {
        $store = $this->store();
        $ms    = (float) $store->ms('azera', 'roadrunner', 'GET /');

        // Regression pin: the buggy scalar read produced 0.779 (= 0.267 +
        // 0.5125) where the correct occupancy is 0.269. If these ever meet,
        // the accessor is reading the wrong field again.
        self::assertEqualsWithDelta(0.7793, $ms + 0.5125, 1e-3);
        self::assertEqualsWithDelta(0.2693, (float) $store->msWithBoot('azera', 'roadrunner', 'GET /'), 1e-3);
        self::assertGreaterThan(
            0.4,
            abs((float) $store->msWithBoot('azera', 'roadrunner', 'GET /') - ($ms + 0.5125)),
            'the wrong-field read is off by the whole FPM boot — that gap must stay visible'
        );
    }

    public function testHarnessDatasetsWithoutABootAreUnaffected(): void
    {
        $dir = dirname(__DIR__) . '/temp/modeboot-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/harness.json";
        file_put_contents($path, json_encode([
            'env'  => ['sapi' => 'cli', 'budget' => '100x3'],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'roadrunner' => [
                            'iterations_per_run' => 100,
                            'runs'               => 3,
                            'requests'           => [
                                [
                                    'request'         => 'GET /',
                                    'trimmed_mean_ms' => 0.2591,
                                    'median_ms'       => 0.262861,
                                    'p95_ms'          => 0.398613,
                                ]
                            ],
                        ]
                    ],
                ]
            ],
        ], JSON_PRETTY_PRINT));

        try {
            $store = ResultStore::load($path);
            self::assertFalse($store->isProbedBoot());
            self::assertNull($store->modeBootMs('azera', 'roadrunner'));
            // bootAddOn() is 0.0 (never null) when nothing was measured, so a
            // harness dataset renders raw request times.
            self::assertEqualsWithDelta(
                0.2591,
                (float) $store->msWithBoot('azera', 'roadrunner', 'GET /'),
                1e-9
            );
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }
}