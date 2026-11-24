<?php

declare(strict_types=1);

/**
 * Base controller for the CI4 benchmark app — everything a stock CI4
 * BaseController offers, minus the magic helpers (no url/form helpers are
 * needed for the benchmark templates).
 */

namespace Ci4App\Controllers;

use CodeIgniter\Controller;

abstract class BaseController extends Controller
{
    /**
     * View globals — locale and platform variables for every template render,
     * resolved by the SAME parser every other entrant runs (`bench-view-globals.php`).
     *
     * See Laravel's FeatureController::viewGlobals() and the Azera middleware for why
     * this is a call rather than the constant pair it used to be.
     *
     * @return array{locale: string, platform: string}
     */
    protected function viewGlobals(): array
    {
        return benchViewGlobalsFromGlobals();
    }
}