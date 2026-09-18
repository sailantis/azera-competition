<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The boot a warm/roadrunner row carries is a property of the POOL, not of
 * the framework.
 *
 * ResultStore::bootAddOn() used to add the worker's whole recycle to every
 * row, unconditionally. But the benchmark stamps `max_jobs: 0` (RoadRunner:
 * "no limit" — the worker is never recycled), so the measured worker booted
 * ONCE and served the entire block. The report was therefore charging a boot
 * to 210,000 requests that never performed one: a constant per-framework
 * offset (+15.0 ms Spiral, +2.56 Laravel, +0.59 Symfony) that pushed Spiral
 * from mid-pack to last place on every endpoint.
 *
 * These assertions pin the amortisation:
 *   max_jobs 0    -> no boot in a row (the measured deployment)
 *   max_jobs N    -> boot/N per row
 *   un-stamped    -> the historical per-request reading, so old renders and
 *                    old datasets do not silently move
 */
final class BootAmortisationTest extends TestCase
{
    /** A row with the given trimmed mean. */
    private static function row(string $r, float $ms): array
    {
        return [
            'request'            => $r,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => $ms,
            'min_ms'             => $ms,
            'mean_ms'            => $ms,
            'median_ms'          => $ms,
            'p95_ms'             => $ms,
        ];
    }

    /**
     * @param array<string,mixed>|null $env extra env keys (rr_max_jobs)
     */
    private function store(?float $spiralWarm = 15.0365, ?array $env = null): ResultStore
    {
        $dir = dirname(__DIR__) . '/temp/bootamort-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/probe.json";

        $bootByMode = [
            'php-fpm'    => ['cold_ms' => 15.8958, 'warm_ms' => 15.8958],
            'roadrunner' => ['cold_ms' => $spiralWarm, 'warm_ms' => $spiralWarm],
        ];

        $app = [
            'app'   => 'spiral',
            'modes' => [
                'roadrunner' => [
                    'iterations_per_run' => 1000,
                    'runs'               => 10,
                    'requests'           => [self::row('GET /', 0.6633540978749998)],
                ],
                'php-fpm' => [
                    'iterations_per_run' => 1000,
                    'runs'               => 10,
                    // FPM rows carry their boot inside the measurement.
                    'requests' => [self::row('GET /', 19.242) + ['boot_ms' => 15.8958]],
                ],
            ],
            'boot_by_mode' => $bootByMode,
        ];
        if ($spiralWarm === null) {
            unset($app['boot_by_mode']['roadrunner']);
        }

        file_put_contents($path, json_encode([
            'env' => array_merge([
                'php_version'      => '8.3.33',
                'deployment'       => 'real',
                'boot_probe'       => 50,
                'budget'           => '1000x10',
                'fpm_max_requests' => 0,
            ], $env ?? []),
            'apps' => [$app],
        ], JSON_PRETTY_PRINT));

        try {
            return ResultStore::load($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    public function testNeverRecycledPoolPutsNoBootInARow(): void
    {
        $store = $this->store(15.0365, ['rr_max_jobs' => 0]);

        self::assertSame(0, $store->rrMaxJobs());
        self::assertSame('never', $store->rrRecycleModel());

        // The measured request, unchanged: the pool never recycled the worker.
        self::assertEqualsWithDelta(
            0.6633540978749998,
            (float) $store->msWithBoot('spiral', 'roadrunner', 'GET /'),
            1e-9,
            'max_jobs=0 means the worker is never recycled, so no request carries a boot'
        );
    }

    /**
     * The exact regression this change fixes: charging the whole recycle to a
     * row of a pool that never performed one moves Spiral from 0.663 to 15.70.
     */
    public function testChargingTheWholeRecycleWouldInflateTheRowTwentyfold(): void
    {
        $store    = $this->store(15.0365, ['rr_max_jobs' => 0]);
        $measured = (float) $store->ms('spiral', 'roadrunner', 'GET /');

        self::assertEqualsWithDelta(15.70, $measured + 15.0365, 0.01);
        self::assertEqualsWithDelta(0.663, (float) $store->msWithBoot('spiral', 'roadrunner', 'GET /'), 0.001);
        self::assertGreaterThan(
            15.0,
            abs((float) $store->msWithBoot('spiral', 'roadrunner', 'GET /') - ($measured + 15.0365)),
            'the per-request reading is off by the whole boot — that gap must stay visible'
        );
    }

    public function testPoolThatRecyclesEveryNJobsCarriesBootOverN(): void
    {
        $store = $this->store(15.0365, ['rr_max_jobs' => 100]);

        self::assertSame(100, $store->rrMaxJobs());
        self::assertSame('every', $store->rrRecycleModel());

        self::assertEqualsWithDelta(
            0.6633540978749998 + 15.0365 / 100,
            (float) $store->msWithBoot('spiral', 'roadrunner', 'GET /'),
            1e-9
        );
    }

    public function testUnstampedDatasetKeepsTheHistoricalPerRequestReading(): void
    {
        $store = $this->store(15.0365, []);

        self::assertNull($store->rrMaxJobs());
        self::assertSame('unknown', $store->rrRecycleModel());
        self::assertEqualsWithDelta(
            0.6633540978749998 + 15.0365,
            (float) $store->msWithBoot('spiral', 'roadrunner', 'GET /'),
            1e-9,
            'an un-stamped dataset must render exactly as it did before the stamp existed'
        );
    }

    /**
     * The FPM branch is untouched: those rows already contain their boot, so
     * the stamp must not make the report add anything there.
     */
    public function testFpmRowsStillCarryNoAddOn(): void
    {
        $store = $this->store(15.0365, ['rr_max_jobs' => 0]);

        self::assertEqualsWithDelta(
            19.242,
            (float) $store->msWithBoot('spiral', 'php-fpm', 'GET /'),
            1e-9,
            'php-fpm rows time their boot inside the request; adding would double-count'
        );
    }
}
