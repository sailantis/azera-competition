<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Which races an entrant takes part in must match what it can actually run.
 *
 * WHY. `BenchmarkConfig::adapterFeatures()` drives `ResultStore::supports()`,
 * and that decides whether an app appears in a feature's winners table and its
 * chart. An app missing from that list does not show as a failure — it silently
 * vanishes from the comparison, which reads exactly like an app that chose not
 * to compete.
 *
 * The two ways to get this wrong, both of which have happened here:
 *
 *   1. CLAIM MORE THAN THE APP CAN DO. Phalcon shipped with 14 of 25 routes
 *      while its adapter features listed nothing, so the omission was at least
 *      honest; had the list claimed the features, the reporter would have asked
 *      for rows that could not exist.
 *   2. CLAIM LESS THAN IT CAN DO. After the routes were added, Phalcon was still
 *      excluded from all eleven features — including the seven it implements on
 *      native primitives (cache, db-events, events, validation, config,
 *      rate-limiter). It competed in none of them, and no page
 *      said so.
 *
 * ROUTE PRESENCE is deliberately NOT re-checked here: `FeatureRouteParityTest`
 * already asserts every app registers all 11 feature endpoints, and
 * `EndpointParityTest` does the same for the single-cost routes. A second,
 * looser check in this file only produces false negatives — a parameterised
 * `/items/([0-9]+)` serves the benchmark's `/items/1`, so a literal search
 * reports a route that plainly exists. What is tested here is the MAP: that
 * nobody claims a race that does not exist, that nobody drops out of a core
 * race, and that Phalcon's one exclusion stays exactly one.
 */
