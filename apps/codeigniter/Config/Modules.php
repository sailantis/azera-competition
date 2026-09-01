<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Modules config.
 *
 * Auto-discovery disabled: no modules/plugins are needed for the benchmark
 * and discovery would scan composer namespaces on every service lookup.
 */

namespace Config;

use CodeIgniter\Modules\Modules as BaseModules;

class Modules extends BaseModules
{
    public $enabled = false;

    public $discoverInComposer = false;

    public $aliases = [];
}