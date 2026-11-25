<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every app must REGISTER every endpoint the harness measures.
 *
 * What this pins. `EndpointParityTest` guards the two routes that isolate a
 * single cost (`/echo`, `/items-fast`, `/api/routing/1`). It says nothing about
 * the 11 feature demos, because for six of the seven apps those were never in
 * question — they were written with the apps. Phalcon 6 was added later and
 * shipped with 14 of the 25 endpoints, and nothing noticed:
 *
 *   - every local check passed, because the in-process parity run only asked
 *     the app for the routes it had;
 *   - the real run aborted at the first missing one
 *     (`[ABORT] fpm/phalcon GET /features/aop: HTTP status 404`);
 *   - and the volucer arm of the same block reported "0 failed", because the
 *     404 body went out as 200 OK (see ResponseGuardTest).
 *
 * A route that is in the canonical request order but absent from an app's route
 * table is not a slow row — it is a row that cannot exist. The report silently
 * omits a framework from that endpoint's comparison, which looks exactly like a
 * framework that did not compete.
 *
 * Checked against each app's own route declarations as SOURCE, for the reason
 * EndpointParityTest documents: the route table is where the mistake is made, a
 * controller action with no route is dead code, and a route with no action is a
 * 500 only a live run would reveal. Registration is the half a unit test can
 * hold.
 */
