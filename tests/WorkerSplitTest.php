<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The resident-worker teardown split (worker_handle_ms / worker_cleanup_ms)
 * answers "what does a resident worker do BETWEEN requests" — the little
 * per-request resets and initialisations that a warm/roadrunner row otherwise
 * hides inside one client-side round-trip.
 *
 * It is deliberately NOT stored under handle_ms/cleanup_ms. Those keys have a
 * sum-to-headline contract (`boot + handle + cleanup` decomposes the cell)
 * which holds for fork-mode rows because both terms are timed in the request's
 * own clock. The probe numbers are framework-side and the headline is the
 * client's HTTP round-trip, so publishing them as those terms would print a
 * sub-line whose residual gets parked on the largest term — i.e. a false
 * decomposition.
 */
final class WorkerSplitTest extends TestCase
{
    /**
     * @param array<string,mixed> $extra row-level keys to add
     */
    private function store(array $extra, string $mode = 'roadrunner'): ResultStore
    {
        $dir = dirname(__DIR__) . '/temp/workrsplit-' . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $path = "{$dir}/probe.json";

        $row = array_merge([
            'request'            => 'GET /',
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => 0.663354097875,
            'min_ms'             => 0.467781,
            'mean_ms'            => 0.6636475234,
            'median_ms'          => 0.641126,
            'p95_ms'             => 0.840944,
        ], $extra);

        file_put_contents($path, json_encode([
            'env'  => ['php_version' => '8.3.33', 'deployment' => 'real', 'rr_max_jobs' => 0],
            'apps' => [
                [
                    'app'   => 'spiral',
                    'modes' => [
                        $mode => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [$row],
                        ],
                    ],
                ],
            ],
        ], JSON_PRETTY_PRINT));

        try {
            return ResultStore::load($path);
        } finally {
            @unlink($path);
            @rmdir($dir);
        }
    }

    public function testWorkerSplitIsReadableWhenPresent(): void
    {
        $store = $this->store([
            'worker_handle_ms'  => 0.392,
            'worker_cleanup_ms' => 0.015,
            'split_samples'     => 30,
        ]);

        self::assertTrue($store->hasWorkerSplit());
        self::assertEqualsWithDelta(0.392, (float) $store->workerHandleMs('spiral', 'roadrunner', 'GET /'), 1e-9);
        self::assertEqualsWithDelta(0.015, (float) $store->workerCleanupMs('spiral', 'roadrunner', 'GET /'), 1e-9);
    }

    /**
     * The contract that matters: the worker split must NOT register as the
     * sum-to-headline cleanup split, or the report would print
     * `boot + handle + cleanup` terms that do not add up to the cell.
     */
    public function testWorkerSplitDoesNotClaimTheSumToHeadlineSplit(): void
    {
        $store = $this->store([
            'worker_handle_ms'  => 0.392,
            'worker_cleanup_ms' => 0.015,
        ]);

        self::assertTrue($store->hasWorkerSplit());
        self::assertFalse(
            $store->hasCleanupSplit(),
            'worker_* keys must not satisfy the boot+handle+cleanup contract'
        );
    }

    public function testDatasetWithoutTheSplitIsHandled(): void
    {
        $store = $this->store([]);

        self::assertFalse($store->hasWorkerSplit());
        self::assertNull($store->workerHandleMs('spiral', 'roadrunner', 'GET /'));
        self::assertNull($store->workerCleanupMs('spiral', 'roadrunner', 'GET /'));
    }

    /**
     * The split must not disturb the headline. With max_jobs=0 the row is the
     * measured request and the worker terms are context, not addends.
     */
    public function testWorkerSplitDoesNotChangeTheHeadline(): void
    {
        $with = $this->store([
            'worker_handle_ms'  => 0.392,
            'worker_cleanup_ms' => 0.015,
        ]);
        $without = $this->store([]);

        self::assertSame(
            $without->msWithBoot('spiral', 'roadrunner', 'GET /'),
            $with->msWithBoot('spiral', 'roadrunner', 'GET /')
        );
    }
}
