<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Template PARTIALS must be used by EVERY entrant, not a subset.
 *
 * Why this is a rule and not a preference. `{% include %}` is not free: both
 * engines inline the partial at compile time, but each ALSO records it as a
 * dependency whose mtime is re-checked per render, so a partial is one extra
 * `stat(2)` on every request that renders the page. Measured on the bench VM:
 *
 *   cold stat(2)                    1.03 us
 *   azera GET /items, php-fpm      1.3616 ms   -> 0.076 % of the row
 *   azera GET /items, volucer      0.3003 ms   -> 0 %, memoised by revalidateFreq
 *
 * That is small, but a structural feature present in one entrant and absent in
 * six is exactly the asymmetry this benchmark exists to avoid: the report cannot
 * show it, and a reader cannot see it in the source either.
 *
 * HISTORY. Azera was the only app with partials; Spiral carried two partial files
 * that NOTHING included (the identical markup was copy-pasted inline, and had
 * already drifted — the dead partial read `$title` while the live copy hardcoded
 * 'Items'). Removing Azera's made it zero-of-seven; this change gives all seven a
 * shared header and item footer, each in its own engine's include syntax, and
 * `testEveryEntrantRendersTheSamePageWithOrWithoutPartials` proves the refactor is
 * output-preserving.
 *
 * The rule is therefore all-or-nothing. Adding an eighth entrant means adding its
 * include syntax to $engines below in the same change.
 */
