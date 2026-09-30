<?php

/**
 * The pages the view-engine harness measures.
 *
 * ONE table, required by every participant (run.php, peak-probe.php,
 * verify.php). An earlier draft declared the table in run.php and again in
 * peak-probe.php and relied on the parity gate to notice a divergence — which is
 * the wrong shape: the memory probe is spawned by the harness and would then be
 * measuring a page whose definition it holds a private copy of, so a typo would
 * produce a plausible number for the wrong work. A shared file cannot diverge.
 *
 * One page per RENDERING SHAPE, because a single page cannot separate them:
 *
 *   sample   - loops over scalars with a filter, plus a nested loop. What the
 *              published figures have always measured. Its template and data are
 *              FROZEN: every headline figure, and every chart the consumer repos
 *              embed, was measured on this page, so it is never reworded.
 *   mixed    - the heavy page: 20 flat variables and 20 rows of six fields each.
 *              It replaced the former `escaping` page, and absorbed its job:
 *              EVERY value carries HTML-special characters, so the escape path
 *              does real work and an engine that escapes is distinguishable from
 *              one that does not. An escaper and a non-escaper are identical on
 *              `items = [1, 2, 3]` — they differ only on data that needs
 *              escaping — so the escaping property lives here now, on a page
 *              whose WIDTH is also the point.
 *
 *              WIDTH is the point, and the count is deliberate rather than
 *              incidental: every one of the 20 variables is READ TWICE — once as
 *              text and once as a `data-value` attribute — for 40 flat accesses
 *              per render, and the row loop adds 7 field reads per row (1400 at
 *              the default 200 items, i.e. 1440 variable accesses in total).
 *              Against `sample`'s handful, that is the width at which a
 *              per-access cost shows up instead of being lost in render noise —
 *              and a per-access cost is exactly what the two Clarity MODES
 *              differ in, so this page and those two entrants belong together.
 *
 *              The second access is an ATTRIBUTE rather than a repeated echo so
 *              that it is genuinely different work: the value is emitted in a
 *              different escape context, the DOM gains an attribute a wrong
 *              template cannot fake, and the parity gate therefore sees a
 *              missing second access as a rendering difference.
 *
 *              It superseded `escaping` rather than joining it because the two
 *              were structural twins — same layout, same partial, same loop
 *              nesting — differing only in their data, which made a second
 *              nearly-identical page expensive to keep equivalent across six
 *              templates and cheap to replace.
 *
 *   entities - objects instead of scalars: a loop over entities reading two
 *              properties, a nested object, a nullable property behind a
 *              default, and an array-inside-an-object loop. This is the shape
 *              most real templates are, and it is the only page where property
 *              access itself is on the clock.
 *   entities-array - the SAME page content in the SAME order with the SAME
 *              values, held as nested arrays instead of objects, and therefore
 *              accessed by KEY instead of by property. Comparing these two pages
 *              separates "how expensive is property access" from "how expensive
 *              is everything around it".
 *
 *              It exists because Clarity USED TO convert the whole variable tree
 *              to arrays on every render, which made the objects page measure
 *              that conversion rather than property access. That conversion is
 *              gone (the compiler emits real `$item->label` property reads), so
 *              the original motivation no longer holds — but the pair is still
 *              informative, because it is the same page in two access shapes and
 *              a difference between them is now attributable to the access shape
 *              alone.
 *
 * Adding a page is ADDITIVE: `sample` keeps its exact template and data, so no
 * figure measured before this file existed becomes incomparable with one
 * measured after it. Removing one is not: the `escaping` page is gone, so the
 * escaping figures it used to publish are not comparable with this file's.
 *
 * `vars` is a closure of the item count, so the existing --items knob scales
 * every page and the budget stays recorded in `env`.
 *
 * @return array<string,array{template:string,vars:callable(int):array}>
 */

// The entity fixtures are required here rather than inside the closure: a
// closure that returned objects would need the classes at call time, and every
// consumer (harness, memory probe, parity gate) therefore loads them through
// this one file instead of three separate require lines that could go stale.
require_once __DIR__ . '/templates/fixtures.php';

return [
    'sample' => [
        'template' => 'benchmarks::sample',
        'vars'     => static fn(int $items): array => [
            'title' => 'Benchmark',
            'items' => range(1, $items),
        ],
    ],

    'mixed' => [
        'template' => 'benchmarks::mixed',
        'vars'     => static function (int $items): array {
            $vars = benchmarkMixedVars($items);
            $vars['title'] = 'Benchmark <b>&"x"</b>';

            return $vars;
        },
    ],

    'entities' => [
        'template' => 'benchmarks::entities',
        'vars'     => static function (int $items): array {
            $vars = benchmarkEntityVars($items);
            $vars['title'] = 'Benchmark';

            return $vars;
        },
    ],

    'entities-array' => [
        'template' => 'benchmarks::entities-array',
        'vars'     => static function (int $items): array {
            $vars = benchmarkEntityArrayVars($items);
            $vars['title'] = 'Benchmark';

            return $vars;
        },
    ],
];
