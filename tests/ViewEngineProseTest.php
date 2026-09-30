<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Measured figures must not appear in hand-written prose.
 *
 * This is the report layer's central contract, and the view-engine comparison
 * violated it in the worst possible way: three repositories each carried a
 * HAND-TYPED table of the same measurements, nothing recomputed them when a
 * dataset changed, and the copies drifted apart by roughly a factor of two
 * (azera-framework's docs/03b quoted one run, clarity-engine's README another).
 * A reader had no way to tell which — if either — was current.
 *
 * The mechanism of the drift is not carelessness, it is structural: a chart and
 * a generated table are BUILT FROM the dataset, so they cannot disagree with it,
 * while a sentence is not recomputed by anything. A figure typed into one
 * therefore outlives the run it describes, silently.
 *
 * So the rule is: a number may appear inside a generated region (a chart, or the
 * table the generator emits) and nowhere else. These tests check the consumer
 * documents against exactly that, INSIDE their generated region vs OUTSIDE it,
 * because the distinction is the whole point — the region legitimately carries
 * every figure, and that is what makes the surrounding prose safe to read.
 *
 * One consumer document has NO region at all. Clarity's README is hand-written
 * prose that embeds two GENERATED charts and describes them in its own words;
 * the generator does not touch its Markdown. That is a stronger guarantee than a
 * region, not a weaker one — there is nothing in it that a dataset change could
 * invalidate except the two charts, which are regenerated — so the README is
 * checked for the opposite property: it must state NO figure, because nothing
 * recomputes it.
 */
final class ViewEngineProseTest extends TestCase
{
    /**
     * A measured figure as it would appear in a sentence: a decimal, or an
     * integer carrying a unit.
     *
     * The trailing boundary is deliberately ABSENT. A word boundary cannot
     * follow `%` — both it and the following space are non-word characters — so
     * a `\b`-terminated pattern silently misses EVERY percentage. That mistake
     * shipped once already in the framework report's guard, where injecting
     * "stays under 1%" left the test green.
     */
    private const FIGURE = '/\b\d+\.\d+|\b\d+\s*(?:ms|MB|%)/';

    /**
     * The same, but a UNIT IS REQUIRED — used for the consumer documents.
     *
     * A README is not a report: it legitimately names versions and requirements
     * ("PHP 8.1 or higher", "no dependencies beyond PHP 8.1+"), and a bare
     * decimal pattern flags every one of them. That is a false positive that
     * would push an author to reword a correct sentence, so the consumer check
     * asks the narrower question — does this sentence quote a MEASUREMENT?
     * — by requiring the unit a measurement always carries.
     *
     * The fully-generated page keeps the stricter pattern above, because nothing
     * on a generated page needs to name a version outside the provenance stamp.
     */
    private const FIGURE_UNIT = '/\b\d+(?:\.\d+)?\s*(?:ms|MB|%)/';

    private const BEGIN = '<!-- view-engine:begin -->';
    private const END = '<!-- view-engine:end -->';

    private static function sibling(string $path): string
    {
        return dirname(__DIR__, 2) . '/' . $path;
    }

    /**
     * Split a document into the generated region and everything else.
     *
     * @return array{inside:string,outside:string}
     */
    private static function split(string $body): array
    {
        $begin = strpos($body, self::BEGIN);
        $end   = strpos($body, self::END);
        if ($begin === false || $end === false) {
            return ['inside' => '', 'outside' => $body];
        }

        return [
            'inside'  => substr($body, $begin, $end - $begin),
            'outside' => substr($body, 0, $begin) . substr($body, $end + strlen(self::END)),
        ];
    }

    /**
     * Strip the things that legitimately carry numbers around the region:
     * fenced/inline code, link targets, and the version-stamp lines the
     * generator emits.
     */
    private static function proseOnly(string $s): string
    {
        $s = (string) preg_replace('/```.*?```/s', '', $s);      // fenced code
        $s = (string) preg_replace('/`[^`]*`/', '', $s);         // inline code
        $s = (string) preg_replace('/\]\([^)]*\)/', '](…)', $s); // link targets
        $s = (string) preg_replace('/<\S+>/', '', $s);           // bare urls in <...>

        $s = (string) preg_replace('/^\s*\*\*(Environment|Budget|Engines|Method)\*\*.*$/m', '', $s);
        $s = (string) preg_replace('/^_Measured .*_$/m', '', $s);

        return $s;
    }

