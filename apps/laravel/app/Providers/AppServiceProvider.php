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

        // Laravel 12 scoped flush: reset per-request state after each request.
        $events->listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function (): void {
            $this->app->forgetScopedInstances();
        });
    }
}