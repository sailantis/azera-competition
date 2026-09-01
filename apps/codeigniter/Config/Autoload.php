<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Autoloader config.
 *
 * Maps the Ci4App namespace to the benchmark app dir.  We deliberately do
 * NOT use the default `App` namespace here because the Azera benchmark app
 * already occupies `App\` inside the shared benchmark process.
 */

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

class Autoload extends AutoloadConfig
{
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    public $classmap = [];

    public $files = [];

    public $helpers = [];
}