    /**
     * The documents that carry a generated REGION, and must therefore both
     * CARRY figures inside it and state none outside it.
     *
     * Clarity's README is deliberately NOT here — it has no region at all (see
     * generatedPages() and the two README tests below).
     *
     * @return list<string>
     */
    private static function regionConsumers(): array
    {
        return ['azera-framework/docs/03b-CLARITY-ENGINE.md'];
    }

    /**
     * The generated region is where the figures live, and it is present.
     *
     * A guard that only asserts "no figures outside" passes vacuously when the
     * region is MISSING — the document is then all "outside" and would fail for
     * the wrong reason, or pass if the consumer deleted the whole section.
     */
    public function testEachConsumerDocumentHasAGeneratedRegionCarryingFigures(): void
    {
        foreach (self::regionConsumers() as $rel) {
            $body  = (string) file_get_contents(self::sibling($rel));
            $parts = self::split($body);

            self::assertNotSame('', $parts['inside'], "{$rel} must carry a generated region");
            // The BROADER pattern here: the generated table states its unit in
            // the column HEADER ('First render (ms)'), so the cells are bare
            // decimals. A unit-bearing match is the wrong question for a table.
            self::assertMatchesRegularExpression(
                self::FIGURE,
                $parts['inside'],
                "{$rel}'s region must carry the measured figures — otherwise the guard below is vacuous"
            );
        }
    }

    /**
     * ...and nowhere outside it.
     *
     * Reported per offending line rather than as a boolean, so a failure names
     * the sentence to fix instead of leaving the operator to grep for it.
     */
    public function testNoConsumerProseOutsideTheRegionNamesAMeasuredFigure(): void
    {
        foreach (self::regionConsumers() as $rel) {
            $body  = (string) file_get_contents(self::sibling($rel));
            $parts = self::split($body);
            $prose = self::proseOnly($parts['outside']);

            preg_match_all(self::FIGURE_UNIT, $prose, $m, PREG_OFFSET_CAPTURE);
            $offenders = [];
            foreach ($m[0] as [$hit, $offset]) {
                $lineStart = (int) strrpos(substr($prose, 0, (int) $offset), "\n");
                $offenders[] = trim(substr($prose, $lineStart, 120)) . '   [matched: ' . $hit . ']';
            }

            self::assertSame(
                [],
                $offenders,
                "{$rel} states a measured figure outside its generated region:\n  "
                    . implode("\n  ", $offenders)
                    . "\nA sentence is not recomputed when the dataset changes, so the figure would"
                    . ' outlive the run it describes. Move it into the generated region, or delete it.'
            );
        }
    }

    /**
     * A document the generator writes WHOLE, and the shapes it carries.
     *
     * Listed separately from the region-bearing consumers because the rule is
     * different: a whole-page target has no hand-written prose to protect, so its
     * guard is the generated PAGE's guard (figures in charts and tables, never in
     * a sentence), not the region split.
     *
     * @return array<string,list<string>>
     */
    private static function generatedPages(): array
    {
        return [
            'clarity-engine/docs/08-benchmark.md' => ['## Mixed', '## Objects'],
        ];
    }

    /**
     * The generated page itself names no figure in its prose.
     *
     * It has no marker region — the whole file is generated — so its guard is a
     * structural one: the FIGURES are all inside charts or the generated table,
     * which are rendered, not written. What is checked here is the page the
     * generator emits for the site, after stripping tables, code, link targets
     * and the provenance stamp.
     */
    public function testTheGeneratedPageNamesNoFigureInProse(): void
    {
        $page = dirname(__DIR__) . '/docs/benchmarks/view-engine.md';
        if (!is_file($page)) {
            self::markTestSkipped('the published view-engine page has not been rendered yet');
        }

        $body = (string) file_get_contents($page);
        // Tables are generated from the dataset (the numbers are supposed to be
        // there); the same is true of the chart references.
        $body = (string) preg_replace('/^\|.*$/m', '', $body);
        $body = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $body);
        $body = self::proseOnly($body);

