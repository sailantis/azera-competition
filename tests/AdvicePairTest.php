<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `GET /features/aop-direct` must stay the SAME WORK as `GET /features/aop`.
 *
 * WHY THIS EXISTS. The pair is the only way to price transparent interception:
 * `/features/aop` runs the transaction through generated advice, `/features/aop-direct`
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

    /**
     * Both Azera methods do the same write and the same dispatch.
     *
     * The transaction handling is deliberately NOT compared: supplying it is the
     * one variable under test, so on the advised side it comes from
     * `#[Transactional]` and on the direct side from explicit `begin(true)` /
     * `commit()` / `rollback()`.
     */
    public function testAzeraTwinMethodsPerformTheSameWork(): void
    {
        [$advised, $direct] = self::azeraBodies();

        self::assertNotSame('', $advised, 'could not read createItemTransactional()');
        self::assertNotSame('', $direct, 'could not read createItemDirect()');

        foreach ([$advised, $direct] as $body) {
            self::assertStringContainsString(
                'ON CONFLICT(id) DO UPDATE SET title = excluded.title',
                $body,
                'both twins must run the same upsert'
            );
            self::assertStringContainsString(
                'events()->dispatch(new \App\Azera\Events\ItemCreated',
                $body,
                'both twins must dispatch the same event — otherwise the advised row pays for a '
                    . 'listener the direct row does not, and the difference is not proxy cost'
            );
            self::assertStringContainsString('self::FEATURE_SENTINEL_ID', $body);
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
            if (!str_contains($source, '/features/aop-direct')) {
                $missing[] = $app;
            }
        }

        self::assertSame([], $missing, 'these apps do not serve the aop-direct control: ' . implode(', ', $missing));
    }

    /**
     * The control is measured, and lands in the same feature as its twin.
     *
     * If it drifted into another feature the two rows would be charted apart and
     * the comparison the pair exists for would be invisible.
     */
    public function testTheControlIsMeasuredAndGroupedWithItsTwin(): void
    {
        require_once dirname(__DIR__) . '/scripts/report/BenchmarkConfig.php';

        $map = \AzeraCompetition\Report\BenchmarkConfig::featureMap();

        self::assertArrayHasKey('GET /features/aop-direct', $map, 'the control must be measured');
        self::assertSame(
            $map['GET /features/aop'] ?? null,
            $map['GET /features/aop-direct'],
            'the control must sit in the same feature as its advised twin'
        );

        $canonical = (string) file_get_contents(self::root() . '/scripts/bench-lib.php');
        self::assertStringContainsString(
            "'GET /features/aop-direct'",
            $canonical,
            'the control must be in the canonical request list or the harness never asks for it'
        );
    }
}
