<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the harness INVOCATION surface: which output path a run writes to,
 * whether its numbers can reach the published report, and whether the source
 * transfer can even deliver a binary archive on Windows.
 *
 * These are source-level assertions. They cannot prove a benchmark ran — they
 * pin the decisions that are expensive to get wrong: a smoke run that
 * overwrites the canonical dataset, or a smoke render that publishes 100x3
 * numbers over measured ones, is silent rather than loud.
 */
final class HarnessInvocationTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function read(string $relative): string
    {
        $path = self::repoRoot() . '/' . $relative;
        self::assertFileExists($path, "{$relative} must exist");

        return (string) file_get_contents($path);
    }

    /** Extract one function/block body by its opening line, brace-matched. */
    private static function block(string $source, string $openingLine): string
    {
        $start = strpos($source, $openingLine);
        self::assertNotFalse($start, "expected to find: {$openingLine}");

        $depth = 0;
        $len   = strlen($source);
        for ($i = $start; $i < $len; $i++) {
            $c = $source[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start + 1);
                }
            }
        }

        self::fail("unbalanced braces after: {$openingLine}");
    }

    public function testQuickRunsWriteToASmokePathNotTheCanonicalDataset(): void
    {
        $ps1 = self::read('scripts/run-remote.ps1');

        // Both -Run and -Run -Quick previously targeted results/real-deployments,
        // so a 100x3 sanity check silently replaced the measured dataset.
        self::assertStringContainsString(
            "if (\$Quick) { 'results/smoke-'",
            $ps1,
            'a quick run must write to a smoke-named output, not the canonical dataset'
        );
        self::assertStringContainsString(
            "else { 'results/real-deployments' }",
            $ps1,
            'the canonical output must be the NON-quick branch'
        );
    }

    public function testQuickFetchRendersWithoutPublishing(): void
    {
        $ps1  = self::read('scripts/run-remote.ps1');
        $body = self::block($ps1, 'function Get-Results {');

        $quickBranch = strpos($body, 'if ($Quick) {');
        $publish     = strpos($body, '--publish=framework');
        self::assertNotFalse($quickBranch, 'Get-Results must branch on -Quick');
        self::assertNotFalse($publish, 'the full run must publish');

        // The default report out dir is docs/benchmarks: publishing a smoke run
        // would overwrite the measured views (and the framework repo's copies)
        // with 100x3 numbers.
        self::assertGreaterThan(
            $quickBranch,
            $publish,
            '--publish must live in the non-quick branch only'
        );
        self::assertStringContainsString(
            '--out="results/$OutName-report"',
            $body,
            'a smoke render must go to a scratch dir, never docs/benchmarks'
        );
    }

    public function testFullRunStatesItsBudgetInsteadOfInheritingTheDefaults(): void
    {
        $ps1 = self::read('scripts/run-remote.ps1');

        // The defaults live in run-http.php; a silent default change must not
        // silently change what a published run measured.
        self::assertStringContainsString(
            "'--iterations-per-run=1000 --runs=10'",
            $ps1,
            'the canonical run must state its budget explicitly'
        );
    }

    public function testDatasetOverrideCanBeatAViewsPinnedDataset(): void
    {
        $src = self::read('scripts/report.php');

        // Without this, `--dataset=<path>` is silently ignored by every view
        // that names its own dataset, so rendering a smoke run re-prints the
        // PUBLISHED numbers while reporting success.
        self::assertStringContainsString(
            "'force-dataset'",
            $src,
            'report.php must accept --force-dataset'
        );
        self::assertStringContainsString(
            '($forceDataset || ($view[\'dataset\'] ?? null) === null)',
            $src,
            'the view-pin must yield to --force-dataset'
        );
    }

    public function testSourceSyncDoesNotPipeABinaryArchiveThroughTheShell(): void
    {
        $ps1  = self::read('scripts/run-remote.ps1');
        $body = self::block($ps1, 'function Sync-Source {');

        // PowerShell pipes a native command's stdout as TEXT, which corrupts a
        // tar stream ("does not look like a tar archive" on the remote end).
        self::assertStringNotContainsString(
            '| ssh',
            $body,
            'the tar stream must not be piped through PowerShell into ssh'
        );
        self::assertStringContainsString(
            'scp',
            $body,
            'the archive must be transferred with scp instead'
        );
        self::assertMatchesRegularExpression(
            '/tar\s+-C\s+\$Sailantis\s+-cf\s+\$tarFile/',
            $body,
            'tar must be written to a file, not to stdout'
        );
    }
}
