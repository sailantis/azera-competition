<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/merge-modes.php — splicing the PHP-FPM half of a fresh
 * real-deployment run into the canonical dataset.
 *
 * Why this matters: run-http.php writes a FRESH dataset, so a `--servers=fpm`
 * re-measure would otherwise replace the file and silently DROP every
 * `roadrunner` block and `floor-rr` — the real-roadrunner view would render an
 * empty band with no error anywhere. scripts/merge-app.php cannot do this
 * splice (it requires identical mode shapes), so this script owns it, and the
 * guards are what this test pins:
 *
 *   - the php-fpm modes are REPLACED (proved by a marker value)
 *   - roadrunner rows and floor-rr SURVIVE byte-identical
 *   - a request set that differs on either side is REFUSED
 *   - a budget mismatch is REFUSED (the historical asymmetry)
 *   - env.timestamp is NOT rewritten by a partial re-measure
 *
 * Runs the real script in a child process against temp files.
 */
final class MergeModesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__) . '/temp/mergemodes-' . bin2hex(random_bytes(4));
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

    /**
     * One measured request row. $ms is the marker the assertions look for.
     *
     * @return array<string,mixed>
     */
    private function row(string $request, float $ms = 1.0, int $iters = 1000): array
    {
        return [
            'request'            => $request,
            'iterations_per_run' => $iters,
            'runs'               => 10,
            'trimmed_mean_ms'    => $ms,
            'min_ms'             => $ms * 0.9,
            'mean_ms'            => $ms * 1.1,
            'median_ms'          => $ms,
            'p95_ms'             => $ms * 1.3,
            'peak_mem'           => 0,
            'connect_ms'         => 0.1,
            'mem_boot_heap'      => 0,
            'mem_heap'           => 0,
            'mem_rss'            => 0,
            'mem_hwm'            => 0,
        ];
    }

    /**
     * A canonical-shaped dataset: two apps, BOTH modes, one floor per server.
     *
     * @param list<string> $requests the request set every mode carries
     * @return array<string,mixed>
     */
    private function dataset(
        string $mode = 'php-fpm',
        float $ms = 1.0,
        array $requests = ['GET /'],
        int $iters = 1000,
        string $timestamp = '2026-09-15T23:21:30+00:00'
    ): array {
        $rows = array_map(fn(string $r): array => $this->row($r, $ms, $iters), $requests);

        $app = function (string $name) use ($rows, $mode, $ms): array {
            $out = [
                'app'   => $name,
                'modes' => [
                    'roadrunner' => [
                        'iterations_per_run' => 1000,
                        'runs'               => 10,
                        'requests'           => array_map(
                            fn(array $r): array => $this->row((string) $r['request'], 0.5),
                            $rows
                        ),
                    ],
                    $mode => [
                        'iterations_per_run' => 1000,
                        'runs'               => 10,
                        'requests'           => $rows,
                    ],
                ],
                'boot_by_mode' => [
                    'roadrunner' => ['cold_ms' => 15.0365, 'warm_ms' => 15.0365],
                ],
                'boot_kind_by_mode' => [
                    'roadrunner' => 'warm_recycle',
                ],
            ];
            $out['boot_by_mode'][$mode] = ['cold_ms' => 15.8958, 'warm_ms' => 15.8958];
            $out['boot_kind_by_mode'][$mode] = 'per_request_boot';

            return $out;
        };

        return [
            'env' => [
                'php_version' => '8.3.33',
                'os'          => 'Linux',
                'sapi'        => 'cli',
                'budget'      => '1000x10',
                'timestamp'   => $timestamp,
            ],
            'apps'   => [$app('azera'), $app('spiral')],
            'floors' => [
                [
                    'app'   => 'floor-http',
                    'modes' => ['php-fpm' => ['iterations_per_run' => $iters, 'runs' => 10, 'requests' => [$this->row('GET /', 0.07, $iters)]]],
                ],
                [
                    'app'   => 'floor-rr',
                    'modes' => ['roadrunner' => ['iterations_per_run' => 1000, 'runs' => 10, 'requests' => [$this->row('GET /', 0.19)]]],
                ],
            ],
        ];
    }

    /**
     * A `--servers=fpm`-shaped fresh run: php-fpm modes only, marker values.
     *
     * @param list<string> $requests
     * @return array<string,mixed>
     */
    private function freshRun(
        float $marker = 99.4321,
        array $requests = ['GET /'],
        int $iters = 1000,
        string $timestamp = '2099-01-01T00:00:00+00:00'
    ): array {
        $apps = [];
        foreach (['azera', 'spiral'] as $name) {
            $apps[] = [
                'app'   => $name,
                'modes' => [
                    'php-fpm' => [
                        'iterations_per_run' => $iters,
                        'runs'               => 10,
                        'requests'           => array_map(
                            fn(string $r): array => $this->row($r, $marker, $iters),
                            $requests
                        ),
                    ],
                ],
                'boot_by_mode'      => ['php-fpm' => ['cold_ms' => 6.49, 'warm_ms' => 6.49]],
                'boot_kind_by_mode' => ['php-fpm' => 'per_request_boot'],
            ];
        }

        return [
            'env' => [
                'php_version' => '8.3.33',
                'os'          => 'Linux',
                'sapi'        => 'cli',
                'budget'      => '1000x10',
                'timestamp'   => $timestamp,
            ],
            'apps'   => $apps,
            'floors' => [
                [
                    'app'   => 'floor-http',
                    'modes' => ['php-fpm' => ['iterations_per_run' => $iters, 'runs' => 10, 'requests' => [$this->row('GET /', 77.5555, $iters)]]],
                ],
            ],
        ];
    }

    /**
     * @param list<string> $modes
     * @return array{0:int,1:string} exit code + output
     */
    private function runMerge(string $target, string $fresh, array $modes = ['php-fpm']): array
    {
        $script = escapeshellarg(dirname(__DIR__) . '/scripts/merge-modes.php');
        $args   = implode(' ', array_map('escapeshellarg', array_merge([$target, $fresh], $modes)));
        exec("php {$script} {$args} 2>&1", $out, $code);
        return [$code, implode("\n", $out)];
    }

    /** @return array<string,mixed> */
    private function load(string $path): array
    {
        return (array) json_decode((string) file_get_contents($path), true);
    }

    /** @param array<string,mixed> $data */
    private function app(array $data, string $name): array
    {
        foreach ($data['apps'] as $app) {
            if (($app['app'] ?? '') === $name) {
                return $app;
            }
        }
        self::fail("app {$name} not found");
    }

    public function testSplicesFpmAndPreservesRoadrunner(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fr = $this->write('fresh.json', $this->freshRun());

        [$code, $out] = $this->runMerge($ds, $fr);
        self::assertSame(0, $code, $out);

        $after = $this->load($ds);
        $azera = $this->app($after, 'azera');

        // php-fpm REPLACED — proved by the marker, not by "the script ran".
        self::assertSame(99.4321, $azera['modes']['php-fpm']['requests'][0]['trimmed_mean_ms']);

        // roadrunner UNTOUCHED — the value the canonical dataset carried.
        self::assertSame(0.5, $azera['modes']['roadrunner']['requests'][0]['trimmed_mean_ms']);

        // The RR boot block and its kind survive a partial re-measure.
        self::assertSame(15.0365, $azera['boot_by_mode']['roadrunner']['warm_ms']);
        self::assertSame('warm_recycle', $azera['boot_kind_by_mode']['roadrunner']);

        // The FPM boot block travelled with its rows.
        self::assertSame(6.49, $azera['boot_by_mode']['php-fpm']['warm_ms']);

        // floor-rr is preserved, floor-http refreshed.
        $floors = [];
        foreach ($after['floors'] as $f) {
            $floors[(string) $f['app']] = $f;
        }
        self::assertArrayHasKey('floor-rr', $floors);
        self::assertArrayHasKey('roadrunner', $floors['floor-rr']['modes']);
        self::assertSame(77.5555, $floors['floor-http']['modes']['php-fpm']['requests'][0]['trimmed_mean_ms']);
    }

    public function testDoesNotRewriteDatasetTimestampOnPartialRemeasure(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fr = $this->write('fresh.json', $this->freshRun());

        [$code, $out] = $this->runMerge($ds, $fr);
        self::assertSame(0, $code, $out);

        $after = $this->load($ds);

        // The un-refreshed half was NOT measured today, so the stamp must stay.
        self::assertSame('2026-09-15T23:21:30+00:00', $after['env']['timestamp']);
        // Provenance for the spliced half instead.
        self::assertSame('2099-01-01T00:00:00+00:00', $after['env']['mode_refresh']['php-fpm']['timestamp']);
    }

    public function testRefusesWhenRequestSetsDiffer(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        // Fresh run is missing a request the target has.
        $fr = $this->write('fresh.json', $this->freshRun(requests: ['GET /', 'GET /items']));

        [$code, $out] = $this->runMerge($ds, $fr);

        self::assertNotSame(0, $code, 'a differing request set must be refused');
        self::assertStringContainsString('request sets differ', $out);
    }

    public function testRefusesOnBudgetMismatch(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        // Fresh run was measured at a different sample size.
        $fr = $this->write('fresh.json', $this->freshRun(iters: 300));

        [$code, $out] = $this->runMerge($ds, $fr);

        self::assertNotSame(0, $code, 'a mixed budget must be refused');
        self::assertMatchesRegularExpression('/budget mismatch/i', $out);
    }

    public function testRefusesOnEnvMismatch(): void
    {
        $ds    = $this->write('ds.json', $this->dataset());
        $fresh = $this->freshRun();
        $fresh['env']['php_version'] = '8.2.0';
        $fr = $this->write('fresh.json', $fresh);

        [$code, $out] = $this->runMerge($ds, $fr);

        self::assertNotSame(0, $code, 'a different runtime must be refused');
        self::assertStringContainsString('Env mismatch', $out);
    }

    public function testRefusesWhenFreshRunLacksTheMode(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fr = $this->write('fresh.json', $this->freshRun());

        // Asking for a mode the fresh run never measured.
        [$code, $out] = $this->runMerge($ds, $fr, ['php-fpm', 'roadrunner']);

        self::assertNotSame(0, $code);
        self::assertStringContainsString('roadrunner', $out);
    }

    public function testLeavesTheModeCountIntact(): void
    {
        $ds = $this->write('ds.json', $this->dataset());
        $fr = $this->write('fresh.json', $this->freshRun());

        [$code, $out] = $this->runMerge($ds, $fr);
        self::assertSame(0, $code, $out);

        $after = $this->load($ds);
        self::assertCount(2, $after['apps']);
        foreach ($after['apps'] as $app) {
            self::assertArrayHasKey('roadrunner', $app['modes']);
            self::assertArrayHasKey('php-fpm', $app['modes']);
        }
    }
}
