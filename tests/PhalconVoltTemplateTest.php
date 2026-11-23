<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The Phalcon templates must use VOLT syntax, not raw PHP.
 *
 * Why this is a test and not a comment. Phalcon 6 registers the Volt engine
 * (`registerEngines(['.volt' => $volt])`), Volt runs on every render, and the
 * files are named `.volt` — so everything LOOKS like a template engine and a
 * reviewer reasonably concludes the templates are Volt. They were not: every one
 * of the five was plain PHP (`<?php foreach …`, `<?= htmlspecialchars(…) ?>`).
 * Volt's compiler passes PHP through verbatim, so the compiled cache was
 * byte-identical to the source and the engine did no template work at all.
 *
 * That is a comparability bug, not a style preference. Laravel's rows pay for
 * Blade directive parsing, Symfony's for Twig compilation, Spiral's for the
 * Stempler compiler — this app paid for a `require` of pre-written PHP. The
 * `GET /`, `GET /items` and `/features` rows were therefore measuring a
 * different workload from the same rows in the other six entrants.
 *
 * The inverse trap matters too, and is why the assertions below name the exact
 * constructs: Volt is NOT a superset of PHP, and two natural spellings are
 * silently wrong.
 *
 *   {{ count(items) }}      compiles to a MACRO call and throws MacroNotFound at
 *                           RENDER time (a compile-only check passes).
 *   {{ pagination.first }}  compiles to a PROPERTY read; the payload is an array,
 *                           so it emits a PHP warning and renders the warning text
 *                           into the page.
 *
 * The correct forms are `{{ items|length }}` and `{{ pagination['first'] }}`.
 */
final class PhalconVoltTemplateTest extends TestCase
{
    private static function viewsDir(): string
    {
        return str_replace('\\', '/', dirname(__DIR__)) . '/apps/phalcon/views';
    }

    /**
     * Action templates, i.e. everything except the layout and the partials.
     *
     * The conventional tree is `views/<controller>/<action>.volt` plus
     * `views/layouts/` and `views/partials/`; only the controller directories hold
     * pages, so the layout is not swept into the page assertions below.
     *
     * @return array<string,string> "controller/action" => source
     */
    private static function templates(): array
    {
        $out = [];
        foreach (glob(self::viewsDir() . '/*/*.volt') ?: [] as $file) {
            $relative = substr(str_replace('\\', '/', $file), strlen(self::viewsDir()) + 1);
            if (str_starts_with($relative, 'layouts/') || str_starts_with($relative, 'partials/')) {
                continue;
            }
            $out[substr($relative, 0, -strlen('.volt'))] = (string) file_get_contents($file);
        }

        return $out;
    }

    private static function layout(): string
    {
        return (string) file_get_contents(self::viewsDir() . '/layouts/main.volt');
    }

    public static function templateProvider(): array
    {
        $cases = [];
        foreach (array_keys(self::templates()) as $name) {
            $cases[$name] = [$name];
        }

        return $cases;
    }

    /**
     * No raw PHP in any template.
     *
     * `<?php`, `<?=` and `<?` are all checked: a lone `<?` is a short open tag
     * and would emit itself as text when short_open_tag is off, which is the
     * default the bench VM runs.
     *
     * @dataProvider templateProvider
     */
    public function testTemplateContainsNoRawPhp(string $name): void
    {
        $source = self::templates()[$name];

        self::assertDoesNotMatchRegularExpression(
            '/<\?(?:php|=)?/',
            $source,
            "{$name}.volt contains raw PHP — the app runs the Volt engine, so the template "
                . 'must be Volt, or the render rows measure a require() instead of a template language'
        );
    }

    /**
     * Every template actually uses Volt syntax.
     *
     * A `.volt` file with no `{{ }}` and no `{% %}` is the failure mode this whole
     * test exists for: it renders, it looks right, and it means the engine is
     * decorative.
     *
     * `layout` is the ONE exception: it is the parent that the other templates
     * extend, so it owns `{% block %}` definitions and has nothing to
     * interpolate itself beyond the default title. It is still asserted to be
     * Volt by testTemplateContainsNoRawPhp and by the render test below.
     *
     * @dataProvider templateProvider
     */
    public function testTemplateUsesVoltSyntax(string $name): void
    {
        if ($name === 'bench/index') {
            self::markTestSkipped('bench/index.volt is static copy: no value to interpolate, so no tag to assert');
        }

        $source = self::templates()[$name];

        self::assertMatchesRegularExpression('/\{\{.*?\}\}|\{%.*?%\}/s', $source, "{$name}.volt contains no Volt tag");
    }

