<?php
// Compare the dumped HTML structurally - the thing verify.php cannot do.
//
// The parity gate strips every tag before comparing, so it is blind to exactly
// the failures this benchmark is exposed to: a leaked `<use:...>` tag, a missing
// layout wrapper, a header rendered in the wrong place, an attribute on the
// wrong element. All of those normalise to identical TEXT.
//
// Three levels, reported separately, because they mean different things:
//
//   1. markup   - whitespace between tags collapsed. Strictest useful form.
//   2. DOM tree - both pages parsed and walked, and each node compared by
//                 element name, attributes and text. This is what a browser
//                 builds, so it is the authoritative answer. A difference here
//                 IS a rendering difference.
//   3. (level 1 differing while level 2 matches) = only the serializer's
//      spelling differs: a doctype, `<br />` vs `<br>`, spaces inside a tag.
//      Harmless, and named so it is not mistaken for a bug later.
//
// Usage:
//   php compare-dump.php
//   php compare-dump.php temp/render-dump twig stempler

$dir     = $argv[1] ?? __DIR__ . '/temp/render-dump';
$engines = array_slice($argv, 2);
$engines = $engines !== [] ? $engines : ['native', 'clarity', 'clarity-open', 'plates', 'blade', 'twig', 'stempler'];

/** Level 1: collapse whitespace between tags, keep the tags. */
function canon(string $html): string
{
    $s = preg_replace('/\s+/', ' ', $html) ?? '';
    $s = preg_replace('/>\s+</', '><', $s) ?? '';
    $s = preg_replace('/\s+>/', '>', $s) ?? '';

    return trim($s);
}

/**
 * Level 2: an ordered signature of the parsed tree.
 *
 * `C14N()` was the first attempt and it is the wrong tool here: it is a
 * canonicalisation of the SOURCE serialisation, so it keeps differences that no
 * browser cares about while its output is awkward to diff and it silently
 * mangles documents it dislikes. Walking the tree and emitting one line per node
 * gives a signature that is trivial to read, trivially deterministic, and
 * compares exactly the things that change a rendered page: element names, their
 * attributes, and their text.
 *
 * Whitespace-only text nodes are skipped - indentation is engine-specific and
 * invisible. All other text is normalised the same way in every document, so a
 * real content difference always shows up.
 *
 * Returns null when the document cannot be parsed, so "could not check" is never
 * reported as "equal".
 */
function treeSignature(string $html): ?string
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
                    $text = trim(preg_replace('/\s+/', ' ', $child->wholeText) ?? '');
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

/** The serializer spellings that differ between engines without mattering. */
function cosmeticNotes(string $raw, string $baselineRaw): array
{
    $notes = [];

    if (str_contains($raw, '<!doctype') !== str_contains($baselineRaw, '<!doctype')) {
        $notes[] = 'doctype';
    }

    // Self-closing tags are the common one, and it has two independent spellings:
    // whether the tag self-closes at all (`<meta>` vs `<meta/>`) and whether it
    // puts a space before the slash (`<meta />` vs `<meta/>`). Both produce the
    // same element, but they are different bytes, so they are named apart —
    // "unclassified spelling" is exactly the vagueness that lets a real bug hide.
    $selfCloses     = str_contains($raw, '/>');
    $baseSelfCloses = str_contains($baselineRaw, '/>');
    if ($selfCloses !== $baseSelfCloses) {
        $notes[] = $selfCloses ? 'self-closing tags' : 'no self-closing tags';
    }

    if ($selfCloses === $baseSelfCloses && $selfCloses) {
        $spaced     = (bool) preg_match('/\s\/>/', $raw);
        $baseSpaced = (bool) preg_match('/\s\/>/', $baselineRaw);
        if ($spaced !== $baseSpaced) {
            $notes[] = 'space before ' . '/>';
        }
    }

    return $notes;
}

$data = [];
foreach ($engines as $e) {
    $p = "$dir/$e.html";
    if (!is_file($p)) {
        fwrite(STDERR, "missing $p (run: php dump.php)\n");
        continue;
    }
    $raw = (string) file_get_contents($p);
    $data[$e] = [
        'raw'   => $raw,
        'canon' => canon($raw),
        'tree'  => treeSignature($raw),
    ];
}

if ($data === []) {
    fwrite(STDERR, "nothing to compare\n");
    exit(2);
}

$baseline = array_key_first($data);
echo "baseline: $baseline\n\n";

$failed = false;
foreach ($data as $e => $d) {
    if ($e === $baseline) {
        continue;
    }

    $sameMarkup = $d['canon'] === $data[$baseline]['canon'];
    $bothParsed = $d['tree'] !== null && $data[$baseline]['tree'] !== null;
    $sameTree   = $bothParsed && $d['tree'] === $data[$baseline]['tree'];

    if (!$bothParsed) {
        printf("%-9s UNKNOWN (could not parse)\n", $e);
        $failed = true;
        continue;
    }

    if ($sameMarkup) {
        printf("%-9s IDENTICAL markup\n", $e);
        continue;
    }

    if ($sameTree) {
        $notes = cosmeticNotes($d['raw'], $data[$baseline]['raw']);
        printf(
            "%-9s EQUIVALENT DOM - serializer only: %s\n",
            $e,
            $notes === [] ? 'unclassified spelling' : implode(', ', $notes)
        );
        continue;
    }

    // A real difference. Show the first differing NODE, which is far more
    // useful than a byte offset into serialised source.
    $a   = explode("\n", (string) $data[$baseline]['tree']);
    $b   = explode("\n", (string) $d['tree']);
    $at  = 0;
    $max = min(count($a), count($b));
    while ($at < $max && $a[$at] === $b[$at]) {
        $at++;
    }
    printf("%-9s DOM DIFFERS\n", $e);
    echo "  first different node (line " . ($at + 1) . ")\n";
    echo "    baseline: " . ($a[$at] ?? '(none)') . "\n";
    echo "    $e: " . ($b[$at] ?? '(none)') . "\n";
    $failed = true;
}

// Structural markers: present/absent per engine. These are what a strip_tags()
// comparison cannot see at all.
$markers = [
    'layout' => '<div class="page">',
    'header' => '<header>',
    'h1'     => '<h1>',
    'count'  => 'Items:',
    'extras' => '<div class="extras">',
    'li'     => '<li',
];

echo "\n--- structural markers ---\n";
printf("%-10s", 'engine');
foreach (array_keys($markers) as $label) {
    printf("%-8s", $label);
}
echo "\n";
foreach ($data as $e => $d) {
    printf("%-10s", $e);
    foreach ($markers as $needle) {
        printf("%-8s", str_contains($d['raw'], $needle) ? 'yes' : 'NO');
    }
    echo "\n";
}

// Template-syntax leakage: any of these appearing in OUTPUT means the template
// was not processed. This is the failure mode that renders "nearly fine".
$leaks = ['<use', 'use:element', '<extends', '<block:', '@foreach', '@if', '@for', '{{', '{%'];
echo "\n--- template-syntax leakage ---\n";
foreach ($data as $e => $d) {
    $found = array_values(array_filter($leaks, fn($p) => str_contains($d['raw'], $p)));
    printf("%-9s %s\n", $e, $found === [] ? 'clean' : 'LEAKED: ' . implode(', ', $found));
    if ($found !== []) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
