<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `/features/aop-tx-explicit` must exercise the framework's OWN interceptor facility.
 *
 * WHY THIS EXISTS. The suite used to have a single AOP pair, `/features/aop-tx` and
 * `/features/aop-tx-direct`. The second row was described as "the same-work control",
 * but on Azera it was a hand-written `try { … } catch { rollback }` — no
 * interceptor, no `Azera\Aop\Pipeline`, nothing from the framework's explicit-AOP
 * facility at all. So Azera's SECOND real AOP mode was measured nowhere while the
 * report implied the pair priced the abstraction.
 *
 * THE RULE THIS ENCODES. A row may only exist for a facility the entrant actually
 * ships. Writing the same work by hand is a workaround: it measures the workaround,
 * not the feature, and it lets a framework "compete" in an area where it has
 * nothing to compete with. So:
 *
 *   - the endpoint must go through the real pipeline, not stand in for it;
 *   - the hand-written path may stay, but only as the cross-entrant CONTROL
 *     (`aop-tx-direct`), which every app can serve by definition;
 *   - an app without the facility does not get a row, and must not register a
 *     route that pretends to have one.
 *
 * `competesOn()` is asserted separately in `ExclusionParityGateTest`; this file is
 * about the IMPLEMENTATION behind the two participating rows.
 */
