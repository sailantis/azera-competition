<?php

declare(strict_types=1);

namespace App\Phalcon;

use App\Phalcon\Support\DbEventLog;
use App\Phalcon\Support\MemoryLoggerAdapter;
use App\Phalcon\Support\RequestCounter;
use Phalcon\Cache\Adapter\Memory as CacheMemory;
use Phalcon\Cache\Cache;
use Phalcon\Cache\CacheInterface;
use Phalcon\Config\Config;
use Phalcon\DataMapper\Pdo\Connection;
use Phalcon\DataMapper\Query\QueryFactory;
use Phalcon\Db\Adapter\Pdo\Sqlite;
use Phalcon\Di\Di;
use Phalcon\Events\Manager as EventsManager;
use Phalcon\Html\Escaper;
use Phalcon\Http\Request;
use Phalcon\Logger\Logger;
use Phalcon\Mvc\Application;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Mvc\Model\Manager as ModelsManager;
use Phalcon\Mvc\Model\MetaData\Memory as MetadataMemory;
use Phalcon\Mvc\Router;
use Phalcon\Mvc\Url;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Phalcon\Storage\SerializerFactory;

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

    /** Namespace the dispatcher resolves controller classes in. */
    public const CONTROLLER_NAMESPACE = 'App\\Phalcon\\Controller';

    private static ?Di $di = null;

    private static ?self $instance = null;

    private static ?Application $application = null;

    /**
     * Benchmark instrumentation, NOT part of the app: a closure the A/B probe
     * sets to observe (or replace) the dispatch step from inside a real request.
     * Null on every measured path.
     *
     * @var null|callable(Di, string, array<string,string>): void
     */
    private static $dispatchHook = null;

    /**
     * Install the dispatch hook (see {@see self::$dispatchHook}). Pass null to
     * remove it. Only the probe calls this.
     *
     * @param null|callable(Di, string, array<string,string>): void $hook
     */
    public static function setDispatchHook(?callable $hook): void
    {
        self::$dispatchHook = $hook;
    }

    /**
     * Status of the last handle() — see {@see handle()} and
     * `WebAppAdapter::lastStatus()`.
     */
    private int $lastStatus = 200;

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
        $di->setShared('db', function () use ($di, $dbPath): Sqlite {
            $db = new Sqlite([
                'dbname' => $dbPath,
            ]);

            // SQLite pragma parity with every other entrant. SQLite defaults to
            // synchronous=FULL, which fsyncs the WAL on every commit; when
            // autocheckpoint fires, one commit in a few hundred pays milliseconds
            // instead of microseconds. NORMAL removes that stall. Phalcon has no
            // option key for it, so it is a PRAGMA on the adapter.
            // See tests/SqlitePragmaParityTest.php.
            $db->execute('PRAGMA journal_mode = WAL');
            $db->execute('PRAGMA synchronous = NORMAL');
            $db->execute('PRAGMA busy_timeout = 5000');

            // The adapter fires db:beforeQuery / db:afterQuery /
            // db:beginTransaction / db:commitTransaction, which is what the
            // /features/db-events demo shows. Attached here (once, on the shared
            // adapter) because Phalcon gives no per-request way to remove it —
            // see App\Phalcon\Support\DbEventLog for the measured cost.
            $events = $di->getShared('eventsManager');
            $events->attach('db', $di->getShared('dbEventLog'));
            $db->setEventsManager($events);

            return $db;
        });

        // The query-builder arm's connection. `/items-qb` exists to measure SQL
        // construction without the ORM, so it needs a builder that is not the
        // model layer: `Phalcon\DataMapper\Query` is Phalcon 6's raw builder
        // (Select/Insert/Update/Delete -> SQL + bound values).
        //
        // WHY A SECOND CONNECTION. `QueryFactory::newSelect()` type-hints the
        // concrete `DataMapper\Pdo\Connection`, so the `Decorated` wrapper —
        // the class that exists to adopt an existing PDO handle — cannot be
        // handed to it, and the Db adapter exposes no way to publish its handle
        // as that type. The alternatives were a subclass that injects
        // `$adapter->getInternalHandler()` through a protected property, or a
        // second connection. This is the second connection: it is the wiring
        // the DataMapper docs describe, and it carries the SAME pragmas so the
        // two handles see one storage configuration. WAL is a property of the
        // FILE, so both handles are in WAL mode and each sees the other's
        // commits — which is what makes the write endpoints comparable.
        //
        // Kept as the documented constructor (DSN + connection-time queries)
        // rather than a subclass because the queries run at connect time, once
        // per process, which is exactly how the adapter applies its own.
        $di->setShared('dbBuilder', function () use ($dbPath): Connection {
            return new Connection(
                'sqlite:' . $dbPath,
                null,
                null,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION],
                [
                    'PRAGMA journal_mode = WAL',
                    'PRAGMA synchronous = NORMAL',
                    'PRAGMA busy_timeout = 5000',
                ]
            );
        });

        $di->setShared('dbQueryFactory', fn(): QueryFactory => new QueryFactory());

        // MetaData memory, not the default filesystem adapter: introspection of
        // the `items` table would otherwise hit the disk on first use. It is a
        // shared service and survives cleanup() — see the note there for why
        // throwing it away per request made resident workers re-derive it.
        $di->setShared('modelsMetadata', fn(): MetadataMemory => new MetadataMemory());

        $di->setShared('modelsManager', fn(): ModelsManager => new ModelsManager());

        // The /features/ demos are built on the primitives Phalcon actually
        // ships (see Controller\FeatureController for the mapping). They are
        // shared services because that is what a resident worker holds: the
        // cache must survive between requests for the cache demo to show a hit,
        // and the events manager must be the SAME one the db adapter fires on.
        $di->setShared('cache', fn(): CacheInterface => new Cache(
            new CacheMemory(new SerializerFactory())
        ));

        $di->setShared('eventsManager', fn(): EventsManager => new EventsManager());

        // The logger writes into an in-memory adapter, so the log demo can show
        // what it logged without measuring disk I/O (see MemoryLoggerAdapter).
        $di->setShared('benchLoggerAdapter', fn(): MemoryLoggerAdapter => new MemoryLoggerAdapter('bench'));
        $di->setShared('benchLogger', fn(): Logger => new Logger('bench', [
            'memory' => $di->getShared('benchLoggerAdapter'),
        ]));

        $di->setShared('benchConfig', fn(): Config => new Config([
            'app' => [
                'name'      => 'Azera Competition',
                'page_size' => self::PAGE_SIZE,
            ],
            'db' => [
                'dsn' => 'sqlite:data/bench.sqlite',
            ],
        ]));

        $di->setShared('requestCounter', fn(): RequestCounter => new RequestCounter());

        // The db-events demos read this log, and the adapter's events manager
        // fires into it. Attached HERE, once per process, for the reason spelled
        // out in DbEventLog: setEventsManager() rejects null and detachAll()
        // leaves the manager attached, so there is no per-request way to remove
        // the hook. Measured cost of an idle manager is ~0.17 us/query.
        $di->setShared('dbEventLog', fn(): DbEventLog => new DbEventLog());

        // Volt's `|e` filter escapes through this service.
        $di->setShared('escaper', fn(): Escaper => new Escaper());

        // The framework's own view layer, in its conventional shape.
        //
        // This used to be `View\Simple`, which is the stand-alone renderer: it has
        // no layouts, no controller/action resolution, and `Application` cannot
        // auto-render through it (Simple implements `ViewBaseInterface`, not
        // `ViewInterface`, so `useImplicitView(true)` would have thrown). Actions
        // therefore called `render()` by hand and returned the string.
        //
        // `Mvc\View` is what a normal Phalcon app uses: the dispatcher's
        // controller/action names select `<controller>/<action>.volt`, the layout
        // comes from `layouts/`, and `Application::handle()` copies
        // `$view->getContent()` into the response after the action returns. One
        // consequence worth stating: the controller/action path is derived from the
        // DISPATCHER (`bench` + `listAction`), not from this directory structure, so
        // `bench/` below is the controller name, not the layout name.
        $view = new View();
        $view->setDI($di);
        $view->setViewsDir(dirname(__DIR__) . '/views/');
        $view->setLayoutsDir('layouts/');
        $view->setLayout('main');

        // The layout footer reads `locale` and `platform`, so a DEFAULT pair is set
        // when the view is built — the same values `benchViewGlobals(null, null)`
        // answers. `handle()` overwrites them per request from the shared parser
        // (see there), which is what a real request gets.
        //
        // This is not belt-and-braces: the Volt template tests render this layout
        // directly, with no request in scope, and an unset variable makes Volt emit
        // "Undefined variable" into the rendered page. Declaring the default in one
        // place — the view's own construction — keeps the layout renderable on its
        // own, which is what the tests assert, while leaving the per-request value to
        // the resolver.
        foreach (benchViewGlobals(null, null) as $key => $value) {
            $view->setVar($key, $value);
        }

        $volt = new Volt($view, $di);
        $volt->setOptions([
            'path' => dirname(__DIR__) . '/cache/',
            // The benchmark re-uses one compiled template per process, which is
            // what a warm worker serves. Compiling once is the deploy step every
            // other entrant also performs (Twig, Blade and Stempler all cache).
            'compileAlways' => false,
        ]);
        $view->registerEngines(['.volt' => $volt]);

        $di->setShared('view', fn(): View => $view);

        // The HTTP response the dispatcher's controllers write into, and the
        // one Application::handle() returns. Registered as a factory, not a
        // shared instance: a resident worker must hand each request its own
        // response, or one page's body would be appended to the previous one.
        $di->setShared('response', fn(): \Phalcon\Http\Response => new \Phalcon\Http\Response());

        // The dispatcher is the framework's own dispatch path. It resolves a
        // controller from this container by class name and calls the action
        // itself, which is why the controllers are `Phalcon\Mvc\Controller`
        // subclasses with `...Action()` methods: that is what a normal Phalcon
        // app looks like, and it keeps this entrant comparable with the six
        // that run their framework's front controller.
        $dispatcher = new Dispatcher();
        $dispatcher->setDI($di);
        // Route targets carry a fully-qualified namespace, so the dispatcher
        // must not rewrite the controller name through its camelizer: with a
        // namespace set it takes the handler name verbatim.
        $dispatcher->setDefaultNamespace(self::CONTROLLER_NAMESPACE);
        $di->setShared('dispatcher', fn(): Dispatcher => $dispatcher);

        // Router: the same 8 benchmark endpoints + 100 filler routes every other
        // app declares, so the route table sizes stay comparable.
        $router = new Router(false);
        $router->removeExtraSlashes(true);
        // The router reads the HTTP method from the `request` service, so it
        // needs the container AND a request inside it before handle() works.
        $router->setDI($di);

        // Every route target names its controller class the way a Phalcon app
        // does: a short name plus the dispatcher's `Controller` suffix inside
        // the namespace the dispatcher already holds. `_TARGET` also carries the
        // action's public name (`index`, not `indexAction`); the suffix belongs
        // to the dispatch layer, exactly as `Phalcon\Mvc\Router` documents.
        $router->addGet('/echo', self::target('bench', 'echo'));
        $router->addGet('/', self::target('bench', 'index'));

        $router->addGet('/items', self::target('bench', 'list'));
        $router->addGet('/items-fast', self::target('bench', 'listFast'));
        $router->addGet('/items/([0-9]+)', self::target('bench', 'show', 1));
        $router->addPost('/items', self::target('bench', 'create'));

        $router->addGet('/items-qb', self::target('bench', 'listQb'));
        $router->addGet('/items-qb/([0-9]+)', self::target('bench', 'showQb', 1));
        $router->addPost('/items-qb', self::target('bench', 'createQb'));

        // Routing cost in the deep 'api' bucket with a constant handler and no
        // ORM: the only endpoint whose measured time is route matching.
        $router->addGet('/api/routing/([0-9]+)', self::target('bench', 'routing', 1));
        $router->addGet('/api/items', self::target('api', 'index'));
        $router->addGet('/api/items-fast', self::target('api', 'indexFast'));
        $router->addGet('/api/items/([0-9]+)', self::target('api', 'show', 1));
        $router->addPost('/api/items', self::target('api', 'create'));

        // The /features/ endpoints are what the harness measures. Phalcon is asked
        // for all of them EXCEPT `/features/aop`: see
        // BenchmarkConfig::excludedRequests() for why that one endpoint is not a
        // race Phalcon can be timed on. It has no route here, and run-http.php
        // passes a per-app endpoint list so the harness never requests it — the
        // absence is deliberate, not a missing implementation.
        $router->addGet('/features', self::target('feature', 'index'));
        $router->addGet('/features/aop-direct', self::target('feature', 'aopDirect'));
        $router->addGet('/features/cache', self::target('feature', 'cache'));
        $router->addGet('/features/log', self::target('feature', 'log'));
        $router->addGet('/features/retry', self::target('feature', 'retry'));
        $router->addGet('/features/pipeline', self::target('feature', 'pipeline'));
        $router->addGet('/features/db-events', self::target('feature', 'dbEvents'));
        $router->addGet('/features/events', self::target('feature', 'events'));
        $router->addGet('/features/validation', self::target('feature', 'validation'));
        $router->addGet('/features/config', self::target('feature', 'config'));
        $router->addGet('/features/request-scoped', self::target('feature', 'requestScoped'));
        $router->addGet('/features/rate-limit', self::target('feature', 'rateLimit'));

        // 100 filler routes — never dispatched, they exist so the table size
        // matches the other six apps.
        for ($i = 1; $i <= 100; $i++) {
            $router->addGet("/filler/{$i}", self::target('bench', 'filler'));
        }

        // What every normal Phalcon app does: give the router a target for a
        // path that matches nothing. Without it the dispatcher's default
        // controller is `IndexController`, which does not exist here, so every
        // 404 would surface as a thrown dispatch exception instead of the
        // application's own not-found page.
        //
        // `paths` marks this as an internal target so the dispatcher does not
        // re-prefix `controller` with the namespace again (it is already
        // absolute).
        $router->notFound([
            'namespace'  => self::CONTROLLER_NAMESPACE,
            'controller' => self::target('bench', 'notFound')['controller'],
            'action'     => 'notFound',
        ]);

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
     * Handle one request by driving Phalcon's own front controller.
     *
     * This used to route and dispatch by hand — `$router->handle()`, then
     * `new $class($di, $query, $params)`, then `$instance->{$action}()` — on the
     * argument that `Phalcon\Mvc\Application` "builds a second view layer and a
     * full dispatcher graph per call". That argument does not hold:
     *
     *   - It is not a second view layer. `Application` uses whatever `view`
     *     service the container already holds; `useImplicitView(false)` simply
     *     suppresses its auto-render step, which a JSON app does not want. The
     *     controllers keep rendering through the same Volt-configured
     *     `View\Simple` they always used.
     *   - The "dispatcher graph" is the framework's dispatch path, and the other
     *     six entrants all run theirs: Symfony and Laravel through
     *     `HttpKernel::handle()`, CakePHP through `Server::run()`. Measuring
     *     Phalcon by calling its router and instantiating its controllers by hand
     *     compared a hand-rolled front controller against six real ones.
     *
     * So this is the normal Phalcon request lifecycle: `Application::handle()` →
     * router → dispatcher → controller action → response. The controllers are
     * `Phalcon\Mvc\Controller` subclasses with `...Action()` methods, which is
     * what makes the dispatcher able to resolve them at all.
     *
     * What still has to be supplied here is the SAPI state a real web request
     * would provide, because the harness dispatches synthetic requests:
     *   - `Phalcon\Http\Request::getMethod()` reads `$_SERVER['REQUEST_METHOD']`
     *     directly and there is no setter, so the superglobal is set per request.
     *     Without it every request routes as GET and POST answers 404.
     *   - The page number comes from `$this->request->getQuery('page')`, which
     *     reads `$_GET`, so the parsed query string is published there.
     *
     * `getContent()` (not `getBody()`): `Phalcon\Http\Response` names its body
     * accessor `getContent()`. The status travels separately, but the benchmark's
     * transports take a body string, so a 404 is returned as the status-shaped
     * body `response-guard.php` classifies (see `BenchController::notFoundAction`).
     */
    public function handle(string $method, string $uri): string
    {
        $di = self::di();

        // Reset first: a throw below must not publish the previous request's
        // status (see WebAppAdapter::lastStatus()).
        $this->lastStatus = 200;

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $query = [];
        parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);

        // The SAPI state a real request would carry (see the docblock).
        $_SERVER['REQUEST_METHOD'] = $method;
        $_SERVER['REQUEST_URI']    = $uri;
        $_GET                      = $query;

        // A fresh response per request: it is mutable and would otherwise carry
        // the previous request's body into this one.
        $di->removeShared('response');

        // The view is a SHARED service, and a JSON controller disables it in
        // initialize() (the documented way to say "this controller has no
        // templates"). Without this reset the disable would leak out of the
        // ApiController and into the next request's BenchController, which would
        // then serve an empty body for every HTML route. `enable()` is the
        // inverse of `disable()` and changes nothing else — `reset()` is NOT used
        // because it also clears the registered engines.
        $di->getShared('view')->enable();

        // Locale + platform for the layout footer, resolved by the SAME parser every
        // other entrant runs (`bench-view-globals.php`) and stamped per request.
        //
        // Phalcon is the one entrant with no framework slot for "shared view data":
        // `Mvc\View` has no `setVars()`/`share()` — `assign()`/`setVar()` are
        // per-render and are cleared by the render that consumes them — so a request
        // that renders has to set them itself. Doing it here, in the code path every
        // request passes, is the equivalent of the middleware the others use, and it
        // is why this app previously rendered NO footer values at all: the layout
        // had nothing to read, so the `<footer>` printed only its static half and the
        // page silently disagreed with the other six.
        $globals = benchViewGlobals(
            isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
            isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null,
        );
        $view = $di->getShared('view');
        $view->setVar('locale', $globals['locale']);
        $view->setVar('platform', $globals['platform']);

        // `Application::handle()` calls `$view->start()` (an `ob_start()`) and then
        // `$view->finish()` (`ob_end_clean()`) only when it took the rendering
        // branch. An action that RETURNS A STRING short-circuits that branch, so
        // `finish()` never runs and the buffer it opened stays on the stack. In a
        // resident worker that is one leaked buffer per string-returning request —
        // every JSON and error endpoint — and `finish()`'s `ob_end_clean()` would
        // eventually pop a buffer it does not own. Unwinding anything this request
        // added keeps the two balanced without touching the caller's buffers.
        $level = ob_get_level();

        // Bench instrumentation hook, closed unless a probe opens it: the point
        // of the A/B is to compare `Mvc\Application::handle()` against the
        // pre-2026-11-23 `router->handle()` + instantiate + call, from inside a
        // real request. Null on every measured path, so it costs one comparison.
        if (self::$dispatchHook !== null) {
            (self::$dispatchHook)($di, $path, $query);
        }

        $response = $this->application()->handle($path);

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        if (!is_object($response)) {
            // A false return means an event handler vetoed the request; nothing
            // answered, which the transport must not time as a 200.
            return '500 Application::handle() returned ' . var_export($response, true);
        }

        $body = (string) $response->getContent();

        // Keep the status the application set. The benchmark's transports read a
        // body string, so the not-found action returns the status-shaped body; but
        // a controller that sets a status without such a body (or the error
        // handler) must not be published as a 200.
        //
        // `getStatusCode()` returns NULL when nothing set one — casting that to int
        // yields 0, which is not a status at all. An unset status means "the
        // framework was happy with the default", i.e. 200.
        $status = (int) ($response->getStatusCode() ?? 200);
        if ($status >= 400 && preg_match('/^[1-5]\d{2} /', $body) !== 1) {
            $body = $status . ' ' . $body;
        }

        $this->lastStatus = $status;

        return $body;
    }

    /**
     * The status {@see handle()} produced for the request it last served.
     *
     * Reported alongside the body through `WebAppAdapter::lastStatus()`, so the
     * transport does not have to re-derive a status from the body text.
     */
    public function lastStatus(): int
    {
        return $this->lastStatus;
    }

    /**
     * The framework's front controller, built once and reused.
     *
     * `useImplicitView(false)` because every action here returns its own body;
     * the auto-render step would buffer output and try to render a `.volt` file
     * named after the action for each JSON route. The two `send*` switches are off
     * because the harness owns the transport — `header()` from inside a worker
     * would write to the wrong layer.
     */
    private function application(): Application
    {
        return self::$application ??= (static function (): Application {
            $application = new Application(self::di());
            $application
                // A normal Phalcon app lets the view render. Each HTML action sets
                // its vars and returns nothing, so Application calls
                // `$view->render(controller, action)` and copies
                // `$view->getContent()` into the response — the same division of
                // labour Twig/Blade/Stempler have in the other six apps. JSON and
                // error actions return a string instead, and Application treats
                // that verbatim as the body without touching the view.
                ->useImplicitView(true)
                ->sendHeadersOnHandleRequest(false)
                ->sendCookiesOnHandleRequest(false);

            return $application;
        })();
    }

    /**
     * Build a route target in the shape `Phalcon\Mvc\Router` expects.
     *
     * `controller` is a SHORT name and the dispatcher appends `Controller`; the
     * namespace is stated so the name is not camelized. `action` is the public
     * action name and the dispatcher appends `Action`. `'id' => 1` names the first
     * capture: the router replaces that position with the matched value and leaves
     * it in `getParams()`, which is what makes `$this->dispatcher->getParam('id')`
     * work in the controller.
     *
     * @return array<string,string|int>
     */
    private static function target(string $controller, string $action, ?int $firstParam = null): array
    {
        $target = [
            'namespace'  => self::CONTROLLER_NAMESPACE,
            'controller' => $controller,
            'action'     => $action,
        ];

        if ($firstParam !== null) {
            $target['id'] = $firstParam;
        }

        return $target;
    }

    /**
     * Per-request teardown, mirroring what a resident worker does between
     * requests: drop the models manager's identity map so nothing accumulates
     * across the run.
     *
     * `modelsMetadata` is deliberately NOT reset. It is a CACHE of the table
     * mapping (column names, types, primary key) that `MetadataMemory` derives
     * by introspecting the schema. Deriving it costs ~0.45 ms, so throwing it
     * away per request makes a resident worker re-pay that on every request:
     * measured on this VM with an interleaved A/B in one booted worker, keeping
     * it is 0.200 ms/request against 0.319 ms when reset — ~0.12 ms, 37%, of a
     * `GET /items` row.
     *
     * On php-fpm this changes nothing: every request is a fresh process, so the
     * cache is cold there whether or not `cleanup()` clears it. The saving is
     * the resident arms' alone. (An earlier revision of this comment claimed a
     * 0.504 ms php-fpm win; that figure came from a harness that built a fresh
     * DI per iteration, which models neither server, and it was wrong.)
     *
     * Every other entrant keeps model/schema knowledge warm between requests, so
     * resetting it here also made the `items` rows measure a teardown the other
     * six do not perform: azera clears request scope, Symfony terminates the
     * kernel, Spiral finalizes, CakePHP clears the session, Laravel and
     * CodeIgniter reset request state. Azera's equivalent — generated AOP proxies
     * in `data/aop/` — is likewise memoised across requests.
     *
     * The identity map DOES still go: it holds hydrated entities and is
     * request-scoped state, which is what this method exists to clear. It costs
     * ~0.03 ms to rebuild.
     */
    public function cleanup(): void
    {
        if (self::di()->has('modelsManager')) {
            self::di()->remove('modelsManager');
        }

        self::di()->setShared('modelsManager', fn(): ModelsManager => new ModelsManager());
    }
}
