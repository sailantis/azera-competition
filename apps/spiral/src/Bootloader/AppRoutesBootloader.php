<?php

declare(strict_types=1);

/**
 * All benchmark routes, registered through Spiral's RoutingConfigurator
 * (the idiomatic RoutesBootloader extension point).
 *
 * Route parity with the azera app: same paths, same methods, ~100 filler
 * routes so both frameworks match a comparable route table size.
 *
 * NOTE: Spiral resolves controller actions via ReflectionMethod — the route
 * action name must equal the method name exactly (no "Action" suffix).
 */

namespace App\Spiral\Bootloader;

use App\Spiral\Controller\ApiController;
use App\Spiral\Controller\BenchController;
use App\Spiral\Controller\FeatureController;
use Spiral\Bootloader\Http\RoutesBootloader;
use Spiral\Router\Loader\Configurator\RoutingConfigurator;

final class AppRoutesBootloader extends RoutesBootloader
{
    protected function globalMiddleware(): array
    {
        return [];
    }

    protected function middlewareGroups(): array
    {
        return [];
    }

    /**
     * Bind Slugify as a container SINGLETON.
     *
     * This is not cosmetic: it is the difference between a RoadRunner worker
     * recycle costing ~15 ms and ~5 ms.
     *
     * `RouteGroup::register()` (vendor: Spiral\Router\RouteGroup) runs once per
     * route on EVERY re-boot and calls `$factory->make(UriHandler::class)` for
     * each route. `UriHandler::__construct()` takes
     * `?SlugifyInterface $slugify = null` and then does
     * `$slugify ??= new Slugify();` — so with nothing bound, autowiring passes
     * null and every route builds its OWN `Cocur\Slugify\Slugify`, whose
     * constructor loads ~20 language rulesets for a total of ~0.13 ms.
     *
     * With 121 routes that is 121 x 0.13 ms ~ 16 ms per recycle, measured
     * (2026-09-17) to scale linearly with the route table:
     *
     *     31 routes 11.1 ms | 121 routes 25.7 ms | 221 routes 43.2 ms
     *
     * Binding the interface — explicitly supported by UriHandler's constructor
     * — reuses one instance instead, flattening that slope:
     *
     *     46 routes  7.9 ms | 121 routes 12.2 ms | 221 routes 20.0 ms
     *
     * A real Spiral application pays the unbound cost too; it is a property of
     * the router, not of this benchmark app. Binding it removes an artefact
     * that would otherwise be charged to "Spiral is slow at warm start".
     *
     * @return array<class-string, class-string|array{0: class-string, 1: string}>
     */
    public function defineSingletons(): array
    {
        return [
            \Cocur\Slugify\SlugifyInterface::class => [self::class, 'initSlugify'],
        ];
    }

    public static function initSlugify(): \Cocur\Slugify\SlugifyInterface
    {
        return new \Cocur\Slugify\Slugify();
    }

    protected function defineRoutes(RoutingConfigurator $routes): void
    {
        // --- Core benchmark endpoints -----------------------------------
        $routes->add('home', '/')
            ->action(BenchController::class, 'index')
            ->methods('GET');

        $routes->add('items:list', '/items')
            ->action(BenchController::class, 'list')
            ->methods('GET');
        $routes->add('items:show', '/items/<id:\d+>')
            ->action(BenchController::class, 'show')
            ->methods('GET');
        $routes->add('items:create', '/items')
            ->action(BenchController::class, 'create')
            ->methods('POST');

        // --- Query builder variants (no ORM hydration) ------------------
        $routes->add('items-qb:list', '/items-qb')
            ->action(BenchController::class, 'listQb')
            ->methods('GET');
        $routes->add('items-qb:show', '/items-qb/<id:\d+>')
            ->action(BenchController::class, 'showQb')
            ->methods('GET');
        $routes->add('items-qb:create', '/items-qb')
            ->action(BenchController::class, 'createQb')
            ->methods('POST');

        // --- REST API ----------------------------------------------------
        $routes->add('api:items:index', '/api/items')
            ->action(ApiController::class, 'index')
            ->methods('GET');
        $routes->add('api:items:show', '/api/items/<id:\d+>')
            ->action(ApiController::class, 'show')
            ->methods('GET');
        $routes->add('api:items:create', '/api/items')
            ->action(ApiController::class, 'create')
            ->methods('POST');

        // --- Feature demo endpoints --------------------------------------
        $routes->add('features:index', '/features')
            ->action(FeatureController::class, 'index')
            ->methods('GET');
        $routes->add('feature:aop', '/features/aop')
            ->action(FeatureController::class, 'aop')
            ->methods('GET');
        $routes->add('feature:cache', '/features/cache')
            ->action(FeatureController::class, 'cache')
            ->methods('GET');
        $routes->add('feature:log', '/features/log')
            ->action(FeatureController::class, 'log')
            ->methods('GET');
        $routes->add('feature:retry', '/features/retry')
            ->action(FeatureController::class, 'retry')
            ->methods('GET');
        $routes->add('feature:pipeline', '/features/pipeline')
            ->action(FeatureController::class, 'pipeline')
            ->methods('GET');
        $routes->add('feature:events', '/features/events')
            ->action(FeatureController::class, 'events')
            ->methods('GET');
        $routes->add('feature:validation', '/features/validation')
            ->action(FeatureController::class, 'validation')
            ->methods('GET');
        $routes->add('feature:config', '/features/config')
            ->action(FeatureController::class, 'config')
            ->methods('GET');
        $routes->add('feature:db-events', '/features/db-events')
            ->action(FeatureController::class, 'dbEvents')
            ->methods('GET');
        $routes->add('feature:request-scoped', '/features/request-scoped')
            ->action(FeatureController::class, 'requestScoped')
            ->methods('GET');
        $routes->add('feature:rate-limit', '/features/rate-limit')
            ->action(FeatureController::class, 'rateLimit')
            ->methods('GET');

        // --- Filler routes (route-table size parity with azera app) ------
        foreach (\range(1, 100) as $i) {
            $routes->add("filler:{$i}", "/filler/{$i}")
                ->action(BenchController::class, 'filler')
                ->methods('GET');
        }
    }
}