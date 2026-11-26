<?php
/**
 * Azera benchmark bootstrap.
 *
 * Wires AppContext with SQLite, ClarityEngine views, and the 4 benchmark
 * routes.  Called by AzeraAdapter::bootstrap().
 */

namespace App\Azera;

use App\Azera\Controllers\BenchController;
use Azera\Aop\Cache as CacheAdvice;
use Azera\Aop\CacheInterceptor;
use Azera\Aop\Transactional;
use Azera\Aop\TransactionalInterceptor;
use Azera\AppContext;
use Azera\Cache\ArrayCache;
use Azera\Core\Engines\ClarityEngine;
use Azera\Core\ViewEngine;
use Azera\Db\Database;
use Azera\Event\EventDispatcher;
use Azera\Queue\QueueInterface;
use Azera\Queue\SyncQueue;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class Bootstrap
{
    /**
     * Wiring memo — static so it survives the fresh AppContext a
     * worker-mode re-bootstrap would otherwise create. The route table is
     * static data (like CI4's RouteCollection service): re-registering
     * ~120 routes on every warm recycle cost ~0.38 ms of pure rebuild.
     * Keyed by dbPath so separate contexts (different databases) never
     * share a table.
     *
     * @var array<string, AppContext>
     */
    private static array $wired = [];

    /**
     * Forget the wiring memo — the next boot() rebuilds everything (fresh
     * AppContext, route table, services). verify.php/tests call this to get
     * true cold isolation; the benchmark's cold mode runs each app in its
     * own child process, where the memo is empty anyway.
     */
    public static function forget(): void
    {
        self::$wired = [];
        AppContext::reset();
    }

    public static function boot(string $dbPath): AppContext
    {
        $ctx = self::$wired[$dbPath] ?? null;

        if ($ctx !== null) {
            // Worker-mode re-bootstrap: the singleton AppContext already
            // carries the fully wired route table, dispatcher and services.
            // Request-scoped state was reset by clearRequestScope() after
            // the previous request (the RequestScoped contract) — nothing
            // to rebuild here beyond handing back the same context.
            AppContext::setInstance($ctx);
            return $ctx;
        }

        // Fresh singleton for cold-mode reproducibility
        AppContext::setInstance(new AppContext());
        $ctx = AppContext::instance();

        // --- Database (SQLite) ---
        // Use a factory so we can configure PRAGMAs right after connecting.
        $ctx->dbManager()->set('default', function () use ($dbPath) {
            $db = new Database('sqlite:' . $dbPath);
            // WAL + busy timeout so POST (write) iterations don't lock.
            $db->query('PRAGMA journal_mode = WAL');
            $db->query('PRAGMA busy_timeout = 5000');
            // synchronous=NORMAL, not SQLite's FULL default. Under WAL, FULL makes
            // every commit fsync the WAL, and when autocheckpoint fires the commit
            // pays milliseconds instead of microseconds. Measured on the bench VM on
            // 600 write requests: FULL gave p99 1.2 ms and 8 outliers over 1 ms;
            // NORMAL removes the stall without changing durability semantics that
            // matter for a benchmark DB (a crash can lose the last commits, which
            // this workload never observes). Laravel's entrant already configured
            // NORMAL, so leaving the others on FULL had the pack measuring two
            // different storage configurations. See tests/SqlitePragmaParityTest.php.
            $db->query('PRAGMA synchronous = NORMAL');
            return $db;
        });

        // Tell the Item model to use the default role
        //Item::setDefaultRole('default');

        // The shared locale/platform resolver. `bench-view-globals.php` defines
        // plain (namespace-less) functions, so whoever uses them must load them;
        // the FPM entry script did, which is why the dependency went unnoticed until
        // the in-process harness called boot() with no entry script in front of it.
        require_once dirname(__DIR__, 2) . '/bench-view-globals.php';

        // --- Views (Clarity) ---
        // Register a deferred factory on the container instead of calling
        // $ctx->view() at boot: boot never renders, so the Clarity class
        // stack must not be autoloaded here. The engine is built on the
        // first render (inside dispatch).
        $cacheDir = __DIR__ . '/../../data/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        $viewsPath = __DIR__ . '/Views';
        // Register the Clarity view engine lazily because not all requests need to render views.
        //
        // setRevalidateFreq(60) puts the template cache on the same schedule as the
        // route cache below, the pool's `opcache.validate_timestamps=0` and php-fpm's
        // own `opcache.revalidate_freq = 60`. Without it Clarity stats every template
        // dependency on every render -- the one filesystem check a real production
        // deploy of this app does not perform per request, since the deploy path
        // clears both caches.
        $ctx->set(ViewEngine::class, fn() =>
            (new ClarityEngine())
                ->setExtension('.clarity.html')
                ->setViewPath($viewsPath)
                ->setCachePath($cacheDir)
                ->setRevalidateFreq(60)
                // Boot-time DEFAULTS for the two view vars, from the SAME shared
                // resolver the middleware calls (bench-view-globals.php). These exist
                // so a render with no request in scope (the template tests render the
                // layout directly) still produces a footer. A real request is
                // overwritten by RequestContextMiddleware before the render, which is
                // why this must stay a cheap default and not the only resolution.
                //
                // REQUIRED HERE, not assumed. The functions live in a plain
                // (namespace-less) file that the FPM entry script happens to require,
                // so before this guard the boot only worked when it was reached
                // THROUGH that script — the in-process harness and any other transport
                // died with "Call to undefined function
                // App\Azera\benchViewGlobalsFromGlobals()". `require_once` keeps the
                // entry script's own require harmless.
                ->setVars(\benchViewGlobalsFromGlobals()));

        // --- Routes ---
        $router = $ctx->router();

        // --- Route cache ---
        // Declaring every route inside a cached() unit is what turns the cache on: the
        // first read either `require`s the compiled table or rebuilds it from the units
        // and writes it. This app compiles to a 118-route, 62 KB table, the same order as
        // the framework's 120-route homepage measurement (191 us -> 5.7 us per request).
        //
        // The path is explicit rather than the derived temp default because a deploy hook
        // warms it: temp/ is gitignored and excluded from the VM sync, so it is created
        // fresh on every host. It is a DEDICATED SUBDIRECTORY rather than temp/routes.php
        // directly because Router::assertSafeCacheDir() refuses a group/world-writable
        // directory (mode & 0o022), and temp/ itself is 0775 on a host with umask 002 --
        // it is the harness's shared scratch dir, created by several callers including
        // some that ask for 0777. Naming a directory that does not exist yet lets the
        // framework create it at 0700, which is the mode it requires; pointing at temp/
        // meant the cache silently degraded to rebuilding on every request, which made
        // this whole change measure nothing.
        //
        // 60 seconds matches the Clarity template cache above and the FPM pool's
        // `opcache.validate_timestamps=0`: a long-lived worker re-checks the watched
        // files at most once a minute instead of on every request. Under php-fpm a
        // process serves one request, so the setting costs nothing there.
        $router->setCachePath(__DIR__ . '/../../temp/routes/routes.php');
        $router->setRevalidateFreq(60);

        // Benchmark endpoints (the ones we actually time).
        // Use the controller() group with a callback so the group stack
        // is automatically restored afterwards.
        $router->cached(__FILE__, function (\Azera\Core\Router $router): void {
            $router->controller(BenchController::class, function (\Azera\Core\Router $r) {
                // The framework floor: routed and dispatched, no work after that.
                $r->get('/echo', '::echoAction');

                // Every route below renders a template that extends
                // `layouts/app.clarity.html`, so every one of them reads `locale` /
                // `platform` and belongs in the `web` group. `/echo` above is the one
                // route in this group that renders nothing — keep it outside.
                $r->middleware('web', function () use ($r) {
                    // ORM endpoints (model hydration)
                    // GET /items with the identity map removed and nothing else changed:
                    // the single-variable control for /items (see listFastAction).
                    $r->get('/items-fast', '::listFastAction');
                    $r->post('/items', '::createAction');

                    // HTML routes
                    $r->get('/', '::indexAction');
                    $r->get('/items', '::listAction');
                    $r->get('/items/{id:int}', '::showAction');

                    // New ORM stack endpoints (Model facade over the EM pipeline)
                    // Deactivated (2026-09-05) — the listOrmAction/showOrmAction/
                    // createOrmAction actions stay in BenchController for the next
                    // comparison run; re-enable these three routes to bring the
                    // /items-orm paths back.
                    // $r->get('/items-orm', '::listOrmAction');
                    // $r->get('/items-orm/{id:int}', '::showOrmAction');
                    // $r->post('/items-orm', '::createOrmAction');

                    // Query Builder endpoints (no model hydration)
                    $r->get('/items-qb', '::listQbAction');
                    $r->get('/items-qb/{id:int}', '::showQbAction');
                    $r->post('/items-qb', '::createQbAction');
                });
            });

            // --- Fill routes ---
            // 100 dummy routes to simulate a real project's route table.
            // We never dispatch these — they exist only so the router has a
            // realistic number of entries to search through on every match.
            // Mix of static, parametric, grouped, and multi-method routes.
            //
            // Inside this unit rather than beside it: every route has to live in a
            // cached() block or the first read refuses the registration outright, and a
            // route outside one would silently fall out of the compiled table.
            self::registerFillerRoutes($router, 100);
        });

        // --- Dispatcher + Route-Scoped Middleware ---
        $dispatcher = $ctx->dispatcher();
        $dispatcher->setBaseNamespace('\App\Azera\Controllers');

        // The view-context group: resolves `locale` / `platform` for
        // `layouts/app.clarity.html`, the only template that reads them.
        //
        // WHY MIDDLEWARE AND NOT BOOT. Laravel stamps the same two vars once per boot
        // (`View::share()` in `AppServiceProvider::boot()`), and this app briefly
        // copied it. That only works for a CONSTANT: at boot no request exists, so
        // `AppContext::request()` resolves the container's empty default and every
        // footer on the resident arms read `en_US` no matter what the client sent —
        // while the FPM arm, whose entry script re-runs per request, resolved the real
        // header. Two arms, two answers, from the same app. Resolving per request puts
        // them back on one path, because `Kernel::handle()` publishes the live request
        // into the context before the middleware chain runs.
        //
        // WHY NOT GLOBAL. Every JSON route (`/echo`, every `/api` endpoint, every
        // feature demo) returns without rendering, so a global group would charge
        // header parsing to rows that never read the result — the same fault that got
        // `SecurityHeadersMiddleware` deleted.
        //
        // WHY NOT JUST THE THREE ORIGINAL ROUTES. `/items-fast`, `/items-qb`,
        // `/items-qb/{id}` and both POST routes render these layouts too; they were
        // simply missing from the first definition and printed a footer with no locale
        // at all. `tests/ViewGlobalsCoverageTest.php` enumerates every rendering route
        // from the controller source and fails if one is not in this group.
        //
        // CLASS STRING, not an instance: `Dispatcher::normalizeMiddleware()` builds it
        // on the first request that runs the group, so registering costs one array
        // append at boot.
        $dispatcher->defineMiddlewareGroup('web', [
            \App\Azera\Middleware\RequestContextMiddleware::class,
        ]);

        // The lifecycle-hook group for `GET /features/lifecycle-hooks` alone. Separate
        // from `web` because it does different work: `web` resolves the two view vars
        // the HTML layouts read, while this one rewrites the response (a marker header
        // plus status 203) and exists to make the lifecycle row measurable.
        $dispatcher->defineMiddlewareGroup('lifecycle', [
            \App\Azera\Middleware\LifecycleHookMiddleware::class,
        ]);


        // --- Enterprise Infrastructure (PSR-3/14/16 + AOP) ---
        self::registerEnterpriseServices($ctx);

        // --- FeatureService (autowired so AOP proxy is generated) ---
        // Registered as a class string (no factory) so AppContext::build()
        // generates the AOP proxy.  The constructor receives AppContext
        // which is already available as a registered service.
        $ctx->set(\App\Azera\Services\FeatureService::class);

        // --- Config ---
        // Register a Config instance with a small nested array so the
        // /features/config demo can read dot-notation keys.
        $ctx->set(\Azera\Config\Config::class, new \Azera\Config\Config([
            'app' => ['name' => 'azera-competition', 'env' => 'bench'],
            'db'  => ['dsn' => 'sqlite:data/bench.sqlite', 'driver' => 'sqlite'],
        ]));

        // --- Session middleware (needed for CSRF demo) ---
        // IMPORTANT: registered as a NAMED GROUP, NOT globally. In CLI
        // mode (verify.php / run.php) output is echoed before dispatch, so
        // session_start() fails ("headers already sent") and leaves
        // $_SESSION null, which crashes SessionMiddleware. Scoping it to
        // only the /features/security routes keeps the benchmark endpoints
        // free of session overhead (fair comparison with the other 5
        // frameworks) while preserving the CSRF demo over HTTP.
        $dispatcher->defineMiddlewareGroup('session', [
            // Class string for the same reason as the `web` group above: only the
            // two `/features/security` routes run this group, so the boot must not
            // load `SessionMiddleware` (and its session/cookie graph) for `/`.
            \Azera\Http\SessionMiddleware::class,
        ]);

        // --- Feature Demo Routes ---
        // Endpoints that exercise the new enterprise features (AOP,
        // events, cache, logging, security).  These don't interfere
        // with benchmark routes. A second cached() unit, because a unit is the natural
        // granularity for a source file and the table must be entirely attributable to
        // cached() blocks.
        $router->cached(__FILE__, function (\Azera\Core\Router $router): void {
            $router->controller(\App\Azera\Controllers\FeatureController::class, function (\Azera\Core\Router $r) {
                // The one RENDERING route in this group: `features/index.clarity.html`
                // extends the same layout, so it reads the same two vars. Every route
                // below it returns JSON and stays outside the group.
                $r->middleware('web', function () use ($r) {
                    $r->get('/features', '::indexAction');
                });

                $r->get('/features/aop-tx', '::aopAction');
                // Second real AOP mode: the explicit Pipeline. Raced only by the
                // entrants that actually ship an interceptor abstraction
                // (BenchmarkConfig::adapterFeatures()).
                $r->get('/features/aop-tx-explicit', '::aopExplicitAction');
                $r->get('/features/aop-tx-direct', '::aopDirectAction');
                $r->get('/features/aop-cache', '::aopCacheAction');
                $r->get('/features/aop-cache-direct', '::aopCacheDirectAction');
                $r->get('/features/log', '::logAction');
                $r->get('/features/db-events', '::dbEventsAction');
                $r->get('/features/validation', '::validationAction');
                $r->get('/features/config', '::configAction');
                $r->get('/features/events', '::eventsAction');
                $r->get('/features/rate-limit', '::rateLimitAction');

                // The lifecycle row: one route, one hook group. Applying the hook here
                // rather than globally is deliberate — no other entrant runs a
                // middleware stack on the benchmark rows (see the `web` group's
                // comment), so a global hook would charge this row's cost to every
                // other one.
                $r->middleware('lifecycle', function () use ($r) {
                    $r->get('/features/lifecycle-hooks', '::lifecycleHooksAction');
                });

                // CSRF demo needs a real session — apply the 'session' group
                // only to these routes.
                $r->middleware('session', function () use ($r) {
                    $r->get('/features/security', '::securityAction');
                    $r->post('/features/security', '::securityPostAction');
                });
            });
        });

        // --- REST API Routes ---
        // A dedicated JSON API category.  Every framework implements the
        // same /api endpoints so routing + controller + JSON
        // serialization overhead is compared apples-to-apples.
        $router->cached(__FILE__, function (\Azera\Core\Router $router): void {
            $router->controller(\App\Azera\Controllers\ApiController::class, function (\Azera\Core\Router $r) {
                $r->get('/api/items', '::indexAction');
                // Routing cost in the DEEP 'api' bucket, with a constant handler and
                // no database access: the only endpoint whose measured time is route
                // matching. Declared BEFORE /api/items/{id:int} so the literal
                // segment is not swallowed, exactly as /api/items-fast is.
                $r->get('/api/routing/{id:int}', '::routingAction');
                // /api/items with hydration switched off: the single-variable
                // control for /api/items (see indexFastAction). Declared before
                // the {id:int} placeholder so the literal segment wins.
                $r->get('/api/items-fast', '::indexFastAction');
                $r->get('/api/items/{id:int}', '::showAction');
                $r->post('/api/items', '::createAction');
            });
        });

        // Memoize the wired context per dbPath so a worker-mode re-bootstrap
        // (same process, same database) can skip straight to the request
        // loop. CI4 parity: its RouteCollection service also loads once per
        // process; request-scoped state resets via clearRequestScope().
        self::$wired[$dbPath] = $ctx;

        return $ctx;
    }

    /**
     * Register enterprise infrastructure services (PSR-3 Logger, PSR-14
     * Event Dispatcher, PSR-16 Cache, Queue) and AOP interceptors.
     *
     * This wires the new framework subsystems into the benchmark app so
     * we can validate them on a real Azera app without touching the
     * production sailantis-homepage.
     */
    private static function registerEnterpriseServices(AppContext $ctx): void
    {
        // --- PSR-3 Logger ---
        // Register a single in-memory logger instance under BOTH its
        // concrete class and the PSR-3 interface, so the feature service
        // (which resolves LoggerInterface through AppContext::logger()) and
        // the controller (which injects MemoryLogger) share the same object.
        // Swap to Monolog for real logs.
        $memoryLogger = new \App\Azera\Services\MemoryLogger();
        $ctx->set(\App\Azera\Services\MemoryLogger::class, $memoryLogger);
        $ctx->set(LoggerInterface::class, $memoryLogger);

        // --- PSR-14 Event Dispatcher ---
        $ctx->set(EventDispatcherInterface::class, function () {
            $dispatcher = new EventDispatcher();
            $dispatcher->listen(
                \App\Azera\Events\ItemCreated::class,
                \App\Azera\Events\Listener\ItemCreatedListener::class,
            );
            // Db event listeners — one handler per concrete event class.
            // EventDispatcher also resolves listeners for parent classes
            // and interfaces, so each registration covers its subtype.
            $dispatcher->listen(
                \Azera\Db\Event\QueryExecuted::class,
                \App\Azera\Events\Listener\DatabaseEventListener::class,
            );
            $dispatcher->listen(
                \Azera\Db\Event\StatementPrepared::class,
                \App\Azera\Events\Listener\DatabaseEventListener::class,
            );
            $dispatcher->listen(
                \Azera\Db\Event\TransactionStarted::class,
                \App\Azera\Events\Listener\DatabaseEventListener::class,
            );
            $dispatcher->listen(
                \Azera\Db\Event\TransactionCommitted::class,
                \App\Azera\Events\Listener\DatabaseEventListener::class,
            );
            return $dispatcher;
        });

        // --- PSR-16 Cache ---
        $ctx->set(CacheInterface::class, fn() => new ArrayCache());

        // --- Queue ---
        $ctx->set(QueueInterface::class, fn() => new SyncQueue());

        // --- Demo services (autowired) ---
        $ctx->set(\App\Azera\Services\DbEventLog::class);
        $ctx->set(\App\Azera\Services\OrmDemoService::class);

        // --- AOP Interceptors ---
        // Enable proxy generation for #[Advised] classes.
        //
        // AOP cache dir for file-based proxy generation.
        //
        // Same rule as the route cache above, and for the same reason:
        // ProxyFactory::assertSafeCacheDir() refuses to `require` generated code
        // out of a group/world-writable directory (mode & 0o022), because a file
        // another user can replace is a file another user can make us execute. So
        // the APP owns the directory and creates it 0700, rather than pointing at
        // a path the harness's own `mkdir(0777)` had already made 0775.
        //
        // This bit exactly once and only on the VM: data/aop had been created 0775
        // by an earlier run, before the framework grew the check, so every
        // /features/aop-tx and /features/aop-tx-direct request turned into
        // "RuntimeException: AOP proxy cache directory is group/world writable"
        // -> a 500 -> an aborted benchmark. Locally the directory either did not
        // exist (ProxyFactory created it 0700) or Windows has no mode bits to
        // inspect, so nothing failed until a stale Linux directory was reused.
        //
        // Tighten an existing directory as well: a checkout that already has a
        // 0775 data/aop keeps failing otherwise, and `chmod` on our own directory
        // is harmless when it is already 0700.
        $aopCacheDir = __DIR__ . '/../../data/aop';
        if (!is_dir($aopCacheDir) && !@mkdir($aopCacheDir, 0700, true) && !is_dir($aopCacheDir)) {
            // Let ProxyFactory report the failure with its own message; it stats
            // the directory before loading anything and names the path.
        } elseif (\DIRECTORY_SEPARATOR !== '\\') {
            $perms = @fileperms($aopCacheDir);
            if ($perms !== false && ($perms & 0x0022) !== 0) {
                @chmod($aopCacheDir, 0700);
            }
        }

        $ctx->setAopCacheDir($aopCacheDir);

        // Interceptors as FACTORIES: built once, on the first advised-class
        // construction (proxy build), not at boot. The eager versions pulled
        // ArrayCache + DatabaseManager + all interceptor classes into the
        // cold-boot path; AOP requests resolve them on first use instead.
        $ctx->registerInterceptor(
            Transactional::class,
            fn() => new TransactionalInterceptor($ctx->dbManager()),
        );
        $ctx->registerInterceptor(
            CacheAdvice::class,
            fn() => new CacheInterceptor($ctx->cache()),
        );
    }

    /**
     * Register N dummy routes that simulate a larger application.
     *
     * The routes are deliberately varied:
     *   - Static routes (/about, /contact, /api/v1/users, ...)
     *   - Parametric routes (/users/{id}, /blog/{slug}, /api/v1/users/{id}/posts/{postId}, ...)
     *   - Grouped routes (prefix + namespace + controller)
     *   - Multi-method routes (GET + POST on the same path)
     *
     * All handlers point to BenchController::indexAction so no extra
     * controller classes are needed; we never actually dispatch them.
     */
    private static function registerFillerRoutes(\Azera\Core\Router $router, int $count): void
    {
        $controllers = ['User', 'Post', 'Comment', 'Category', 'Tag', 'Order', 'Product', 'Page', 'Setting', 'Search'];
        $actions     = ['index', 'show', 'create', 'update', 'delete', 'edit', 'list', 'search'];
        $sections    = ['admin', 'api', 'api/v1', 'api/v2', 'dashboard', 'panel', 'manage'];
        $slugPool    = ['hello-world', 'getting-started', 'first-post', 'welcome', 'about-us', 'faq', 'docs', 'changelog'];

        $registered = 0;
        $i          = 0;

        while ($registered < $count) {
            $controller = $controllers[$i % count($controllers)];
            $action     = $actions[$i % count($actions)];
            $section    = $sections[$i % count($sections)];
            $slug       = $slugPool[$i % count($slugPool)];
            $id         = $i + 1;

            // Pattern 1: static route e.g. /admin/users/index
            $path1 = "/{$section}/{$controller}/{$action}";
            $router->add(['GET', 'POST'], $path1);
            $registered += 2; // two methods

            if ($registered >= $count) {
                break;
            }

            // Pattern 2: parametric route e.g. /api/v1/users/{id}
            $path2 = "/{$section}/{$controller}/{param{$id}:int}";
            $router->get($path2);
            $registered++;

            if ($registered >= $count) {
                break;
            }

            // Pattern 3: nested parametric e.g. /api/v1/users/{id}/posts/{postId}
            $path3 = "/{$section}/{$controller}/{param{$id}:int}/posts/{post{$id}:int}";
            $router->get($path3);
            $registered++;

            if ($registered >= $count) {
                break;
            }

            // Pattern 4: slug parametric e.g. /blog/hello-world
            $path4 = "/blog/{$slug}";
            $router->get($path4);
            $registered++;

            if ($registered >= $count) {
                break;
            }

            // Pattern 5: wildcard e.g. /assets/css/{file:*}
            $path5 = "/assets/" . strtolower($controller) . "/{file{$id}:*}";
            $router->get($path5);
            $registered++;

            $i++;
        }
    }
}