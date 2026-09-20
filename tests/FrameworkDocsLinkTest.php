<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The framework repository's side of the publication.
 *
 * The generator can write a correct page into azera-framework and still leave
 * the framework unable to find it. That is not hypothetical: the framework's
 * README carried TWO dead links for several sessions — `docs/02-MVC-ROUTING.md`
 * and `docs/03b-CLARITY-TEMPLATES.md`, neither of which has existed since the
 * files were renamed to `02-CORE-ROUTING.md` and `03b-CLARITY-ENGINE.md`. A
 * documentation index that points at nothing is invisible until a reader
 * follows it, so the links are checked here rather than trusted.
 *
 * The path to the framework is resolved the same way report.php resolves it
 * (`dirname(__DIR__, 2) . '/azera-framework'`) so both sides agree on where the
 * sibling repository is. When it is absent — a standalone checkout of this
 * repository — the tests are SKIPPED rather than failed: the publication is
 * cross-repo by design, and its absence is not an error in this one.
 */
final class FrameworkDocsLinkTest extends TestCase
{
    private static function frameworkRoot(): string
    {
        return dirname(__DIR__, 2) . '/azera-framework';
    }

    protected function setUp(): void
    {
        if (!is_dir(self::frameworkRoot())) {
            self::markTestSkipped('azera-framework is not checked out beside this repository');
        }
    }

    /** @return list<string> */
    private static function markdownFiles(): array
    {
        $root  = self::frameworkRoot();
        $files = glob($root . '/*.md') ?: [];
        foreach (glob($root . '/docs/*.md') ?: [] as $f) {
            $files[] = $f;
        }

        return $files;
    }

    /**
     * Pre-existing dead links, named so the scan can stay REPO-WIDE.
     *
     * The README's own two dead links were repaired (see the class docblock),
     * but a full scan finds nine more in other pages, and every one of their
     * targets is genuinely gone rather than renamed:
     *
     *  - `docs/00-GETTING-STARTED.md` demonstrates a project layout with links
     *    into a hypothetical app (`../public/index.php`, `../app/Models/User.php`,
     *    …). They were never files in THIS repository — it is the framework, not
     *    an application — so there is nothing to point them at.
     *  - `docs/03b-CLARITY-ENGINE.md` references a Clarity logo SVG and the
     *    view-engine benchmark's JSON/CSV/SVG, all of which were REMOVED from the
     *    repository on purpose (commit 6f78b70, "Remove benchmark from repo").
     *
     * Listing them keeps the assertion strict in both directions: a NEW dead
     * link fails because it is not in this list, and REPAIRING one of these
     * fails until its entry is deleted — so the list cannot quietly rot.
     *
     * @var list<string>
     */
    private const KNOWN_DEAD = [
        'docs/00-GETTING-STARTED.md -> ../app/Controllers/IndexController.php',
        'docs/00-GETTING-STARTED.md -> ../app/Models/User.php',
        'docs/00-GETTING-STARTED.md -> ../console.php',
        'docs/00-GETTING-STARTED.md -> ../public/index.php',
        'docs/00-GETTING-STARTED.md -> ../views/home/index.php',
        'docs/00-GETTING-STARTED.md -> ../views/layouts/main.php',
        'docs/03b-CLARITY-ENGINE.md -> ../benchmarks/view-engine/results-2026-03-07-01.svg',
        'docs/03b-CLARITY-ENGINE.md -> images/clarity-dsl-logo-opt.svg',
    ];

