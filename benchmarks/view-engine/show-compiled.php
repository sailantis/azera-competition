<?php

/**
 * Show the PHP that a template engine COMPILES a page into.
 *
 * Clarity and Stempler both compile to plain PHP class files on disk, so "what
 * does the compiled template look like" is answerable by pointing them at a
 * private directory and reading the result. Doing that by hand is fiddly for
 * three reasons, all handled here:
 *
 *  1. Both default to a SHARED temp cache (`/tmp/clarity_cache`,
 *     `/tmp/stempler_cache`), which holds hundreds of files from earlier runs.
 *     Rendering without redirecting the cache finds nothing new to look at and
 *     shows you some other run's output. A private, freshly-emptied directory is
 *     used instead.
 *  2. The cache FILENAME is a hash, not the template name (Clarity uses
 *     md5(name) in a two-character bucket dir), so the file cannot be guessed
 *     from the template name — it has to be discovered by listing.
 *  3. A compile only happens on a MISS, and a class already declared in a
 *     process cannot be redeclared, so the reading must come from files written
 *     during this process, not from the in-memory registry.
 *
 * Usage:
 *   php benchmarks/view-engine/show-compiled.php <engine> <page> [--out=DIR]
 *
 *   <engine>  clarity | stempler (anything with setCachePath(); others keep no
 *             compiled PHP on disk and the tool says so rather than pretending)
 *   <page>    a key from pages.php (sample, escaping, entities, entities-array)
 *   --out=DIR also COPY the compiled files under DIR, keeping their relative
 *             paths, so they can be opened in an editor / diffed.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Azera\Core\Engines\Adapters\StemplerAdapter;
use Azera\Core\Engines\ClarityEngine;

$args      = array_slice($argv, 1);
$engineKey = 'clarity';
$pageKey   = 'sample';
$outDir    = null;

foreach ($args as $arg) {
    if (str_starts_with($arg, '--out=')) {
        $outDir = substr($arg, 6);
        continue;
    }
    if ($engineKey === 'clarity' && $pageKey === 'sample' && $arg !== '') {
        // First positional is the engine only if it is a known one; otherwise
        // treat it as the page, so `show-compiled.php entities` works too.
        if (in_array($arg, ['clarity', 'stempler'], true)) {
            $engineKey = $arg;
            continue;
        }
    }
    $pageKey = $arg;
}

$pages = require __DIR__ . '/pages.php';
if (!isset($pages[$pageKey])) {
    fwrite(STDERR, "unknown page '{$pageKey}'. Known: " . implode(', ', array_keys($pages)) . "\n");
    exit(1);
}

$viewPath = __DIR__ . '/templates';
$items    = 5; // small, so the compiled loop is short enough to read
$template = $pages[$pageKey]['template'];
$vars     = $pages[$pageKey]['vars']($items);

switch ($engineKey) {
    case 'clarity':
        $view = new ClarityEngine();
        $view->setExtension('.clarity.html');
        break;
    case 'stempler':
        $view = new StemplerAdapter();
        break;
    default:
        fwrite(STDERR, "engine '{$engineKey}' is not supported here.\n");
        exit(1);
}

// A private, EMPTY cache directory, so what we list is what THIS render compiled
// and not whatever the machine's shared cache happened to hold.
$cacheDir   = sys_get_temp_dir() . '/ve-show-compiled-' . $engineKey . '-' . $pageKey . '-' . getmypid();
$removeTree = static function (string $dir) use (&$removeTree): void {
    if (!is_dir($dir)) {
        return;
    }
    foreach (@scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $removeTree($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($dir);
};
$removeTree($cacheDir);
mkdir($cacheDir, 0777, true);

$view->setViewPath($viewPath)->addNamespace('benchmarks', $viewPath);
$view->setCachePath($cacheDir);

echo "engine     : {$engineKey}\n";
echo "page       : {$pageKey}  (template: {$template}, items: {$items})\n";
echo "cache dir  : {$cacheDir}\n";

// Render once to force the compile. Nothing is measured here, so no timer.
$html = $view->render($template, $vars);
echo "rendered   : " . strlen($html) . " bytes of HTML\n\n";

// Discover what was written. Clarity nests one bucket dir deep; Stempler may
// nest differently, so this walks the tree rather than assuming a depth.
$files = [];
if (is_dir($cacheDir)) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iter as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }
}
sort($files);

if ($files === []) {
    echo "No compiled files were written. This engine keeps no compiled PHP on\n";
    echo "disk (Native and Plates evaluate their templates directly and their\n";
    echo "setCachePath() throws by design), or its cache is configured off.\n";
    $removeTree($cacheDir);
    exit(0);
}

printf("=== compiled files: %d ===\n", count($files));
foreach ($files as $file) {
    printf("  %-52s %6d bytes\n", substr($file, strlen($cacheDir) + 1), filesize($file));
}
echo "\n";

foreach ($files as $file) {
    $rel  = substr($file, strlen($cacheDir) + 1);
    $code = (string) file_get_contents($file);
    echo str_repeat('=', 78) . "\n";
    echo "### {$rel}\n";
    echo str_repeat('=', 78) . "\n";
    echo $code;
    if (!str_ends_with($code, "\n")) {
        echo "\n";
    }
    echo "\n";
}

if ($outDir !== null) {
    // Relative path by SUBSTRING, not str_replace($cacheDir . '/'): the cache
    // directory path mixes separators on Windows (`C:\...\Temp/ve-show-...`),
    // so the joined-prefix form silently fails to match and every "relative"
    // path kept its absolute prefix — which then made copy() write to a path
    // containing a drive letter and fail, while this function reported success.
    $copied = 0;
    foreach ($files as $file) {
        $rel  = substr($file, strlen($cacheDir) + 1);
        $rel  = str_replace('\\', '/', $rel);
        $dest = rtrim($outDir, '/\\') . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0777, true);
        }
        if (!copy($file, $dest)) {
            fwrite(STDERR, "copy failed: {$file} -> {$dest}\n");
            continue;
        }
        $copied++;
    }
    echo "copied {$copied}/" . count($files) . " file(s) under: {$outDir}\n";
}

// Tidy up unless the caller asked for a copy: the point of the private dir is to
// avoid accumulating exactly the kind of shared clutter that made this tool
// necessary.
$removeTree($cacheDir);
