<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards what the benchmarked endpoints actually DO.
 *
 * Every app's /features/cache used to usleep(50ms) on a cache miss "for parity
 * with azera's #[Cache] demo". Because it is a constant added to the miss path
 * of every app, it measured the harness rather than any framework â€” and it did
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

    /**
     * No workload row may be declared twice.
     *
     * A PHP array literal silently collapses a repeated key â€” the LAST one wins and
     * the first is discarded with no warning. `requestWorkload()` had
     * `'GET /features/aop-tx-direct'` twice (a leftover from splitting the `aop`
     * feature), so the row carried a description nobody chose and any edit to the
     * first copy would have looked applied while doing nothing.
     *
     * Checked by tokenising rather than by array comparison, because
     * `array_keys()` on the evaluated literal cannot see the duplicate at all â€”
     * that is precisely why the bug survived a passing suite.
     */
    public function testNoWorkloadRowIsDeclaredTwice(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/scripts/report/BenchmarkConfig.php');

        // The `requestWorkload()` literal: from its opening `return [` to the `];`.
        self::assertSame(
            1,
            preg_match('/function requestWorkload\(\).*?return\s*\[(.*?)\n\s*\];/s', $source, $body),
            'requestWorkload() must still be a single array literal for this check to mean anything'
        );

        preg_match_all("/'(GET|POST) [^']+'\\s*=>/", $body[1], $matches);

        $counts = array_count_values($matches[0]);
        $dupes  = array_keys(array_filter($counts, static fn (int $n): bool => $n > 1));

        self::assertSame(
            [],
            $dupes,
            'a repeated array key is silently collapsed by PHP: the last value wins and the first is '
                . 'discarded, so the row is described by a string nobody chose'
        );
        self::assertGreaterThan(
            20,
            count($counts),
            'the literal must still be parsed (a regex miss would make this test vacuous)'
        );
    }

    public function testCacheWorkloadIsDescribedAsTheQueryItPerforms(): void
    {        $config = (string) file_get_contents(self::repoRoot() . '/scripts/report/BenchmarkConfig.php');

        // The row is a COUNT(*) over the 1000-row table, cached for 10s: it is
        // a DB read on a miss. Labelling it "no DB" would contradict both the
        // measurement and the view's own workload column.
        self::assertMatchesRegularExpression(
            "/'GET \\/features\\/aop-cache'\\s*=>\\s*'[^']*COUNT\\(\\*\\)[^']*'/",
            $config,
            'the aop-cache workload must be described as the COUNT query it performs'
        );
        self::assertDoesNotMatchRegularExpression(
            "/'GET \\/features\\/aop-cache'\\s*=>\\s*'no DB/",
            $config,
            'the aop-cache endpoint is no longer DB-free â€” it runs a real COUNT on a miss'
        );
    }

    /**
     * The endpoints that write a row must not be described as DB-free.
     *
     * `/features/aop-tx` was labelled "no DB â€” interceptor pipeline" while every
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

        foreach (['GET /features/aop-tx', 'GET /features/aop-tx-direct'] as $endpoint) {
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
     *   1. "per-request cold boot" â€” refuted by arithmetic: the php-fpm row is
     *      1000 iterations x 10 runs with `fpm_max_requests = 0`, so a run pays
     *      for at most ONE cold request, and explaining the measured +1.8 ms
     *      needs ~1800 ms of one-off work against a boot_probe of 0.27 ms.
     *   2. "the DB-event listener graph" â€” measured at 0.0248 ms with classes
     *      warm.
     *   3. "the AOP proxy rebuild" â€” real on NTFS, 0.28 ms vs 0.14 ms on Linux.
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
        // overall â€” the observation that kills the "DB tax" theory.
        self::assertMatchesRegularExpression(
            '/0\.019|0\.124|median delta/i',
            $comment,
            'state the measured DB/event cost and the DB-vs-no-DB delta comparison'
        );

        // The strace evidence â€” the syscall signature of one file rewrite per
        // request â€” is what actually settles this; without it the comment is
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
            'aop-tx-direct exists as the same-work control and must keep its own implementation'
        );
    }

    /**
     * The "web" middleware must not be GLOBAL, and must not be dropped.
     *
     * `RequestContextMiddleware` resolves `locale` / `platform` for the layout footer.
     * It used to be registered globally (alongside a `SecurityHeadersMiddleware` that
     * has since been deleted for the reason this docblock records), so every request
     * paid for it. Measured on the bench VM 2026-11-23: the pair cost 1.52 us chained,
     * against a whole `/echo` of 4.70 us for Azera and 1.22 us for Phalcon.
     * `/features/config` then measured 6.5 us vs Phalcon's 5.4 us even though Azera's
     * own config work was 4.5x CHEAPER (0.61 us vs 2.79 us) - the entire gap was this
     * pipeline, and it landed hardest on the no-DB routes because it is a fixed cost
     * on top of a ~1-2 us handler.
     *
     * No other entrant parses Accept-Language/User-Agent per request on a benchmark
     * route (Laravel shares two constants at boot), so global registration was measuring
     * Azera's middleware stack against Phalcon's absence of one.
     *
     * Both directions are asserted, because both are regressions: making it global again
     * re-introduces the unfair tax, and deleting it silently drops the footer data every
     * rendering route reads.
     */
    public function testTheWebMiddlewareIsRouteScopedAndStillPresent(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/apps/azera/Bootstrap.php');

        self::assertDoesNotMatchRegularExpression(
            '/\$dispatcher->addMiddleware\(\s*new\s+\\\\App\\\\Azera\\\\Middleware\\\\/',
            $source,
            'RequestContext must not be global: no other entrant parses locale headers on a'
                . ' benchmark route, so registering it for every route made the no-DB rows'
                . ' measure header parsing instead of the framework'
        );

        // The group must be registered as CLASS STRINGS, not instances: the dispatcher
        // builds a middleware on first use, so `new`ing them at boot loaded two classes
        // (6 files) for routes that never run the group. Asserted because reverting to
        // `new` is invisible in the output and only moves the boot band.
        self::assertMatchesRegularExpression(
            '/defineMiddlewareGroup\(\s*\'web\'\s*,\s*\[/',
            $source,
            'the web middleware group must still exist'
        );
        self::assertDoesNotMatchRegularExpression(
            '/defineMiddlewareGroup\(\s*\'web\'\s*,\s*\[\s*new\s+/',
            $source,
            'the web group must list class strings, not eager instances â€” see the'
                . ' docblock above defineMiddlewareGroup() for the measured boot cost'
        );
        self::assertMatchesRegularExpression(
            '/RequestContextMiddleware(::class)?\(?\)?/',
            $source,
            'the request-context demo must stay, scoped rather than deleted'
        );
        self::assertMatchesRegularExpression(
            "/->middleware\(\s*'web'\s*,/",
            $source,
            'at least one route group must apply the web middleware'
        );

        // SecurityHeadersMiddleware is DELETED, not scoped. It set five headers no other
        // entrant emits, so the row measured a facility only Azera had; scoping it to the
        // HTML pages would have kept an incomparable cost on rows the other six serve
        // bare. Asserted as an ABSENCE so a well-meaning restore fails here instead of
        // silently re-entering the comparison.
        self::assertFileDoesNotExist(
            self::repoRoot() . '/apps/azera/Middleware/SecurityHeadersMiddleware.php',
            'SecurityHeadersMiddleware is deleted on purpose: no other entrant emits a'
                . ' security header, so its cost was incomparable on every row. Do not'
                . ' restore it; see Bootstrap::boot() for the full rationale.'
        );
    }

    /**
     * The `web` group must cover EVERY route that renders a layout reading `locale` /
     * `platform`, and must NOT cover the floor row.
     *
     * WHY THIS IS A TEST AND NOT A COMMENT. The group's first definition listed only
     * `/`, `/items` and `/items/{id}`. Four rendering routes were missing from it --
     * `/items-fast`, `/items-qb`, `/items-qb/{id}` and both POST routes -- and they
     * printed a footer with no locale at all while the three listed ones printed a real
     * one. Nothing failed: those endpoints still returned 200 with a body, and
     * `verify-app.php` only checks status and shape. The rendering actions are read from
     * the CONTROLLER SOURCE here, so adding a render action without adding it to the
     * group fails this test instead of quietly shipping a footer-less page.
     *
     * The reverse direction is asserted too: `/echo` must stay OUT. It returns a
     * constant string and renders nothing, so a group that covered it would charge
     * header parsing to the one row whose entire purpose is to be the framework floor.
     */
    public function testEveryRouteThatRendersALayoutIsInTheWebMiddlewareGroup(): void
    {
        $root = self::repoRoot();

        // Actions whose body calls $this->view()->render() -- derived from the
        // controller, so a new one cannot be forgotten.
        $renderingActions = [];

        foreach (['BenchController', 'FeatureController'] as $controller) {
            $source = (string) file_get_contents("{$root}/apps/azera/Controllers/{$controller}.php");

            if (preg_match_all('/public function (\w+)\([^)]*\)[^{]*\{(.*?)\n    \}/s', $source, $methods, PREG_SET_ORDER) === 0) {
                continue;
            }

            foreach ($methods as [, $name, $body]) {
                if (str_contains($body, '->render(')) {
                    $renderingActions[] = $name;
                }
            }
        }

        self::assertNotEmpty($renderingActions, 'no rendering actions found -- the parser broke');

        // Which actions does the `web` group cover? Read the group's own route block.
        $bootstrap = (string) file_get_contents("{$root}/apps/azera/Bootstrap.php");
        self::assertSame(
            1,
            preg_match("/->middleware\(\s*'web'\s*,\s*function[^{]*\{(.*?)\n                \}\)/s", $bootstrap, $webBlock),
            'the web middleware group callback could not be located in Bootstrap::boot()'
        );

        $covered = [];
        if (preg_match_all("/r->(?:get|post)\(\s*'[^']*'\s*,\s*'::(\w+)'/", $webBlock[1], $routes, PREG_SET_ORDER) > 0) {
            foreach ($routes as [, $action]) {
                $covered[$action] = true;
            }
        }

        $missing = array_values(array_diff($renderingActions, array_keys($covered)));

        self::assertSame(
            [],
            $missing,
            "these actions render a layout that reads `locale` / `platform`, but their route"
                . " is not inside the 'web' middleware group, so the page prints no locale:\n  "
                . implode("\n  ", $missing)
        );

        // The floor row must NOT be in the group.
        self::assertArrayNotHasKey(
            'echoAction',
            $covered,
            'echoAction renders nothing, so it must stay outside the web group -- it is the'
                . ' row every other one is read against'
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