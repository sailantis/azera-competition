<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — App config (minimal).
 *
 * Only the values that actually matter for in-process dispatch; everything
 * else inherits from CodeIgniter\Config\BaseConfig defaults via property
 * initialization (BaseConfig fills missing values from its own defaults).
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

class App extends BaseConfig
{
    public string $baseURL = 'http://bench.local/';

    public string $indexPage = '';

    public string $uriProtocol = 'REQUEST_URI';

    public string $permittedURIChars = 'a-z 0-9~%.:_\-';

    public string $defaultLocale = 'en';

    public array $supportedLocales = ['en'];

    public string $appTimezone = 'UTC';

    public string $charset = 'UTF-8';

    public bool $negotiateLocale = false;

    public array $allowedHostnames = [];

    public array $proxyIPs = [];

    public bool $forceGlobalSecureRequests = false;

    public bool $CSPEnabled = false;
}