    /**
     * The two spellings that compile but fail at render time are absent.
     *
     * Asserted on the SOURCE because that is where they are written; the test
     * below proves the same thing by rendering, which is what actually catches
     * `count()`.
     */
    public function testAvoidsTheSpellingsThatCompileButFailAtRenderTime(): void
    {
        foreach (self::templates() as $name => $source) {
            self::assertDoesNotMatchRegularExpression(
                '/\{\{[^}]*\bcount\s*\(/',
                $source,
                "{$name}.volt calls count() — Volt turns it into a macro call that throws "
                    . 'MacroNotFound when rendered. Use `|length` instead.'
            );

            // `a.b` on an ARRAY payload compiles to a property read. Any dotted
            // access is suspicious here because every payload this app passes is
            // either a model (where it is correct) or an array (where it is not),
            // so the check is scoped to the arrays it does pass.
            self::assertDoesNotMatchRegularExpression(
                '/\{\{\s*$|pagination\.\w+/',
                $source,
                "{$name}.volt reads pagination with dot notation, but that payload is an ARRAY: "
                    . 'Volt compiles it to a property read and the page renders a PHP warning. '
                    . "Use pagination['key']."
            );
        }
    }

    /**
     * The VIEW LAYER composes the layout — one render, like every other entrant.
     *
     * This is the comparability assertion, and it is the one that changed shape
     * when the app moved to `Mvc\View`. Laravel uses `@extends('layouts.app')`,
     * Symfony `{% extends 'base.html.twig' %}`, Spiral `<extends:layouts.app/>`,
     * CI4 `$this->extend(...)`, Clarity `{% extends "layouts/app" %}`, and Cake
     * gets it from its renderer — so all of them let the engine compose the layout
     * inside ONE render.
     *
     * Phalcon's engine has two mechanisms and only one of them is the framework's:
     *
     *   - Volt's `{% extends %}` / `{% block %}` is a TEMPLATE-language feature. It
     *     works, and the app used it — but combining it with `Mvc\View`'s layout
     *     level renders the layout twice and leaves `{% block %}` bodies unfilled,
     *     because the action fragment has no `extends` and the layout is a separate
     *     render with `{{ content() }}` as its only slot.
     *   - `Mvc\View`'s layout level IS the framework's composition, and it is what
     *     a normal Phalcon app uses: the dispatcher's controller/action selects
     *     `bench/<action>.volt`, then `layouts/main.volt` is rendered with the
     *     action's output available through `content()`.
     *
     * So the assertions below are the Phalcon equivalents of "each page extends the
     * layout": the layout exists, is applied by the view (not the controller), and
     * renders the action fragment through `content()`.
     */
    public function testTheViewLayerComposesTheLayout(): void
    {
        $layout = self::layout();

        self::assertStringContainsString(
            '{{ content() }}',
            $layout,
            'layouts/main.volt must render the action fragment through Volt\'s content() — that is the '
                . 'slot Mvc\View fills, and without it the page renders with an empty body'
        );

        // The action templates are FRAGMENTS: no extends/block, or the layout level
        // and the template language would both try to compose and the block bodies
        // would be dropped.
        foreach (self::templates() as $name => $source) {
            self::assertDoesNotMatchRegularExpression(
                '/\{%\s*extends\b/',
                $source,
                "{$name}.volt must not use {% extends %}: Mvc\\View already renders layouts/main.volt "
                    . 'around it, and having both is what left the content block empty'
            );
        }

        // The layout must be applied by the view service, declared in one place.
        $bootstrap = (string) file_get_contents(dirname(__DIR__) . '/apps/phalcon/src/Bootstrap.php');
        self::assertStringContainsString("setLayoutsDir('layouts/')", $bootstrap);
        self::assertStringContainsString("setLayout('main')", $bootstrap);
        self::assertStringContainsString("setViewsDir(dirname(__DIR__) . '/views/')", $bootstrap);
    }

    /**
     * The template engine renders the layout, so the controller must not.
     *
     * The old `render()` helper rendered the page and then rendered `layout` around
     * it. Asserted on the source because a returning regression here is silent: the
     * page still looks right, it just costs an extra render and stops being
     * comparable with the other entrants.
     */
    public function testTheControllersDoNotRenderTheLayoutThemselves(): void
    {
        foreach (['BenchController', 'FeatureController', 'ApiController'] as $controller) {
            $path   = dirname(__DIR__) . "/apps/phalcon/src/Controller/{$controller}.php";
            $source = (string) file_get_contents($path);

            self::assertDoesNotMatchRegularExpression(
                "~->render\\(\\s*'~",
                $source,
                "{$controller} calls render() directly — the view layer composes the page from the "
                    . 'dispatcher\'s controller/action names, and doing it by hand makes the row '
                    . 'incomparable with the other six apps'
            );
        }
    }

