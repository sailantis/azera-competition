<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Pins the hydration contract for the items endpoints.
 *
 * The benchmark's claim is that a difference between neighbouring routes is
 * attributable to one variable. For the ORM and REST-API categories that
 * variable is hydration, so the routes must agree on it in every app:
 *
 *   GET /items        hydrates      GET /items-fast       does not
 *   GET /api/items    hydrates      GET /api/items-fast   does not
 *
 * This contract was violated in a way no existing test could see. CodeIgniter's
 * Model had `$returnType = 'object'`, so `/items` AND `/items-fast` both
 * returned `stdClass`: the published CI4 `-fast` band measured the difference
 * between `findAll()` and an explicit `builder()->get()->getResultObject()`,
 * i.e. nothing, while the chart presented it as the cost of hydration. Azera's
 * `/api/items` had the same defect from the other direction —
 * `select()->toArray()` is `PDO::FETCH_ASSOC`, so it never hydrated at all and
 * was not comparable with the other five apps' API routes.
 *
 * Both escaped because the existing tests checked that a route was REGISTERED
 * and MEASURED, never that it measured the thing its name claims.
 *
 * THE ASSERTION PATTERN is deliberately one rule applied per route pair:
 *
 *   the control's non-hydrating call appears in the control method,
 *   and does NOT appear in the hydrating method.
 *
 * That is enough to guarantee the pair differs by hydration, and it works even
 * for frameworks where the hydrating route has no unique positive marker —
 * Laravel's `toBase()->get()` still ends in `->get()`, so `->get()` cannot be
 * used as a hydrating marker there. Where the hydrating call leaves no trace
 * (CodeIgniter's `findAll()` looks hydrating even when the model's
 * `$returnType` flattens it to stdClass), the model configuration is asserted
 * separately by {@see testTheCodeIgniterModelHydratesByEntityClass()}.
 */