final class AdapterFeaturesTest extends TestCase
{
    /** @return array<string,list<string>> app => features it competes in */
    private static function config(): array
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        return \AzeraCompetition\Report\BenchmarkConfig::adapterFeatures();
    }

    /** @return array<string,list<string>> feature => the requests that exercise it */
    private static function featureRequests(): array
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $out = [];
        foreach (\AzeraCompetition\Report\BenchmarkConfig::featureMap() as $request => $feature) {
            $out[$feature][] = $request;
        }

        return $out;
    }

    /**
     * Phalcon's one genuine gap is ONE ENDPOINT of AOP, not the feature.
     *
     * `GET /features/aop-tx` compares generated proxies against hand-written calls. Phalcon
     * 6 has no interceptor layer, so racing its hand-written insert there would compare
     * machinery rather than speed — that endpoint is excluded, per app, in
     * `BenchmarkConfig::excludedRequests()`.
     *
     * `GET /features/aop-tx-direct` is a DIFFERENT question and Phalcon answers it best: it
     * is the hand-written CONTROL that every entrant serves, and Phalcon's `/features/aop-tx`
     * is already that same code. Excluding the whole `aop-tx` feature (what this test used
     * to require) hid the control row from the entrant that reads it best and dropped
     * Phalcon from the totals' comparison silently.
     *
     * Everything else is a native Phalcon subsystem — Phalcon\Cache, the db adapter's
     * events manager, Phalcon\Events\Manager, Phalcon\Filter\Validation, Phalcon\Config,
     * an explicit request-scope reset and a cache-backed limiter. Excluding it from
     * those would hide a real result.
     */
    public function testPhalconSitsOutOneAopEndpointNotTheFeature(): void
    {
        $features = self::config()['phalcon'] ?? [];
        $all      = array_keys(self::featureRequests());

        // Phalcon ships neither AOP facility: no proxy-generated advice (`aop-tx`,
        // `aop-cache`) and no interceptor abstraction (`aop-tx-explicit`). Everything
        // else is a native subsystem, and — critically — it DOES compete in
        // `aop-tx-direct` and `aop-cache-direct`, the hand-written controls that need
        // no framework facility.
        self::assertSame(
            ['aop-tx', 'aop-tx-explicit', 'aop-cache'],
            array_values(array_diff($all, $features)),
            'Phalcon ships neither transparent advice nor an interceptor abstraction; '
                . 'missing: ' . implode(', ', array_values(array_diff($all, $features)))
        );

        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';
        $cfg = \AzeraCompetition\Report\BenchmarkConfig::class;

        self::assertTrue(
            $cfg::racesOn('phalcon', 'GET /features/aop-tx-direct'),
            'the hand-written control is everyone\'s race, including the entrant that always writes it by hand'
        );
        self::assertFalse(
            $cfg::racesOn('phalcon', 'GET /features/aop-tx'),
            'the proxy comparison is the one endpoint Phalcon cannot be raced on'
        );
        self::assertTrue(
            $cfg::racesOn('azera', 'GET /features/aop-tx'),
            'every other entrant races both endpoints'
        );

        // `aop-tx-explicit` is the second exception, and it is a FACILITY gap rather
        // than an endpoint exclusion: Phalcon has no interceptor abstraction, so
        // the feature is not in its list at all.
        self::assertNotContains(
            'aop-tx-explicit',
            $features,
            'Phalcon cannot race the explicit interceptor pipeline — it has no such facility'
        );
        self::assertFalse(
            $cfg::competesOn('phalcon', 'GET /features/aop-tx-explicit'),
            'competesOn() must report a facility gap, not merely an endpoint exclusion'
        );
    }

    /**
     * The logging demo is its own feature, not part of the AOP race.
     *
     * `GET /features/log` used to map to 'aop-tx', which meant that excluding a
     * framework from the proxy-vs-hand-written comparison ALSO removed its logger
     * from the report. CodeIgniter, CakePHP and Phalcon all implement it on a
     * facility they genuinely have, so the exclusion was silently hiding a
     * measurable row from three entrants.
     *
     * Splitting it keeps the AOP exclusion honest and narrow.
     */
    public function testTheAdviceDemosAreNotPartOfTheAopRace(): void
    {
        $map = [];
        foreach (\AzeraCompetition\Report\BenchmarkConfig::featureMap() as $request => $feature) {
            $map[$request] = $feature;
        }

        self::assertSame('aop-tx', $map['GET /features/aop-tx'], 'the proxy advice demo is the AOP race');

        self::assertSame(
            'logging',
            $map['GET /features/log'] ?? null,
            "GET /features/log must be its own feature; folding it into 'aop-tx' hides it from every "
                . 'entrant excluded from the proxy comparison'
        );
    }

    /**
     * TRANSPARENT vs DIRECT interception, pinned per entrant.
     *
     * Every measured app declares at most one style, and the classification must
     * agree with what the code actually does. The claim this encodes was checked
     * by grepping each app for an advice attribute (`#[Transactional]`, `#[Log]`,
     * `#[Retry]`, `#[Cache]`, `#[Advised]`): only Azera has any. Laravel's
     * `AopService` and Symfony's both say in their own headers that the framework
     * has "no proxy-based AOP" and that a hand-composed pipeline is the idiomatic
     * substitute; Spiral's `PipelineBuilder` pipeline admits in situ that "the
     * interceptor would manage the transaction in a real app; here the write
     * happens inside the wrapped callable".
     *
     * If someone later gives another entrant real generated-proxy advice, this
     * test is the thing that must be updated deliberately — the report's note on
     * which facility an entrant ships is generated from the same map.
     *
     * The abstraction is priced by the `/features/aop-tx` vs `/features/aop-tx-direct`
     * pair, which must stay same-work; `AdvicePairTest` holds that.
     */
    public function testInterceptionStyleMatchesTheImplementation(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $styles = \AzeraCompetition\Report\BenchmarkConfig::aopStyle();

        self::assertSame(
            ['transparent', 'direct'],
            array_values(array_unique(array_values($styles))),
            'both styles must be represented'
        );

        self::assertSame(
            ['azera'],
            array_keys(array_filter($styles, static fn(string $s): bool => $s === 'transparent')),
            'only Azera applies advice transparently (attribute-declared, proxy-generated). '
                . 'Any other app listed here needs the attribute grep re-run.'
        );

        self::assertSame(
            array_keys(\AzeraCompetition\Report\BenchmarkConfig::adapterFeatures()),
            array_keys($styles),
            'every measured app must be classified'
        );
    }

    /**
     * The in-race style split names only participants — and there is exactly one.
     *
     * Now that `aop-tx` means transparent AOP and the six hand-written entrants have
     * left it, Azera is the ONLY entrant in the race. The split therefore has an
     * empty `direct` list, which is the honest reading: `aop-tx-direct` is a separate
     * feature precisely so those six keep a row they can serve.
     */
    public function testTheStyleSplitNamesOnlyParticipants(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $split = \AzeraCompetition\Report\BenchmarkConfig::aopStylesInRace();

        self::assertSame(['Azera'], $split['transparent']);

        self::assertSame(
            [],
            $split['direct'],
            'no hand-written entrant competes in `aop-tx` any more — the row that compares those is '
                . '`aop-tx-direct`, which every app races. Naming them here would claim they competed '
                . 'in a race for a facility they do not ship.'
        );
        self::assertNotContains('Phalcon 6 (RC)', $split['transparent']);
    }

    /**
     * Nobody silently drops out of the four core races.
     *
     * `routing`, `orm`, `query-builder` and `rest-api` are not optional features
     * — every app implements them, and they are the tables the published pages
     * lead with. An app missing from `orm` would not appear slow; it would not
     * appear.
     */
    public function testEveryAppTakesPartInTheCoreRaces(): void
    {
        $core = ['routing', 'orm', 'query-builder', 'rest-api'];

        foreach (self::config() as $app => $features) {
            self::assertSame(
                [],
                array_values(array_diff($core, $features)),
                "{$app} must compete in every core race; it is missing: "
                    . implode(', ', array_diff($core, $features))
            );
        }
    }

    /** The feature keys must all be real, or a typo silently means "no race". */
    public function testEveryNamedFeatureExistsInTheFeatureMap(): void
    {
        $known = array_keys(self::featureRequests());
        self::assertNotSame([], $known, 'featureMap() is empty — the reporter has no races');

        foreach (self::config() as $app => $features) {
            $unknown = array_values(array_diff($features, $known));
            self::assertSame(
                [],
                $unknown,
                "{$app} claims features that no request exercises: " . implode(', ', $unknown)
            );
        }
    }

    /**
     * Every app in the dataset has an entry, and nothing else does.
     *
     * A missing app falls back to `[]` in `ResultStore::featuresFor()`, which
     * excludes it from every feature race without a word.
     */
    public function testEveryMeasuredAppHasAnEntry(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        self::assertSame(
            \AzeraCompetition\Report\BenchmarkConfig::appOrder(),
            array_keys(self::config()),
            'adapterFeatures() must cover exactly the measured apps, in display order'
        );
    }
}
