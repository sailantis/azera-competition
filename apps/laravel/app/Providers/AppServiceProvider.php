<?php

/**
 * Benchmark app service provider — config merging + feature service bindings.
 *
 * The Laravel benchmark app has no package discovery; this provider wires
 * the benchmark config namespace and the DB event log explicitly, mirroring
 * Spiral's AppBootloader.
 */

namespace App\Laravel\Providers;

use App\Laravel\Events\ItemCreated;
use App\Laravel\Listeners\StampListener;
use App\Laravel\Service\DbEventLog;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // DB event log lives for the process (query log accumulates there).
        $this->app->singleton(DbEventLog::class);

        // Wire Laravel's idiomatic DB observation hooks into the log ONCE
        // per process: DB::listen() fires per executed query and the
        // transaction lifecycle events fire per begin/commit/rollback.
        // Without these listeners the db-events demo reported an empty
        // events list (the other five adapters show the live pipeline).
        $log = $this->app->make(DbEventLog::class);
        \Illuminate\Support\Facades\DB::listen(function (\Illuminate\Database\Events\QueryExecuted $query) use ($log): void {
            $log->recordQuery($query->sql, $query->time);
        });
        $events = $this->app->make(\Illuminate\Contracts\Events\Dispatcher::class);
        foreach ([
            \Illuminate\Database\Events\TransactionBeginning::class  => 'TransactionStarted',
            \Illuminate\Database\Events\TransactionCommitted::class  => 'TransactionCommitted',
            \Illuminate\Database\Events\TransactionRolledBack::class => 'TransactionRolledBack',
        ] as $eventClass => $type) {
            $events->listen($eventClass, function () use ($log, $type): void {
                $log->recordTransaction($type);
            });
        }
    }

    public function boot(Dispatcher $events): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/benchmark.php', 'benchmark');
        $this->mergeConfigFrom(__DIR__ . '/../../config/cache.php', 'cache');
        $this->mergeConfigFrom(__DIR__ . '/../../config/database.php', 'database');
        $this->mergeConfigFrom(__DIR__ . '/../../config/logging.php', 'logging');
        $this->mergeConfigFrom(__DIR__ . '/../../config/view.php', 'view');

        // View globals — locale + platform for the layout footer, resolved by the
        // SAME parser every other entrant runs (`bench-view-globals.php`).
        //
        // These used to be two literal constants shared at boot, which meant the
        // footer asserted a locale and platform Laravel had never resolved while
        // Azera parsed headers to produce the identical values. One entrant doing the
        // work and the rest asserting the result is a comparison between different
        // work; running one parser in all seven removes the difference.
        $globals = benchViewGlobalsFromGlobals();
        View::share('locale', $globals['locale']);
        View::share('platform', $globals['platform']);

        // ItemCreated fan-out for /features/events.
        //
        // Registered EXPLICITLY, not by discovery. bootstrap/app.php calls
        // withEvents(discover: false) — event discovery costs a filesystem scan
        // per process, and this app has exactly one listener — but nothing
        // replaced discovery afterwards, so StampListener was never registered
        // and /features/events returned "listener_log": []. The endpoint was
        // timed while the listener did nothing, and verify-app.php only checks
        // that the key EXISTS, so an empty array passed parity.
        //
        // Keep the registration here rather than re-enabling discovery: it needs
        // no discoverEventsWithin() path, cannot be undone by a later
        // withEvents() call, and states the wiring in the same place as the
        // other listeners this app registers.
        $events->listen(ItemCreated::class, StampListener::class);

        // Worker-mode per-request teardown.
        //
        // `forgetScopedInstances()` is Laravel's own scoped-container flush and
        // runs after every RequestHandled so a resident worker cannot carry
        // request state into the next request. Nothing in this app binds a
        // scoped service today, so it is currently a no-op guard — kept because
        // it is the framework's contract, not because a demo needs it.
        //
        // The DB event log, by contrast, is live: it records one entry per
        // executed query into a PROCESS-LIFETIME singleton (see register()). In
        // a resident worker that grows without bound — measured +~850 B per
        // query-request over 5,000 requests (2026-09-14) — so it must be wiped
        // per request. Without this, the db-events demo turns the benchmark's own
        // instrumentation into a false "framework leaks" signal.
        $events->listen(\Illuminate\Foundation\Http\Events\RequestHandled::class, function (): void {
            $this->app->forgetScopedInstances();
            $this->app->make(DbEventLog::class)->clear();
        });
    }
}