    /**
     * Every relative link in the framework's README and docs resolves — except
     * the pre-existing breakage listed above.
     *
     * Only RELATIVE targets are checked: an https:// link belongs to someone
     * else's site, and an anchor (`#benchmarks`) is resolved within the page by
     * GitHub. Directory targets (`api/`) are accepted when the directory exists,
     * since GitHub serves a README from one.
     *
     * Resolution is relative to the FILE, not the repository root — that is how
     * a Markdown link works, and getting it wrong made `docs/README.md`'s own
     * links (`00-GETTING-STARTED.md`) look dead while they are correct.
     */
    public function testRelativeLinksInTheFrameworkDocsResolve(): void
    {
        $root    = self::frameworkRoot();
        $broken  = [];
        $checked = 0;

        foreach (self::markdownFiles() as $file) {
            $body = (string) file_get_contents($file);
            preg_match_all('/\]\(([^)\s]+)\)/', $body, $m);

            foreach ($m[1] as $target) {
                if (str_contains($target, '://') || str_starts_with($target, '#')) {
                    continue;
                }
                // Strip an in-page anchor: `README.md#benchmarks` checks
                // `README.md`.
                $path = explode('#', $target)[0];
                if ($path === '') {
                    continue;
                }
                $checked++;
                if (!file_exists(dirname($file) . '/' . $path)) {
                    $broken[] = str_replace($root . '/', '', $file) . ' -> ' . $target;
                }
            }
        }

        $broken = array_values(array_unique($broken));
        sort($broken);
        $known = self::KNOWN_DEAD;
        sort($known);

        self::assertGreaterThan(20, $checked, 'the link scan must actually find links');
        self::assertSame(
            $known,
            $broken,
            'the dead-link set changed. A NEW entry is a broken link to fix; a MISSING one means a link '
                . "was repaired, so delete its KNOWN_DEAD entry.\n"
                . 'newly broken: ' . implode(', ', array_diff($broken, $known)) . "\n"
                . 'no longer broken (delete the entry): ' . implode(', ', array_diff($known, $broken))
        );
    }

