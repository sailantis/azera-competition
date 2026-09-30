<?php
// Render one page with every engine and WRITE the HTML, so the result can be
// looked at rather than only compared.
//
// Why this exists next to verify.php: the parity gate is the right guard but a
// poor debugging tool. It normalises each engine's output with strip_tags() and
// a whitespace collapse, so it can only say OK or MISMATCH - it cannot show you
// WHAT differs, and it cannot show you anything when the answer is OK. The
// differences that matter here are exactly the ones strip_tags() destroys: a
// leaked `<use:...>` tag, a missing layout wrapper, an un-escaped `&`, or a
// header rendered in the wrong place. All of those normalise to identical text.
//
// The engine setup now comes from the SHARED factory rather than a copy here.
// It used to be duplicated consciously, pinned by a test that required both files
// to build an engine the same way — but a test can only check what it is told to
// check, and a fourth private copy (this file) is how `clarity-open` had to be
// taught to four places. `engines.php` is the one definition now.
//
// Usage:
//   php dump.php                       # 5 items, into temp/render-dump/
//   php dump.php --items=200           # the benchmark's own item count
//   php dump.php --engines=twig,stempler
//   php dump.php --out=/tmp/dump       # absolute or relative to benchmarks/view-engine/

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/engines.php';

$opts    = getopt('', ['engines::', 'items::', 'out::', 'page::']);
$engines = isset($opts['engines'])
    ? explode(',', $opts['engines'])
    : ['native', 'clarity', 'plates', 'blade', 'twig', 'stempler'];
// Small by default: this is for reading, not for measuring. `--items=200` prints
// the real thing and is mostly useful for checking that nothing is truncated.
$itemsCount = isset($opts['items']) ? (int) $opts['items'] : 5;

// The page comes from the SAME table the harness measures, not a copy here. A
// private copy is how a debugging tool ends up dumping a page no run ever
// measured — confidently showing something that is not the benchmark.
$pages   = require __DIR__ . '/pages.php';
$pageKey = $opts['page'] ?? array_key_first($pages);
if (!isset($pages[$pageKey])) {
    fwrite(STDERR, "unknown page '{$pageKey}'; known: " . implode(', ', array_keys($pages)) . "\n");
    exit(1);
}

$outDir = $opts['out'] ?? __DIR__ . '/temp/render-dump';
if (!str_starts_with($outDir, '/') && !preg_match('/^[A-Za-z]:[\\\\\\/]/', $outDir)) {
    $outDir = __DIR__ . '/' . $outDir;
}

$template = $pages[$pageKey]['template'];
$viewPath = __DIR__ . '/templates';

if (!is_dir($outDir) && !mkdir($outDir, 0777, true) && !is_dir($outDir)) {
    fwrite(STDERR, "cannot create output directory: $outDir\n");
    exit(1);
}

/**
 * Build one engine through the factory the harness measures with.
 *
 * A thin wrapper rather than a switch: the whole point is that this tool cannot
 * build a DIFFERENT engine than the benchmark ran. The penalty is built from the
 * SAME template name the run measures rather than from a literal, so a renamed
 * page cannot make this tool translate a different view.
 *
 * @return array{0:\Azera\Core\ViewEngine,1:?callable}
 */
function makeEngine(string $key, string $template): array
{
    $built = benchmarkEngine($key, $template);

    return [$built['engine'], $built['penalty']];
}

/** The gate's normalisation, reused so this tool and verify.php agree. */
function normalise(string $html): string
{
    return preg_replace('/\s+/', ' ', trim(strip_tags($html))) ?? '';
}

$vars     = $pages[$pageKey]['vars']($itemsCount);
$rendered = [];
$baseline = null;
$failed   = false;

foreach ($engines as $key) {
    $key = trim($key);
    try {
        [$engine, ] = makeEngine($key, $template);
        $engine->setViewPath($viewPath);
        $engine->addNamespace('benchmarks', $viewPath);

        // Same cold-cache reset run.php performs, so the dump shows what the
        // benchmark measured rather than whatever a previous process left cached.
        if (method_exists($engine, 'flushCache')) {
            try {
                $engine->flushCache();
            } catch (\LogicException) {}
        }

        $html = $engine->render($template, $vars);
    } catch (Throwable $e) {
        printf("%-9s ERROR  %s: %s\n", $key, (new ReflectionClass($e))->getShortName(), $e->getMessage());
        $failed = true;
        continue;
    }

    file_put_contents("$outDir/$key.html", $html);
    $rendered[$key] = $html;
    $norm = normalise($html);
    if ($baseline === null) {
        $baseline = $norm;
        $verdict  = 'baseline';
    } elseif ($norm === $baseline) {
        $verdict = 'same text';
    } else {
        $verdict = 'TEXT DIFFERS';
        $failed  = true;
    }

    printf("%-9s %6d bytes  %s\n", $key, strlen($html), $verdict);
}

// A side-by-side page. Each engine gets its own iframe (so the browser renders
// it with its own base styles) plus its escaped source, because the two failures
// look different: a leaked tag is obvious in the source and nearly invisible
// rendered, while a missing layout is obvious rendered and easy to skim past in
// source.
$cards = '';
foreach ($rendered as $key => $html) {
    $cards .= '<section><h2>' . htmlspecialchars($key) . '</h2>'
        . '<iframe sandbox="" srcdoc="' . htmlspecialchars($html, ENT_QUOTES) . '"></iframe>'
        . '<details><summary>source</summary><pre>' . htmlspecialchars($html) . '</pre></details>'
        . '</section>' . "\n";
}

$page = '<!doctype html><html><head><meta charset="utf-8"><title>view-engine render dump</title>'
    . '<style>body{font:14px/1.5 system-ui,sans-serif;margin:0;padding:20px;background:#f6f7f9}'
    . 'section{background:#fff;border:1px solid #d8dde5;border-radius:8px;margin:0 0 16px;padding:12px}'
    . 'h2{margin:0 0 8px;font-size:15px;text-transform:uppercase;letter-spacing:.04em;color:#334}'
    . 'iframe{width:100%;height:150px;border:1px solid #e2e6ec;border-radius:4px;background:#fff}'
    . 'pre{max-height:340px;overflow:auto;background:#f2f4f7;padding:10px;border-radius:4px;font-size:12px}'
    . 'summary{cursor:pointer;color:#0b7285;margin:8px 0}</style></head><body>'
    . '<h1>view-engine render dump</h1>'
    . '<p>' . count($rendered) . ' engine(s) &middot; page <code>' . htmlspecialchars($pageKey)
    . '</code> &middot; ' . $itemsCount . ' items &middot; written to <code>'
    . htmlspecialchars($outDir) . '</code></p>'
    . $cards . '</body></html>';

file_put_contents("$outDir/preview.html", $page);

echo "\nwrote $outDir/{engine}.html + preview.html\n";
echo "open: $outDir/preview.html\n";

exit($failed ? 1 : 0);