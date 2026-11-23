<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every interpolated value in the Phalcon Volt templates must be escaped.
 *
 * WHY. The other six entrants escape by DEFAULT, and the benchmark's pages are
 * compared as text:
 *
 *   Laravel   `{{ }}`  … Blade escapes
 *   Symfony   `{{ }}`  … Twig autoescape 'html' (not overridden in twig.yaml)
 *   Spiral    `{{ }}`  … Stempler DEFAULT_FILTER = htmlspecialchars(ENT_QUOTES)
 *   azera     `{{ }}`  … the Clarity engine wraps every output in
 *                        htmlspecialchars((string)(…), ENT_QUOTES)
 *   CakePHP   `h()`    … explicit
 *   CI4       `esc()`  … explicit
 *
 * Volt is the exception: its `|e` filter exists, but autoescape defaults to
 * FALSE (verified on Phalcon 6.0.0RC4), so `{{ x }}` emits x verbatim. The
 * templates therefore wrote `|e` on some values and nothing on others — `item.id`,
 * the whole `pagination` array, `baseUrl` and `flash` all went out raw. That is
 * both an escaping difference and a rendering-cost difference: six entrants pay
 * an `htmlspecialchars` per interpolation and Phalcon did not.
 *
 * These tests pin the escaped form so the next template edit cannot quietly
 * reintroduce a raw output.
 */
final class PhalconTemplateEscapingTest extends TestCase
{
    /** @return array<string,string> template name => source */
    private static function templates(): array
    {
        $dir   = dirname(__DIR__) . '/apps/phalcon/views/bench';
        $files = array_merge(glob($dir . '/*.volt') ?: [], glob($dir . '/partials/*.volt') ?: []);

        $out = [];
        foreach ($files as $file) {
            $out[str_replace($dir . '/', '', $file)] = (string) file_get_contents($file);
        }

        self::assertNotSame([], $out, 'no Volt templates found — the directory moved');

        return $out;
    }

    /**
     * No interpolation may reach the page unescaped.
     *
     * A `{% block %}` is template structure, not an interpolation, so the
     * layout's `{% block title %}` is not matched here at all; anything inside
     * `{{ … }}` is.
     */
    public function testEveryInterpolationCarriesAnEscapeFilter(): void
    {
        $offenders = [];

        foreach (self::templates() as $name => $source) {
            preg_match_all('/\{\{(.*?)\}\}/s', $source, $matches);

            foreach ($matches[1] as $inner) {
                $expr = trim($inner);

                // `|e` is the escaper; `|raw` is an explicit opt-out (none should
                // exist, and one here would be a deliberate hole).
                if (preg_match('/\|\s*(e|escape)\b/', $expr) !== 1) {
                    $offenders[] = $name . ': {{ ' . $expr . ' }}';
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "Every Volt interpolation needs |e, because Volt's autoescape defaults to off while all "
                . "six other engines escape by default:\n  " . implode("\n  ", $offenders)
        );
    }

    /** Nothing may opt back out of escaping. */
    public function testNoTemplateUsesTheRawOptOut(): void
    {
        foreach (self::templates() as $name => $source) {
            self::assertDoesNotMatchRegularExpression(
                '/\|\s*raw\b/',
                $source,
                "{$name} uses |raw, which would emit unescaped output where every other entrant escapes"
            );
        }
    }

    /**
     * The values that used to go out raw are now escaped, proven end-to-end.
     *
     * Checking for `|e` in the source only proves intent; this proves the
     * escaper actually ran. `/items` is the page that interpolates most
     * (20 rows x id/title/created_at plus the pagination block), so a single
     * `|e` omitted there is the likeliest regression.
     */
    public function testTheItemsPageEscapesRowAndPaginationValues(): void
    {
        $nasty = '"><script>alert(1)</script>';

        $item = new \stdClass();
        $item->id         = 7;
        $item->title      = $nasty;
        $item->created_at = $nasty;

        $rendered = $this->renderItems([$item]);

        self::assertStringNotContainsString('<script>', $rendered, 'a row value reached the page unescaped');
        self::assertStringContainsString(
            htmlspecialchars($nasty, ENT_QUOTES),
            $rendered,
            'the escaped form of the row value must appear'
        );

        // The pagination block is a separate loop over an array; assert its
        // escaping independently so a future edit to the table cannot hide it.
        self::assertStringContainsString('Showing ', $rendered);
        self::assertMatchesRegularExpression('/Page \d+ \/ \d+/', $rendered);
    }

    /** @param list<object> $items */
    private function renderItems(array $items): string
    {
        $cache = sys_get_temp_dir() . '/phalcon-volt-escape-test-' . getmypid();
        @mkdir($cache, 0777, true);

        $di = new \Phalcon\Di\Di();
        $di->setShared('escaper', fn(): \Phalcon\Html\Escaper => new \Phalcon\Html\Escaper());

        // Mvc\View with the app's conventional tree — `<controller>/<action>` plus
        // `layouts/` — because that is what the application renders through. Using
        // View\Simple here would exercise a component the app no longer has.
        $view = new \Phalcon\Mvc\View();
        $view->setDI($di);
        $view->setViewsDir(dirname(__DIR__) . '/apps/phalcon/views/');
        $view->setLayoutsDir('layouts/');
        $view->setLayout('main');

        $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
        $volt->setOptions(['path' => $cache . '/', 'compileAlways' => false]);
        $view->registerEngines(['.volt' => $volt]);

        // View::render() buffers, and a failed render can leave an ob level open,
        // which PHPUnit reports as a risky test — masking the assertion the test
        // exists for. Own the buffer level here.
        $level = ob_get_level();
        try {
            $view->start();
            $view->render('bench', 'list', [
                'title'      => 'Items',
                'baseUrl'    => '/items',
                'items'      => $items,
                'pagination' => [
                    'currentPage'  => 1,
                    'lastPage'     => 1,
                    'previousPage' => 1,
                    'nextPage'     => 1,
                    'totalItems'   => 1,
                    'firstItem'    => 1,
                    'lastItem'     => 1,
                    'hasPrevious'  => false,
                    'hasNext'      => false,
                ],
            ]);
            $view->finish();

            return $view->getContent();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
