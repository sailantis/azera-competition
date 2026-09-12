#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * azera-competition — report generator.
 *
 * Turns benchmark result JSON into a nice, easily-updatable report:
 * standalone HTML dashboard + embeddable static SVG charts + a Markdown
 * fragment for the framework README/docs.
 *
 * Pure PHP, no JS, no build step.
 *
 * Usage:
 *   php scripts/report.php                          # all views
 *   php scripts/report.php --view=azera-vs-all      # one view
 *   php scripts/report.php --dataset=free-for-all   # pick dataset by name
 *   php scripts/report.php --dataset=results/free-for-all-opcache.json
 *   php scripts/report.php --list                   # list datasets + views
 *   php scripts/report.php --publish=framework      # also write into azera-framework
 *
 * Output (default docs/benchmarks/):
 *   index.html          dashboard linking every view
 *   view-<key>.html     one page per view
 *   <view>.md          Markdown fragment (README/docs ready)
 *   svg/<view>/*.svg    the charts
 */

require_once __DIR__ . '/report/BenchmarkConfig.php';
require_once __DIR__ . '/report/ResultStore.php';
require_once __DIR__ . '/report/SvgChart.php';
require_once __DIR__ . '/report/Tables.php';
require_once __DIR__ . '/report/MarkdownReport.php';
require_once __DIR__ . '/report/HtmlReport.php';

use AzeraCompetition\Report\BenchmarkConfig;
use AzeraCompetition\Report\HtmlReport;
use AzeraCompetition\Report\MarkdownReport;
use AzeraCompetition\Report\ResultStore;

$root     = dirname(__DIR__);
$manifest = require __DIR__ . '/report/views.php';

$opts     = getopt('', ['dataset::', 'view::', 'out::', 'publish::', 'list', 'help']);
$outDir   = rtrim($opts['out'] ?? $root . '/docs/benchmarks', '/');
$onlyView = $opts['view'] ?? null;
$publish  = $opts['publish'] ?? null;

if (isset($opts['help'])) {
    echo <<<TXT
azera-competition report generator

Options:
  --dataset=<name|path>  Dataset to render (default: first dataset that exists)
  --view=<key>           Render only this view (default: all views)
  --out=<dir>            Output directory (default: docs/benchmarks)
  --publish=<target>     Publish views that declare this target (e.g. framework)
  --list                 List available datasets and views
  --help                 Show this help

TXT;
    exit(0);
}

if (isset($opts['list'])) {
    echo "Datasets:\n";
    foreach ($manifest['datasets'] as $key => $ds) {
        echo "  {$key}  — {$ds['label']}\n";
    }
    echo "\nViews:\n";
    foreach ($manifest['views'] as $key => $view) {
        $apps = implode(', ', $view['apps'] ?? []);
        echo "  {$key}  — {$view['title']} [{$apps}]\n";
    }
    exit(0);
}

// --- Resolve dataset -------------------------------------------------------

$datasetArg = $opts['dataset'] ?? null;
$store      = null;
$dsLabel    = '';

if ($datasetArg !== null && is_file($datasetArg)) {
    $store   = ResultStore::load($datasetArg);
    $dsLabel = basename($datasetArg);
} elseif ($datasetArg !== null) {
    if (!isset($manifest['datasets'][$datasetArg])) {
        fwrite(STDERR, "Unknown dataset: {$datasetArg}\n");
        exit(1);
    }
    $store   = resolveDataset($manifest['datasets'][$datasetArg]);
    $dsLabel = $datasetArg;
} else {
    // First dataset that actually exists.
    foreach ($manifest['datasets'] as $key => $ds) {
        $try = tryResolveDataset($ds);
        if ($try !== null) {
            $store   = $try;
            $dsLabel = $key;
            break;
        }
    }
}

if ($store === null) {
    fwrite(STDERR, "No dataset available. Run the benchmark first (php run.php --out=results/<name>).\n");
    exit(1);
}

echo "Report generator\n";
echo "  dataset: {$dsLabel}\n";
echo "  apps:    " . implode(', ', $store->apps()) . "\n";
echo "  modes:   " . implode(', ', $store->modes()) . "\n\n";

// --- Render views ----------------------------------------------------------

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create output dir: {$outDir}\n");
    exit(1);
}

$views = $manifest['views'];
if ($onlyView !== null) {
    if (!isset($views[$onlyView])) {
        fwrite(STDERR, "Unknown view: {$onlyView}\n");
        exit(1);
    }
    $views = [$onlyView => $views[$onlyView]];
}

$viewFiles = []; // viewKey => html filename
$mdFiles   = []; // viewKey => md filename
$svgByView = []; // viewKey => [chartKey => rel path]

