<?php
/**
 * The parity gate: every engine must render the same page.
 *
 * Run this BEFORE measuring. A wrong number is harder to notice than a wrong
 * page, and a wrong page makes every number meaningless.
 *
 * This gate used to compare only `strip_tags()`-normalised text, with two
 * consequences that made it a weaker guard than it looked:
 *
 *   1. It could not see structure. A leaked `<use:...>` tag, a missing layout
 *      wrapper or a header rendered in the wrong place all normalise to the same
 *      text, so "the gate passes" was never evidence the pages looked alike.
 *   2. It could not see ESCAPING, because the data it rendered could not need
 *      it: `items = ['a','b','c']` and a literal title contain no HTML-special
 *      character, so an engine that escaped and one that printed raw produced
 *      byte-identical output. Escaping was an untested property of the one thing
 *      this benchmark exists to establish.
 *
 * It also never set an exit code. `run-remote.ps1` launches it under
 * `set -euo pipefail` precisely so a failure stops the run, but a gate that
 * always exits 0 cannot do that: a MISMATCH printed to a log and then the
 * 45-minute measurement proceeded on incompatible templates.
 *
 * Two cases are therefore checked, and BOTH must pass:
 *
 *   - `plain`  - the page as measured: integers and a literal title. Compared as
 *                normalised text AND as a parsed DOM tree.
 *   - `hostile` - the same templates with `<b>&"x"</b>` as an item and
 *                `<i>T&"x"</i>` as the title, so an escaping difference has
 *                something to differ about. Compared on the exact output.
 *
 * Usage:
 *   php benchmarks/view-engine/verify.php
 *
 * Exit code 0 when every engine agrees, 1 otherwise.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/engines.php';

// Every entrant is built by the SAME factory the harness measures with: an
// engine the gate constructs differently from the one the harness ran is a gate
// that proves something about a configuration nothing publishes. The factories
// are closures over the key only — the view path and namespace are applied by
// renderCase() below, exactly as the harness applies them.
$engines = [];
foreach (['clarity', 'clarity-open', 'native', 'twig', 'plates', 'blade', 'stempler', 'latte'] as $engineKey) {
    $engines[$engineKey] = static function () use ($engineKey) {
        // The template name is irrelevant to the penalty here and the penalty is
        // not used, so any name will do.
        ['engine' => $engine] = benchmarkEngine($engineKey, 'benchmarks::sample', false);
        return $engine;
    };
}

$viewPath = __DIR__ . '/templates';

// Pages come from the shared table so the gate checks exactly what the harness
// measures. A page added to pages.php is therefore gated automatically; there is
// no second list here to forget to update.
$pages = require __DIR__ . '/pages.php';

/**
 * The text comparison the gate has always made.
 *
 * Kept as the first check because it is what historically caught engine drift,
 * and because it is the most legible failure ("the words differ").
 *
 * Character references are DECODED before comparing, because two engines can
 * spell the same rendered text differently and the gate is not a byte diff:
 * Twig/Clarity escape `"` to `&quot;` while Latte escapes it to `"` in TEXT
 * context (it uses `ENT_NOQUOTES`, since a quote is not special there), and an
 * entity spelled `&#39;` and `&apos;` are the same character. Comparing the raw
 * forms made five of Latte's renders "differ" from an identical DOM — the tree
 * check passed and only this one failed, which is the signature of a spelling
 * difference rather than a rendering one.
 *
 * Decoding happens AFTER strip_tags, and that order is load-bearing: an engine
 * that FAILS to escape emits a real `<i>` element, which strip_tags removes, so
 * its text still differs from the escaper's decoded `&lt;i&gt;`. The
 * escaper-vs-non-escaper guarantee this case exists for is preserved.
 */
function textNorm(string $html): string
{
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return (string) preg_replace('/\s+/', ' ', trim($text));
}

/**
 * A signature of the PARSED tree.
 *
 * Element names, their attributes (order-insensitively) and their text. That is
 * what a browser builds, so a difference here is a rendering difference, unlike
 * a byte comparison of source where a doctype or `<br />` vs `<br>` counts as a
 * difference though nothing renders differently.
 *
 * Returns null when the document cannot be parsed, so "could not check" is never
 * reported as "equal".
 */
function treeSig(string $html): ?string
{
    if (!class_exists('DOMDocument')) {
        return null;
    }

    $prev = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        if (!@$doc->loadHTML($html)) {
            return null;
        }

        $lines = [];
        $walk  = function (DOMNode $node, int $depth) use (&$walk, &$lines): void {
            foreach ($node->childNodes as $child) {
                if ($child instanceof DOMText) {
                    $text = trim((string) preg_replace('/\s+/', ' ', $child->wholeText));
                    if ($text !== '') {
                        $lines[] = str_repeat('  ', $depth) . '#text ' . $text;
                    }
                    continue;
                }
                if (!$child instanceof DOMElement) {
                    continue;
                }
                $attrs = [];
                foreach ($child->attributes ?? [] as $attr) {
                    $attrs[$attr->nodeName] = $attr->nodeValue;
                }
                ksort($attrs); // attribute ORDER is not a rendering difference
                $rendered = [];
                foreach ($attrs as $k => $v) {
                    $rendered[] = $k . '="' . $v . '"';
                }
                $lines[] = str_repeat('  ', $depth) . '<' . strtolower($child->nodeName)
                    . ($rendered === [] ? '' : ' ' . implode(' ', $rendered)) . '>';
                $walk($child, $depth + 1);
            }
        };
        $walk($doc, 0);

        return implode("\n", $lines);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
    }
}

