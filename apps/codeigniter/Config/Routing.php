<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Router config.
 *
 * Defined routes only (idiomatic for a benchmark: the auto-router is off in
 * any performance-conscious CI4 deployment).  Route presets live in
 * Config/Routes.php; ~100 filler routes give route-table size parity with
 * the azera/spiral apps.
 */

namespace Config;

use CodeIgniter\Config\Routing as BaseRouting;

class Routing extends BaseRouting
{
    public array $routeFiles = [
        APPPATH . 'Config/Routes.php',
    ];

    public string $defaultNamespace = 'Ci4App\Controllers';

    public string $defaultController = 'Bench';

    public string $defaultMethod = 'index';

    public bool $translateURIDashes = false;

    public ?string $override404 = null;

    public bool $autoRoute = false;

    public bool $useControllerAttributes = true;

    public bool $prioritize = false;
}