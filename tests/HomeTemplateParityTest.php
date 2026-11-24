<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every entrant's `GET /` template must carry the same SECTIONS.
 *
 * Why this is a rule and not a preference. `/` is the one row in the suite with no
 * database work, no ORM and no write — it is routing + a template render — so the
 * template IS the workload. On 2026-11-24 the row was compared across entrants while
 * Phalcon's page was 3402 bytes and Azera's was 4976: the Phalcon template carried
 * only "Endpoints" and "Try it", while all six others also had "What is measured?"
 * and "Stack". The row therefore compared a larger document against a smaller one,
 * and the missing sections are most of the difference.
 *
 * The report states that "the payload is identical no matter which framework served
 * it" (scripts/report/BenchmarkConfig.php::requestWorkload()). That is true of every
 * endpoint with a shared contract — `/items` pages the same 20 seeded rows in all
 * seven apps, and HydrationContractTest enforces the hydration half of it — but `/`
 * had no such test, which is exactly why it drifted.
 *
 * WHAT IS ASSERTED is heading TEXT, not byte equality. Each entrant writes its own
 * copy — that is the point, they are independently written apps — so the sections
 * are what must agree: a page missing one is doing less work, and a page with an
 * extra one is doing more. Byte equality would also fail on whitespace and on each
 * engine's escaping, which no reader of the row cares about.
 */
final class HomeTemplateParityTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * The template each app renders for `GET /`.
     *
     * Named per app because there is no convention across seven frameworks: the file
     * is `home.blade.php`, `home.html.twig`, `home.dark.php`, `home.php`,
     * `templates/Bench/index.php`, `views/bench/index.volt` or a Clarity template —
     * and the Phalcon and CakePHP paths are derived from the CONTROLLER and ACTION
     * names, not from the route.
     *
     * @return array<string,string>
     */
    private static function homeTemplates(): array
    {
        $root = self::repoRoot();

        return [
            'azera'       => "{$root}/apps/azera/Views/home/welcome.clarity.html",
            'laravel'     => "{$root}/apps/laravel/resources/views/home.blade.php",
            'symfony'     => "{$root}/apps/symfony/templates/home.html.twig",
            'spiral'      => "{$root}/apps/spiral/views/home.dark.php",
            'codeigniter' => "{$root}/apps/codeigniter/Views/home.php",
            'cakephp'     => "{$root}/apps/cakephp/templates/Bench/index.php",
            'phalcon'     => "{$root}/apps/phalcon/views/bench/index.volt",
        ];
    }

    /**
     * The sections every home page must have.
     *
     * These are the headings the six original entrants agreed on before Phalcon was
     * added. "What is measured?" documents the workload the page's own endpoints
     * exercise and "Stack" names the framework, engine and ORM — a page without them
     * is a shorter render of the same route.
     *
     * @return list<string>
     */
    private static function requiredSections(): array
    {
        return ['Endpoints', 'Try it', 'What is measured?', 'Stack'];
    }

    public static function entrantProvider(): array
    {
        return array_map(static fn(string $app): array => [$app], array_keys(self::homeTemplates()));
    }

    #[DataProvider('entrantProvider')]
    public function testTheHomePageCarriesEverySection(string $app): void
    {
        $path = self::homeTemplates()[$app];
        self::assertFileExists($path, "{$app}: no home template at {$path}");

        $source = (string) file_get_contents($path);

        preg_match_all('~<h2>(.*?)</h2>~', $source, $matches);
        $headings = array_map(static fn(string $h): string => trim(strip_tags($h)), $matches[1]);

        foreach (self::requiredSections() as $section) {
            self::assertContains(
                $section,
                $headings,
                "{$app}'s GET / template is missing the \"{$section}\" section. / is the only row with"
                    . ' no DB work, so its template IS the workload: a page without this section'
                    . ' renders less than every other entrant\'s and the row stops being comparable.'
            );
        }
    }

    /**
     * The "What is measured?" section is a table of endpoints, not prose.
     *
     * The six original entrants all use a `<table>` with one row per measured
     * endpoint. A paragraph would satisfy the heading check while doing far less work
     * than the table it replaces, so the SHAPE is asserted too.
     */
    #[DataProvider('entrantProvider')]
    public function testTheMeasuredSectionIsATableOfEndpoints(string $app): void
    {
        $source = (string) file_get_contents(self::homeTemplates()[$app]);

        $section = null;
        if (preg_match('~<h2>What is measured\?</h2>(.*?)</table>~s', $source, $m) === 1) {
            $section = $m[1];
        }

        self::assertNotNull($section, "{$app}: no table follows the \"What is measured?\" heading");
        self::assertStringContainsString(
            'GET /items',
            $section,
            "{$app}: the workload table must name the endpoints, as the other six do"
        );
    }
}
