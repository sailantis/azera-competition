<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every write endpoint must write a title that actually DIFFERS from the row it replaces.
 *
 * WHY THIS IS A CORRECTNESS TEST AND NOT A STYLE RULE.
 *
 * The demo write endpoints upsert a FIXED sentinel row (`ON CONFLICT (id) DO UPDATE`).
 * The id never changes and `created_at` has one-second resolution, so the TITLE is the
 * only column that can differ between two consecutive requests. If it is built from a
 * timestamp alone, every request inside the same second proposes byte-identical row
 * content — and SQLite then decides there is nothing to write and skips the commit
 * instead of forcing a no-op page through the journal.
 *
 * Measured on the bench VM, rollback-journal mode, where one real commit costs ~0.5 ms
 * and is impossible to mistake for a cache hit:
 *
 *   40 upserts with an identical title             ->   4 fdatasync calls
 *   40 upserts with a per-request unique title     -> 160 fdatasync calls
 *
 * Through a real entrant this is not a micro-optimisation either. Phalcon was the only
 * framework whose `/features/aop-tx` and `/features/aop-tx-direct` wrote a date-only title, and
 * it was also the only framework that appeared to commit for free:
 *
 *   Phalcon  /features/aop-tx-direct, rollback-journal   date-only title   0.021 ms
 *   Phalcon  /features/aop-tx-direct, rollback-journal   unique title      0.486 ms  (23x)
 *   Azera    /features/aop-tx-direct, rollback-journal   unique title      0.515 ms
 *
 * So the "0.021 ms write" was a read: no page was dirtied, nothing was journaled. That
 * single asymmetry is what made a WAL-vs-rollback-journal experiment look as though
 * disabling WAL cost Azera 29% and cost Phalcon nothing — it was not the journal mode,
 * it was one entrant not writing.
 *
 * Every entrant already used a `#<random>` suffix on its `/features/aop-tx` titles, so the
 * fix is to apply that same suffix to EVERY title that reaches a write. Phalcon routes
 * them through `writeTitle()`; the others append it inline.
 */
