<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/merge-app.php — the app-block splice.
 *
 * This script had NO test file, and carried three defects that no test could
 * have missed but none existed to catch:
 *
 *   1. A key-set env comparison refused every splice between the two harnesses
 *      (fixed by scripts/env-sanity.php — see EnvSanityTest).
 *   2. `run.php --export` died on a redeclaration fatal, so the companion
 *      .csv/.md were left stale after every splice.
 *   3. A source that spliced a mode WITHOUT measuring its boot left the
 *      target's previous boot in place, so the report paired fresh latency rows
 *      with a stale boot and showed it as one measurement.
 *
 * What is NOT a defect, though the docblock's phrasing invites the doubt: the
 * boot blocks DO travel with the app block, because boot_by_mode /
 * boot_samples_by_mode are keys OF the app array. That is pinned here so the
 * behaviour cannot silently change.
 */
final class MergeAppTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = dirname(__DIR__) . '/temp/mergeapp-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $f) {
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
    private function row(string $request, float $ms): array
    {
        return [
            'request'            => $request,
            'iterations_per_run' => 1000,
            'runs'               => 10,
            'trimmed_mean_ms'    => $ms,
            'min_ms'             => $ms * 0.9,
            'mean_ms'            => $ms,
            'median_ms'          => $ms,
            'p95_ms'             => $ms * 1.2,
            'peak_mem'           => 0,
            'connect_ms'         => 5.0e-5,
        ];
    }

    /**
     * @param array<string,mixed>|null $bootByMode
     * @return array<string,mixed>
     */
    private function dataset(
        float $marker = 1.1111,
        ?array $bootByMode = null,
        ?array $bootSamplesByMode = null
    ): array {
        return [
            'env' => [
                'php_version' => '8.3.33',
                'os'          => 'Linux',
                'budget'      => '1000x10',
                'timestamp'   => '2026-09-15T23:21:30+00:00',
            ],
            'apps' => [
                [
                    'app'   => 'azera',
                    'modes' => [
                        'roadrunner' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [$this->row('GET /', $marker)],
                        ],
                        'php-fpm' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [$this->row('GET /', $marker) + ['boot_ms' => 0.4]],
                        ],
                    ],
                    'boot_by_mode' => $bootByMode ?? [
                            'roadrunner' => ['cold_ms' => 0.0015, 'warm_ms' => 0.0015],
                            'php-fpm'    => ['cold_ms' => 0.4561, 'warm_ms' => 0.4561],
                        ],
                    'boot_samples_by_mode' => $bootSamplesByMode ?? [
                            'roadrunner' => ['count' => 20, 'median' => 0.0015],
                            'php-fpm'    => ['count' => 50, 'median' => 0.4561],
                        ],
                ],
                [
                    'app'   => 'laravel',
                    'modes' => [
                        'php-fpm' => [
                            'iterations_per_run' => 1000,
                            'runs'               => 10,
                            'requests'           => [$this->row('GET /', 3.03)],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return array{0:int,1:string} */
    private function runMerge(string $targetPrefix, string $sourcePrefix): array
    {
        $script = escapeshellarg(dirname(__DIR__) . '/scripts/merge-app.php');
        exec(
            'php ' . $script . ' ' . escapeshellarg($targetPrefix) . ' ' . escapeshellarg($sourcePrefix) . ' azera 2>&1',
            $out,
            $code
        );

        return [$code, implode("\n", $out)];
    }

    /** @return array<string,mixed> */
    private function app(string $path, string $name = 'azera'): array
    {
        $d = json_decode((string) file_get_contents($path), true);
        foreach ($d['apps'] as $a) {
            if (($a['app'] ?? '') === $name) {
                return $a;
            }
        }
        self::fail("no {$name} in {$path}");
    }

    public function testTheBootBlocksTravelWithTheAppBlock(): void
    {
        $target = $this->write('target.json', $this->dataset());
        $source = $this->write('source.json', $this->dataset(99.4321));

        [$code, $out] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code, "splice must succeed: {$out}");
        $boot = $this->app($target)['boot_by_mode'];
        self::assertSame(
            ['cold_ms' => 0.0015, 'warm_ms' => 0.0015],
            $boot['roadrunner'],
            'the RoadRunner recycle must come from the fresh run, not the target'
        );
        self::assertSame(
            ['cold_ms' => 0.4561, 'warm_ms' => 0.4561],
            $boot['php-fpm']
        );
    }

    public function testTheFreshRowsReplaceTheTargets(): void
    {
        $target = $this->write('target.json', $this->dataset(1.1111));
        $source = $this->write('source.json', $this->dataset(99.4321));

        [$code] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code);
        self::assertSame(
            99.4321,
            $this->app($target)['modes']['roadrunner']['requests'][0]['trimmed_mean_ms']
        );
    }

    public function testOtherAppsAreLeftAlone(): void
    {
        $target = $this->write('target.json', $this->dataset());
        $source = $this->write('source.json', $this->dataset(99.4321));

        [$code] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code);
        self::assertSame(3.03, $this->app($target, 'laravel')['modes']['php-fpm']['requests'][0]['trimmed_mean_ms']);
    }

    /**
     * A source that spliced a mode WITHOUT measuring its boot must leave no
     * boot for that mode — never the target's previous number beside fresh
     * rows.
     *
     * Note what this test does and does not prove. The guarantee comes from the
     * splice replacing the app array wholesale (boot_by_mode is a key OF it),
     * NOT from a guard in the script — an earlier revision added such a guard,
     * and it was dead code because the assignment had already done the work.
     * So this pins the INVARIANT, which would break if the splice ever changed
     * to merge per-key instead of replacing the array. It does not pin any
     * specific line of merge-app.php, and it should not: there is no such line.
     */
    public function testASplicedModeWithoutAFreshBootLeavesNoBootBehind(): void
    {
        $target = $this->write('target.json', $this->dataset());
        $source = $this->write('source.json', $this->dataset(
            99.4321,
            ['roadrunner' => ['cold_ms' => 0.0015, 'warm_ms' => 0.0015]],
            ['roadrunner' => ['count' => 20, 'median' => 0.0015]]
        ));

        [$code, $out] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code, $out);
        $boot = $this->app($target)['boot_by_mode'];
        self::assertArrayNotHasKey(
            'php-fpm',
            $boot,
            'the target\'s stale boot must not survive beside freshly measured rows'
        );
        self::assertArrayHasKey('roadrunner', $boot, 'the mode that DID measure a boot keeps it');

        // Assert on the boot-carried LINE, not the whole output: the modes line
        // legitimately names php-fpm (it was spliced), so a whole-output search
        // would fail for the wrong reason.
        $bootLine = '';
        foreach (explode("\n", $out) as $line) {
            if (str_contains($line, 'boot carried:')) {
                $bootLine = trim($line);
            }
        }
        self::assertSame(
            'boot carried: boot_by_mode[roadrunner], boot_samples_by_mode[roadrunner]',
            $bootLine,
            'the script must report exactly the boot it carried — no claim about the mode it did not'
        );
    }

    /**
     * The env guard must accept a splice between the two harnesses' datasets.
     *
     * The shape under test is the one that actually occurs: the CANONICAL file
     * keeps a now-retired `sapi` from the run.php era while a freshly measured
     * run-http.php dataset omits it. That one-sided key must be reported and
     * skipped, not treated as a contradiction.
     */
    public function testASpliceAcrossTheTwoHarnessesIsAccepted(): void
    {
        $targetData = $this->dataset();
        // The legacy file records the retired key...
        $targetData['env']['sapi']    = 'cli';
        $targetData['env']['opcache'] = true;
        $target = $this->write('target.json', $targetData);

        // ...and the fresh one deliberately records neither.
        $sourceData = $this->dataset(99.4321);
        unset($sourceData['env']['sapi'], $sourceData['env']['opcache']);
        $this->write('source.json', $sourceData);

        [$code, $out] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code, "a one-sided env key must not refuse the splice: {$out}");
        self::assertStringContainsString(
            'one side only',
            $out,
            'and the skipped key must be named, not silently ignored'
        );
    }

    public function testADifferentPhpVersionIsRefused(): void
    {
        $target = $this->write('target.json', $this->dataset());
        $source = $this->dataset(99.4321);
        $source['env']['php_version'] = '8.2.0';
        $src = $this->write('source.json', $source);

        [$code, $out] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertNotSame(0, $code, 'a different runtime must be refused');
        self::assertStringContainsString('Env mismatch', $out);
    }

    /**
     * The companion export must actually run — this is the regression pin for
     * the redeclaration fatal that silently left .csv/.md stale.
     */
    public function testTheCompanionExportSucceeds(): void
    {
        $target = $this->write('target.json', $this->dataset());
        $source = $this->write('source.json', $this->dataset(99.4321));

        [$code, $out] = $this->runMerge("{$this->dir}/target", "{$this->dir}/source");

        self::assertSame(0, $code);
        self::assertStringNotContainsString(
            'companion export failed',
            $out,
            'the .csv/.md export must not fail silently'
        );
        self::assertFileExists("{$this->dir}/target.csv");
        self::assertFileExists("{$this->dir}/target.md");
    }

    /**
     * `run.php --export` must not die on a redeclaration.
     *
     * run.php defines its OWN `azeraFrameworkRef()` (no $root param) while
     * ALSO requiring scripts/version-lib.php; `--export` then shells out to
     * run.php again. That combination used to abort with
     * "Cannot redeclare azeraFrameworkRef()", so run.php --export exited 255
     * and merge-app.php's companion export fell back to "failed" — leaving the
     * .csv/.md describing the pre-splice numbers. Nothing reported an error a
     * user would see; the merge simply printed "Merged ..." and moved on.
     *
     * Asserted on BOTH the exit code and the absence of the redeclare text: a
     * future change could reintroduce the clash while still exiting 0 if it
     * merely warned, and a warning here means the export did not do its job.
     */
    public function testRunPhpExportDoesNotDieOnARedeclaration(): void
    {
        $target = $this->write('target.json', $this->dataset());

        $out = [];
        exec(
            'php ' . escapeshellarg(dirname(__DIR__) . '/run.php')
                . ' --export=' . escapeshellarg("{$this->dir}/target") . ' 2>&1',
            $out,
            $code
        );
        $text = implode("\n", $out);

        self::assertStringNotContainsString(
            'Cannot redeclare',
            $text,
            'azeraFrameworkRef() must be declared once: run.php defines its own, so the shared lib must be guarded'
        );
        self::assertSame(0, $code, "run.php --export must succeed:\n{$text}");
        self::assertStringContainsString('Exported', $text);
    }

    /**
     * The environment lines come from the keys the dataset RECORDS.
     *
     * A real-deployment dataset stamps neither `opcache` nor `sapi` (its
     * opcache answer is per-mode in `opcache_by_mode`). The writer previously
     * read both unconditionally, so `--export` on such a dataset emitted
     * undefined-key warnings AND would have printed "OPcache (CLI): no" — the
     * exact false claim that was removed from the real-deployment pages, since
     * the FPM pool sets `opcache.enable = 1`.
     */
    public function testTheExportEnvironmentBlockOmitsUnrecordedKeys(): void
    {
        $data = $this->dataset();
        unset($data['env']['opcache'], $data['env']['sapi']);
        $data['env']['opcache_by_mode'] = ['php-fpm' => true, 'roadrunner' => true];
        $this->write('bare.json', $data);

        $out = [];
        exec(
            'php ' . escapeshellarg(dirname(__DIR__) . '/run.php')
                . ' --export=' . escapeshellarg("{$this->dir}/bare") . ' 2>&1',
            $out,
            $code
        );
        $text = implode("\n", $out);

        self::assertSame(0, $code, $text);
        self::assertStringNotContainsString('Undefined array key', $text);

        $md = (string) file_get_contents("{$this->dir}/bare.md");
        self::assertStringNotContainsString(
            'OPcache (CLI)',
            $md,
            'a dataset that does not record opcache must not be reported as "OPcache: no"'
        );
        self::assertStringNotContainsString('SAPI', $md);
        self::assertStringContainsString('- PHP: 8.3.33', $md, 'the recorded keys must still be printed');
    }
}
