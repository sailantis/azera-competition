<?php

declare(strict_types=1);

/**
 * CakePHP 5 benchmark application.
 *
 * A real full-stack Cake app: BaseApplication + RoutingMiddleware +
 * ControllerFactory (idiomatic controllers, templates, Table ORM), wired
 * against the shared SQLite database ??? the same structure a stock Cake 5
 * app ships, so boot/dispatch costs reflect what Cake users actually pay.
 */

namespace App\Cake;

use Cake\Core\Configure;
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
     * ??? and one retained request object graph with it ??? grows per request.
     * We register the two entries once and then swap their concrete values
     * per request, keeping the definitions array at a fixed size.
     */
    private ?\Cake\Core\ContainerInterface $handleContainer = null;
    private object|false $requestDefinition = false;
    private object|false $containerDefinition = false;

    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        // ErrorHandlerMiddleware FIRST ??? it must wrap everything below to catch
        // what they throw. A stock Cake app ships it for the same reason:
        // without it a route miss leaves Server::run() as a
        // MissingRouteException, and the adapter could only report the class
        // name as a 500 body. The exception already carries the right status
        // (MissingRouteException::$_defaultCode = 404), so Cake renders its own
        // 404 page and the transport gets a real status.
        //
        // `exceptionRenderer` is pinned to WebExceptionRenderer because Cake
        // chooses by SAPI (`ExceptionTrap::chooseRenderer()`: `PHP_SAPI === 'cli'`
        // ??? ConsoleExceptionRenderer). The resident arms are a web app running
        // inside a CLI process, so the heuristic picked the console renderer,
        // whose output is a string ??? which the middleware then republishes as a
        // hard 500 ("An Internal Server Error Occurred") instead of the 404 the
        // exception carried. A real FPM request gets the web renderer anyway, so
        // pinning it makes the two arms agree on the thing being measured.
        $middlewareQueue->add(new \Cake\Error\Middleware\ErrorHandlerMiddleware([
            'exceptionRenderer' => \Cake\Error\Renderer\WebExceptionRenderer::class,
        ]));

        // RoutingMiddleware resolves the request into a controller + action, and
        // is what invokes the routes() hook (see the routesLoaded guard above).
        $middlewareQueue->add(new \Cake\Routing\Middleware\RoutingMiddleware($this));

        // The lifecycle row's hook, appended LAST so it sits closest to the controller
        // and therefore sees the final response. It acts only on
        // Feature::lifecycleHooks and passes everything else through untouched ??? a
        // global hook that rewrote the status of every response would change what all
        // 26 benchmark rows measure. See the class docblock.
        $middlewareQueue->add(new \App\Cake\Middleware\LifecycleHookMiddleware());

        return $middlewareQueue;
    }

    public function bootstrap(): void
    {
        // Register a log engine, because Cake ships NONE by default.
        //
        // `Log::write()` iterates `LogEngineRegistry`; with an empty registry it
        // returns `false` and the message is dropped. Nothing in this app called
        // `Log::setConfig()`, so `/features/log` was writing into the void and
        // then reporting a hand-built array as if the logger had produced it ????????
        // the row measured array construction, not logging.
        //
        // `ArrayLog` is Cake's own in-memory engine (its docblock names this exact
        // use case). The subclass keeps `level`/`message`/`context` instead of
        // `ArrayLog::read()`'s formatted strings, so the payload matches the
        // triple every other entrant reports. No `scopes` restriction is set: an
        // engine scoped to a channel would silently ignore an unscoped write,
        // which is the same silent-drop failure in a different disguise.
        if (Configure::read('Bench.featureLog') === null) {
            \Cake\Log\Log::setConfig('bench_feature', [
                'className' => \App\Cake\Support\FeatureLogEngine::class,
                'levels'    => [],
                'scopes'    => [],
            ]);
            Configure::write('Bench.featureLog', 'bench_feature');
        }
    }

    /**
     * The engine registered by bootstrap().
     *
     * `Log::engine()` returns the live engine instance, so `/features/log` reads
     * back what the logger itself captured rather than re-describing the call.
     */
    public static function featureLogEngine(): ?\App\Cake\Support\FeatureLogEngine
    {
        $name = Configure::read('Bench.featureLog');
        if (!is_string($name)) {
            return null;
        }

        $engine = \Cake\Log\Log::engine($name);

        return $engine instanceof \App\Cake\Support\FeatureLogEngine ? $engine : null;
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
            $builder->connect('/echo', ['controller' => 'Bench', 'action' => 'echo'])
                ->setMethods(['GET']);
            $builder->connect('/', ['controller' => 'Bench', 'action' => 'index'])
                ->setMethods(['GET']);
            $builder->connect('/items', ['controller' => 'Bench', 'action' => 'list'])
                ->setMethods(['GET']);
            // GET /items with hydration off: the single-variable control for /items
            // (see BenchController::listFast()). Declared before /items/{id} so the
            // literal segment is not swallowed by the id pattern.
            $builder->connect('/items-fast', ['controller' => 'Bench', 'action' => 'listFast'])
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
            // Routing cost in the deep 'api' bucket with a constant handler and
            // no ORM: the only endpoint whose measured time is route matching.
            // Declared before /api/items/{id} so the literal segment is matched
            // before the placeholder. See BenchController::routing().
            $builder->connect('/api/routing/{id}', ['controller' => 'Bench', 'action' => 'routing'])
                ->setMethods(['GET'])
                ->setPatterns(['id' => '\d+'])
                ->setPass(['id']);
            $builder->connect('/api/items', ['controller' => 'Api', 'action' => 'index'])
                ->setMethods(['GET']);
            // Declared before /api/items/{id} so the literal segment is matched
            // before the placeholder (whose pattern is \d+ anyway, but the
            // ordering makes the precedence explicit rather than incidental).
            $builder->connect('/api/items-fast', ['controller' => 'Api', 'action' => 'indexFast'])
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
            $builder->connect('/features/aop-tx-direct', ['controller' => 'Feature', 'action' => 'aopDirect'])
                ->setMethods(['GET']);
            $builder->connect('/features/aop-cache-direct', ['controller' => 'Feature', 'action' => 'aopCacheDirect'])
                ->setMethods(['GET']);
            $builder->connect('/features/log', ['controller' => 'Feature', 'action' => 'logDemo'])
                ->setMethods(['GET']);
            $builder->connect('/features/db-events', ['controller' => 'Feature', 'action' => 'dbEvents']);
            $builder->connect('/features/events', ['controller' => 'Feature', 'action' => 'events']);
            $builder->connect('/features/validation', ['controller' => 'Feature', 'action' => 'validation']);
            $builder->connect('/features/config', ['controller' => 'Feature', 'action' => 'config']);
            $builder->connect('/features/rate-limit', ['controller' => 'Feature', 'action' => 'rateLimit']);
            // The lifecycle row. Cake's middleware queue is global, so the hook scopes
            // itself to this controller/action pair (see
            // App\Cake\Middleware\LifecycleHookMiddleware).
            $builder->connect('/features/lifecycle-hooks', ['controller' => 'Feature', 'action' => 'lifecycleHooks']);

            // --- Filler routes (route-table size parity) ---
            for ($i = 1; $i <= 100; $i++) {
                $builder->connect("/filler/{$i}", ['controller' => 'Bench', 'action' => 'filler', 'n' => (string) $i]);
            }
        });
    }

    /**
     * Stock handle() appends two Definitions per request (leak in worker
     * mode ??? see property docs above).  Register once, then mutate the
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