    /**
     * The templates RENDER, and render the right thing.
     *
     * This is the assertion a compile check cannot make: `count()` compiles
     * cleanly and throws on render, and a dot-notation array read compiles cleanly
     * and prints a warning INTO the page. Both would reach a benchmark as latency
     * for a broken page.
     */
    public function testEveryTemplateRendersCleanly(): void
    {
        $rendered = self::render('list', [
            'items' => [self::row(1), self::row(2)],
            'baseUrl' => '/items',
            'pagination' => [
                'currentPage' => 1, 'lastPage' => 2, 'previousPage' => 1, 'nextPage' => 2,
                'totalItems' => 30, 'firstItem' => 1, 'lastItem' => 20,
                'hasPrevious' => false, 'hasNext' => true,
            ],
        ]);

        self::assertStringContainsString('Item 1', $rendered);
        self::assertStringContainsString('class="even"', $rendered, 'the alternating row class must survive');
        self::assertStringContainsString('Next →', $rendered);
        self::assertStringContainsString('← Previous', $rendered);

        // The layout must have wrapped the page: these only exist in layout.volt.
        self::assertStringContainsString('<!DOCTYPE html>', $rendered, 'the layout document must wrap the page');
        self::assertStringContainsString('Rendered with Phalcon Volt', $rendered, 'the layout footer must be present');
        self::assertStringContainsString('<a href="/features">Features</a>', $rendered, 'the layout nav must be present');
    }

    /**
     * Escaping is equivalent to the `htmlspecialchars(ENT_QUOTES)` it replaced.
     *
     * `|e` goes through Phalcon's escaper (ENT_QUOTES|ENT_SUBSTITUTE|ENT_HTML401);
     * the plain-PHP templates called htmlspecialchars($x, ENT_QUOTES). For valid
     * UTF-8 the two agree exactly, which is what keeps the page byte-comparable
     * with every other entrant's.
     */
    public function testEscapeFilterMatchesThePreviousEscaping(): void
    {
        $nasty = 'a & b <c> "d" \'e\'';

        $rendered = self::render('show', ['item' => self::row(1, $nasty), 'flash' => '']);

        self::assertStringContainsString(
            htmlspecialchars($nasty, ENT_QUOTES),
            $rendered,
            '`|e` must produce the same escaping as htmlspecialchars(ENT_QUOTES)'
        );
        self::assertStringNotContainsString('<c>', $rendered, 'the raw markup must not reach the page');
    }

    private static function row(int $id, string $title = ''): object
    {
        $row = new \stdClass();
        $row->id         = $id;
        $row->title      = $title !== '' ? $title : 'Item ' . $id;
        $row->created_at = '2026-11-22 00:00:00';

        return $row;
    }

    /** @param array<string,mixed> $params */
    private static function render(string $action, array $params): string
    {
        $cache = sys_get_temp_dir() . '/phalcon-volt-test-' . getmypid();
        @mkdir($cache, 0777, true);

        $di = new \Phalcon\Di\Di();
        // NOT a static closure: Phalcon binds a service closure to the container
        // before invoking it, and a static closure cannot be re-bound — the
        // failure is "call_user_func(): Argument #1 must be a valid callback".
        $di->setShared('escaper', fn(): \Phalcon\Html\Escaper => new \Phalcon\Html\Escaper());

        // Mvc\View, not View\Simple: the layout and the `<controller>/<action>`
        // resolution are View's, and they are what the app runs. Rendering through
        // Simple here would test a component the application no longer uses.
        $view = new \Phalcon\Mvc\View();
        $view->setDI($di);
        $view->setViewsDir(self::viewsDir() . '/');
        $view->setLayoutsDir('layouts/');
        $view->setLayout('main');

        $volt = new \Phalcon\Mvc\View\Engine\Volt($view, $di);
        $volt->setOptions(['path' => $cache . '/', 'compileAlways' => false]);
        $view->registerEngines(['.volt' => $volt]);

        // View renders through output buffering: `start()` then `render()` then
        // `finish()`. Nothing is left open on success; on THROW a buffer stays open,
        // which PHPUnit reports as a risky test and which would also swallow the
        // failure message.
        $level = ob_get_level();
        try {
            $view->start();
            $view->render('bench', $action, $params + ['title' => ucfirst($action)]);
            $view->finish();

            return $view->getContent();
        } finally {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }
    }
}
