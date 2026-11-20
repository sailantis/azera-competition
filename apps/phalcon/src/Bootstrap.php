<?php

declare(strict_types=1);

namespace App\Phalcon;

use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Di\Di;
use Phalcon\Html\Escaper;
use Phalcon\Http\Request;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\Model\Metadata\Memory as MetadataMemory;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Mvc\View\Simple as SimpleView;

/**
 * Boots the Phalcon 6 benchmark app.
 *
 * Phalcon 6 is pure PHP (no extension), so this is a plain composer package —
 * there is no `phpize`, no `extension=` line and no PECL build step. That is
 * the whole reason this entrant is comparable at all: the v5 line needed a C
 * extension compiled against the exact PHP the benchmark measures.
 *
 * The container is built once per process; `dispatch()` re-uses it, which is
 * what a warm worker does. `cleanup()` resets the request-scoped state the
 * models manager and the DI hold, the same teardown every other app performs
 * (see WebAppAdapter::cleanup).
 */
final class Bootstrap
{
    public const PAGE_SIZE = 20;
    public const SENTINEL_ORM_ID = 999999;
    public const SENTINEL_QB_ID = 999997;

    private static ?Di $di = null;

    private static ?self $instance = null;

    public function __construct(private readonly string $dbPath) {}

    /**
     * Build (or rebuild) the container and the router.
     *
     * Rebuildable on purpose: the RoadRunner/Volucer workers and the in-process
     * harness all call bootstrap() again after cleanup(), so a boot that could
     * only run once would measure a different code path than the other apps.
     */
    public static function boot(string $dbPath): self
    {
        $self = new self($dbPath);

        $di = new Di();

        // Every closure below is a PLAIN closure, not a static one: Phalcon's
        // container binds a service closure to ITSELF before calling it, so
        // `$this` inside is the Di, not this class. Captured values therefore
        // come in through `use`, and a static closure is a deprecation here
        // (fatal in PHP 9).
        $di->setShared('db', fn(): Sqlite => new Sqlite([
            'dbname' => $dbPath,
        ]));

        // Metadata memory, not the default filesystem adapter: introspection of
        // the `items` table would otherwise hit the disk on every model call.
        // Every other app caches its mapping in-process too.
        $di->setShared('modelsMetadata', fn(): MetadataMemory => new MetadataMemory());

        $di->setShared('modelsManager', fn(): ModelsManager => new ModelsManager());

        // Volt's `|e` filter escapes through this service.
        $di->setShared('escaper', fn(): Escaper => new Escaper());

        $view = new SimpleView();
        $view->setDI($di);
        $view->setViewsDir(dirname(__DIR__) . '/views/bench/');

        $volt = new Volt($view, $di);
        $volt->setOptions([
            'path' => dirname(__DIR__) . '/cache/',
            // The benchmark re-uses one compiled template per process, which is
            // what a warm worker serves. Compiling once is the deploy step every
            // other entrant also performs (Twig, Blade and Stempler all cache).
            'compileAlways' => false,
        ]);
        $view->registerEngines(['.volt' => $volt]);

        $di->setShared('view', fn(): SimpleView => $view);

        // Router: the same 8 benchmark endpoints + 100 filler routes every other
        // app declares, so the route table sizes stay comparable.
        $router = new Router(false);
        $router->removeExtraSlashes(true);
        // The router reads the HTTP method from the `request` service, so it
        // needs the container AND a request inside it before handle() works.
        $router->setDI($di);

        $router->addGet('/echo', ['controller' => 'bench', 'action' => 'echo']);
        $router->addGet('/routing/([0-9]+)', ['controller' => 'bench', 'action' => 'routing', 'id' => 1]);
        $router->addGet('/', ['controller' => 'bench', 'action' => 'index']);

        $router->addGet('/items', ['controller' => 'bench', 'action' => 'list']);
        $router->addGet('/items-fast', ['controller' => 'bench', 'action' => 'listFast']);
        $router->addGet('/items/([0-9]+)', ['controller' => 'bench', 'action' => 'show', 'id' => 1]);
        $router->addPost('/items', ['controller' => 'bench', 'action' => 'create']);

        $router->addGet('/items-qb', ['controller' => 'bench', 'action' => 'listQb']);
        $router->addGet('/items-qb/([0-9]+)', ['controller' => 'bench', 'action' => 'showQb', 'id' => 1]);
        $router->addPost('/items-qb', ['controller' => 'bench', 'action' => 'createQb']);

        $router->addGet('/api/items', ['controller' => 'api', 'action' => 'index']);
        $router->addGet('/api/items-fast', ['controller' => 'api', 'action' => 'indexFast']);
        $router->addGet('/api/items/([0-9]+)', ['controller' => 'api', 'action' => 'show', 'id' => 1]);
        $router->addPost('/api/items', ['controller' => 'api', 'action' => 'create']);

        // 100 filler routes — never dispatched, they exist so the table size
        // matches the other six apps.
        for ($i = 1; $i <= 100; $i++) {
            $router->addGet("/filler/{$i}", ['controller' => 'bench', 'action' => 'filler']);
        }

        $di->setShared('router', fn(): Router => $router);

        $di->setShared('url', fn(): Url => new Url());

        // The router resolves the request method through this service; without
        // it, handle() throws RequestServiceUnavailable.
        $request = new Request();
        $request->setDI($di);
        $di->setShared('request', fn(): Request => $request);

        self::$di = $di;
        self::$instance = $self;

        Di::setDefault($di);

        return $self;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Bootstrap::boot() has not run');
        }