        preg_match_all(self::FIGURE, $body, $m);
        self::assertSame(
            [],
            $m[0],
            'the generated page must carry its figures in the table and charts, not in sentences: '
                . implode(', ', array_slice($m[0], 0, 10))
        );
    }

    /**
     * Clarity's README is hand-written and states NO figure.
     *
     * It used to carry a generated region with a table, which is why it needed
     * the region split. It now embeds two generated CHARTS and describes them in
     * its author's own words, so the generator does not touch its Markdown and
     * there is nothing about it a dataset change could invalidate except the
     * charts. That makes the rule the strictest version of the contract: prose
     * that nothing recomputes must name no measurement at all, not merely keep
     * them inside a marked region.
     */
    public function testClarityReadmeNamesNoMeasuredFigure(): void
    {
        $rel   = 'clarity-engine/README.md';
        $body  = (string) file_get_contents(self::sibling($rel));
        $prose = self::proseOnly($body);

        preg_match_all(self::FIGURE_UNIT, $prose, $m, PREG_OFFSET_CAPTURE);
        $offenders = [];
        foreach ($m[0] as [$hit, $offset]) {
            $lineStart = (int) strrpos(substr($prose, 0, (int) $offset), "\n");
            $offenders[] = trim(substr($prose, $lineStart, 120)) . '   [matched: ' . $hit . ']';
        }

        self::assertSame(
            [],
            $offenders,
            "{$rel} states a measured figure, and it is HAND-WRITTEN — nothing recomputes it, so the"
                . " figure would silently outlive the run it describes:\n  " . implode("\n  ", $offenders)
                . "\nPut the number in a chart, or delete the sentence."
        );
    }

    /**
     * ...and it embeds exactly the two charts it describes.
     *
     * The README's diagrams are generated while its prose is not, so a publish is
     * the only thing keeping them correct — which makes the image lines the one
     * part of the README that must stay true. Asserted on the real file, because
     * the failure this guards is a chart being renamed (or swept) and the
     * README's `![]()` silently pointing at nothing.
     *
     * BOTH the path and its absence matter, and the second is the subtle half. The
     * charts live in `docs/images/benchmarks/` — the directory the `clarity-docs`
     * publish target owns and sweeps — and the README must point THERE. It must
     * not point at `docs/images/`, where a previous era of this generator copied
     * the same two figures under fixed `benchmark-*` names: those copies are no
     * longer written by anything, so a README still citing them would show a
     * frozen chart from an old run beside prose describing the current one, and no
     * publish could ever refresh it.
     */
    public function testClarityReadmeEmbedsTheGeneratedChartsItDescribes(): void
    {
        $rel  = 'clarity-engine/README.md';
        $body = (string) file_get_contents(self::sibling($rel));

        foreach ([
            'docs/images/benchmarks/mixed-render-time.svg',
            'docs/images/benchmarks/mixed-memory.svg',
        ] as $img) {
            self::assertStringContainsString('](' . $img . ')', $body, "{$rel} must embed {$img}");
            self::assertFileExists(
                self::sibling('clarity-engine/' . $img),
                "{$rel} links {$img}, which is not there — a renamed chart must not leave a broken image"
            );
        }

        foreach ([
            'benchmark-results.svg',
            'benchmark-mixed-time.svg',
            'benchmark-mixed-first-render.svg',
        ] as $dead) {
            self::assertStringNotContainsString(
                $dead,
                $body,
                "{$rel} must not point at {$dead}: nothing writes it any more, so it would never be refreshed"
            );
        }
    }

    /**
     * The dedicated benchmark page is generated, so it must carry figures and
     * name none in prose — and it must actually be the shape it claims.
     *
     * Its page list is stated here rather than inferred, because the shapes it
     * publishes are a decision (the interesting rendering shapes, not the trivial
     * one, which the README's diagrams and the published site already cover).
     */
    public function testTheDedicatedBenchmarkPageIsGeneratedAndScoped(): void
    {
        foreach (self::generatedPages() as $rel => $expectedSections) {
            $file = self::sibling($rel);
            if (!is_file($file)) {
                self::markTestSkipped("{$rel} has not been published yet");
            }

            $body = (string) file_get_contents($file);

            foreach ($expectedSections as $heading) {
                self::assertStringContainsString($heading, $body, "{$rel} must carry {$heading}");
            }
            // The headline shape belongs to the published site, not this page.
            self::assertStringNotContainsString('## Scalars', $body);

            // Figures live in tables and charts, never in a sentence.
            $prose = (string) preg_replace('/^\|.*$/m', '', $body);
            $prose = (string) preg_replace('/!\[[^\]]*\]\([^)]*\)/', '', $prose);
            $prose = self::proseOnly($prose);

            preg_match_all(self::FIGURE, $prose, $m);
            self::assertSame(
                [],
                $m[0],
                "{$rel} is generated from the dataset, so a figure in its prose would be one no re-run"
                    . ' would correct: ' . implode(', ', array_slice($m[0], 0, 10))
            );
        }
    }
}