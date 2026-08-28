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

        // --- Filler routes (route-table size parity with azera app) ------
        foreach (\range(1, 100) as $i) {
            $routes->add("filler:{$i}", "/filler/{$i}")
                ->action(BenchController::class, 'filler')
                ->methods('GET');
        }
    }
}