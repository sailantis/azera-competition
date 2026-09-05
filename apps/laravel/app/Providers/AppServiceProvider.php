<?php

/**
 * Benchmark app service provider — config merging + feature service bindings.
 *
 * The Laravel benchmark app has no package discovery; this provider wires
 * the benchmark config namespace and request-scoped services explicitly,
 * mirroring Spiral's AppBootloader.
 */

namespace App\Laravel\Providers;

use App\Laravel\Service\DbEventLog;
use App\Laravel\Service\RequestCounter;
use App\Laravel\Service\ScopeState;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped state: scoped singleton semantics via
        // `$app->scoped()` — a fresh instance per request, single instance
        // within the request (mirrors Spiral's #[Scope('http')] services).
        $this->app->scoped(RequestCounter::class);
        $this->app->scoped(ScopeState::class);

        // DB event log lives for the process (query log accumulates there).
        $this->app->singleton(DbEventLog::class);
    }

    public function boot(Dispatcher $events): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/benchmark.php', 'benchmark');
        $this->mergeConfigFrom(__DIR__ . '/../../config/cache.php', 'cache');
        $this->mergeConfigFrom(__DIR__ . '/../../config/database.php', 'database');
        $this->mergeConfigFrom(__DIR__ . '/../../config/view.php', 'view');

        // View globals — locale + platform stamped into EVERY template render
        // (the layout footer renders them unconditionally). Mirrors azera's
        // RequestContextMiddleware, which reads Accept-Language + User-Agent
        // and stamps the detected values into the view engine. The benchmark
        // adapter dispatches synthetic requests with fixed headers, so the
        // values are the same deterministic defaults azera and Spiral render.
        View::share('locale', 'en_US');
        View::share('platform', 'desktop');

        // Laravel 12 scoped flush: reset per-request state after each request.
        // forgetScopedInstances() alone is NOT sufficient in a long-running
        // worker: the Router caches each Route's resolved controller for the
        // process lifetime, so a cached controller keeps its ORIGINAL
        // constructor-injected scoped services even after the container
        // flushes them (observed: request-scoped scope_trace grew +2 entries
        // per request, count never reset). Flushing the matched route's
        // controller forces re-resolution from the container on the next
        // request — the worker-safe equivalent of php-fpm teardown.
        $events->listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function (\Illuminate\Foundation\Http\Events\RequestHandled $event): void {
            $this->app->forgetScopedInstances();
            $event->request->route()?->flushController();
        });
    }
}