    /**
     * The published summary pages exist and are the ones the docs index names.
     *
     * A page the generator writes but no index links to is unreachable; an index
     * entry for a page that was never written is dead. Both directions.
     */
    public function testThePublishedSummaryPagesExistAndAreLinked(): void
    {
        $root     = self::frameworkRoot();
        $index    = (string) file_get_contents($root . '/docs/README.md');
        $expected = [
            'docs/19-BENCHMARKS-SUMMARY-ROADRUNNER.md',
            'docs/19-BENCHMARKS-SUMMARY-FPM.md',
        ];

        foreach ($expected as $rel) {
            self::assertFileExists($root . '/' . $rel, "{$rel} must be published");

            $name = basename($rel);
            self::assertStringContainsString(
                "({$name})",
                $index,
                "docs/README.md must link to {$name}"
            );

            // The chart images the page references must have travelled with it.
            $body = (string) file_get_contents($root . '/' . $rel);
            preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $body, $m);
            self::assertNotSame([], $m[1], "{$name} must embed charts");
            foreach ($m[1] as $img) {
                self::assertFileExists($root . '/docs/' . $img, "{$name} references a missing image: {$img}");
            }
        }
    }

    /**
     * The published pages carry no measured figure in their prose.
     *
     * The same contract the generated pages hold (see
     * ChartManifestTest::testTheReportProseNamesNoMeasuredFigure), asserted on
     * the framework's copy because that is the copy a reader cannot re-render:
     * a number transcribed into a sentence there outlives the benchmark that
     * produced it, and nothing on the framework side can notice.
     */
    public function testThePublishedPagesCarryNoProseFigures(): void
    {
        $root   = self::frameworkRoot();
        $figure = '/\b\d+\.\d+\b|\b\d+\s*(?:ms|MB|%)/';

        foreach (glob($root . '/docs/19-BENCHMARKS-SUMMARY-*.md') ?: [] as $file) {
            $prose = self::proseOnly((string) file_get_contents($file));
            $prose = preg_replace('/`[^`]*`/', ' ', $prose) ?? $prose;
            preg_match_all($figure, $prose, $m);

            self::assertSame(
                [],
                array_values(array_unique($m[0])),
                basename($file) . ' names a measured figure in prose: ' . implode(', ', $m[0])
            );
        }
    }

    /**
     * The README's benchmark section carries no measured figure either.
     *
     * The README is the one page in this arrangement that is HAND-WRITTEN —
     * the summaries are generated and this section is not — so it is the page
     * where a figure most easily outlives its dataset. It embeds two generated
     * charts and must let them carry every number, exactly as the generated
     * pages do.
     *
     * Scoped to the section, not the whole file: the README legitimately names
     * numbers elsewhere (PHP version requirements, `1000` in an example), and an
     * assertion over the whole document would have to special-case them until it
     * stopped meaning anything.
     */
    public function testTheReadmeBenchmarkSectionCarriesNoProseFigures(): void
    {
        $readme  = (string) file_get_contents(self::frameworkRoot() . '/README.md');
        $section = self::readmeBenchmarkSection($readme);
        $prose   = preg_replace('/`[^`]*`/', ' ', $section) ?? $section;
        // Image references are chart pointers, not sentences.
        $prose = preg_replace('/^!\[[^\]]*\]\([^)]+\)$/m', ' ', $prose) ?? $prose;
        preg_match_all('/\b\d+\.\d+\b|\b\d+\s*(?:ms|MB|%)/', $prose, $m);

        self::assertSame(
            [],
            array_values(array_unique($m[0])),
            'README.md names a measured figure in its benchmark prose: ' . implode(', ', $m[0])
        );
    }

    /**
     * The README's benchmark section embeds one generated chart per deployment
     * model, and each image it points at exists.
     *
     * The two charts are the section's whole point: a reader who cannot see them
     * gets a claim with no evidence. Their presence is asserted rather than
     * assumed because the images live in a directory the generator owns, so a
     * future `--publish=framework` that changes its layout would silently empty
     * the README.
     */
    public function testTheReadmeBenchmarkSectionEmbedsBothGeneratedCharts(): void
    {
        $root    = self::frameworkRoot();
        $section = self::readmeBenchmarkSection((string) file_get_contents($root . '/README.md'));

        preg_match_all('/!\[[^\]]*\]\(([^)]+)\)/', $section, $m);
        $images = $m[1];

        self::assertCount(2, $images, 'the benchmark section must embed exactly two charts');
        foreach ($images as $img) {
            self::assertFileExists($root . '/' . $img, "README.md references a missing chart: {$img}");
        }

        // One per deployment model, because the two stories differ — the
        // runner-up is a different framework under FPM than under RoadRunner.
        self::assertMatchesRegularExpression('/summary-roadrunner\//', implode("\n", $images));
        self::assertMatchesRegularExpression('/summary-fpm\//', implode("\n", $images));
    }

    /**
     * The README's benchmark section, from its heading to the next `## `.
     *
     * Scoping matters: it is what lets the README carry figures elsewhere
     * without exempting the section that must not.
     *
     * Line endings are normalised FIRST. The framework repository is CRLF, so
     * a `\n`-anchored `strpos` finds nothing and the assertion fails claiming
     * the section is absent — while it is plainly there (the same trap as the
     * `strpos()` multi-line needles noted for this workspace).
     */
    private static function readmeBenchmarkSection(string $readme): string
    {
        $readme = str_replace("\r\n", "\n", $readme);

        $start = strpos($readme, "\n## Benchmarks\n");
        self::assertNotFalse($start, 'README.md must have a "## Benchmarks" section');

        $rest = substr($readme, $start + 1);
        $end  = strpos($rest, "\n## ", strlen('## Benchmarks'));
        self::assertNotFalse($end, 'the Benchmarks section must be followed by another top-level heading');

        return substr($rest, 0, $end);
    }

    /**
     * Everything that is NOT a number-bearing structure, mirroring the
     * competition-side helper: tables, chart references and the provenance
     * lines legitimately carry figures.
     */
    private static function proseOnly(string $markdown): string
    {
        $keep    = [];
        $inTable = false;
        $inFoot  = false;

        foreach (explode("\n", $markdown) as $line) {
            if (str_starts_with($line, '> **Auto-generated.**')) {
                $inFoot = true;
            }
            if ($inFoot) {
                continue;
            }
            if (preg_match('/^\s*\|/', $line) === 1) {
                $inTable = true;
                continue;
            }
            if ($inTable) {
                if (trim($line) === '') {
                    $inTable = false;
                }
                continue;
            }
            // Provenance lines, all regenerated from the dataset on every
            // render — the environment stamp, the measurement date, and the
            // version of every framework measured. A figure in one of these
            // cannot outlive its run, which is the whole reason the guard
            // exists; `**Frameworks**` is a stamp, not a sentence about a
            // number. Kept in step with ChartManifestTest::proseOnly().
            if (
                str_starts_with($line, '**Environment**')
                    || str_starts_with($line, '**Frameworks**')
                    || str_starts_with($line, '_Measured ')
            ) {
                continue;
            }
            if (preg_match('/^!\[/', $line) === 1) {
                continue;
            }
            $keep[] = $line;
        }

        return implode("\n", $keep);
    }
}
