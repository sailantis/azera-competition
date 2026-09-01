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

// --- Core benchmark endpoints -------------------------------------------
$routes->get('/', 'Bench::index');
$routes->get('/items', 'Bench::list');
$routes->get('/items/(:num)', 'Bench::show/$1');
$routes->post('/items', 'Bench::create');

// --- Query builder variants (no ORM hydration) ---------------------------
$routes->get('/items-qb', 'Bench::listQb');
$routes->get('/items-qb/(:num)', 'Bench::showQb/$1');
$routes->post('/items-qb', 'Bench::createQb');

// --- REST API ------------------------------------------------------------
$routes->get('/api/items', 'Api::index');
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