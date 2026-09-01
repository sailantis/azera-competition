<?php

/**
 * Laravel benchmark application bootstrap (Laravel 11+ style builder).
 *
 * Boots a real Laravel HTTP Kernel over the shared SQLite database, with
 * Blade views, Eloquent and the full provider stack — the idiomatic setup a
 * real Laravel app ships, so cold/warm boot costs reflect what Laravel
 * users actually pay.
 */

use App\Laravel\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$_ENV['LARAVEL_STORAGE_PATH'] = dirname(__DIR__, 2) . '/writable/laravel';

return Application::configure(basePath: __DIR__ . '/..')
    ->withProviders([AppServiceProvider::class], withBootstrapProviders: false)
    ->withEvents(discover: false)
    ->withRouting(
        using: function () {
            require __DIR__ . '/../routes/web.php';
            \Illuminate\Support\Facades\Route::prefix('api')->group(
                __DIR__ . '/../routes/api.php'
            );
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // No CSRF/web middleware group — benchmark dispatch is synthetic
        // (no session); flash is passed inline as a view variable. Default
        // global middleware stack is kept as-is.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Adapter catches Throwable itself; keep default rendering.
    })
    ->create();