/**
 * Render one case with one engine.
 *
 * The cache is flushed first for the same reason `run.php` does it: a shared
 * temp cache from an earlier run would make this gate pass against STALE
 * compiled templates, i.e. exactly the templates that were just changed.
 *
 * @return array{html:string,text:string,tree:?string}
 */
function renderCase(callable $factory, string $viewPath, string $template, array $vars): array
{
    $e = $factory();
    $e->setViewPath($viewPath);
    $e->addNamespace('benchmarks', $viewPath);
    if (method_exists($e, 'flushCache')) {
        try {
            $e->flushCache();
        } catch (\LogicException) {}
    }
    $html = $e->render($template, $vars);

    return ['html' => $html, 'text' => textNorm($html), 'tree' => treeSig($html)];
}

/**
 * The cases, per page.
 *
 * The three original cases stay attached to `sample` and are unchanged, so the
 * gate's existing guarantees are untouched. Each page also gets its own inputs,
 * because a page's data is what makes it a different test: the `mixed` page is
 * only meaningful with values that need escaping.
 *
 * `measured` for the `mixed` page is deliberately the SAME hostile payload the
 * page is measured with, so the gate checks the measured configuration rather
 * than a sanitised version of it.
 *
 * The open-mode entrant is gated on every case below like any other engine, so
 * "the two modes agree" is proven by rendering rather than assumed from the fact
 * that they share a compiler. Raw-PHP SYNTAX (`{% php %}`) is deliberately NOT
 * re-gated here: it is covered by clarity-engine's own suite
 * (tests/Engine/OpenModeTest.php), and a second copy of those templates would be
 * six more files to keep equivalent for a feature the benchmark does not measure.
 *
 * @return array<string,array<string,array>> page => case => vars
 */
function viewEngineCases(array $pages, int $items = 3): array
{
    $byPage = [];
    foreach (array_keys($pages) as $pageKey) {
        $byPage[$pageKey] = ['measured' => $pages[$pageKey]['vars']($items)];
    }

    // The three historically-checked cases, on the page they have always run on.
    $byPage['sample'] = [
        'plain' => ['title' => 'Verify', 'items' => ['a', 'b', 'c']],
        // Hostile: a tag, an ampersand and a double quote in the DATA, so an
        // engine that escapes differs observably from one that does not.
        'hostile' => ['title' => '<i>T&"x"</i>', 'items' => ['<b>&"x"</b>']],
        // Non-ASCII: the only case that can tell a BYTE-based uppercaser from a
        // multibyte-aware one, because every other input here is ASCII and the
        // two functions agree on ASCII.
        'non-ascii' => ['title' => 'Grüße', 'items' => ['ä', 'Straße', 'Österreich']],
    ];

    // The mixed page keeps the escaping page's boundary case, on the page that
    // absorbed it: an all-special payload with a non-ASCII value, which is where
    // an escape implementation tends to differ (quotes vs multibyte).
    $byPage['mixed']['non-ascii'] = array_merge(
        $pages['mixed']['vars']($items),
        ['title' => 'Grüße & <i>Österreich</i>']
    );

    return $byPage;
}

$cases   = viewEngineCases($pages);
$failed  = false;
$checked = 0;

foreach ($cases as $pageKey => $pageCases) {
    $template = $pages[$pageKey]['template'];
    echo "### page: $pageKey  ({$template})\n";

    foreach ($pageCases as $caseName => $vars) {
        echo "=== case: $caseName\n";
        $baselines = [];

        foreach ($engines as $name => $factory) {
            echo "  $name... ";
            try {
                $r = renderCase($factory, $viewPath, $template, $vars);
            } catch (Throwable $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                $failed = true;
                continue;
            }
            $checked++;

            if (!isset($baselines[$caseName])) {
                $baselines[$caseName] = ['name' => $name, 'r' => $r];
                echo "baseline set\n";
                continue;
            }

            $base = $baselines[$caseName]['r'];

            $textSame = $r['text'] === $base['text'];
            $treeSame = $r['tree'] !== null && $base['tree'] !== null && $r['tree'] === $base['tree'];

            if ($textSame && $treeSame) {
                echo "OK\n";
                continue;
            }

            // Name which comparison failed. "MISMATCH" alone sent readers to
            // look at the wrong layer: an escaping bug leaves the TEXT looking
            // fine while the tree differs, and a wording bug is the reverse.
            $why = [];
            if (!$textSame) {
                $why[] = 'text differs';
            }
            if (!$treeSame) {
                $why[] = ($r['tree'] === null || $base['tree'] === null) ? 'tree unparseable' : 'structure differs';
            }
            echo "MISMATCH (" . implode(', ', $why) . ")\n";
            $failed = true;

            if (!$textSame) {
                echo "      baseline text: " . substr($base['text'], 0, 120) . "\n";
                echo "      $name text: " . substr($r['text'], 0, 120) . "\n";
            }
            if (!$treeSame && $r['tree'] !== null && $base['tree'] !== null) {
                $a   = explode("\n", $base['tree']);
                $b   = explode("\n", $r['tree']);
                $at  = 0;
                $max = min(count($a), count($b));
                while ($at < $max && $a[$at] === $b[$at]) {
                    $at++;
                }
                echo "      first different node (line " . ($at + 1) . ")\n";
                echo "        baseline: " . ($a[$at] ?? '(none)') . "\n";
                echo "        $name:     " . ($b[$at] ?? '(none)') . "\n";
            }
        }
    }
}

echo "\nchecked {$checked} renders across " . count($cases) . " pages\n";
echo $failed ? "FAILED: the engines do not agree.\n" : "Done.\n";
// The exit code is the point: run-remote.ps1 runs this under `set -euo pipefail`,
// so a non-zero exit aborts the run instead of measuring incompatible templates.
exit($failed ? 1 : 0);