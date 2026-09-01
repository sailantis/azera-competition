<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — Paths.
 *
 * Points APPPATH/WRITEPATH/system at the competition layout: the shared
 * "benchmark app" lives in apps/codeigniter (Config/Controllers/Models/Views),
 * the framework system dir comes from the composer package and the writable
 * dir is local to the competition (so template/session caches are isolated).
 */

namespace Config;

class Paths
{
    public string $systemDirectory = __DIR__ . '/../../../vendor/codeigniter4/framework/system';
    public string $appDirectory = __DIR__ . '/..';
    public string $writableDirectory = __DIR__ . '/../../../writable/ci4';
    public string $testsDirectory = __DIR__ . '/../../../tests';
    public string $viewDirectory = __DIR__ . '/../Views';
    public string $envDirectory = __DIR__ . '/../../../';
}