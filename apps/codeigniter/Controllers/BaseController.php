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
     * View globals — locale and platform variables stamped into every
     * template render, mirroring azera's RequestContextMiddleware (which
     * reads Accept-Language + User-Agent and stamps the detected values
     * into the view engine). The benchmark adapter dispatches synthetic
     * requests with fixed headers, so the resolved values are the same
     * deterministic defaults azera and Spiral render (en_US / desktop).
     *
     * @return array<string, string>
     */
    protected function viewGlobals(): array
    {
        return ['locale' => 'en_US', 'platform' => 'desktop'];
    }
}