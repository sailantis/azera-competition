<?php

declare(strict_types=1);

/**
 * Filler routes — 100 routes so the Symfony route table size matches the
 * other benchmark apps (azera, Spiral, Laravel all register ~100 filler
 * routes for route-table parity).
 */

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    for ($i = 1; $i <= 100; $i++) {
        $routes->add("filler_{$i}", "/filler/{$i}")
            ->controller(\App\Symfony\Controller\BenchController::class . '::filler')
            ->methods(['GET']);
    }
};