<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\BenchmarkConfig;
use PHPUnit\Framework\TestCase;

/**
 * The local gates must honour the same per-app exclusion the measurement does.
 *
 * WHY. `BenchmarkConfig::excludedRequests()` is the single source of truth for
 * "this entrant sits out this endpoint" — Phalcon is excluded from exactly one,
 * `GET /features/aop-tx`, because the endpoint compares proxy-generated advice
 * against hand-written calls and Phalcon 6 has no interceptor layer.
 * `scripts/run-http.php` honoured it; the other two gates did not, and they
 * disagreed with the harness in both directions:
 *
 *   - `verify.php` / `verify-app.php` demanded the excluded route from every
 *     app, so a correct checkout reported a FAILURE on the endpoint the report
 *     had already declared uncounted;
 *   - `run.php` passed the FULL request list to its child process, so
 *     `php run.php --apps=phalcon` aborted the whole run with
 *       "[ABORT] warm GET /features/aop-tx returned an error response ... 404".
 *
 * Neither the PHPUnit suite nor `smoke.php` caught this, because neither boots
 * every app through the harness path. Both gates are now source contracts: each
 * must consult racesOn() before it asks an app for an endpoint.
 */
final class ExclusionParityGateTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function read(string $relative): string
    {
        return (string) file_get_contents(self::repoRoot() . '/' . $relative);
    }

    /**
     * The parity gate must skip an endpoint the app does not compete on.
     *
     * It must consult `competesOn()`, not `racesOn()`: the second reason an app has
     * no row is that the FEATURE is absent from its list (five entrants have no
     * explicit-interceptor pipeline), and `racesOn()` only knows the per-endpoint
     * exclusions.
     */
    public function testTheParityGateHonoursTheExclusion(): void
    {
        $source = self::read('verify-app.php');

        self::assertStringContainsString(
            'BenchmarkConfig::competesOn(',
            $source,
            'verify-app.php must consult the participation map — otherwise it reports a failure '
                . 'for an endpoint the report says the app does not compete on'
        );
        self::assertStringContainsString(
            'adapterFeatures()',
            $source,
            'verify-app.php must name the map that decides feature participation'
        );

        // The check must live inside the expectation loop, i.e. before dispatch.
        $loop = strpos($source, 'foreach ($expect as $req => $needle)');
        $skip = strpos($source, 'competesOn(');
        self::assertNotFalse($loop);
        self::assertNotFalse($skip);
        self::assertGreaterThan($loop, $skip, 'the exclusion check must run inside the expectation loop');
    }

    /**
     * The smoke pre-flight must skip it too.
     */
    public function testTheSmokeGateHonoursTheExclusion(): void
    {
        $source = self::read('smoke-app.php');

        self::assertStringContainsString('BenchmarkConfig::competesOn(', $source);
    }

    /**
     * ...and the in-process harness must not ask an app for it at all.
     *
     * This is the one that actually aborted a run, so it is asserted on the
     * value handed to the child process, not merely on the presence of a filter.
     */
    public function testTheInProcessHarnessFiltersRequestsPerApp(): void
    {
        $source = self::read('run.php');

        self::assertStringContainsString(
            'BenchmarkConfig::competesOn(',
            $source,
            'run.php must filter its request list per app — the child process cannot do it '
                . 'and aborts on the first 404'
        );

        // Extract the sprintf() that builds the child command and assert the
        // request argument it interpolates is the FILTERED one. Passing
        // $requestArg (the full set) is the exact defect: the filter would exist
        // and have no effect on what the child is asked to measure.
        self::assertMatchesRegularExpression(
            '/--requests=%s.*?\$appRequestArg/s',
            $source,
            'run.php must pass the per-app request list to run-app.php, not the full one'
        );

        $cmdStart = strpos($source, '--requests=%s');
        self::assertNotFalse($cmdStart);
        $cmdEnd = strpos($source, '--out-json', $cmdStart);
        self::assertNotFalse($cmdEnd);
        self::assertStringNotContainsString(
            '$requestArg',
            substr($source, $cmdStart, $cmdEnd - $cmdStart),
            'run.php still passes the unfiltered $requestArg to the child'
        );
    }

    /**
     * The exclusion itself stays exactly one endpoint.
     *
     * A regression in the other direction — widening the map to hide a broken
     * app — would make every gate above pass while a real row disappeared. The
     * new `aop-tx-explicit` gap is expressed as feature participation
     * (adapterFeatures()), NOT by adding five endpoints to this map.
     */
    public function testPhalconStillSitsOutExactlyOneEndpoint(): void
    {
        self::assertSame(
            ['phalcon' => ['GET /features/aop-tx']],
            BenchmarkConfig::excludedRequests(),
            'the exclusion map is one endpoint on one app; widening it hides a measurable row'
        );
    }

    /**
     * The facility gap is expressed as participation, and only Azera and Spiral
     * have the facility.
     *
     * This is the distinction the user's rule turns on: an app that does not ship
     * the feature does not get a row produced by a workaround. If a future edit
     * adds `aop-tx-explicit` to a third entrant, that entrant must first actually
     * ship an interceptor abstraction.
     */
    public function testOnlyEntrantsWithAnInterceptorAbstractionRaceAopExplicit(): void
    {
        $features = BenchmarkConfig::adapterFeatures();

        $participants = array_keys(array_filter(
            $features,
            static fn(array $list): bool => in_array('aop-tx-explicit', $list, true)
        ));
        sort($participants);

        self::assertSame(
            ['azera', 'spiral'],
            $participants,
            'only Azera (Azera\\Aop\\InterceptorInterface + Pipeline) and Spiral '
                . '(Spiral\\Interceptors\\InterceptorInterface + PipelineBuilder) ship a dedicated '
                . 'interceptor abstraction; anyone else racing this row would be measuring a '
                . 'hand-written workaround'
        );
    }
}
