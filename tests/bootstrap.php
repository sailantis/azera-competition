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

// Version provenance, loaded by BOTH harnesses (run.php and scripts/run-http.php)
// so their stamps cannot diverge. Tests exercise it directly — the composer.json
// vs git-tag agreement is the whole point of the drift guard — and a test
// harness that does not load what the harness loads cannot assert on it.
require_once "{$root}/scripts/version-lib.php";

// Runtime-comparability check, shared by merge-app.php / merge-modes.php /
// assemble-real.php. Loaded here so the tests drive the real rule rather than
// a re-implementation of it.
require_once "{$root}/scripts/env-sanity.php";

// Report tooling has no composer autoload entry (report.php requires the files
// explicitly), so the tests do the same. The whole chain is loaded, in
// report.php's own order, so a test can construct a real MarkdownReport and
// render a view instead of asserting on source text alone.
require_once "{$root}/scripts/report/BenchmarkConfig.php";
require_once "{$root}/scripts/report/ResultStore.php";
require_once "{$root}/scripts/report/SvgChart.php";
require_once "{$root}/scripts/report/Tables.php";
require_once "{$root}/scripts/report/MarkdownReport.php";
require_once "{$root}/scripts/report/HtmlReport.php";
