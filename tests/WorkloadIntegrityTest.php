<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards what the benchmarked endpoints actually DO.
 *
 * Every app's /features/cache used to usleep(50ms) on a cache miss "for parity
 * with azera's #[Cache] demo". Because it is a constant added to the miss path
 * of every app, it measured the harness rather than any framework — and it did
 * so asymmetrically, since php-fpm rebuilds the app context per request (so
 * every app always missed) while a resident RoadRunner worker hit and paid
 * nothing. On php-fpm it was 67% of azera's total-response-time metric and
 * flattened the spread between frameworks from 15.3x to 6.0x.
 *
 * A synthetic delay is invisible in a per-endpoint table (the row just looks
 * slower) and only shows up in the aggregate, so it is exactly the kind of
 * thing that gets reintroduced by a well-meaning "parity" edit. These tests
 * pin it.
 */
final class WorkloadIntegrityTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Every PHP file under apps/, relative to the repo root.
     *
     * @return list<string>
     */
    private static function appFiles(): array
    {
        $root    = self::repoRoot();
        $files   = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/apps', \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            }
        }
        sort($files);

        return $files;
    }

    public function testBenchmarkedEndpointsContainNoArtificialSleep(): void
    {
        $offenders = [];
        $allowed   = 0;

        foreach (self::appFiles() as $relative) {
            $source = (string) file_get_contents(self::repoRoot() . '/' . $relative);
            if (preg_match('/\\\\?u?sleep\s*\(/', $source) !== 1) {
                continue;
            }

            // A retry interceptor's backoff is real behaviour on a failure
            // path, not a delay injected into a healthy request: it must stay.
            if (str_contains($relative, 'RetryInterceptor')) {
                $allowed++;
                continue;
            }

            $offenders[] = $relative;
        }

        self::assertSame(
            [],
            $offenders,
            "artificial sleep found in benchmarked app code:\n  " . implode("\n  ", $offenders)
                . "\nA fixed delay is a constant on every app's cache-miss path and measures"
                . ' the harness, not the framework.'
        );
        self::assertGreaterThan(
            0,
            $allowed,
            'the retry backoff sleeps must still exist (this test would otherwise pass vacuously)'
        );
    }

    public function testCacheWorkloadIsDescribedAsTheQueryItPerforms(): void
    {
        $config = (string) file_get_contents(self::repoRoot() . '/scripts/report/BenchmarkConfig.php');

        // The row is a COUNT(*) over the 1000-row table, cached for 10s: it is
        // a DB read on a miss. Labelling it "no DB" would contradict both the
        // measurement and the view's own workload column.
        self::assertMatchesRegularExpression(
            "/'GET \\/features\\/cache'\\s*=>\\s*'[^']*COUNT\\(\\*\\)[^']*'/",
            $config,
            'the cache workload must be described as the COUNT query it performs'
        );
        self::assertDoesNotMatchRegularExpression(
            "/'GET \\/features\\/cache'\\s*=>\\s*'no DB/",
            $config,
            'the cache endpoint is no longer DB-free — it runs a real COUNT on a miss'
        );
    }

    public function testTheCacheDemoStillReportsItsOwnMissAndHitTimings(): void
    {
        // Removing the delay must not remove the demo: the endpoint still
        // measures its first (miss) and second (hit) call itself.
        foreach (self::appFiles() as $relative) {
            $source = (string) file_get_contents(self::repoRoot() . '/' . $relative);
            if (!str_contains($source, 'second_call_ms')) {
                continue;
            }
            self::assertStringContainsString(
                'first_call_ms',
                $source,
                "{$relative} reports second_call_ms but not first_call_ms"
            );
            self::assertStringContainsString(
                'same_result',
                $source,
                "{$relative} must still prove the cached value equals the fresh one"
            );
        }
    }
}