final class TemplatePartialsParityTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Include syntax per engine, as a regex over the app's view files.
     *
     * Each pattern is that engine's OWN spelling, so this cannot be satisfied by
     * a comment or by another engine's syntax appearing in a string.
     *
     * @return array<string,array{dir:string,pattern:string}>
     */
    private static function engines(): array
    {
        $root = self::repoRoot();

        return [
            // Clarity: {% include "partials/header" %}
            'azera' => [
                'dir'     => "{$root}/apps/azera/Views",
                'pattern' => '~\{%\s*include\b~',
            ],
            // Blade: @include('...') / @each('...') / <x-component />
            'laravel' => [
                'dir'     => "{$root}/apps/laravel/resources/views",
                'pattern' => '~@(include|each)\s*\(|<x-[a-z-]~',
            ],
            // Twig: {{ include('...') }} / {% include %} / {% embed %}
            'symfony' => [
                'dir'     => "{$root}/apps/symfony/templates",
                'pattern' => '~\{\{\s*include\s*\(|\{%\s*(include|embed)\b~',
            ],
            // Stempler: <use:element path="..."/> — NOT `use` (see the Stempler
            // test: a bare <use> tag is never resolved by ResolveImports).
            'spiral' => [
                'dir'     => "{$root}/apps/spiral/views",
                'pattern' => '~<use:element\s|@include\b~',
            ],
            // CodeIgniter 4: $this->include('...')
            'codeigniter' => [
                'dir'     => "{$root}/apps/codeigniter/Views",
                'pattern' => '~\$this->include\s*\(~',
            ],
            // CakePHP: $this->element('...')
            'cakephp' => [
                'dir'     => "{$root}/apps/cakephp/templates",
                'pattern' => '~\$this->element\s*\(~',
            ],
            // Volt: {% include '...' %}
            'phalcon' => [
                'dir'     => "{$root}/apps/phalcon/views",
                'pattern' => '~\{%\s*include\b~',
            ],
        ];
    }

    /**
     * Either every entrant includes partials, or none does.
     *
     * Asserted as an all-or-nothing set rather than "zero", so this keeps working
     * whatever the current state — it fails only on a partial adoption.
     */
    public function testPartialsAreUsedByEveryEntrantOrByNone(): void
    {
        $users = [];
        $files = [];

        foreach (self::engines() as $app => $spec) {
            if (!is_dir($spec['dir'])) {
                self::fail("{$app}: view directory {$spec['dir']} does not exist");
            }

            $hits = [];
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($spec['dir'], \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $name = $file->getFilename();
                // Layout files are fine everywhere; only INCLUDE syntax is at issue,
                // and a layout's own {% block %} is not an include.
                // `.volt` must be here — Phalcon's templates use it, and omitting it
                // made this test report "5 of 7" when all seven were wired.
                if (!preg_match('~\.(html|php|twig|volt)$~', $name)) {
                    continue;
                }
                $source = (string) file_get_contents($file->getPathname());
                if (preg_match($spec['pattern'], $source) === 1) {
                    $hits[] = str_replace(self::repoRoot() . '/', '', $file->getPathname());
                    $files[] = $app . ': ' . basename($file->getPathname());
                }
            }

            if ($hits !== []) {
                $users[$app] = $hits;
            }
        }

        $count = count($users);
        $total = count(self::engines());

        self::assertContains(
            $count,
            [0, $total],
            'partials are used by ' . $count . ' of ' . $total . ' entrants, which is a structural '
                . "asymmetry: the including rows pay one extra stat(2) per render and the others do not.\n"
                . "Used by:\n  " . implode("\n  ", $files)
        );
    }

    /**
     * Every entrant that includes a partial must also SHIP it.
     *
     * An include whose file is missing is a 500 at render, and a partial whose
     * only difference from the inline copy is drift is worse than no partial at
     * all — that is the state Spiral was in before this change (two orphaned
     * files, the live markup copy-pasted and already out of sync).
     */
    public function testEveryIncludedPartialExists(): void
    {
        $root = self::repoRoot();

        $expected = [
            'azera'       => ["{$root}/apps/azera/Views/partials/header.clarity.html",
                              "{$root}/apps/azera/Views/partials/item_footer.clarity.html"],
            'laravel'     => ["{$root}/apps/laravel/resources/views/partials/header.blade.php",
                              "{$root}/apps/laravel/resources/views/partials/item_footer.blade.php"],
            'symfony'     => ["{$root}/apps/symfony/templates/partials/header.html.twig",
                              "{$root}/apps/symfony/templates/partials/item_footer.html.twig"],
            'spiral'      => ["{$root}/apps/spiral/views/partials/header.dark.php",
                              "{$root}/apps/spiral/views/partials/item_footer.dark.php"],
            'codeigniter' => ["{$root}/apps/codeigniter/Views/partials/header.php",
                              "{$root}/apps/codeigniter/Views/partials/item_footer.php"],
            'cakephp'     => ["{$root}/apps/cakephp/templates/element/header.php",
                              "{$root}/apps/cakephp/templates/element/item_footer.php"],
            'phalcon'     => ["{$root}/apps/phalcon/views/partials/header.volt",
                              "{$root}/apps/phalcon/views/partials/item_footer.volt"],
        ];

        foreach ($expected as $app => $paths) {
            foreach ($paths as $path) {
                self::assertFileExists($path, "{$app} includes a partial that is not on disk");
            }
        }
    }

    /**
     * The partial's markup is not ALSO written out inline in the page.
     *
     * This is the failure mode that hides: Spiral had the partial files AND the
     * same markup written out in the page, so the partial looked used. A grep for
     * "partials" passed while the engine never read the file.
     *
     * Scoped to the ITEMS list page only: that is the one whose header partial
     * renders the `<h1>` + "Showing N items" pair. The item detail page has an
     * unrelated `<h1>` and only shares the footer.
     */
    public function testTheIncludedMarkupIsNotAlsoInlineInTheItemsPage(): void
    {
        $root = self::repoRoot();

        $files = [
            'azera'       => "{$root}/apps/azera/Views/items/list.clarity.html",
            'laravel'     => "{$root}/apps/laravel/resources/views/items.blade.php",
            'symfony'     => "{$root}/apps/symfony/templates/items.html.twig",
            'spiral'      => "{$root}/apps/spiral/views/items.dark.php",
            'codeigniter' => "{$root}/apps/codeigniter/Views/items.php",
            'cakephp'     => "{$root}/apps/cakephp/templates/Bench/list.php",
            'phalcon'     => "{$root}/apps/phalcon/views/bench/list.volt",
        ];

        foreach ($files as $app => $file) {
            self::assertFileExists($file, "{$app}: items template missing");
            $src = (string) file_get_contents($file);

            // The header partial owns the `<h1>` + item-count pair. The pagination
            // block ALSO says "Showing 21–40 of 1000 items", so a bare 'Showing '
            // check would fail on correct templates — assert the pair instead.
            self::assertDoesNotMatchRegularExpression(
                '~<h1>[^<]*</h1>\s*<p>\s*Showing\b~',
                $src,
                "{$app}: " . basename($file) . ' still renders the heading + item-count pair inline, '
                    . 'so the header partial is decorative rather than used'
            );
        }
    }
}