final class HydrationContractTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function source(string $relative): string
    {
        $path = self::repoRoot() . '/' . $relative;
        self::assertFileExists($path, "{$relative} is missing");

        return (string) file_get_contents($path);
    }

    /**
     * Per-app wiring and the call that identifies each route's row shape.
     *
     * `fast` is the call that switches hydration OFF, so it must appear in the
     * control and nowhere in the route it controls. Method names differ between
     * apps (azera suffixes every action with `Action`).
     *
     * @return array<string,array{
     *     html:string, api:string,
     *     list:string, listFast:string, apiIndex:string, apiFast:string,
     *     htmlFast:string, apiFastCall:string,
     *     htmlWhat:string, apiWhat:string
     * }>
     */
    private static function apps(): array
    {
        return [
            'azera' => [
                'html' => 'apps/azera/Controllers/BenchController.php',
                'api'  => 'apps/azera/Controllers/ApiController.php',
                'list' => 'listAction', 'listFast' => 'listFastAction',
                'apiIndex' => 'indexAction', 'apiFast' => 'indexFastAction',
                'htmlFast' => '->objects()',
                'apiFastCall' => '->toArray()',
                'htmlWhat' => 'paginate()->objects() instead of ->entities()',
                'apiWhat' => 'select()->toArray() (PDO::FETCH_ASSOC) instead of ->entities()',
            ],
            'laravel' => [
                'html' => 'apps/laravel/app/Http/Controllers/BenchController.php',
                'api'  => 'apps/laravel/app/Http/Controllers/ApiController.php',
                'list' => 'list', 'listFast' => 'listFast',
                'apiIndex' => 'index', 'apiFast' => 'indexFast',
                'htmlFast' => '->toBase()',
                'apiFastCall' => '->toBase()',
                'htmlWhat' => 'toBase() instead of Eloquent get()',
                'apiWhat' => 'toBase() instead of Eloquent get()',
            ],
            'symfony' => [
                'html' => 'apps/symfony/src/Controller/BenchController.php',
                'api'  => 'apps/symfony/src/Controller/ApiController.php',
                'list' => 'list', 'listFast' => 'listFast',
                'apiIndex' => 'index', 'apiFast' => 'indexFast',
                'htmlFast' => '->getArrayResult()',
                'apiFastCall' => '->getArrayResult()',
                'htmlWhat' => 'getArrayResult() instead of the hydrating Paginator',
                'apiWhat' => 'getArrayResult() instead of getResult()',
            ],
            'spiral' => [
                'html' => 'apps/spiral/src/Controller/BenchController.php',
                'api'  => 'apps/spiral/src/Controller/ApiController.php',
                'list' => 'list', 'listFast' => 'listFast',
                'apiIndex' => 'index', 'apiFast' => 'indexFast',
                'htmlFast' => '->fetchData()',
                'apiFastCall' => '->fetchData()',
                'htmlWhat' => 'fetchData() instead of fetchAll()',
                'apiWhat' => 'fetchData() instead of fetchAll()',
            ],
            'cakephp' => [
                'html' => 'apps/cakephp/src/Controller/BenchController.php',
                'api'  => 'apps/cakephp/src/Controller/ApiController.php',
                'list' => 'list', 'listFast' => 'listFast',
                'apiIndex' => 'index', 'apiFast' => 'indexFast',
                'htmlFast' => '->disableHydration()',
                'apiFastCall' => '->disableHydration()',
                'htmlWhat' => 'disableHydration() (Cake hydrates by default)',
                'apiWhat' => 'disableHydration() (Cake hydrates by default)',
            ],
            'codeigniter' => [
                'html' => 'apps/codeigniter/Controllers/Bench.php',
                'api'  => 'apps/codeigniter/Controllers/Api.php',
                'list' => 'list', 'listFast' => 'listFast',
                'apiIndex' => 'index', 'apiFast' => 'indexFast',
                'htmlFast' => '->getResultObject()',
                'apiFastCall' => '->getResultObject()',
                'htmlWhat' => 'getResultObject() instead of Model findAll()',
                'apiWhat' => 'getResultObject() instead of Model findAll()',
            ],
        ];
    }

    public static function appProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::apps()) as $app) {
            $cases[$app] = [$app];
        }

        return $cases;
    }

    /**
     * Extracts one method body from a PHP source string.
     *
     * Brace-counted rather than regex-terminated: these controllers contain
     * nested closures and array literals, and a lazy `.*?}` stops at the first
     * one.
     */
    private static function methodBody(string $source, string $method): string
    {
        $pattern = '~function\s+' . preg_quote($method, '~') . '\s*\([^)]*\)\s*(?::[^{;]+)?\{~';
        if (preg_match($pattern, $source, $m, PREG_OFFSET_CAPTURE) !== 1) {
            throw new \RuntimeException("method {$method}() not found");
        }

        $start = $m[0][1] + \strlen($m[0][0]);
        $depth = 1;
        $len   = \strlen($source);

        for ($i = $start; $i < $len; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($source, $start, $i - $start);
                }
            }
        }

        throw new \RuntimeException("unbalanced braces in {$method}()");
    }

    /**
     * The HTML list route hydrates and its `-fast` sibling does not.
     *
     * @dataProvider appProvider
     */
    public function testTheHtmlListRouteHydratesAndTheFastRouteDoesNot(string $app): void
    {
        $cfg = self::apps()[$app];
        $src = self::source($cfg['html']);

        $this->assertPairDivergesByHydration(
            "{$app} GET /items vs GET /items-fast",
            $cfg['htmlWhat'],
            $cfg['htmlFast'],
            self::methodBody($src, $cfg['listFast']),
            self::methodBody($src, $cfg['list']),
        );
    }

    /**
     * The API list route hydrates and its `-fast` sibling does not.
     *
     * @dataProvider appProvider
     */
    public function testTheApiListRouteHydratesAndTheFastRouteDoesNot(string $app): void
    {
        $cfg = self::apps()[$app];
        $src = self::source($cfg['api']);

        $this->assertPairDivergesByHydration(
            "{$app} GET /api/items vs GET /api/items-fast",
            $cfg['apiWhat'],
            $cfg['apiFastCall'],
            self::methodBody($src, $cfg['apiFast']),
            self::methodBody($src, $cfg['apiIndex']),
        );
    }

    private function assertPairDivergesByHydration(
        string $label,
        string $what,
        string $marker,
        string $fastBody,
        string $hydratedBody,
    ): void {
        self::assertStringContainsString(
            $marker,
            $fastBody,
            "{$label}: the control does not use {$what} — it would measure hydration it never removed"
        );
        self::assertStringNotContainsString(
            $marker,
            $hydratedBody,
            "{$label}: the hydrating route ALSO uses {$what} — the pair measures nothing"
        );
    }

    /**
     * CodeIgniter's Model hydrates by entity class, not by `'object'`.
     *
     * Asserted on the MODEL, because that is where the shape is decided: both
     * controllers look correct while the model silently flattens both routes to
     * stdClass. This is the precise configuration that made CI4's `/items` and
     * `/items-fast` return identical row types, which the per-method assertions
     * above cannot detect — `findAll()` looks hydrating either way.
     */
    public function testTheCodeIgniterModelHydratesByEntityClass(): void
    {
        $model = self::source('apps/codeigniter/Models/Item.php');

        self::assertMatchesRegularExpression(
            '~\$returnType\s*=\s*[A-Za-z_\\\\]+::class~',
            $model,
            "CodeIgniter's Model must set \$returnType to an entity class; 'object' returns "
                . 'stdClass on BOTH /items and /items-fast, so the -fast band measures nothing'
        );
    }

    /**
     * The CI4 entity must not cast `created_at`, or the JSON body changes.
     *
     * CI4's Entity casts created_at/updated_at/deleted_at into I18n Time objects
     * by default. The shared items table stores created_at as plain TEXT, so the
     * cast would serialize it as
     * {"date":...,"timezone_type":3,"timezone":"UTC"} instead of the raw string
     * the other five apps emit — a body difference caused by nothing but
     * hydration, in a harness that compares bodies byte-for-byte across apps.
     */
    public function testTheCodeIgniterEntityKeepsCreatedAtAString(): void
    {
        $entity = self::source('apps/codeigniter/Entities/Item.php');

        self::assertMatchesRegularExpression(
            '~protected\s+\$dates\s*=\s*\[\s*\]~',
            $entity,
            'the CI4 entity must declare $dates = [] so created_at stays the raw TEXT value'
        );
    }

    /**
     * CI4's POST /items renders a hydrated entity, not a hand-built object.
     *
     * Its write path used to construct a literal `(object)` for the template,
     * making POST the one items endpoint that skipped hydration in every app.
     */
    public function testTheCodeIgniterWritePathRendersTheHydratedEntity(): void
    {
        $body = self::methodBody(self::source('apps/codeigniter/Controllers/Bench.php'), 'create');

        self::assertStringNotContainsString(
            '(object) [',
            str_replace(["\r", "\n", ' '], '', $body),
            'CI4 POST /items builds a literal object instead of re-reading the hydrated entity'
        );
        self::assertStringContainsString(
            '->find(',
            $body,
            'CI4 POST /items should re-read the row through the Model so the template gets an entity'
        );
    }

    /**
     * Every app registers the API control route.
     *
     * Laravel's routes file declares `/items-fast` because the file is mounted
     * under the `api` prefix (`apiPrefix: 'api'` in bootstrap/app.php), so the
     * literal path differs per app while the resulting URL does not.
     */
    public function testEveryAppRegistersTheApiFastRoute(): void
    {
        $routes = [
            'azera'       => 'apps/azera/Bootstrap.php',
            'cakephp'     => 'apps/cakephp/src/Application.php',
            'codeigniter' => 'apps/codeigniter/Config/Routes.php',
            'laravel'     => 'apps/laravel/routes/api.php',
            'spiral'      => 'apps/spiral/src/Bootloader/AppRoutesBootloader.php',
            'symfony'     => 'apps/symfony/config/routes.yaml',
        ];

        foreach ($routes as $app => $file) {
            self::assertStringContainsString(
                'items-fast',
                self::source($file),
                "{$app} does not register the /api/items-fast control route ({$file})"
            );
        }
    }

    /**
     * The API control is in the canonical request order, right after its pair.
     *
     * `/echo` and `/items-fast` had such a test; the API control did not, so it
     * could be routed and never measured — the exact failure mode
     * `/items-fast` once had, where the route existed and was reachable for
     * months while no run requested it.
     */
    public function testTheApiFastEndpointIsMeasured(): void
    {
        $order = \AzeraCompetition\Report\BenchmarkConfig::requestOrder();

        foreach (['GET /api/items', 'GET /api/items-fast'] as $request) {
            self::assertContains($request, $order, "{$request} is missing from featureMap()");
            self::assertNotSame(
                '',
                \AzeraCompetition\Report\BenchmarkConfig::workloadFor($request),
                "{$request} has no workload description"
            );
        }

        $i = array_search('GET /api/items', $order, true);
        self::assertSame(
            'GET /api/items-fast',
            $order[$i + 1] ?? null,
            'GET /api/items-fast must directly follow GET /api/items so the two rows read as a pair'
        );
    }

    /**
     * The control route is declared before the {id} placeholder.
     *
     * A literal declared after a placeholder is only reachable because the
     * placeholder happens to be constrained (`\d+`, `(:num)`). That is true in
     * all six apps today, which makes the endpoints' correctness depend on a
     * distant constraint — so the ordering is pinned rather than assumed.
     */
    public function testTheApiFastRoutePrecedesTheIdPlaceholder(): void
    {
        foreach ([
            'azera'       => 'apps/azera/Bootstrap.php',
            'cakephp'     => 'apps/cakephp/src/Application.php',
            'codeigniter' => 'apps/codeigniter/Config/Routes.php',
            'laravel'     => 'apps/laravel/routes/api.php',
            'spiral'      => 'apps/spiral/src/Bootloader/AppRoutesBootloader.php',
            'symfony'     => 'apps/symfony/config/routes.yaml',
        ] as $app => $file) {
            $code = self::withoutComments(self::source($file));

            $fast = strpos($code, 'items-fast');
            self::assertNotFalse($fast, "{$app}: the items-fast route declaration was not found");

            // Covers azera `{id:int}`, symfony/cake `{id}`, CI4 `(:num)`,
            // spiral `<id:\d+>` and Laravel's `/items/{id}` (api-prefixed file).
            $show = false;
            foreach (['/api/items/{id', '/api/items/(:num)', '/api/items/<id', '/items/{id'] as $placeholder) {
                $pos = strpos($code, $placeholder);
                if ($pos !== false) {
                    $show = $pos;
                    break;
                }
            }
            self::assertNotFalse($show, "{$app}: the items/{id} placeholder declaration was not found");
            self::assertLessThan(
                $show,
                $fast,
                "{$app}: the items-fast route is declared after the {id} placeholder"
            );
        }
    }

    /**
     * Strips comments so ordering assertions read declarations, not prose.
     *
     * These files DOCUMENT the ordering rule, so a naive `strpos` finds the
     * paths inside the very comments that explain them.
     */
    private static function withoutComments(string $source): string
    {
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);
        $out    = [];

        foreach (preg_split('~\R~', $source) ?: [] as $line) {
            $trim = ltrim($line);
            if (str_starts_with($trim, '//') || str_starts_with($trim, '*') || str_starts_with($trim, '#')) {
                continue;
            }
            $out[] = $line;
        }

        return implode("\n", $out);
    }
}
