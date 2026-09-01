<?php

/**
 * Routes for the Laravel benchmark app.
 *
 * Mirrors Spiral's AppRoutesBootloader: 8 benchmark endpoints + features
 * index + 11 feature routes + 100 filler routes for route-table parity.
 */

use App\Laravel\Http\Controllers\ApiController;
use App\Laravel\Http\Controllers\BenchController;
use App\Laravel\Http\Controllers\FeatureController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BenchController::class, 'index']);

// --- ORM endpoints ---------------------------------------------------------
Route::get('/items', [BenchController::class, 'list']);
Route::get('/items/{id}', [BenchController::class, 'show'])->whereNumber('id');
Route::post('/items', [BenchController::class, 'create']);

// --- Query builder endpoints (no ORM hydration) ----------------------------
Route::get('/items-qb', [BenchController::class, 'listQb']);
Route::get('/items-qb/{id}', [BenchController::class, 'showQb'])->whereNumber('id');
Route::post('/items-qb', [BenchController::class, 'createQb']);

// --- Feature demos ----------------------------------------------------------
Route::get('/features', [FeatureController::class, 'index']);
Route::get('/features/aop', [FeatureController::class, 'aop']);
Route::get('/features/cache', [FeatureController::class, 'cache']);
Route::get('/features/log', [FeatureController::class, 'log']);
Route::get('/features/retry', [FeatureController::class, 'retry']);
Route::get('/features/pipeline', [FeatureController::class, 'pipeline']);
Route::get('/features/db-events', [FeatureController::class, 'dbEvents']);
Route::get('/features/events', [FeatureController::class, 'events']);
Route::get('/features/validation', [FeatureController::class, 'validation']);
Route::get('/features/config', [FeatureController::class, 'config']);
Route::get('/features/request-scoped', [FeatureController::class, 'requestScoped']);
Route::get('/features/rate-limit', [FeatureController::class, 'rateLimit']);

// --- Filler routes (route-table size parity with other apps) ----------------
for ($i = 1; $i <= 100; $i++) {
    Route::get("/filler/{$i}", fn() => 'filler');
}