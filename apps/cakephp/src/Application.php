<?php

declare(strict_types=1);

/**
 * CakePHP 5 benchmark application.
 *
 * A real full-stack Cake app: BaseApplication + RoutingMiddleware +
 * ControllerFactory (idiomatic controllers, templates, Table ORM), wired
 * against the shared SQLite database — the same structure a stock Cake 5
 * app ships, so boot/dispatch costs reflect what Cake users actually pay.
 */

namespace App\Cake;

use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\RouteBuilder;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\Router;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class Application extends BaseApplication
{
    /**
     * Stock Cake loads routes once per process (FPM).  RoutingMiddleware has
     * no internal guard in worker mode, so gate the routes() hook here to
     * keep the static RouteCollection from growing on every dispatch.
     */
    private bool $routesLoaded = false;

    /**
     * Worker-mode definitions slot.  BaseApplication::handle() calls
     * $container->add() on every request; League Container's add() APPENDS a
     * new Definition per call (overwrite is opt-in), so the definitions array
     * — and one retained request object graph with it — grows per request.
     * We register the two entries once and then swap their concrete values
     * per request, keeping the definitions array at a fixed size.
     */
    private ?\Cake\Core\ContainerInterface $handleContainer = null;
    private object|false $requestDefinition = false;
    private object|false $containerDefinition = false;

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // Own middleware only (no ErrorHandler — errors become adapter
        // 500s like the other apps; no Session — nothing needs it).
        $middlewareQueue->add(new \Cake\Routing\Middleware\RoutingMiddleware($this));

        return $middlewareQueue;
    }

    public function bootstrap(): void
    {
        // Configure::initialize equivalent: no Configure state needed.
    }

    public function routes(RouteBuilder $routes): void
    {
        if ($this->routesLoaded) {
            return;
        }
        $this->routesLoaded = true;

        // DashedRoute is the stock Cake 5 default route class.
        $routes->setRouteClass(DashedRoute::class);

        $routes->scope('/', function (RouteBuilder $builder): void {
            // --- Core benchmark endpoints ---
            $builder->connect('/', ['controller' => 'Bench', 'action' => 'index'])
                ->setMethods(['GET']);
            $builder->connect('/items', ['controller' => 'Bench', 'action' => 'list'])
                ->setMethods(['GET']);
            $builder->connect('/items/{id}', ['controller' => 'Bench', 'action' => 'show'])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id'])
                ->setMethods(['GET']);
            $builder->connect('/items', ['controller' => 'Bench', 'action' => 'create'])
                ->setMethods(['POST']);

            // --- Query builder variants ---
            $builder->connect('/items-qb', ['controller' => 'Bench', 'action' => 'listQb'])
                ->setMethods(['GET']);
            $builder->connect('/items-qb/{id}', ['controller' => 'Bench', 'action' => 'showQb'])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id'])
                ->setMethods(['GET']);
            $builder->connect('/items-qb', ['controller' => 'Bench', 'action' => 'createQb'])
                ->setMethods(['POST']);

            // --- REST API ---
            $builder->connect('/api/items', ['controller' => 'Api', 'action' => 'index'])
                ->setMethods(['GET']);
            $builder->connect('/api/items/{id}', ['controller' => 'Api', 'action' => 'show'])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id'])
                ->setMethods(['GET']);
            $builder->connect('/api/items', ['controller' => 'Api', 'action' => 'create'])
                ->setMethods(['POST']);

            // --- Feature demo endpoints (GET) ---
            $builder->connect('/features', ['controller' => 'Feature', 'action' => 'index'])
                ->setMethods(['GET']);
            $builder->connect('/features/aop', ['controller' => 'Feature', 'action' => 'aop'])
                ->setMethods(['GET']);
            $builder->connect('/features/cache', ['controller' => 'Feature', 'action' => 'cache'])
                ->setMethods(['GET']);
            $builder->connect('/features/log', ['controller' => 'Feature', 'action' => 'logDemo'])
                ->setMethods(['GET']);
            $builder->connect('/features/retry', ['controller' => 'Feature', 'action' => 'retry']);
            $builder->connect('/features/pipeline', ['controller' => 'Feature', 'action' => 'pipeline']);
            $builder->connect('/features/db-events', ['controller' => 'Feature', 'action' => 'dbEvents']);
            $builder->connect('/features/events', ['controller' => 'Feature', 'action' => 'events']);
            $builder->connect('/features/validation', ['controller' => 'Feature', 'action' => 'validation']);
            $builder->connect('/features/config', ['controller' => 'Feature', 'action' => 'config']);
            $builder->connect('/features/request-scoped', ['controller' => 'Feature', 'action' => 'requestScoped']);
            $builder->connect('/features/rate-limit', ['controller' => 'Feature', 'action' => 'rateLimit']);

            // --- Filler routes (route-table size parity) ---
            for ($i = 1; $i <= 100; $i++) {
                $builder->connect("/filler/{$i}", ['controller' => 'Bench', 'action' => 'filler', 'n' => (string) $i]);
            }
        });
    }

    /**
     * Stock handle() appends two Definitions per request (leak in worker
     * mode — see property docs above).  Register once, then mutate the
     * concrete value per request.
     */
    public function handle(
        ServerRequestInterface $request,
    ): ResponseInterface {
        $container = $this->getContainer();

        if (
            $this->requestDefinition === false
            || $container !== $this->handleContainer
        ) {
            $this->requestDefinition   = $container->add(\Cake\Http\ServerRequest::class, $request);
            $this->containerDefinition = $container->add(
                \Psr\Container\ContainerInterface::class,
                $container,
            );
            $this->handleContainer = $container;
        } else {
            $this->requestDefinition->setConcrete($request);
            $this->containerDefinition->setConcrete($container);
        }

        $this->controllerFactory ??= new BenchControllerFactory($container);

        if (Router::getRequest() !== $request) {
            assert($request instanceof \Cake\Http\ServerRequest);
            Router::setRequest($request);
        }

        $controller = $this->controllerFactory->create($request);

        return $this->controllerFactory->invoke($controller);
    }
}