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
        $root     = self::repoRoot();
        $files    = [];
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

    /**
     * The endpoints that write a row must not be described as DB-free.
     *
     * `/features/aop` was labelled "no DB — interceptor pipeline" while every
     * entrant that implements it upserts a sentinel row inside a transaction.
     * The label is what a reader consults to interpret the bar, so calling a
     * write "no DB" reads as "this number is about interception", when the row
     * is mostly a SQLite commit. `/features/db-events` had the same defect in the
     * other direction: it was described as one INSERT, but it upserts AND runs a
     * COUNT(*).
     *
     * Cross-checks against the code rather than against a hand-kept list: if any
     * FeatureController in the suite still writes a row, the endpoints that
     * exercise it cannot claim otherwise.
     */
    public function testWriteEndpointsAreNotDescribedAsDbFree(): void
    {
        $config = (string) file_get_contents(self::repoRoot() . '/scripts/report/BenchmarkConfig.php');

        foreach (['GET /features/aop', 'GET /features/aop-direct'] as $endpoint) {
            $quoted = preg_quote($endpoint, '/');

            self::assertDoesNotMatchRegularExpression(
                "/'" . $quoted . "'\\s*=>\\s*'no DB/",
                $config,
                "{$endpoint} upserts a sentinel row inside a transaction and must not be labelled DB-free"
            );
            self::assertMatchesRegularExpression(
                "/'" . $quoted . "'\\s*=>\\s*'[^']*(upserted|INSERT)/i",
                $config,
                "{$endpoint} must state the write it performs"
            );
        }

        // The db-events demo collects events AND runs a COUNT on top of its write.
        self::assertMatchesRegularExpression(
            "/'GET \\/features\\/db-events'\\s*=>\\s*'[^']*COUNT\\(\\*\\)/",
            $config,
            'db-events upserts a row and then runs a COUNT(*) to raise QueryExecuted events'
        );
        self::assertDoesNotMatchRegularExpression(
            "/'GET \\/features\\/db-events'\\s*=>\\s*'no DB/",
            $config,
            'db-events is a write plus a read, not DB-free'
        );
    }

    /**
     * The AOP commentary must not resurrect a retracted explanation.
     *
     * Three causes have been written into this comment and then refuted:
     *
     *   1. "per-request cold boot" — refuted by arithmetic: the php-fpm row is
     *      1000 iterations x 10 runs with `fpm_max_requests = 0`, so a run pays
     *      for at most ONE cold request, and explaining the measured +1.8 ms
     *      needs ~1800 ms of one-off work against a boot_probe of 0.27 ms.
     *   2. "the DB-event listener graph" — measured at 0.0248 ms with classes
     *      warm.
     *   3. "the AOP proxy rebuild" — real on NTFS, 0.28 ms vs 0.14 ms on Linux.
     *
     * Each was plausible, survived review, and sent the next reader to optimise
     * the wrong layer. The comment must keep saying the attribution is OPEN and
     * name the measurement that would settle it, rather than proposing a fourth
     * guess.
     */
    public function testTheAopCommentaryKeepsTheAttributionOpen(): void
    {
        $config = (string) file_get_contents(self::repoRoot() . '/scripts/report/BenchmarkConfig.php');

        // Isolate the aopStyle() docblock; the rest of the file legitimately
        // discusses cold boot and proxies for other reasons.
        $start = strpos($config, 'public static function aopStyle()');
        self::assertNotFalse($start, 'aopStyle() moved; this test needs updating');

        $comment = substr($config, 0, $start);
        $comment = substr($comment, (int) strrpos($comment, '/**'));

        // The refutation of cold boot rests on the iteration count and worker
        // recycling; without them the claim is just an assertion.
        self::assertMatchesRegularExpression(
            '/1000 iterations|fpm_max_requests/i',
            $comment,
            'cite the iteration count and fpm_max_requests when refuting cold boot'
        );
        self::assertMatchesRegularExpression('/refuted/i', $comment, 'name the retracted explanations');

        // The DB/event figure, and the fact that Azera is faster on DB endpoints
        // overall — the observation that kills the "DB tax" theory.
        self::assertMatchesRegularExpression(
            '/0\.019|0\.124|median delta/i',
            $comment,
            'state the measured DB/event cost and the DB-vs-no-DB delta comparison'
        );

        // The strace evidence — the syscall signature of one file rewrite per
        // request — is what actually settles this; without it the comment is
        // just a fifth opinion.
        self::assertMatchesRegularExpression(
            '/strace/i',
            $comment,
            'cite the strace measurement that identified the per-request proxy rewrite'
        );
        self::assertMatchesRegularExpression(
            '/rename|ftruncate/i',
            $comment,
            'name the syscalls that proved a file was rewritten per request'
        );

        // And the A/B, because "it is fixed" without a before/after is a claim.
        self::assertMatchesRegularExpression(
            '/2\.412|0\.696|-1\.7/',
            $comment,
            'state the measured before/after on the affected route'
        );
    }

    /**
     * The write labels above are only meaningful if the endpoints really write.
     *
     * Guards against the label test passing vacuously after a refactor that
     * turned the AOP demo into an in-memory one.
     */
    public function testTheAopEndpointsStillPerformAWrite(): void
    {
        $azera = (string) file_get_contents(
            self::repoRoot() . '/apps/azera/Services/FeatureService.php'
        );

        self::assertMatchesRegularExpression(
            '/INSERT\s+INTO\s+items.*ON\s+CONFLICT/is',
            $azera,
            'the AOP demo is defined by its upsert; without it the row measures nothing'
        );
        self::assertStringContainsString(
            'createItemDirect',
            $azera,
            'aop-direct exists as the same-work control and must keep its own implementation'
        );
    }

    /**
     * The two "web" middlewares must not be GLOBAL, and must not be dropped.
     *
     * `SecurityHeadersMiddleware` and `RequestContextMiddleware` used to be
     * registered with `addMiddleware()`, so every request paid for both. Measured
     * on the bench VM 2026-11-23: they cost 1.52 us chained, against a whole
     * `/echo` of 4.70 us for Azera and 1.22 us for Phalcon. `/features/config`
     * then measured 6.5 us vs Phalcon's 5.4 us even though Azera's own config
     * work was 4.5x CHEAPER (0.61 us vs 2.79 us) — the entire gap was this
     * pipeline, and it landed hardest on the no-DB routes because it is a fixed
     * cost on top of a ~1-2 us handler.
     *
     * No other entrant does this work: none sets a security header, and none
     * parses Accept-Language/User-Agent per request on a benchmark route
     * (Laravel stamps a constant `locale` at boot). So global registration was
     * measuring Azera's middleware stack against Phalcon's absence of one.
     *
     * Both directions are asserted, because both are regressions: making them
     * global again re-introduces the unfair tax, and deleting them silently
     * drops a demo the app advertises on its /features page.
     */
    public function testTheWebMiddlewaresAreRouteScopedAndStillPresent(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/apps/azera/Bootstrap.php');

        self::assertDoesNotMatchRegularExpression(
            '/\$dispatcher->addMiddleware\(\s*new\s+\\\\App\\\\Azera\\\\Middleware\\\\/',
            $source,
            'SecurityHeaders/RequestContext must not be global: no other entrant runs an'
                . ' equivalent per request, so registering them for every route made the'
                . ' no-DB rows measure middleware instead of the framework'
        );

        self::assertMatchesRegularExpression(
            "/defineMiddlewareGroup\(\s*'web'\s*,/",
            $source,
            'the web middleware group must still exist'
        );
        self::assertMatchesRegularExpression(
            '/SecurityHeadersMiddleware\(\)/',
            $source,
            'the security-headers demo must stay, scoped rather than deleted'
        );
        self::assertMatchesRegularExpression(
            '/RequestContextMiddleware\(\)/',
            $source,
            'the request-context demo must stay, scoped rather than deleted'
        );
        self::assertMatchesRegularExpression(
            "/->middleware\(\s*'web'\s*,/",
            $source,
            'at least one route group must apply the web middleware'
        );
    }

    public function testTheCacheDemoStillReportsItsOwnMissAndHitTimings(): void    {
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
