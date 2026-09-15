<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the harness tests.
 *
 * vendor/autoload.php covers the report classes' composer autoload only — the
 * harness scripts (bench-lib.php, deploy-lib.php, assemble-real.php) are plain
 * `require_once` files with global functions, so the tests pull them in
 * explicitly.
 */

require_once __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// Pure helpers: stats, budget detection, request parsing.
require_once "{$root}/scripts/bench-lib.php";

// Deployment helpers. The file only DEFINES functions at load time (config
// stamping, port allocation, gateway classification) — it performs no I/O
// until one is called, so requiring it here is side-effect free.
require_once "{$root}/scripts/deploy-lib.php";

// Report tooling has no composer autoload entry (report.php requires the files
// explicitly), so the tests do the same.
require_once "{$root}/scripts/report/ResultStore.php";
