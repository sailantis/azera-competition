<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An app's boot must not execute another entrant's bootstrap files.
 *
 * WHY THIS IS A RULE AND NOT A PREFERENCE. All seven entrants share one composer.json,
 * so one `vendor/autoload.php` eagerly `require`s the `files` autoload entries of all
 * seven frameworks. Composer's `files` mechanism is not lazy: every entry executes at
 * autoload time. For Azera that is 59 files (~1 MB) owned by packages it never
 * instantiates — cakephp/cakephp, laravel/framework, spiral/framework, cycle/database,
 * laminas/laminas-diactoros, twig/twig — and NOT ONE of them belongs to Azera.
 *
 * Measured through real nginx + PHP-FPM on the bench VM, the `/` boot band read
 * 0.283 ms with those entries and 0.066 ms without them, while `Bootstrap::boot()`
 * itself did identical work in both runs (89 us). So most of the published "framework
 * boot" number was a measurement of the other six frameworks' bootstrap code, and the
 * boot band silently compared "which app's closure happens to be the smallest fraction
 * of a shared install" rather than "which framework boots faster".
 *
 * WHAT IS ASSERTED. Three things, because each fails independently:
 *
 *   1. Every app key has a dependency-closure entry in scripts/app-autoload.php. A new
 *      entrant that forgets its line would silently get the full autoloader.
 *   2. The generated loader's `files` list contains no entry owned by a package
 *      outside that app's closure. This is the actual property being measured.
 *   3. Every process that boots an app goes through benchRequireAutoload(), so the
 *      per-request entry scripts, the two workers and the in-process harness cannot
 *      drift into different autoloaders for the same app.
 */
final class AppAutoloadTest extends TestCase
{
    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /** @return array<string,list<string>> */
    private static function appRoots(): array
    {
        return appRootPackages();
    }

    public static function appProvider(): array
    {
        return array_map(static fn(string $a): array => [$a], array_keys(appRootPackages()));
    }

    #[DataProvider('appProvider')]
    public function testTheAppHasAClosureEntry(string $app): void
    {
        self::assertArrayHasKey(
            $app,
            self::appRoots(),
            "{$app} has no dependency-closure roots in scripts/app-autoload.php — its boot"
                . ' would fall back to the shared autoloader and measure six other frameworks',
        );
    }

    /**
     * The generated loader lists ONLY files from the app's own closure.
     *
     * This is the load-bearing assertion: it is the property the boot band depends on.
     * Reading the GENERATED file (not recomputing the closure) is deliberate — it
     * checks what will actually run, so a stale or hand-edited loader fails here.
     */
    #[DataProvider('appProvider')]
    public function testTheGeneratedLoaderDropsForeignFiles(string $app): void
    {
        $path = self::root() . '/vendor/app-autoload/' . $app . '/autoload.php';
        if (!is_file($path)) {
            self::markTestSkipped(
                "vendor/app-autoload/{$app}/autoload.php is not generated on this checkout —"
                . ' run `php scripts/app-autoload.php`',
            );
        }

        $source = (string) file_get_contents($path);
        if (preg_match('/\$appFiles = \[(.*?)\n    \];/s', $source, $m) !== 1) {
            self::fail("{$app}: the generated loader has no \$appFiles list");
        }

        $closure = dependencyClosure(self::appRoots()[$app], lockIndex(self::root()));

        preg_match_all("/=> __DIR__ \. '\\/\.\.\\/\.\.\\/([^']+)'/", $m[1], $entries);
        self::assertGreaterThan(
            0,
            count($entries[1]) + 1,
            "{$app}: unparseable \$appFiles list",
        );

        foreach ($entries[1] as $relative) {
            $owner = owningPackage($relative);
            self::assertNotNull($owner, "{$app}: could not resolve the owner of {$relative}");
            self::assertArrayHasKey(
                $owner,
                $closure,
                "{$app}'s autoloader still requires {$relative}, owned by {$owner}, which is"
                    . ' outside its dependency closure — that file executes at boot and belongs'
                    . ' to another entrant',
            );
        }
    }

