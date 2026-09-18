<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/merge-boot.php — grafting a boot-only probe onto an existing
 * real-deployment dataset.
 *
 * Why this matters: a boot-only pass takes minutes, the full 1000x10 latency
 * run takes hours, and the merge is what lets us add boot numbers WITHOUT
 * re-measuring latency. If the merge silently wrote a partial dataset, or put
 * a PHP-FPM boot on the RoadRunner side, the published charts would be wrong
 * with no error at all — so the guards are what this test pins.
 *
 * Runs the real script in a child process against temp files.
 */
final class MergeBootTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__) . '/temp/mergeboot-' . bin2hex(random_bytes(4));
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

    /** @return array<string,mixed> */
    private function dataset(): array
    {
        $row = static fn(string $r): array => [
            'request'            => $r,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => 1.0,
            'min_ms'             => 0.9,
            'mean_ms'            => 1.1,
            'median_ms'          => 1.0,
            'p95_ms'             => 1.3,
            'peak_mem'           => 0,
            'connect_ms'         => 0.1,
            'mem_boot_heap'      => 0,
            'mem_heap'           => 0,
            'mem_rss'            => 0,
            'mem_hwm'            => 0,
        ];
        return [
            'env'  => ['php_version' => '8.3.33', 'os' => 'Linux', 'sapi' => 'cli', 'budget' => '1000x10'],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'roadrunner' => ['requests' => [$row('GET /')]],
                        'php-fpm'    => ['requests' => [$row('GET /')]],
                    ]
                ],
                [
                    'app'   => 'spiral',
                    'modes' => [
                        'roadrunner' => ['requests' => [$row('GET /')]],
                        'php-fpm'    => ['requests' => [$row('GET /')]],
                    ]
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function bootFile(string $app, string $server, float $median): array
    {
        return [
            'app'          => $app,
            'server'       => $server,
            'boot_only'    => true,
            'boot'         => ['cold_ms' => $median, 'warm_ms' => $median],
            'boot_samples' => [
                'count'        => 50,
                'min'          => $median * 0.9,
                'mean'         => $median * 1.05,
                'median'       => $median,
                'p95'          => $median * 1.2,
                'trimmed_mean' => $median * 1.02,
            ],
            'boot_kind' => $server === 'rr' ? 'warm_recycle' : 'per_request_boot',
        ];
    }

    /** @return array{0:int,1:string} exit code + output */
    private function runMerge(string $dataset, array $bootFiles): array
    {
        $script = escapeshellarg(dirname(__DIR__) . '/scripts/merge-boot.php');
        $args   = implode(' ', array_map('escapeshellarg', array_merge([$dataset], $bootFiles)));
        exec("php {$script} {$args} 2>&1", $out, $code);
        return [$code, implode("\n", $out)];
    }

    public function testMergesBootForBothModesWithoutTouchingLatency(): void
    {
        $ds  = $this->write('dataset.json', $this->dataset());
        $fpm = $this->write('b-fpm.json', $this->bootFile('azera', 'fpm', 7.5));
        $rr  = $this->write('b-rr.json', $this->bootFile('azera', 'rr', 0.6));

        [$code, $out] = $this->runMerge($ds, [$fpm, $rr]);
        $this->assertSame(0, $code, $out);

        $d = json_decode((string) file_get_contents($ds), true);

        // Latency rows untouched — that is the entire point of the merge.
        $row = $d['apps'][0]['modes']['php-fpm']['requests'][0];
        $this->assertEquals(1.0, $row['trimmed_mean_ms']);

        // Both modes got their own boot.
        $this->assertSame(7.5, $d['apps'][0]['boot_by_mode']['php-fpm']['cold_ms']);
        $this->assertSame(0.6, $d['apps'][0]['boot_by_mode']['roadrunner']['cold_ms']);
        $this->assertSame(50, $d['apps'][0]['boot_samples_by_mode']['php-fpm']['count']);

        // env stamped so the report stops using the legacy startup proxy.
        $this->assertSame(50, $d['env']['boot_probe']);
    }

    public function testFpmBootIsStampedOnRowsButRoadRunnerBootIsNot(): void
    {
        // A php-fpm request DOES wait for the boot (the entry script re-runs),
        // so boot_ms belongs on its rows. A RoadRunner worker boots once before
        // serving, so putting boot_ms on its rows would double-count the boot
        // in every latency number.
        $ds  = $this->write('dataset.json', $this->dataset());
        $fpm = $this->write('b-fpm.json', $this->bootFile('azera', 'fpm', 7.5));
        $rr  = $this->write('b-rr.json', $this->bootFile('azera', 'rr', 0.6));

        [$code, $out] = $this->runMerge($ds, [$fpm, $rr]);
        $this->assertSame(0, $code, $out);

        $d = json_decode((string) file_get_contents($ds), true);
        $this->assertSame(7.5, $d['apps'][0]['modes']['php-fpm']['requests'][0]['boot_ms']);
        $this->assertArrayNotHasKey('boot_ms', $d['apps'][0]['modes']['roadrunner']['requests'][0]);
    }

    public function testRefusesAFileThatIsNotABootOnlyResult(): void
    {
        // A full run-http result has no boot_only flag: merging it would graft
        // a latency dataset's (absent) boot onto the real dataset.
        $ds  = $this->write('dataset.json', $this->dataset());
        $bad = $this->write('bad.json', ['app' => 'azera', 'server' => 'fpm', 'modes' => []]);

        [$code, $out] = $this->runMerge($ds, [$bad]);
        $this->assertNotSame(0, $code, 'a non-boot-only file must be rejected');
        $this->assertStringContainsString('boot_only', $out);

        // The dataset must be untouched.
        $d = json_decode((string) file_get_contents($ds), true);
        $this->assertArrayNotHasKey('boot_probe', $d['env']);
    }

    public function testRefusesWhenNoSamplesWereCollected(): void
    {
        // A probe that collected nothing must not write a partial result: the
        // chart would then show one framework missing, or silently fall back.
        $ds    = $this->write('dataset.json', $this->dataset());
        $empty = $this->write('empty.json', ['app' => 'azera', 'server' => 'fpm', 'boot_only' => true]);

        [$code, $out] = $this->runMerge($ds, [$empty]);
        $this->assertNotSame(0, $code, 'a sample-less probe must be rejected');
        $this->assertStringContainsString('no boot samples', $out);
    }

    public function testRefusesAnAppMissingFromTheDataset(): void
    {
        $ds  = $this->write('dataset.json', $this->dataset());
        $fpm = $this->write('b.json', $this->bootFile('nonexistent', 'fpm', 1.0));

        [$code, $out] = $this->runMerge($ds, [$fpm]);
        $this->assertNotSame(0, $code);
        $this->assertStringContainsString('not in the dataset', $out);
    }
}