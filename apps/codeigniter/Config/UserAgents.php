<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — UserAgents config.
 *
 * The stock app skeleton ships Config\UserAgents (consumed by
 * CodeIgniter\HTTP\UserAgent, constructed with every IncomingRequest).
 * A minimal table suffices for benchmark traffic: enough platform +
 * browser entries for realistic agents; everything unknown degrades
 * gracefully to 'Unknown Platform'.
 */

namespace Config;

use CodeIgniter\Config\BaseConfig;

class UserAgents extends BaseConfig
{
    /**
     * @var array<string, string>
     */
    public array $platforms = [
        'windows nt 10.0' => 'Windows 10 / Server 2016',
        'windows nt 6.3'  => 'Windows 8.1',
        'windows nt 6.2'  => 'Windows 8',
        'windows nt 6.1'  => 'Windows 7',
        'windows'         => 'Unknown Windows OS',
        'mac os x'        => 'Mac OS X',
        'android'         => 'Android',
        'iphone'          => 'iOS',
        'ipad'            => 'iOS',
        'linux'           => 'Linux',
        'apachebench'     => 'ApacheBench',
    ];

    /**
     * @var array<string, string>
     */
    public array $browsers = [
        'OPR'     => 'Opera',
        'Edg'     => 'Edge',
        'Chrome'  => 'Chrome',
        'Firefox' => 'Firefox',
        'MSIE'    => 'Internet Explorer',
        'Safari'  => 'Safari',
        'Mozilla' => 'Mozilla',
        'curl'    => 'curl',
    ];

    /**
     * @var array<string, string>
     */
    public array $mobiles = [
        'mobileexplorer' => 'Mobile Explorer',
    ];

    /**
     * @var array<string, string>
     */
    public array $robots = [];
}