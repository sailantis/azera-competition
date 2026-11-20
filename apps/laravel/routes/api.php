<?php

/**
 * API routes — REST API benchmark endpoints (JSON serialization).
 *
 * Registered with the `api` prefix (`apiPrefix: 'api'` in bootstrap/app.php),
 * so these resolve as /api/items, /api/items/{id}.
 */

use App\Laravel\Http\Controllers\ApiController;
use App\Laravel\Http\Controllers\BenchController;

use Illuminate\Support\Facades\Route;

Route::get('/items', [ApiController::class, 'index']);
// Routing cost in the deep 'api' bucket with a constant handler and no ORM: the
// only endpoint whose measured time is route matching. Declared before
// /items/{id} so the literal segment is not swallowed by the number constraint.
// See BenchController::routing().
Route::get('/routing/{id}', [BenchController::class, 'routing'])->whereNumber('id');
// Declared before /items/{id}: the number constraint would otherwise let
// /items-fast fall through to show() as an id.
Route::get('/items-fast', [ApiController::class, 'indexFast']);
Route::get('/items/{id}', [ApiController::class, 'show'])->whereNumber('id');
Route::post('/items', [ApiController::class, 'create']);
