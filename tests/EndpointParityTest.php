<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the two endpoints that are easy to half-add.
 *
 * `/echo` and `/items-fast` are the only routes in the set that exist to
 * isolate something rather than to demonstrate it, and they only work if ALL
 * six apps serve them. A missing one is invisible in the results — the
 * framework simply has no row, and a chart that drops a participant silently
 * is indistinguishable from a framework that lost. The same shape of failure
 * happened once already: `/items-fast` was registered in the azera app but
 * left out of run.php's request list for months, so the route existed, was
 * reachable, and was never measured.
 *
 * These tests read the ROUTE TABLES, not the adapters, because that is where
 * the mistake is made: a controller action with no route is dead code, and a
 * route with no action is a 500 that only a live run would reveal.
 */
final class EndpointParityTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * The route table of each app, as source text.
     *
     * @return array<string,string>
     */
    private static function routeFiles(): array
    {
        $root = self::repoRoot();

        return [
            'azera'       => $root . '/apps/azera/Bootstrap.php',
            'cakephp'     => $root . '/apps/cakephp/src/Application.php',
            'codeigniter' => $root . '/apps/codeigniter/Config/Routes.php',
            'laravel'     => $root . '/apps/laravel/routes/web.php',
            'spiral'      => $root . '/apps/spiral/src/Bootloader/AppRoutesBootloader.php',
            'symfony'     => $root . '/apps/symfony/config/routes.yaml',
            'phalcon'     => $root . '/apps/phalcon/src/Bootstrap.php',
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
     * Every app registers both new routes.
     *
     * Asserted per app rather than once over the union, so a failure names the
     * framework that is missing the route instead of only reporting that
     * something changed.
     *
     * @dataProvider appProvider
     */
    public function testEveryAppRegistersBothNewRoutes(string $app): void
    {
        $file   = self::routeFiles()[$app];
        $source = (string) file_get_contents($file);

        // Symfony and Cake declare routes in YAML / with a builder, so the
        // literal appears either as `path: /echo` or as `'/echo'`. Matching the
        // bare path is what all six forms have in common. `$r->get('/echo')`
        // is not matched by requiring the literal `'/echo'`, so the check only
        // requires the path text and the app's own quote style supplies the rest.
        foreach (['/echo', '/items-fast', '/routing'] as $path) {
            self::assertMatchesRegularExpression(
                '~[\'"]?' . preg_quote($path, '~') . '[\'"]?~',
                $source,
                "{$app} does not register {$path} (looked in " . basename($file) . ')'
            );
        }
    }

    /**
     * Route source with comments removed.
     *
     * Necessary because these files DOCUMENT the ordering ("declared before
     * /items/{id} so the literal is not swallowed"), so a naive `strpos` finds
     * the pattern inside the very comment that explains the rule and reports
     * the app as misordered. The check has to read the declarations, not the
     * prose about them.
     */
    private static function declarations(string $source): string
    {
        // Block comments first: they can span the declaration lines below.
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        $lines = preg_split('~\R~', $source) ?: [];
        $kept  = [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            // PHP line comments, and `#` comments (YAML routes and PHP hash
            // comments both use it). A leading `#[` is a PHP attribute, not a
            // comment — no route is declared there, so dropping it is harmless.
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                continue;
            }
            $kept[] = $line;
        }

        return implode("\n", $kept);
    }

    /**
     * `/items-fast` is declared BEFORE the `/items/{id}` pattern wherever both
     * are declared in one file.
     *
     * Where it is not, a literal segment can be swallowed by the numeric
     * placeholder — the route still "exists" in the table but the request
     * resolves to the detail action and returns an item page instead of the
     * list. Symfony's router sorts by specificity rather than declaration
     * order, which is why this is asserted per file rather than globally.
     *
     * @dataProvider appProvider
     */
    public function testTheLiteralSegmentIsNotSwallowedByIdPatterns(string $app): void
    {
        $source = self::declarations((string) file_get_contents(self::routeFiles()[$app]));

        $fast = strpos($source, '/items-fast');
        if ($fast === false) {
            self::fail("{$app} does not register /items-fast at all");
        }

        // The first id-style item route in the same file. Matched by SHAPE
        // rather than by an enumerated list of spellings — the seven apps write
        // it as `/items/{id}`, `/items/{id:int}`, `/items/<id:\d+>`,
        // `/items/(:num)` or `/items/([0-9]+)`, and a list would silently skip
        // the next framework that picks a sixth form (which is exactly what
        // happened here: azera says `{id:int}` and the first version of this
        // test never looked, and Phalcon's `([0-9]+)` was missed the same way).
        //
        // The path must follow a quote or `path:`, so `/api/items/{id}` is not
        // mistaken for it.
        if (preg_match('~(?<=[\'"]|path:\s|path:\s{2})/items/(?:<|\{|\(:num|\(\[)~', $source, $m, PREG_OFFSET_CAPTURE) === 1) {
            self::assertLessThan(
                $m[0][1],
                $fast,
                "{$app} declares /items-fast after an id-style item route; the literal segment "
                    . 'can be swallowed by the placeholder'
            );
        }
    }

    /**
     * The harness measures both routes.
     *
     * A route that is registered in every app but absent from run.php's request
     * list is measured by nobody — which is exactly how `/items-fast` sat unused.
     */
    public function testTheHarnessMeasuresBothRoutes(): void
    {
        $run = (string) file_get_contents(self::repoRoot() . '/run.php');

        self::assertStringContainsString(
            "'GET /echo'",
            $run,
            'run.php does not request GET /echo'
        );
        self::assertStringContainsString(
            "'GET /routing/1'",
            $run,
            'run.php does not request GET /routing/1 — the routing endpoint would measure nothing'
        );
        self::assertStringContainsString(
            "'GET /items-fast'",
            $run,
            'run.php does not request GET /items-fast — the route would be dead code in the benchmark'
        );
    }

    /**
     * All THREE request lists carry the same endpoints.
     *
     * The benchmark has three places that default to "every endpoint":
     *
     *   run.php                     the in-process harness
     *   run-app.php                 its per-app child, used standalone
     *   scripts/bench-lib.php       parseRequestsArg(), used by http-bench.php,
     *                               i.e. by every REAL-DEPLOYMENT run
     *
     * They are separate lists because the real-deployment harness re-parses its
     * arguments in a child process and never receives `--requests` from
     * run-http.php. That makes it possible to add a route to the in-process list,
     * measure it there, and have the published real-deployment dataset silently
     * carry 21 endpoints instead of 23 — which is what happened when these two
     * routes were first added: the VM run measured the old set. A missing endpoint
     * is invisible in a per-endpoint table; the row simply is not there.
     *
     * Compared as SETS, with the canonical order from BenchmarkConfig as the
     * reference, so the failure names the missing endpoint rather than reporting
     * that some list changed.
     */
    public function testEveryRequestListCarriesTheSameEndpoints(): void
    {
        $canonical = \AzeraCompetition\Report\BenchmarkConfig::requestOrder();

        foreach ([
            // Anchored on each file's own request-list assignment, not on a bare
            // `: [` — run.php has several array literals, and an earlier anchor
            // matched the wrong one and reported a phantom failure.
            'run.php'               => "~\\\$requests\s*=\s*isset.*?\[(.*?)\];~s",
            'run-app.php'           => "~\\\$requests = isset.*?\[(.*?)\];~s",
            'scripts/bench-lib.php' => "~function parseRequestsArg.*?\[(.*?)\];~s",
        ] as $file => $blockPattern) {
            $source = (string) file_get_contents(self::repoRoot() . '/' . $file);

            self::assertMatchesRegularExpression($blockPattern, $source, "{$file} must have a default request list");
            preg_match($blockPattern, $source, $block);
            preg_match_all("~'((?:GET|POST) /[^']*)'~", $block[1], $m);
            $found = $m[1] ?? [];

            self::assertSame(
                [],
                array_diff($canonical, $found),
                "{$file}'s default request list is missing endpoints the canonical order has — "
                    . 'the real-deployment harness re-parses its own list and never receives '
                    . '--requests, so a route added elsewhere is measured by nobody here'
            );
        }
    }

    /**
     * Every measured request has a feature and a workload sentence.
     *
     * The report derives its endpoint table, its memory series and its chart
     * ordering from requestOrder(); a request with no feature entry is
     * silently dropped from every one of them.
     */
    public function testEveryMeasuredRequestIsClassified(): void
    {
        $run   = (string) file_get_contents(self::repoRoot() . '/run.php');
        $order = \AzeraCompetition\Report\BenchmarkConfig::requestOrder();

        // The two new routes specifically, since they are the ones this change
        // introduced and the ones a future edit is most likely to half-add.
        foreach (['GET /echo', 'GET /items-fast', 'GET /routing/1'] as $request) {
            self::assertContains($request, $order, "{$request} is missing from featureMap()");
            self::assertNotSame(
                '',
                \AzeraCompetition\Report\BenchmarkConfig::workloadFor($request),
                "{$request} has no workload description"
            );
        }
    }

    /**
     * The Laravel route warmer must clear the cache BEFORE it boots the app.
     *
     * `bootstrap/app.php` registers routes through `withRouting(using: ...)`, and
     * Laravel skips that callback entirely when a compiled routes file exists — it
     * loads the compiled table instead. A warmer that boots first therefore
     * re-exports the table it found, forever: the file gets a new mtime and new
     * bytes, and its contents are a copy of the previous generation.
     *
     * This bit: after `GET /echo` was added the warmer ran, reported success, wrote
     * a 133 KB table, and the FPM arm 404'd on `/echo` because no such route was in
     * it. Every signal said "cache rebuilt". Only comparing the route count against
     * routes/web.php revealed the staleness.
     *
     * Asserted as an ORDER on the two statements, because either alone is
     * insufficient: a warmer with no clear at all fails this, and a warmer that
     * clears after `require bootstrap/app.php` has already loaded the stale table.
     */
    public function testTheLaravelWarmerClearsTheCacheBeforeBooting(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/scripts/warm-laravel-routes.php');

        // Strip comments so the docblock above (which names both statements) cannot
        // satisfy the assertions.
        $code = (string) preg_replace('#/\*.*?\*/#s', '', $source);
        $code = (string) preg_replace('~//[^\n]*~', '', $code);

        $clear = strpos($code, 'unlink($stale)');
        $boot  = strpos($code, "require \$root . '/apps/laravel/bootstrap/app.php'");

        self::assertNotFalse($clear, 'the warmer must clear the compiled table: Laravel skips web.php when one exists');
        self::assertNotFalse($boot, 'the warmer must still boot the app to compile the routes');

        self::assertLessThan(
            $boot,
            $clear,
            'the stale cache must be removed BEFORE the app boots — otherwise Laravel '
                . 'loads the compiled table, skips routes/web.php, and the warmer '
                . 're-exports the routes it was meant to refresh'
        );
    }
}
