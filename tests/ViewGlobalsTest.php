<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `locale` and `platform` must be resolved by ONE parser, and rendered by every app.
 *
 * WHY THIS IS A TEST AND NOT A CONVENTION. The two view vars are printed in the
 * layout footer of every entrant, and before this test the seven apps disagreed
 * three different ways:
 *
 *   - Azera parsed `Accept-Language` and regexed the `User-Agent` per request,
 *     so its HTML rows carried work no other entrant performed;
 *   - Laravel, Symfony, Spiral, CodeIgniter and CakePHP returned the two literal
 *     strings the parser would have produced — an assertion of a detection that
 *     never happened, and, for Symfony, values no template even read;
 *   - Phalcon rendered no footer values at all, so its page silently disagreed
 *     with the other six.
 *
 * One entrant doing the work while five assert the result is a comparison between
 * different work. The fix is one shared parser (`bench-view-globals.php`), called by
 * all seven, which is what these tests pin — including the DEFAULT, because if the
 * parser's no-header answer stopped matching `en_US`/`desktop` every published
 * footer would move and no number would say why.
 */
final class ViewGlobalsTest extends TestCase
{
    /** Every entrant, and the file that must resolve the two values. */
    private const RESOLVERS = [
        'azera'       => 'apps/azera/Middleware/RequestContextMiddleware.php',
        'laravel'     => 'apps/laravel/app/Providers/AppServiceProvider.php',
        'symfony'     => 'apps/symfony/src/Controller/BenchController.php',
        'spiral'      => 'apps/spiral/src/Controller/BenchController.php',
        'codeigniter' => 'apps/codeigniter/Controllers/BaseController.php',
        'cakephp'     => 'apps/cakephp/src/Controller/AppController.php',
        'phalcon'     => 'apps/phalcon/src/Bootstrap.php',
    ];

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /** @return list<array{0: string}> */
    public static function appProvider(): array
    {
        return array_map(static fn(string $app): array => [$app], array_keys(self::RESOLVERS));
    }

    /**
     * The parser's own behaviour — the half that must not drift.
     */
    public function testTheSharedParserResolvesHeadersInTheClientsOrder(): void
    {
        require_once self::root() . '/bench-view-globals.php';

        // No header, empty header, unsupported language: the documented defaults.
        foreach ([null, '', 'xx-YY'] as $header) {
            self::assertSame('en_US', benchLocale($header), 'locale default');
        }

        // The CLIENT's order decides, not the map's. `de-DE,en;q=0.9` asks for German
        // first; a map-order implementation answers en_US because `en` is listed first.
        self::assertSame('de_DE', benchLocale('de-DE,en;q=0.9'), 'first supported tag wins');
        self::assertSame('en_US', benchLocale('en-US,en;q=0.9,de;q=0.8'), 'the header order decides');
        self::assertSame('ja_JP', benchLocale('ja-JP'));
        self::assertSame('es_ES', benchLocale('es'));
        self::assertSame('fr_FR', benchLocale('  fr-CA  '), 'whitespace and region are tolerated');

        // Platform: tablet before mobile, because Safari's iPad UA also says "Mobile".
        self::assertSame('desktop', benchPlatform(null));
        self::assertSame('desktop', benchPlatform('curl/8.5.0'));
        self::assertSame('mobile', benchPlatform('Mozilla/5.0 (Linux; Android 13) Mobile'));
        self::assertSame('mobile', benchPlatform('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)'));
        self::assertSame(
            'tablet',
            benchPlatform('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) Mobile/15E148'),
            'iPad outranks Mobile — Safari reports both'
        );
    }

    /**
     * The no-header answer must equal what every app rendered before this change.
     *
     * This is the regression that would be invisible: the benchmark sends no header
     * hints, so a changed default silently rewrites the footer of every page and
     * every published byte count, with nothing to attribute it to.
     */
    public function testTheNoHeaderDefaultMatchesWhatTheAppsRenderedBefore(): void
    {
        require_once self::root() . '/bench-view-globals.php';

        self::assertSame(
            ['locale' => 'en_US', 'platform' => 'desktop'],
            benchViewGlobals(null, null),
            'the header-less default must not move: every published footer depends on it'
        );
    }

    /**
     * Each app calls the shared parser rather than carrying its own copy.
     *
     * Checked by reading the resolver source: the seven apps hold requests in seven
     * types, so a behavioural test would need seven bootstraps in one process, which
     * the frameworks do not survive together (CI4's helpers collide with Laravel's).
     */
    #[DataProvider('appProvider')]
    public function testTheAppResolvesThroughTheSharedParser(string $app): void
    {
        $path = self::root() . '/' . self::RESOLVERS[$app];
        self::assertFileExists($path);

        $src = (string) file_get_contents($path);

        self::assertMatchesRegularExpression(
            '/benchViewGlobals(?:FromGlobals)?\(/',
            $src,
            "{$app} must resolve locale/platform through bench-view-globals.php"
        );

        // A literal pair is the exact shape this replaced; it must not come back.
        self::assertDoesNotMatchRegularExpression(
            "/'en_US'\s*,\s*'platform'\s*=>\s*'desktop'/",
            $src,
            "{$app} still hardcodes the pair instead of resolving it"
        );
    }

    /**
     * Every app's layout RENDERS the two values.
     *
     * Symfony had the values and printed nothing; Phalcon printed the footer and had
     * nothing. Both satisfy "resolves through the parser" while a reader sees no
     * locale, so the template is checked separately.
     */
    #[DataProvider('appProvider')]
    public function testTheLayoutRendersBothValues(string $app): void
    {
        $layouts = [
            'azera'       => 'apps/azera/Views/layouts/app.clarity.html',
            'laravel'     => 'apps/laravel/resources/views/layouts/app.blade.php',
            'symfony'     => 'apps/symfony/templates/base.html.twig',
            'spiral'      => 'apps/spiral/views/layouts/app.dark.php',
            'codeigniter' => 'apps/codeigniter/Views/layouts/app.php',
            'cakephp'     => 'apps/cakephp/templates/layout/default.php',
            'phalcon'     => 'apps/phalcon/views/layouts/main.volt',
        ];

        $path = self::root() . '/' . $layouts[$app];
        self::assertFileExists($path, "missing layout for {$app}");

        $src = (string) file_get_contents($path);
        foreach (['locale', 'platform'] as $var) {
            self::assertMatchesRegularExpression(
                '/' . $var . '/',
                $src,
                "{$app}'s layout never renders '{$var}'"
            );
        }
    }
}