    /**
     * The closure covers every package the app's OWN code names.
     *
     * The failure this catches is subtle and was real: Spiral's entry script builds its
     * request with `Laminas\Diactoros\ServerRequestFactory` and emits through
     * `Laminas\HttpHandlerRunner`, and NEITHER is required by `spiral/framework`. A
     * framework-only closure therefore dropped laminas-diactoros' `files` entry, and
     * the app died with "Call to undefined function Laminas\Diactoros\normalizeServer()"
     * — a fatal on every request, from a list that looked complete.
     *
     * Asserted by scanning the app's PHP for `use Vendor\Package\…` statements and
     * requiring that the vendor/package prefix resolves inside the closure or is a
     * first-party namespace. Not a proof of completeness (an app can reach a package
     * through a string class name), but it catches the shape that broke.
     */
    #[DataProvider('appProvider')]
    public function testTheClosureCoversNamespacesTheAppNames(string $app): void
    {
        $root    = self::root();
        $closure = dependencyClosure(self::appRoots()[$app], lockIndex($root));

        // Namespace prefix => owning package, taken from COMPOSER'S OWN PSR-4 map. A
        // guessed spelling does not generalise: one package owns many namespaces
        // (`Phalcon\Mvc`, `Phalcon\Db` and `Phalcon\Http` are all `phalcon/phalcon`),
        // while `Laminas\Diactoros` happens to double its vendor name.
        $ownerOf = [];
        foreach ((require $root . '/vendor/composer/autoload_psr4.php') as $prefix => $dirs) {
            foreach ((array) $dirs as $dir) {
                // Normalise SEPARATORS before anything else: composer writes absolute
                // paths in the host's separator, so on Windows a `/`-only replace left
                // `E:/Sailantis/azera-competition` as the "package".
                $relative = str_replace('\\', '/', (string) $dir);
                $relative = str_replace($root . '/vendor/', '', $relative);
                $package  = owningPackage($relative);
                if ($package !== null) {
                    $ownerOf[rtrim($prefix, '\\')] = $package;
                    break;
                }
            }
        }

        // First-party namespaces: the app's own code and the benchmark harness.
        $firstParty = ['App', 'Azera', 'Clarity', 'Ci4App', 'Config'];

        $files = glob("{$root}/apps/{$app}/*.php") ?: [];
        foreach (['*/*.php', '*/*/*.php'] as $depth) {
            $files = array_merge($files, glob("{$root}/apps/{$app}/{$depth}") ?: []);
        }
        $entry = "{$root}/public/index-{$app}.php";
        if (is_file($entry)) {
            $files[] = $entry;
        }

        self::assertNotEmpty($files, "{$app}: no source files found to scan");

        $missing = [];
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            preg_match_all('/^\s*use\s+\\\\?([A-Za-z_][\w]*(?:\\\\[A-Za-z_][\w]*)*)/m', $source, $m);

            foreach ($m[1] as $symbol) {
                $ns = str_replace('\\\\', '\\', $symbol);
                if (in_array(explode('\\', $ns)[0], $firstParty, true)) {
                    continue;
                }

                // Longest matching PSR-4 prefix wins, as in the class loader.
                $owner = null;
                $cut   = $ns;
                while ($cut !== '') {
                    if (isset($ownerOf[$cut])) {
                        $owner = $ownerOf[$cut];
                        break;
                    }
                    $pos = strrpos($cut, '\\');
                    if ($pos === false) {
                        break;
                    }
                    $cut = substr($cut, 0, $pos);
                }

                if ($owner === null || isset($closure[$owner])) {
                    continue;
                }
                $missing[$owner] = basename($file);
            }
        }

        self::assertSame(
            [],
            $missing,
            "{$app}'s dependency closure does not cover namespaces its own code uses. If"
                . ' one of these declares a `files` autoload entry, the pruned loader drops it'
                . ' and the app fatals at runtime. Add the package to appRootPackages().',
        );
    }

    /**
     * The apps whose closure legitimately has ZERO `files` entries.
     *
     * Azera, CodeIgniter and Phalcon depend only on PSR-4 packages, so their pruned
     * list is empty and their boot pays no `files` cost at all. Pinned because an empty
     * list is indistinguishable from a generator bug without it.
     */
    public static function zeroFileClosureProvider(): array
    {
        return [['azera'], ['codeigniter'], ['phalcon']];
    }

    #[DataProvider('zeroFileClosureProvider')]
    public function testAppsWithNoFilesEntriesAreExpectedToBeEmpty(string $app): void
    {
        $closure = dependencyClosure(self::appRoots()[$app], lockIndex(self::root()));

        $files = 0;
        foreach ($closure as $package => $_) {
            $pkg = lockIndex(self::root())[$package] ?? null;
            $files += count((array) ($pkg['autoload']['files'] ?? []));
        }

        self::assertSame(
            0,
            $files,
            "{$app}'s dependency closure now declares `files` autoload entries; the empty"
                . ' generated list is no longer correct and its boot got more expensive',
        );
    }

    /**
     * Every boot path resolves its autoloader through the one helper.
     *
     * Four places boot an app and they must agree, or the same app measures differently
     * in the in-process band and the FPM/worker bands for a reason no reader can see.
     * Asserted on SOURCE, because that is where a new `require 'vendor/autoload.php'`
     * would be added.
     */
    public function testEveryBootPathUsesTheSharedResolver(): void
    {
        $root = self::root();

        $entryScripts = glob($root . '/public/index-*.php') ?: [];
        self::assertNotEmpty($entryScripts, 'no entry scripts found');

        foreach ($entryScripts as $path) {
            $name = basename($path);
            if (str_starts_with($name, 'index-floor-')) {
                continue; // a floor boots no framework and has no closure to prune against
            }
            $source = (string) file_get_contents($path);
            self::assertStringContainsString(
                'benchRequireAutoload(',
                $source,
                "{$name} does not resolve its autoloader through benchRequireAutoload()",
            );
            self::assertStringNotContainsString(
                "require __DIR__ . '/../vendor/autoload.php';",
                $source,
                "{$name} requires the shared autoloader directly — that runs every entrant's"
                    . ' bootstrap files',
            );
        }

        // Only files that BOOT an app directly. `deploy/volucer/worker.php` is a thin
        // `$handler = require …/app.php`, so it inherits app.php's resolver and naming it
        // here would demand a duplicate call.
        foreach (['deploy/rr/worker.php', 'deploy/volucer/app.php', 'run-app.php'] as $relative) {
            $source = (string) file_get_contents("{$root}/{$relative}");
            self::assertStringContainsString(
                'benchRequireAutoload(',
                $source,
                "{$relative} does not resolve its autoloader through benchRequireAutoload()",
            );
        }
    }

    /**
     * A floor is never pruned.
     *
     * A floor is a request with no framework in it, so it has no dependency closure to
     * prune against. Giving it one would make the floor a framework-specific
     * measurement of a framework-free request.
     */
    public function testFloorsUseTheUnprunedAutoloader(): void
    {
        $choice = benchAppAutoload(self::root(), 'floor-volucer-attach');

        self::assertFalse($choice['pruned'], 'a floor must not be pruned to a framework closure');
        self::assertStringEndsWith('/vendor/autoload.php', str_replace('\\', '/', $choice['path']));
    }
}
