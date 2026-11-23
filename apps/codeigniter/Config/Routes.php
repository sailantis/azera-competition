<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Routes.
 *
 * Same paths as the azera/spiral apps: core benchmark endpoints, query-
 * builder variants, REST API and all feature demos.  ~100 filler routes
 * keep route-table size parity.
 */

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// --- 404 handling --------------------------------------------------------
// CI4's own 404 policy, used instead of letting the exception escape.
//
// CodeIgniter::run() catches PageNotFoundException and calls
// display404errors(), which — when no override is registered — rethrows
// PageNotFoundException. That rethrow is correct for FPM (the global handler
// prints the error page) but wrong for this harness: run(null, true) hands the
// response back to the caller, so the rethrow escaped to the adapter and every
// missing route and wrong method surfaced as
// "500 CodeIgniter\Exceptions\PageNotFoundException: ...".
//
// Registering the override is CI4's documented way to answer a 404 yourself, and
// it makes display404errors() return a real 404 response. The method-mismatch
// case (PageNotFoundException::forMethodNotFound) goes through the same catch.
$routes->set404Override(static fn(): string => '404 Not Found');

// --- Core benchmark endpoints -------------------------------------------
$routes->get('/echo', 'Bench::echo');
$routes->get('/', 'Bench::index');
$routes->get('/items', 'Bench::list');
// GET /items with the row shape reduced to plain arrays: the single-variable
// control for /items (see Bench::listFast()). Declared before /items/(:num) so
// the literal segment is not swallowed by the numeric placeholder.
$routes->get('/items-fast', 'Bench::listFast');
$routes->get('/items/(:num)', 'Bench::show/$1');
$routes->post('/items', 'Bench::create');

// --- Query builder variants (no ORM hydration) ---------------------------
$routes->get('/items-qb', 'Bench::listQb');
$routes->get('/items-qb/(:num)', 'Bench::showQb/$1');
$routes->post('/items-qb', 'Bench::createQb');

// --- REST API ------------------------------------------------------------
// Routing cost in the deep 'api' bucket with a constant handler and no ORM:
// the only endpoint whose measured time is route matching. Declared before
// /api/items/(:num) so the literal segment is matched first.
$routes->get('/api/routing/(:num)', 'Bench::routing/$1');
$routes->get('/api/items', 'Api::index');
// Declared before /api/items/(:num): CI4 matches top-down, so a literal
// segment placed after the (:num) placeholder would be swallowed by it.
$routes->get('/api/items-fast', 'Api::indexFast');
$routes->get('/api/items/(:num)', 'Api::show/$1');
$routes->post('/api/items', 'Api::create');

// --- Feature demo endpoints ----------------------------------------------
$routes->get('/features', 'Feature::index');
$routes->get('/features/aop', 'Feature::aop');
$routes->get('/features/cache', 'Feature::cache');
$routes->get('/features/log', 'Feature::log');
$routes->get('/features/retry', 'Feature::retry');
$routes->get('/features/pipeline', 'Feature::pipeline');
$routes->get('/features/db-events', 'Feature::dbEvents');
$routes->get('/features/events', 'Feature::events');
$routes->get('/features/validation', 'Feature::validation');
$routes->get('/features/config', 'Feature::config');
$routes->get('/features/request-scoped', 'Feature::requestScoped');
$routes->get('/features/rate-limit', 'Feature::rateLimit');

// --- Filler routes (route-table size parity) -----------------------------
foreach (range(1, 100) as $i) {
    $routes->get("/filler/{$i}", 'Bench::filler/' . $i);
}