        return self::$instance;
    }

    public static function di(): Di
    {
        if (self::$di === null) {
            throw new \RuntimeException('Bootstrap::boot() has not run');
        }

        return self::$di;
    }

    /**
     * Handle one request, returning the rendered body.
     *
     * Deliberately does NOT use Phalcon\Mvc\Application: that class builds a
     * second view layer and a full dispatcher graph per call. The benchmark
     * measures routing + dispatch + query + render, and every other adapter
     * reaches its framework's equivalent primitives directly for the same
     * reason (Symfony's HttpKernel, Spiral's RouterInterface, Cake's Application
     * is the exception — it *is* the primitive there).
     */
    public function handle(string $method, string $uri): string
    {
        $di = self::di();

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);

        /** @var Router $router */
        $router = $di->getShared('router');

        // The router filters candidates by the method the REQUEST reports, and
        // Phalcon\Http\Request::getMethod() reads $_SERVER['REQUEST_METHOD']
        // directly — there is no setter. Without this every dispatch routes as
        // GET and POST answers 404.
        $_SERVER['REQUEST_METHOD'] = $method;

        $router->handle($path);

        $matched = $router->getMatchedRoute();

        if ($matched === null) {
            return '404 Not Found';
        }

        // Phalcon's router does not enforce HTTP methods on handle(); it records
        // the method each route accepts. Check it so a POST to a GET route is a
        // 404 rather than a silent success.
        $allowed = $router->getMatchedRoute()->getHttpMethods();
        if ($allowed !== null && !in_array($method, (array) $allowed, true)) {
            return '404 Not Found';
        }

        // Controller/action come from the matched route's PATHS, not from
        // getParams(): Phalcon treats the keys it recognises as dispatcher
        // targets (`controller`, `action`, `namespace`, ...) as path defaults
        // and keeps only the captures in getParams().
        $paths = $matched->getPaths();

        $controller = strtolower((string) ($paths['controller'] ?? 'bench'));
        $action     = (string) ($paths['action'] ?? 'index');
        $params     = $router->getParams();

        $class = $controller === 'api'
            ? \App\Phalcon\Controller\ApiController::class
            : \App\Phalcon\Controller\BenchController::class;

        $instance = new $class($di, $query, $params);

        return $instance->{$action}();
    }

    /**
     * Per-request teardown, mirroring what a resident worker does between
     * requests: drop the models manager's identity map and the view's state so
     * nothing accumulates across the run.
     */
    public function cleanup(): void
    {
        foreach (['modelsManager', 'modelsMetadata'] as $service) {
            if (self::di()->has($service)) {
                self::di()->remove($service);
            }
        }

        self::di()->setShared('modelsManager', fn(): ModelsManager => new ModelsManager());
        self::di()->setShared('modelsMetadata', fn(): MetadataMemory => new MetadataMemory());
    }
}
