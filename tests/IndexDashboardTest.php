<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use AzeraCompetition\Report\HtmlReport;
use AzeraCompetition\Report\ResultStore;
use PHPUnit\Framework\TestCase;

/**
 * The dashboard's card order is a PRESENTATION choice, deliberately decoupled
 * from the manifest's declaration order.
 *
 * Why this needs pinning: scripts/report/views.php is walked for something
 * else in every direction that matters — render order, publish order, the .md
 * files, and the tests that iterate `$manifest['views']`. The index used to
 * loop over it directly, which quietly welded two unrelated concerns together:
 * reordering views.php to group the published .md files would also reshuffle
 * the dashboard, and vice versa. HtmlReport::cardOrder() now states its own
 * order, and these tests hold the two apart.
 *
 * The order is the reader's first question — which DEPLOYMENT MODEL — answered
 * first: the two views measured on a real server, then the in-process harness
 * view of each of those two models, so a reader who picks a model finds both of
 * its views adjacent. The two published SUMMARIES come last: they are subsets
 * of the measured views above them, so a reader who wants the headline should
 * meet the full pages first.
 */
final class IndexDashboardTest extends TestCase
{
    private const DATASET = '/results/free-for-all-opcache-iso.json';

    /**
     * The order the dashboard must present, as hrefs.
     *
     * @var list<string>
     */
    private const EXPECTED = [
        'view-real-roadrunner.html',
        'view-real-fpm.html',
        'view-warm-start.html',
        'view-cold-start.html',
        'view-summary-roadrunner.html',
        'view-summary-fpm.html',
    ];

    /** @return array<string,array<string,mixed>> */
    private function manifest(): array
    {
        return require dirname(__DIR__) . '/scripts/report/views.php';
    }

    /** @param array<string,mixed> $manifest */
    private function indexHtml(array $manifest, ?string $outDir = null): string
    {
        $store = ResultStore::load(dirname(__DIR__) . self::DATASET);

        $viewFiles = [];
        foreach (array_keys($manifest['views'] ?? []) as $key) {
            $viewFiles[$key] = 'view-' . $key . '.html';
        }

        return (new HtmlReport($store, $manifest))
            ->index($viewFiles, $outDir ?? sys_get_temp_dir());
    }

    /** @return list<string> */
    private static function cardHrefs(string $html): array
    {
        preg_match_all('/<a class="card" href="([^"]+)"/', $html, $m);

        return $m[1];
    }

    public function testCardsAreOrderedRealServersFirstThenTheirHarnessViews(): void
    {
        $hrefs = self::cardHrefs($this->indexHtml($this->manifest()));

        self::assertSame(self::EXPECTED, $hrefs);
    }

    /**
     * The order must be the renderer's own list, not the manifest's: a
     * manifest whose declaration order is reversed must still produce the
     * dashboard order above. Without this, the assertions could pass merely
     * because views.php happens to be declared that way today.
     */
    public function testCardOrderIsIndependentOfTheManifestDeclarationOrder(): void
    {
        $manifest = $this->manifest();
        $manifest['views'] = array_reverse($manifest['views'], true);

        // views.php declares warm-start … summary-fpm; reversed, the dashboard
        // would be driven by summary-fpm first.
        self::assertSame('summary-fpm', array_key_first($manifest['views']));

        self::assertSame(self::EXPECTED, self::cardHrefs($this->indexHtml($manifest)));
    }

    /**
     * A view the manifest has but the explicit order does not must still get a
     * card — the fallback is what lets a new view be added to views.php
     * without also having to remember this list.
     */
    public function testUnlistedViewStillGetsACard(): void
    {
        $manifest = $this->manifest();
        $manifest['views']['brand-new'] = [
            'title'    => 'Framework Competition — Brand New',
            'subtitle' => 'added to the manifest only',
            'apps'     => ['azera'],
        ];

        $hrefs = self::cardHrefs($this->indexHtml($manifest));

        self::assertContains('view-brand-new.html', $hrefs);
        // ...and appended after the known four, rather than displacing them.
        self::assertSame(
            [...self::EXPECTED, 'view-brand-new.html'],
            $hrefs
        );
    }

    /**
     * A card for a view that was not rendered this run (no entry in
     * $viewFiles — e.g. --view= narrowed the run) must be skipped, not
     * printed as a dead link.
     */
    public function testViewNotRenderedThisRunGetsNoCard(): void
    {
        $manifest = $this->manifest();
        $store    = ResultStore::load(dirname(__DIR__) . self::DATASET);

        $html = (new HtmlReport($store, $manifest))->index(
            ['real-roadrunner' => 'view-real-roadrunner.html'],
            sys_get_temp_dir()
        );

        self::assertSame(['view-real-roadrunner.html'], self::cardHrefs($html));
    }

    /**
     * At most two cards per row. Asserted against the CSS the page actually
     * ships rather than a source string, so the rule cannot be satisfied by a
     * comment.
     */
    public function testGridIsCappedAtTwoColumns(): void
    {
        $html = $this->indexHtml($this->manifest());

        self::assertMatchesRegularExpression(
            '/\.grid\{[^}]*grid-template-columns:repeat\(2,minmax\(0,1fr\)\)/',
            $html,
            'the dashboard grid must be exactly two columns wide'
        );
        self::assertStringNotContainsString(
            'auto-fit',
            $html,
            'auto-fit would let a wide window put three or more cards on one row'
        );
        self::assertMatchesRegularExpression(
            '/@media \(max-width:760px\)\{\.grid\{grid-template-columns:1fr\}\}/',
            $html,
            'a narrow viewport must fall back to one card per row'
        );
    }
}