final class FeatureRouteParityTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Every file an app declares routes in.
     *
     * @return array<string,list<string>>
     */
    private static function routeFiles(): array
    {
        $root = self::repoRoot();

        return [
            'azera'       => [$root . '/apps/azera/Bootstrap.php'],
            'cakephp'     => [$root . '/apps/cakephp/src/Application.php'],
            'codeigniter' => [$root . '/apps/codeigniter/Config/Routes.php'],
            'laravel'     => [$root . '/apps/laravel/routes/web.php'],
            'spiral'      => [$root . '/apps/spiral/src/Bootloader/AppRoutesBootloader.php'],
            'symfony'     => [$root . '/apps/symfony/config/routes.yaml'],
            'phalcon'     => [$root . '/apps/phalcon/src/Bootstrap.php'],
        ];
    }

    public static function appProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::routeFiles()) as $app) {
            $cases[$app] = [$app];
        }

        return $cases;
    }

    /**
     * The 11 feature endpoints, spelled the way the harness requests them.
     *
     * Taken literally rather than from BenchmarkConfig::requestOrder(), so a
     * future edit that renames a path has to be reflected here deliberately
     * instead of silently following the implementation.
     *
     * `/features` (the overview page) is NOT here: the harness measures only the
     * demos, and every app's templates link to the overview for humans. It is
     * asserted separately below so the two requirements cannot be confused.
     *
     * @return list<string>
     */
    private static function featurePaths(): array
    {
        return [
            '/features/aop',
            '/features/cache',
            '/features/log',
            '/features/retry',
            '/features/pipeline',
            '/features/db-events',
            '/features/events',
            '/features/validation',
            '/features/config',
            '/features/request-scoped',
            '/features/rate-limit',
        ];
    }

    /**
     * Comment-stripped source, so a path named only in prose does not count.
     *
     * Same reasoning as EndpointParityTest::declarations(): these files DOCUMENT
     * the routes, and a naive substring search finds the path inside the comment
     * explaining it. That is not hypothetical here — the `/features` section in
     * Phalcon's Bootstrap carries a comment naming `/features/aop`, which would
     * satisfy this test for that one route even with the declaration removed.
     *
     * NOTE on the block-comment regex: it is non-greedy but NOT nesting-aware,
     * so a slash-star inside a comment opens a second one and the match then
     * ends at the NEXT terminator — swallowing everything between. An earlier
     * edit wrote a `/features/` + `*` glob in a route-table comment and this
     * transform ate 7.6 KB of declarations, which made EndpointParityTest
     * report a route as missing that was right there. Comments in the route
     * files therefore say `/features/`.
     */
    private static function declarations(string $source): string
    {
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        $lines = preg_split('~\R~', $source) ?: [];
        $kept  = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * @dataProvider appProvider
     */
    public function testEveryAppRegistersEveryMeasuredFeatureEndpoint(string $app): void
    {
        $source = '';
        foreach (self::routeFiles()[$app] as $file) {
            if (is_file($file)) {
                $source .= (string) file_get_contents($file) . "\n";
            }
        }

        $declarations = self::declarations($source);
        self::assertNotSame('', $declarations, "{$app}'s route file could not be read");

        require_once self::repoRoot() . '/scripts/report/BenchmarkConfig.php';
        $config = \AzeraCompetition\Report\BenchmarkConfig::class;

        $missing   = [];
        $excluded  = [];
        foreach (self::featurePaths() as $path) {
            // A path an app is EXCLUDED from must not be required of it, and must not
            // be silently absent either: it is collected so the assertion below can
            // state which exclusions this app leans on. The exclusion is the single
            // source of truth (BenchmarkConfig::excludedRequests()) that the harness
            // also honours, so a route removed here cannot surprise a run with a 404.
            if (!$config::racesOn($app, 'GET ' . $path)) {
                $excluded[] = $path;
                continue;
            }
            // Laravel groups the feature routes under a prefix, and Symfony
            // declares them in YAML — matching the bare path text is what all
            // seven spellings have in common. A trailing quote or a word
            // boundary keeps `/features/log` from matching `/features/log-x`.
            if (preg_match('~' . preg_quote($path, '~') . '(?![\w/-])~', $declarations) !== 1) {
                $missing[] = $path;
            }
        }

        self::assertSame(
            [],
            $missing,
            "{$app} does not register these measured endpoints: " . implode(', ', $missing)
                . "\nA missing route is not a slow row — the framework silently disappears"
                . ' from that endpoint\'s comparison, and a real-deployment run aborts on it'
                . ($excluded !== []
                    ? "\n(Excluded by BenchmarkConfig::excludedRequests(), so not required: "
                        . implode(', ', $excluded) . ')'
                    : '')
        );

        // An exclusion must actually mean the route is absent, or the app is being
        // timed on an endpoint the report claims it sat out.
        foreach ($excluded as $path) {
            self::assertSame(
                0,
                preg_match('~' . preg_quote($path, '~') . '(?![\w/-])~', $declarations),
                "{$app} IS excluded from `{$path}` but still registers it — either the "
                    . 'exclusion is stale or the route should be served and raced'
            );
        }
    }

    /**
     * The harness actually requests all of them.
     *
     * The converse failure: a route every app serves that the harness never
     * asks for is measured by nobody. `/items-fast` sat like that for months.
     * `scripts/bench-lib.php` is the list the REAL-DEPLOYMENT harness uses
     * (http-bench.php re-parses it and never receives `--requests`), so it is
     * the one that decides what a published dataset contains.
     */
    public function testTheRealDeploymentHarnessRequestsEveryFeatureEndpoint(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/scripts/bench-lib.php');

        self::assertMatchesRegularExpression(
            '~function parseRequestsArg.*?\[(.*?)\];~s',
            $source,
            'scripts/bench-lib.php must have a default request list'
        );
        preg_match('~function parseRequestsArg.*?\[(.*?)\];~s', $source, $block);
        preg_match_all("~'((?:GET|POST) /[^']*)'~", $block[1], $m);
        // The list carries "METHOD /path" labels; the expectation is bare paths.
        $found = array_map(
            static fn(string $label): string => trim(explode(' ', $label, 2)[1] ?? ''),
            $m[1] ?? []
        );

        $missing = array_values(array_diff(self::featurePaths(), $found));
        self::assertSame(
            [],
            $missing,
            'scripts/bench-lib.php does not request: ' . implode(', ', $missing)
        );
    }

    /**
     * Every app also serves the overview page the demos link to.
     *
     * Not measured, but not optional either: five of the seven templates carry
     * `<a href="/features">`, so a missing route is a dead link on the welcome
     * page. Asserted separately from the measured set because the two
     * requirements fail for different reasons and a shared list would let one
     * silently cover for the other.
     *
     * @dataProvider appProvider
     */
    public function testEveryAppRegistersTheFeatureOverviewPage(string $app): void
    {
        $source = '';
        foreach (self::routeFiles()[$app] as $file) {
            if (is_file($file)) {
                $source .= (string) file_get_contents($file) . "\n";
            }
        }

        $declarations = self::declarations($source);

        // The exact path, with nothing after it. The negative lookahead is what
        // distinguishes `/features` from `/features/aop` — without it this test
        // passes for any app that has a single demo route.
        self::assertMatchesRegularExpression(
            '~' . preg_quote('/features', '~') . '(?![\w/-])~',
            $declarations,
            "{$app} does not register the /features overview page its templates link to"
        );
    }
}