final class ExplicitAopFacilityTest extends TestCase
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
     * Azera's explicit row composes the framework Pipeline with the SAME
     * interceptor the proxy registers.
     */
    public function testAzeraExplicitRowUsesTheFrameworkPipeline(): void
    {
        $controller = self::read('apps/azera/Controllers/FeatureController.php');

        self::assertStringContainsString(
            '$ctx->pipeline([',
            $controller,
            'the explicit row must go through AppContext::pipeline(), the framework accessor'
        );
        self::assertStringContainsString(
            'new TransactionalInterceptor(',
            $controller,
            'it must compose the real TransactionalInterceptor, not hand-write the transaction'
        );

        // The composed pipeline must actually RUN. Asserted on the variable that
        // receives it, because the accessor returns a Pipeline and the call is a
        // separate statement (`$pipeline = $ctx->pipeline([...]); … $pipeline->call(…)`)
        // — matching adjacency here would only accept a shape, not the requirement.
        self::assertMatchesRegularExpression(
            '/\$(\w+)\s*=\s*\$ctx->pipeline\(/',
            $controller,
            'the explicit row must assign the framework pipeline'
        );
        preg_match('/\$(\w+)\s*=\s*\$ctx->pipeline\(/', $controller, $m);
        $var = preg_quote($m[1], '/');
        self::assertMatchesRegularExpression(
            '/\$' . $var . '->call\(/',
            $controller,
            'the assembled pipeline must be CALLED — an unused chain measures nothing'
        );

        // The wrapped method must contain no transaction handling of its own, or
        // the "advice" is decorative: the BEGIN/COMMIT would come from the body.
        $service = self::read('apps/azera/Services/FeatureService.php');
        preg_match('/public function createItemUnmanaged\(.*?\n    \}/s', $service, $um);
        self::assertNotSame([], $um ?? [], 'the wrapped body must exist as its own method');

        // Comments are stripped first: the docblock legitimately NAMES the
        // interceptor to explain the split, and prose is not a transaction call.
        $umCode = (string) preg_replace('~/\*.*?\*/~s', '', $um[0]);
        $umCode = (string) preg_replace('~^\s*//.*$~m', '', $umCode);

        self::assertStringNotContainsString(
            'begin',
            $umCode,
            'the body wrapped by the explicit pipeline must not open a transaction itself — '
                . 'otherwise the interceptor is not the thing being measured'
        );
        self::assertStringNotContainsString('commit', $umCode);
    }

    /**
     * Azera's `aop-tx-direct` row is the hand-written control, and stays so.
     *
     * Moving it onto the framework interceptor would make it a second explicit
     * row and remove the only cross-entrant control in the suite.
     */
    public function testAzeroDirectRowStaysTheHandWrittenControl(): void
    {
        $service = self::read('apps/azera/Services/FeatureService.php');

        preg_match('/public function createItemDirect\(.*?\n    \}/s', $service, $m);
        self::assertNotSame([], $m ?? []);

        self::assertStringContainsString(
            '$db->begin(true)',
            $m[0],
            'the control must open its transaction by hand'
        );

        // The docblock on this method deliberately NAMES the framework interceptor
        // to explain why it is not used, so the check runs on code only.
        $code = (string) preg_replace('~/\*.*?\*/~s', '', $m[0]);
        $code = (string) preg_replace('~^\s*//.*$~m', '', $code);

        self::assertStringNotContainsString(
            'TransactionalInterceptor',
            $code,
            'the control must not use framework interception — it is what the other six entrants do'
        );
        self::assertStringNotContainsString('pipeline', $code);

        $controller = self::read('apps/azera/Controllers/FeatureController.php');
        self::assertMatchesRegularExpression(
            '/aopDirectAction\([^)]*\)[\s\S]{0,900}?createItemDirect\(/',
            $controller,
            'the aop-tx-direct action must reach the hand-written method, not the pipeline'
        );
    }

    /**
     * Spiral's explicit row likewise uses PipelineBuilder, not a hand-written
     * transaction.
     */
    public function testSpiralExplicitRowUsesPipelineBuilder(): void
    {
        $controller = self::read('apps/spiral/src/Controller/FeatureController.php');

        self::assertStringContainsString('runThrough(', $controller);
        self::assertStringContainsString(
            'new TransactionalInterceptor(',
            $controller,
            'Spiral must compose its own TransactionalInterceptor through the platform pipeline'
        );

        $service = self::read('apps/spiral/src/Service/AopService.php');
        self::assertStringContainsString(
            'new PipelineBuilder()',
            $service,
            'the chain must be built by Spiral\Interceptors\PipelineBuilder'
        );

        // The interceptor must be a real InterceptorInterface implementation, or
        // Spiral is not supplying the machinery.
        $interceptor = self::read('apps/spiral/src/Interceptor/TransactionalInterceptor.php');
        self::assertStringContainsString(
            'implements InterceptorInterface',
            $interceptor
        );
        self::assertStringContainsString('Spiral\\Interceptors\\InterceptorInterface', $interceptor);
    }

    /**
     * An entrant without the facility must have no route pretending to have one.
     *
     * This is the other half of the user's rule: no workaround rows. The five
     * non-participants must not register `/features/aop-tx-explicit` at all — a route
     * that faked it would be measured and published under the feature's name.
     */
    public function testNonParticipantsRegisterNoExplicitAopRoute(): void
    {
        $routeFiles = [
            'laravel'     => ['apps/laravel/routes/web.php'],
            'symfony'     => ['apps/symfony/config/routes.yaml'],
            'codeigniter' => ['apps/codeigniter/Config/Routes.php'],
            'cakephp'     => ['apps/cakephp/src/Application.php'],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];

        foreach ($routeFiles as $app => $files) {
            $source = '';
            foreach ($files as $file) {
                $path = self::repoRoot() . '/' . $file;
                if (is_file($path)) {
                    $source .= (string) file_get_contents($path);
                }
            }

            // Strip comments first: these files legitimately NAME the endpoint in
            // prose to explain why it is absent (Phalcon's route block says so
            // explicitly), and a substring search would read that as a route.
            $code = (string) preg_replace('~/\*.*?\*/~s', '', $source);
            $code = (string) preg_replace('~^\s*//.*$~m', '', $code);

            self::assertStringNotContainsString(
                '/features/aop-tx-explicit',
                $code,
                "{$app} has no interceptor abstraction, so it must not register the "
                    . 'explicit-AOP route — a workaround row would be published as the feature'
            );
        }
    }

    /**
     * Every participant DOES register it.
     */
    public function testParticipantsRegisterTheRoute(): void
    {
        foreach ([
            'apps/azera/Bootstrap.php',
            'apps/spiral/src/Bootloader/AppRoutesBootloader.php',
        ] as $file) {
            self::assertStringContainsString(
                '/features/aop-tx-explicit',
                self::read($file),
                "{$file} must register the explicit-AOP endpoint it competes on"
            );
        }
    }

    /**
     * The six entrants without transparent AOP must not serve `/features/aop-tx`.
     *
     * The rule the user set: remove the feature test from the frameworks that do
     * not support it. This is the mirror of `testNonParticipantsRegisterNoExplicitAopRoute()`
     * for the OTHER AOP facility — the attribute/proxy one.
     *
     * `aop-tx-direct` is untouched by this: it is a different feature, asks "what would
     * this cost implemented by hand", needs no framework facility, and every app
     * keeps serving it.
     */
    public function testNonParticipantsDoNotServeTransparentAop(): void
    {
        $routeFiles = [
            'laravel'     => ['apps/laravel/routes/web.php'],
            'symfony'     => ['apps/symfony/config/routes.yaml'],
            'codeigniter' => ['apps/codeigniter/Config/Routes.php'],
            'spiral'      => ['apps/spiral/src/Bootloader/AppRoutesBootloader.php'],
            'cakephp'     => ['apps/cakephp/src/Application.php'],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];

        foreach ($routeFiles as $app => $files) {
            $source = '';
            foreach ($files as $file) {
                $path = self::repoRoot() . '/' . $file;
                if (is_file($path)) {
                    $source .= (string) file_get_contents($path);
                }
            }

            $code = (string) preg_replace('~/\*.*?\*/~s', '', $source);
            $code = (string) preg_replace('~^\s*//.*$~m', '', $code);
            $code = (string) preg_replace('~^\s*#.*$~m', '', $code);

            // `/features/aop-tx-direct` and `/features/aop-tx-explicit` both CONTAIN
            // `/features/aop-tx`, so the negative lookahead is what distinguishes the
            // removed route from the two that stay.
            self::assertSame(
                0,
                preg_match('~[\'"]/features/aop-tx[\'"]~', $code),
                "{$app} ships no attribute-to-advice layer, so it must not register "
                    . '`/features/aop-tx` — the row would publish hand-written code under a label '
                    . 'claiming generated interception. It keeps `/features/aop-tx-direct`.'
            );

            self::assertStringContainsString(
                '/features/aop-tx-direct',
                $source,
                "{$app} must keep the hand-written control"
            );
        }
    }

    /**
     * The cache split follows the same rule.
     *
     * `/features/cache` used to be ONE row measuring two different facilities: on
     * Azera the lookup came from a generated `#[Cache]` proxy, on the other six it
     * was hand-written controller code. Under one word, "Cache", the report ranked
     * them as if they were the same thing — the mislabelling the `aop-tx` split fixed.
     * The rows are now `aop-cache` (attribute-applied, Azera only) and
     * `aop-cache-direct` (the same work by hand, all seven).
     */
    public function testOnlyAzeraServesTheAttributeAppliedCache(): void
    {
        $routeFiles = [
            'laravel'     => ['apps/laravel/routes/web.php'],
            'symfony'     => ['apps/symfony/config/routes.yaml'],
            'spiral'      => ['apps/spiral/src/Bootloader/AppRoutesBootloader.php'],
            'codeigniter' => ['apps/codeigniter/Config/Routes.php'],
            'cakephp'     => ['apps/cakephp/src/Application.php'],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];

        foreach ($routeFiles as $app => $files) {
            $source = '';
            foreach ($files as $file) {
                $path = self::repoRoot() . '/' . $file;
                if (is_file($path)) {
                    $source .= (string) file_get_contents($path);
                }
            }

            $code = (string) preg_replace('~/\*.*?\*/~s', '', $source);
            $code = (string) preg_replace('~^\s*//.*$~m', '', $code);
            $code = (string) preg_replace('~^\s*#.*$~m', '', $code);

            self::assertSame(
                0,
                preg_match('~[\'"]/features/aop-cache[\'"]~', $code),
                "{$app} ships no attribute-to-advice layer, so it must not register "
                    . '`/features/aop-cache` — its row is the hand-written control'
            );

            self::assertStringContainsString(
                '/features/aop-cache-direct',
                $code,
                "{$app} must serve the hand-written cache control every entrant can run"
            );
        }

        $azera = self::read('apps/azera/Bootstrap.php');
        self::assertStringContainsString('/features/aop-cache', $azera);
        self::assertStringContainsString('/features/aop-cache-direct', $azera);
    }

    /**
     * Azera's `aop-cache` row really is attribute-applied, and its direct twin
     * really is hand-written.
     *
     * Same contract as the transactional pair: if the AOP route contained the
     * cache lookup, the pair would measure two hand-written rows under a label
     * claiming one of them is generated.
     */
    public function testAzeraCachePairSeparatesTheProxyFromTheHandWrittenLookup(): void
    {
        $service = self::read('apps/azera/Services/FeatureService.php');

        // The attribute is what makes it AOP: declared on the method, applied by
        // the proxy, with no cache access in the body.
        self::assertMatchesRegularExpression(
            '/#\[Cache\(ttl: 10, key: [^\]]+\)\]\s*\n\s*public function countItems\(\)/',
            $service,
            'countItems() must carry the #[Cache] attribute — that is the facility being measured'
        );

        preg_match('/public function countItems\(\).*?\n    \}/s', $service, $m);
        self::assertNotSame([], $m ?? []);
        $cacheBody = (string) preg_replace('~/\*.*?\*/~s', '', $m[0]);
        $cacheBody = (string) preg_replace('~^\s*//.*$~m', '', $cacheBody);

        self::assertStringNotContainsString(
            'cache->',
            $cacheBody,
            'the advised body must not touch the cache itself — the interceptor does that'
        );

        // The AOP action must not perform the lookup either.
        $controller = self::read('apps/azera/Controllers/FeatureController.php');

        preg_match('/public function aopCacheAction\(.*?\n    \}/s', $controller, $aop);
        self::assertNotSame([], $aop ?? []);
        $aopBody = (string) preg_replace('~/\*.*?\*/~s', '', $aop[0]);
        $aopBody = (string) preg_replace('~^\s*//.*$~m', '', $aopBody);
        self::assertStringContainsString('countItems()', $aopBody);
        self::assertStringNotContainsString(
            'cache->get',
            $aopBody,
            'the attribute-applied row must delegate the lookup, not write it'
        );

        // ...and the control must write it, with the same key and TTL.
        preg_match('/public function aopCacheDirectAction\(.*?\n    \}/s', $controller, $direct);
        self::assertNotSame([], $direct ?? []);
        self::assertStringContainsString('cache->get(FeatureService::CACHE_KEY)', $direct[0]);
        self::assertStringContainsString('cache->set(FeatureService::CACHE_KEY', $direct[0]);
        self::assertStringContainsString('countItemsQuery()', $direct[0]);

        // Shared key: the pair reads and writes the SAME entry, which is what makes
        // it same-work rather than two demos that happen to look alike.
        self::assertStringContainsString('CACHE_KEY', $service);
        self::assertMatchesRegularExpression(
            '/public const CACHE_KEY = /',
            $service,
            'the cache key must be a shared constant, or the pair is not interchangeable'
        );
    }
}