final class WriteTitleUniquenessTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Every file that builds a title for one of the sentinel-row write endpoints.
     *
     * @return list<string>
     */
    private static function titleSources(): array
    {
        return [
            'apps/azera/Controllers/FeatureController.php',
            'apps/azera/Services/FeatureService.php',
            'apps/cakephp/src/Controller/FeatureController.php',
            'apps/codeigniter/Controllers/Feature.php',
            'apps/laravel/app/Http/Controllers/FeatureController.php',
            'apps/phalcon/src/Controller/FeatureController.php',
            'apps/spiral/src/Controller/FeatureController.php',
            'apps/symfony/src/Controller/FeatureController.php',
        ];
    }

    /**
     * Every file that builds a title for one of the POST sentinel-row write endpoints.
     *
     * These are a separate list from the feature-demo writes because they were a separate
     * defect: the three POST routes upsert fixed ids (`999999`, `999997`, `999998`) with a
     * `date('Y-m-d H:i:s')` title, so consecutive requests inside one second proposed
     * byte-identical rows and SQLite skipped the commit. Symfony and Azera had already
     * worked around it (microtime suffix); the other five — including Phalcon — had not,
     * which meant the two apps that forced a real write were paying for a commit the rest
     * dodged.
     *
     * @return list<string>
     */
    private static function postSources(): array
    {
        return [
            'apps/azera/Controllers/BenchController.php',
            'apps/azera/Controllers/ApiController.php',
            'apps/azera/Services/OrmDemoService.php',
            'apps/cakephp/src/Controller/BenchController.php',
            'apps/cakephp/src/Controller/ApiController.php',
            'apps/codeigniter/Controllers/Bench.php',
            'apps/codeigniter/Controllers/Api.php',
            'apps/laravel/app/Http/Controllers/BenchController.php',
            'apps/laravel/app/Http/Controllers/ApiController.php',
            'apps/phalcon/src/Controller/BenchController.php',
            'apps/phalcon/src/Controller/ApiController.php',
            'apps/spiral/src/Controller/BenchController.php',
            'apps/spiral/src/Controller/ApiController.php',
            'apps/symfony/src/Controller/BenchController.php',
            'apps/symfony/src/Controller/ApiController.php',
        ];
    }

    /**
     * The POST write paths must also propose a changed row on every request.
     */
    public function testEveryPostWriteProposesAUniqueRow(): void
    {
        $offenders = [];

        foreach (self::postSources() as $relative) {
            $path = self::root() . '/' . $relative;

            if (!is_file($path)) {
                continue; // optional file for some app layouts
            }

            $source = (string) file_get_contents($path);

            // Only POST handlers: isolate each `public function` body and keep the write ones.
            preg_match_all(
                '/public function (\w+)\([^)]*\)[^{]*\{(.*?)\n    \}/s',
                $source,
                $matches,
                PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                [, $method, $body] = $match;

                // A WRITE, not merely an execute(): Phalcon's read path also calls
                // `->execute()` on a SELECT, so `execute` alone would flag read handlers.
                $writes = preg_match(
                    '/upsert|insertQuery|->save\(|->update\(|\bINSERT\s+INTO\b|\bUPDATE\s+\w+\s+SET\b/i',
                    $body,
                ) === 1;
                if (!$writes) {
                    continue;
                }

                // It must build a title/created_at that changes per request.
                $unique = preg_match('/(random_int|uniqid|mt_rand|microtime)/', $body) === 1;
                if (!$unique) {
                    // A monotonic counter also guarantees uniqueness.
                    $unique = preg_match('/\+\+\s*\$counter|\$counter\s*\+\+/', $body) === 1;
                }

                $buildsTitle = preg_match('/[\'"](\w+ )?Item[\'"]\s*\./', $body) === 1
                    || preg_match('/\'title\'\s*=>/', $body) === 1
                    || preg_match('/->title\s*=/', $body) === 1;

                if ($buildsTitle && !$unique) {
                    $offenders[] = $relative . ' :: ' . $method . '()';
                }
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These POST write handlers build a title with no per-request unique component, so a\n"
                . "second request inside the same second proposes an identical row and SQLite\n"
                . "skips the commit. The endpoint then measures a no-op upsert, and every\n"
                . "entrant that DOES force a write pays a commit they dodge:\n  "
                . implode("\n  ", $offenders)
        );
    }

    /**
     * A title built for a sentinel upsert must carry a per-request unique component.
     */
    public function testEverySentinelWriteTitleCarriesAPerRequestSuffix(): void
    {
        $offenders = [];

        foreach (self::titleSources() as $relative) {
            $path = self::root() . '/' . $relative;
            self::assertFileExists($path, "{$relative} moved");

            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];

            // Variables assigned from a per-request random source, so a title line may
            // reuse one (CakePHP and CI4 build `$rand = random_int(1000, 9999)` first).
            $uniqueVars = [];
            foreach ($lines as $line) {
                if (preg_match('/\$(\w+)\s*=\s*(?:random_int|mt_rand|uniqid)\(/', $line, $m) === 1) {
                    $uniqueVars[$m[1]] = true;
                }
            }

            foreach ($lines as $i => $line) {
                // Only lines that actually BUILD one of the sentinel titles.
                if (preg_match('/[\'"](AOP|Event|DbEvent) Item/', $line) !== 1) {
                    continue;
                }

                // `writeTitle()` owns the suffix for Phalcon; the rest append it inline.
                if (preg_match('/random_int|uniqid|writeTitle/', $line) === 1) {
                    continue;
                }

                foreach (array_keys($uniqueVars) as $var) {
                    if (str_contains($line, '$' . $var)) {
                        continue 2;
                    }
                }

                $offenders[] = $relative . ':' . ($i + 1) . '  ' . trim($line);
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These sentinel-row writes build a title with no per-request suffix, so a\n"
                . "second request inside the same second writes identical row content and\n"
                . "SQLite skips the commit entirely (measured: 4 vs 160 fdatasync for 40\n"
                . "upserts). The endpoint would then measure a cache hit, not a write:\n  "
                . implode("\n  ", $offenders)
        );
    }

    /**
     * Phalcon must not build a sentinel title without going through writeTitle().
     *
     * Guarded separately because Phalcon is where the defect was found, and because its
     * helper is the one place the measured rationale is documented.
     */
    public function testPhalconBuildsSentinelTitlesThroughTheHelper(): void
    {
        $source = (string) file_get_contents(
            self::root() . '/apps/phalcon/src/Controller/FeatureController.php'
        );

        self::assertMatchesRegularExpression(
            '/private function writeTitle\(/',
            $source,
            'Phalcon\'s writeTitle() helper is what appends the per-request suffix.'
        );

        self::assertMatchesRegularExpression(
            "/writeTitle\('(AOP|DbEvent|Event) Item'/",
            $source,
            'Phalcon must route its sentinel titles through writeTitle().'
        );

        self::assertSame(
            0,
            preg_match_all("/upsertSentinel\(\\\$db, [^,]+,\s*'(?!.*writeTitle)/", $source),
            'No upsertSentinel() call may pass a literal title instead of writeTitle().'
        );
    }
}
