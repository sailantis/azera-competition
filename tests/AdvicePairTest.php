<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `GET /features/aop-tx-direct` must stay the SAME WORK as `GET /features/aop-tx`.
 *
 * WHY THIS EXISTS. The pair is the only way to price transparent interception:
 * `/features/aop-tx` runs the transaction through generated advice, `/features/aop-tx-direct`
 * runs it through hand-written `begin`/`commit`, and everything else — the upsert, the
 * sentinel id, the event dispatch — has to be identical. The moment one side gains work
 * the other does not, the difference stops being the cost of the abstraction and starts
 * being the difference between two demonstrations, which is the mistake the pair was
 * built to avoid.
 *
 * It has already gone wrong twice while being written:
 *   - Azera's first `createItemDirect()` called `beginTransaction()`, which
 *     `Azera\Db\Database` does not have — a 500 that the run correctly aborted on. The
 *     interceptor uses `begin(true)`, so the twin has to as well or it measures a
 *     different transaction API.
 *   - Symfony's `aopDirect()` declared `Response` while returning `JsonResponse`.
 *
 * Both were caught by running the endpoint. These assertions catch the third case — a
 * silent divergence in the body — at review time instead.
 */
final class AdvicePairTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /** The advised and direct bodies on Azera, comment- and whitespace-normalised. */
    private static function azeraBodies(): array
    {
        $src = (string) file_get_contents(self::root() . '/apps/azera/Services/FeatureService.php');

        $grab = static function (string $method) use ($src): string {
            if (preg_match('/public function ' . $method . '\(.*?\n    \}/s', $src, $m) !== 1) {
                return '';
            }
            $body = $m[0];
            // Strip docblocks/line comments and collapse whitespace, so only the
            // STATEMENTS are compared.
            $body = (string) preg_replace('~/\*.*?\*/~s', '', $body);
            $body = (string) preg_replace('~^\s*//.*$~m', '', $body);

            return (string) preg_replace('~\s+~', ' ', $body);
        };

        return [$grab('createItemTransactional'), $grab('createItemDirect')];
    }

    /** The shared workload method both twins must delegate to. */
    private static function writeSentinelBody(): string
    {
        $src = (string) file_get_contents(self::root() . '/apps/azera/Services/FeatureService.php');

        if (preg_match('/private function writeSentinel\(.*?\n    \}/s', $src, $m) !== 1) {
            return '';
        }

        $body = (string) preg_replace('~/\*.*?\*/~s', '', $m[0]);
        $body = (string) preg_replace('~^\s*//.*$~m', '', $body);

        return (string) preg_replace('~\s+~', ' ', $body);
    }

    /**
     * Both twins do the same write and the same dispatch.
     *
     * The workload itself is asserted ONCE, on `writeSentinel()`, because that is
     * now the only place it exists: the two twins delegate to it and differ only
     * in which layer supplies the transaction. Asserting the upsert inside each
     * twin would fail the moment the shared helper was introduced while the pair
     * stayed correct — and, worse, it would encourage re-inlining the SQL into
     * both methods, which is how the two would drift apart.
     */
    public function testAzeraTwinMethodsPerformTheSameWork(): void
    {
        [$advised, $direct] = self::azeraBodies();
        $workload           = self::writeSentinelBody();

        self::assertNotSame('', $advised, 'could not read createItemTransactional()');
        self::assertNotSame('', $direct, 'could not read createItemDirect()');
        self::assertNotSame('', $workload, 'could not read writeSentinel()');

        self::assertStringContainsString(
            'ON CONFLICT(id) DO UPDATE SET title = excluded.title',
            $workload,
            'the shared workload must run the upsert'
        );
        self::assertStringContainsString(
            'events()->dispatch(new \App\Azera\Events\ItemCreated',
            $workload,
            'the shared workload must dispatch the same event — otherwise the advised row pays for '
                . 'a listener the direct row does not, and the difference is not proxy cost'
        );
        self::assertStringContainsString('self::FEATURE_SENTINEL_ID', $workload);

        // ...and BOTH twins must actually reach it. A twin that had been
        // re-inlined would pass the checks above while measuring its own copy.
        foreach (['advised' => $advised, 'direct' => $direct] as $side => $body) {
            self::assertStringContainsString(
                '$this->writeSentinel($title)',
                $body,
                "the {$side} twin must delegate to the shared workload so the pair cannot drift"
            );
        }

        // The direct twin must use the SAME transaction API the interceptor uses
        // (`begin(true)`, savepoint-aware), not a generic one that happens to exist.
        self::assertStringContainsString('$db->begin(true)', $direct);
        self::assertStringContainsString('$db->commit()', $direct);
        self::assertStringContainsString('$db->rollback()', $direct);

        // ...and the advised twin must contain NO transaction calls of its own:
        // that is the whole point of the advice.
        self::assertStringNotContainsString('$db->begin', $advised);
        self::assertStringNotContainsString('$db->commit', $advised);
    }

    /** The advised method is the one carrying the attribute. */
    public function testOnlyTheAdvisedMethodIsDeclaredTransactional(): void
    {
        $src = (string) file_get_contents(self::root() . '/apps/azera/Services/FeatureService.php');

        self::assertMatchesRegularExpression(
            '/#\[Transactional\]\s*public function createItemTransactional\(/',
            $src,
            'createItemTransactional() must carry #[Transactional]'
        );
        self::assertDoesNotMatchRegularExpression(
            '/#\[[A-Za-z]+[^\]]*\]\s*public function createItemDirect\(/',
            $src,
            'createItemDirect() must carry NO advice — it is the direct control'
        );
    }

    /**
     * Every entrant serves both rows.
     *
     * A missing route is not a slow row: the harness requests all 26 endpoints
     * from every app and a 404 aborts the run (see FeatureRouteParityTest). This
     * is the narrower check that the PAIR specifically reached all seven.
     */
    public function testEveryEntrantServesTheControlEndpoint(): void
    {
        $files = [
            'azera'       => ['apps/azera/Bootstrap.php'],
            'cakephp'     => ['apps/cakephp/src/Application.php'],
            'codeigniter' => ['apps/codeigniter/Config/Routes.php'],
            'laravel'     => ['apps/laravel/routes/web.php'],
            'spiral'      => ['apps/spiral/src/Bootloader/AppRoutesBootloader.php'],
            'symfony'     => ['apps/symfony/config/routes.yaml'],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];

        $missing = [];
        foreach ($files as $app => $paths) {
            $source = '';
            foreach ($paths as $path) {
                $source .= (string) file_get_contents(self::root() . '/' . $path);
            }
            if (!str_contains($source, '/features/aop-tx-direct')) {
                $missing[] = $app;
            }
        }

        self::assertSame([], $missing, 'these apps do not serve the aop-tx-direct control: ' . implode(', ', $missing));
    }

    /**
     * The control is measured, and is a feature of its own.
     *
     * It used to share the `aop-tx` feature so the pair was read together. That no
     * longer works: `aop-tx` now means transparent AOP, which only Azera ships, so a
     * shared key would drop the control from the six entrants that can serve it —
     * the exact "framework silently vanishes" failure this suite exists to catch.
     * The two features are read side by side instead, and this pins that split.
     */
    public function testTheControlIsMeasuredAsItsOwnFeature(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $map = \AzeraCompetition\Report\BenchmarkConfig::featureMap();

        self::assertArrayHasKey('GET /features/aop-tx-direct', $map, 'the control must be measured');
        self::assertSame(
            'aop-tx-direct',
            $map['GET /features/aop-tx-direct'],
            'the control is its own feature: every entrant races it, while `aop-tx` is Azera-only'
        );
        self::assertSame('aop-tx', $map['GET /features/aop-tx']);

        // ...and both features are drawn, or the comparison is invisible on the page.
        $order = \AzeraCompetition\Report\BenchmarkConfig::featureOrder();
        self::assertContains('aop-tx', $order);
        self::assertContains('aop-tx-direct', $order);

        $canonical = (string) file_get_contents(self::root() . '/scripts/bench-lib.php');
        self::assertStringContainsString(
            "'GET /features/aop-tx-direct'",
            $canonical,
            'the control must be in the canonical request list or the harness never asks for it'
        );
        self::assertStringContainsString(
            "'GET /features/aop-cache-direct'",
            $canonical,
            'the cache control must be in the canonical request list too'
        );
    }

    /**
     * The cache pair is same-work too, and must stay that way.
     *
     * `aop-cache` and `aop-cache-direct` are only comparable if both read the
     * same COUNT from the same cache entry with the same TTL. The risk is
     * different from the transactional pair's: there the drift would be in the
     * write, here it is in the KEY or the TTL — and a pair that used different
     * keys would report a cache MISS on one side and a HIT on the other, which
     * would read as a proxy overhead of several milliseconds.
     *
     * Both sides are therefore pinned to one shared constant, and the TTL to the
     * same literal.
     */
    public function testTheCachePairSharesOneKeyAndOneTtl(): void
    {
        $service = (string) file_get_contents(self::root() . '/apps/azera/Services/FeatureService.php');
        $controller = (string) file_get_contents(self::root() . '/apps/azera/Controllers/FeatureController.php');

        // One constant, used by both.
        self::assertMatchesRegularExpression(
            '/public const CACHE_KEY = /',
            $service,
            'the cache key must be a shared constant so the pair cannot drift onto different entries'
        );
        self::assertMatchesRegularExpression(
            '/#\[Cache\(ttl: 10, key: self::CACHE_KEY\)\]/',
            $service,
            'the advised method must use the shared key and the same TTL as the control'
        );
        self::assertStringContainsString('FeatureService::CACHE_KEY', $controller);

        // The control must write with the same TTL the attribute declares.
        preg_match('/public function aopCacheDirectAction\(.*?\n    \}/s', $controller, $m);
        self::assertNotSame([], $m ?? []);
        self::assertMatchesRegularExpression(
            '/cache->set\(FeatureService::CACHE_KEY,\s*\$count,\s*10\)/',
            $m[0],
            'the control must store with the same 10-second TTL the attribute declares, or the two '
                . 'rows expire at different times and stop being interchangeable'
        );
    }

    /**
     * Every entrant serves both cache rows it competes on.
     *
     * `aop-cache` is Azera's alone, `aop-cache-direct` is everyone's — the same
     * shape as `aop-tx` / `aop-tx-direct`, and it fails the same way if forgotten: the
     * route is absent, the run aborts, or (worse) the app silently leaves the
     * comparison.
     */
    public function testEveryEntrantServesTheCacheControl(): void
    {
        $files = [
            'azera'       => ['apps/azera/Bootstrap.php'],
            'cakephp'     => ['apps/cakephp/src/Application.php'],
            'codeigniter' => ['apps/codeigniter/Config/Routes.php'],
            'laravel'     => ['apps/laravel/routes/web.php'],
            'spiral'      => ['apps/spiral/src/Bootloader/AppRoutesBootloader.php'],
            'symfony'     => ['apps/symfony/config/routes.yaml'],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];

        $missing = [];
        foreach ($files as $app => $paths) {
            $source = '';
            foreach ($paths as $path) {
                $source .= (string) file_get_contents(self::root() . '/' . $path);
            }
            if (!str_contains($source, '/features/aop-cache-direct')) {
                $missing[] = $app;
            }
        }

        self::assertSame(
            [],
            $missing,
            'these apps do not serve the aop-cache-direct control: ' . implode(', ', $missing)
        );
    }
}
