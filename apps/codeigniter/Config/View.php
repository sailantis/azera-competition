<?php

declare(strict_types=1);

/**
 * CodeIgniter 4 benchmark app — View config.
 *
 * `saveData` off prevents the ::saveData() copy of large item arrays per
 * render — mirrors the difference between frameworks that keep view data and
 * those that discard it after rendering.
 *
 * NOTE: ViewManager uses config(View) => Config\View (this file).
 */

namespace Config;

use CodeIgniter\Config\View as BaseView;

class View extends BaseView
{
    /**
     * @var bool
     */
    public $saveData = false;

    /**
     * @var array<string, string>
     */
    public $filters = [];

    /**
     * @var array<string, mixed>
     */
    public $plugins = [];

    /**
     * @var list<class-string>
     */
    public array $decorators = [];

    public string $appOverridesFolder = 'overrides';
}