foreach ($views as $key => $view) {
    // A view may pin its own dataset (e.g. the php-fpm view reads the
    // deployments relabelling of the canonical run). Resolve it lazily so
    // one generator invocation can mix datasets across views; views without
    // a dataset entry use the CLI/default store selected above.
    $viewDsKey = $view['dataset'] ?? null;
    if ($viewDsKey !== null && $viewDsKey !== $dsLabel) {
        if (!isset($manifest['datasets'][$viewDsKey])) {
            fwrite(STDERR, "View {$key} declares unknown dataset: {$viewDsKey}\n");
            exit(1);
        }
        $viewStore = resolveDataset($manifest['datasets'][$viewDsKey]);
    } else {
        $viewStore = $store;
    }

    $svgDir = $outDir . '/svg/' . $key;
    $relDir = 'svg/' . $key;

    // Markdown first — it writes the SVGs.
    $md     = new MarkdownReport($viewStore, $key, $view);
    $mdBody = $md->render($svgDir, $relDir);
    $mdFile = $key . '.md';
    file_put_contents($outDir . '/' . $mdFile, $mdBody);
    $mdFiles[$key] = $mdFile;

    // Collect the SVG files that were produced.
    $chartFiles = [];
    foreach (scandir($svgDir) ?: [] as $f) {
        if (str_ends_with($f, '.svg')) {
            $chartFiles[basename($f, '.svg')] = $relDir . '/' . $f;
        }
    }
    $svgByView[$key] = $chartFiles;

    $html     = (new HtmlReport($viewStore, $manifest))->view($key, $view, $chartFiles);
    $htmlFile = 'view-' . $key . '.html';
    file_put_contents($outDir . '/' . $htmlFile, $html);
    $viewFiles[$key] = $htmlFile;

    echo "  ✓ {$key}: {$htmlFile}, {$mdFile}, " . count($chartFiles) . " charts [dataset: "
        . ($viewStore === $store ? $dsLabel : ($view['dataset'] ?? '?')) . "]\n";
}

// --- Index -----------------------------------------------------------------

$index = (new HtmlReport($store, $manifest))->index($viewFiles, $outDir);
file_put_contents($outDir . '/index.html', $index);
file_put_contents($outDir . '/.nojekyll', ''); // GitHub Pages: skip Jekyll
echo "  ✓ index.html (+ .nojekyll)\n";

// --- Publish ---------------------------------------------------------------

if ($publish !== null) {
    $published = publish($publish, $manifest['views'], $views, $outDir, $svgByView, $mdFiles, $store);
    foreach ($published as $line) {
        echo "  → {$line}\n";
    }
}

echo "\nWrote {$outDir}\n";

// --- helpers ---------------------------------------------------------------

function resolveDataset(array $ds): ResultStore
{
    $store = tryResolveDataset($ds);
    if ($store === null) {
        fwrite(STDERR, "Dataset not found (file missing or glob empty).\n");
        exit(1);
    }
    return $store;
}

function tryResolveDataset(array $ds): ?ResultStore
{
    if (isset($ds['file'])) {
        return is_file($ds['file']) ? ResultStore::load($ds['file']) : null;
    }
    if (isset($ds['glob'])) {
        $files = glob($ds['glob']) ?: [];
        return $files === [] ? null : ResultStore::merge($files);
    }
    return null;
}

/**
 * Publish views that declare $target into the framework repo.
 *
 * @return list<string> log lines
 */
function publish(
    string $target,
    array $allViews,
    array $rendered,
    string $outDir,
    array $svgByView,
    array $mdFiles,
    ResultStore $store
): array {
    $log = [];
    if ($target !== 'framework') {
        return ["unknown publish target: {$target}"];
    }

    $framework = dirname(__DIR__, 2) . '/azera-framework';
    if (!is_dir($framework)) {
        return ["azera-framework not found at {$framework}"];
    }
    $imagesDir = $framework . '/docs/images/benchmarks';
    if (!is_dir($imagesDir) && !mkdir($imagesDir, 0777, true) && !is_dir($imagesDir)) {
        return ["cannot create {$imagesDir}"];
    }

    foreach ($rendered as $key => $view) {
        if (!in_array($target, $view['publish'] ?? [], true)) {
            continue;
        }
        // Each published view gets its own markdown file + image subfolder:
        // the views share chart filenames (startup.svg, feature-orm.svg, …)
        // but describe different deployment models, so a flat copy would
        // silently overwrite the first story with the second.
        $publishMd = ($view['publish_md'] ?? null) ?? ($key === 'azera-vs-all' ? '19-BENCHMARKS.md' : "19-BENCHMARKS-{$key}.md");
        $imagesDir = $framework . '/docs/images/benchmarks/' . $key;
        if (!is_dir($imagesDir) && !mkdir($imagesDir, 0777, true) && !is_dir($imagesDir)) {
            $log[] = "cannot create {$imagesDir}";
            continue;
        }
        // Copy this view's SVGs + markdown fragment into the framework docs.
        foreach ($svgByView[$key] ?? [] as $chart => $rel) {
            copy($outDir . '/' . $rel, $imagesDir . '/' . basename($rel));
            $log[] = "copied svg {$key}/" . basename($rel);
        }
        $mdSrc = $outDir . '/' . $mdFiles[$key];
        $mdDst = $framework . '/docs/' . $publishMd;
        // Rewrite svg/ paths to docs/images/benchmarks/<view>/ for the
        // framework tree.
        $body = (string) file_get_contents($mdSrc);
        $body = str_replace('](svg/' . $key . '/', '](images/benchmarks/' . $key . '/', $body);
        file_put_contents($mdDst, $body);
        $log[] = "wrote docs/{$publishMd}";
    }

    return $log;
}