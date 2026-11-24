<?php

declare(strict_types=1);

/**
 * Emit a PRUNED composer autoloader for one benchmark app.
 *
 * WHY THIS EXISTS. All seven entrants share one `composer.json`, so one
 * `vendor/autoload.php` eagerly `require`s the `files` autoload entries of all seven
 * frameworks. Composer's `files` mechanism is not lazy: every entry runs at autoload
 * time, before the app has done anything. On the bench VM that is 59 files (~1 MB)
 * for Azera, and NOT ONE of them belongs to Azera:
 *
 *   cakephp/cakephp 33 · cycle/database 10 · laravel/framework 9
 *   laminas/laminas-diactoros 7 · spiral/framework 5 · twig/twig 4 · polyfills · ...
 *
 * Measured through real nginx + PHP-FPM, the boot band fell from 0.283 ms to
 * 0.066 ms when those entries were dropped with nothing else changed — 4.3x — while
 * `Bootstrap::boot()` itself was byte-for-byte the same work in both runs (89 us).
 * The published "framework boot" number was therefore mostly a measurement of the
 * OTHER six frameworks' bootstrap files.
 *
 * WHAT THIS DOES. Resolves the app's own dependency closure from `composer.lock` (the
 * lock records each package's `require` map; `installed.php` does not), then generates
 *
 *     vendor/app-autoload/<app>/autoload.php
 *
 * which is a complete, standalone composer autoloader — the SAME ClassLoader, the SAME
 * merged PSR-4/classmap tables, the SAME `platform_check.php` — whose `$files` loop
 * runs only the entries the app's closure owns. It mirrors `autoload_real.php` rather
 * than delegating to it, because `autoload_real.php` executes the full `files` list
 * before returning and pruning after that would be too late.
 *
 * WHY NOT ONE `composer install` PER APP. Seven installs is seven lock resolutions and
 * several GB for a tree that already exists, and it makes benchmark setup the slowest
 * part of a run. Pruning the eager list produces the same measurement with one small
 * generated file per app, and it is a pure function of the lock — reproducible and
 * diffable.
 *
 * WHY THE PSR-4 MAP IS NOT PRUNED TOO. It costs nothing: the loader only searches a
 * prefix map when a class is actually requested, so an unused framework's map entry is
 * one array key that is never read. Pruning it would risk breaking a class the app
 * loads through a transitive dependency (and the map is not ordered in a way that lets
 * a generator be sure). The `files` list is the only part that executes eagerly, so it
 * is the only part that is pruned.
 *
 * Usage:
 *   php scripts/app-autoload.php                 # every app, writes the trees
 *   php scripts/app-autoload.php --apps=azera    # one app
 *   php scripts/app-autoload.php --check         # report, write nothing
 *   php scripts/app-autoload.php --stats         # per-app closure contents
 */

$root = dirname(__DIR__);

/**
 * The composer package(s) each app's runtime needs, as the ROOTS of the closure.
 *
 * These are the packages the app's OWN code names, not just its framework's: an entry
 * script may `use` a PSR-7 implementation directly (Spiral's builds its request with
 * `Laminas\Diactoros\ServerRequestFactory` and emits through `Laminas\HttpHandlerRunner`),
 * and those packages are not in `spiral/framework`'s `require` map, so a
 * framework-only closure drops their `files` entries and the app dies with
 * "Call to undefined function Laminas\Diactoros\normalizeServer()".
 *
 * Kept explicit rather than derived from the app directory: two entrants reach their
 * framework through a metapackage (`symfony/framework-bundle`, `spiral/framework`) and
 * the rest through a single package, so no directory-to-package rule covers all seven.
 * A new entrant adds its lines here in the same change that adds its `apps/` directory —
 * and `tests/AppAutoloadTest.php` fails if it does not.
 *
 * @return array<string,list<string>>
 */
function appRootPackages(): array
{
    return [
        'azera'       => ['sailantis/azera-framework'],
        'laravel'     => ['laravel/framework'],
        'symfony'     => [
            'symfony/framework-bundle',
            // The Kernel enables these bundles explicitly and the app's models use
            // Doctrine directly; framework-bundle does not require any of them.
            'symfony/twig-bundle',
            'symfony/validator',
            'doctrine/doctrine-bundle',
            'doctrine/orm',
            'doctrine/dbal',
        ],
        // spiral/framework supplies the kernel; the entry script builds its request and
        // response with laminas-diactoros and emits through laminas-httphandlerrunner,
        // neither of which spiral/framework requires. The app's Kernel and services
        // name Cycle ORM, the Cycle bridge and the validator directly, so those are
        // roots too — `cycle/database` is the one that carries `files` entries.
        'spiral'      => [
            'spiral/framework',
            'spiral/cycle-bridge',
            'spiral/validator',
            'spiral-packages/league-event',
            'laminas/laminas-diactoros',
            'laminas/laminas-httphandlerrunner',
        ],
        'codeigniter' => ['codeigniter4/framework'],
        'cakephp'     => ['cakephp/cakephp'],
        'phalcon'     => ['phalcon/phalcon'],
    ];
}

/**
 * Package names that are platform requirements, not installable packages.
 *
 * `composer.lock` lists requirements of the form `php`, `ext-*` and `lib-*`. Naming
 * the rule once keeps the closure walk from treating them as missing packages.
 */
function isPlatformRequirement(string $name): bool
{
    return $name === 'php'
        || str_starts_with($name, 'php-')
        || str_starts_with($name, 'ext-')
        || str_starts_with($name, 'lib-');
}

/**
 * Index `composer.lock`'s packages by name (both `packages` and `packages-dev`).
 *
 * @return array<string,array<string,mixed>>
 */
function lockIndex(string $root): array
{
    $path = $root . '/composer.lock';
    if (!is_file($path)) {
        throw new RuntimeException("app-autoload: no composer.lock at {$path}");
    }
    $lock = json_decode((string) file_get_contents($path), true);
    if (!is_array($lock)) {
        throw new RuntimeException('app-autoload: composer.lock did not parse');
    }

    $index = [];
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $pkg) {
        $name = $pkg['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $index[$name] = $pkg;
        }
    }

    return $index;
}

/**
 * The transitive `require` closure of some root packages.
 *
 * `replace`/`provide` are honoured: a package declaring
 * `replace: {"symfony/polyfill-php80": "*"}` satisfies that requirement without being
 * installed under the name, so the replaced name is marked seen and never walked.
 *
 * @param list<string>                      $roots
 * @param array<string,array<string,mixed>> $index
 * @return array<string,true>
 */
function dependencyClosure(array $roots, array $index): array
{
    $seen  = [];
    $queue = $roots;

    while ($queue !== []) {
        $name = (string) array_shift($queue);
        if (isset($seen[$name]) || isPlatformRequirement($name)) {
            continue;
        }
        $seen[$name] = true;

        $pkg = $index[$name] ?? null;
        if ($pkg === null) {
            // Satisfied by a `replace`/`provide` we have not met yet, or by the
            // platform. Either way there is nothing to walk into.
            continue;
        }

        foreach (array_keys((array) ($pkg['require'] ?? [])) as $dep) {
            if (!isset($seen[$dep])) {
                $queue[] = (string) $dep;
            }
        }

        foreach (['replace', 'provide'] as $key) {
            foreach (array_keys((array) ($pkg[$key] ?? [])) as $provided) {
                $seen[$provided] = true;
            }
        }
    }

    return $seen;
}

/**
 * The package that owns a path relative to `vendor/`, or null when the path is not
 * two segments deep (`vendor/<owner>/<name>/...`).
 */
function owningPackage(string $relativePath): ?string
{
    $parts = array_values(array_filter(explode('/', ltrim($relativePath, '/'))));
    if (count($parts) < 2) {
        return null;
    }

    return $parts[0] . '/' . $parts[1];
}

/**
 * The `files` entries composer generated, read from the GENERATED
 * `autoload_static.php`.
 *
 * The generated file is the authority, not the lock: it already applied composer's
 * `files` semantics (content-hash dedupe, `autoload-dev`, `exclude-from-classmap`,
 * ordering). Re-deriving the list from the lock would be a second implementation that
 * could disagree with what actually runs.
 *
 * @return list<array{id:string,path:string,absolute:string,package:?string}>
 */
function generatedFilesEntries(string $root): array
{
    $staticPath = $root . '/vendor/composer/autoload_static.php';
    $source     = (string) file_get_contents($staticPath);
    if (preg_match('/public static \$files = array \((.*?)\n    \);/s', $source, $m) !== 1) {
        throw new RuntimeException('app-autoload: could not read $files from autoload_static.php');
    }

    preg_match_all(
        "/'([0-9a-f]{32})' => __DIR__ \. '\\/\\.\\.' \. '([^']+)',/",
        $m[1],
        $rows,
        PREG_SET_ORDER,
    );

    $out = [];
    foreach ($rows as $row) {
        $rel   = ltrim($row[2], '/');
        $out[] = [
            'id'       => $row[1],
            'path'     => $rel,
            'absolute' => $root . '/vendor/' . $rel,
            'package'  => owningPackage($rel),
        ];
    }

    return $out;
}

/**
 * Generate `vendor/app-autoload/<app>/autoload.php` and return stats.
 *
 * The generated file is a standalone autoloader mirroring `autoload_real.php` with a
 * pruned `$files` loop. It must NOT `require` the shared `autoload.php`: that would
 * run the full list before any pruning could apply.
 *
 * @param array<string,true> $closure
 * @return array{app:string,file:string,kept:int,dropped:int,dropped_packages:array<string,int>}
 */
function writeAppAutoload(string $root, string $app, array $closure): array
{
    $entries = generatedFilesEntries($root);

    $kept    = [];
    $dropped = [];
    $byPkg   = [];
    foreach ($entries as $entry) {
        if ($entry['package'] !== null && isset($closure[$entry['package']])) {
            $kept[] = $entry;
            continue;
        }
        $dropped[] = $entry;
        $pkg = $entry['package'] ?? '(unknown)';
        $byPkg[$pkg] = ($byPkg[$pkg] ?? 0) + 1;
    }
    arsort($byPkg);

    // Composer names its generated static class after a hash of the install, and the
    // loader below calls its `getInitializer()` directly, so the name is resolved here.
    $staticSource = (string) file_get_contents($root . '/vendor/composer/autoload_static.php');
    if (preg_match('/class (ComposerStaticInit[0-9a-f]+)/', $staticSource, $ms) !== 1) {
        throw new RuntimeException('app-autoload: no ComposerStaticInit class in autoload_static.php');
    }
    $staticClass = $ms[1];

    $keptLines = '';
    foreach ($kept as $entry) {
        // `$entry['path']` is relative to `vendor/`, and this file lives in
        // `vendor/app-autoload/<app>/`, so the prefix is `../../`.
        $keptLines .= sprintf(
            "            %s => __DIR__ . '/../../%s',\n",
            var_export($entry['id'], true),
            $entry['path'],
        );
    }
    if ($keptLines === '') {
        $keptLines = "            // this app's closure declares no `files` autoload entries\n";
    }

    $dir = $root . '/vendor/app-autoload/' . $app;
    if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
        throw new RuntimeException("app-autoload: could not create {$dir}");
    }

    $code = <<<PHP
    <?php

    // @generated by scripts/app-autoload.php — do not edit.
    //
    // Standalone composer autoloader for the `{$app}` benchmark app: the SAME loader,
    // the SAME PSR-4 / classmap tables and the SAME platform check as
    // vendor/autoload.php, but its EAGER `files` loop runs only the entries this app's
    // own dependency closure owns.
    //
    // Composer's `files` mechanism is not lazy — every entry executes at autoload time.
    // This repository's single composer.json requires all seven benchmark frameworks,
    // so the shared loader runs about 1 MB of bootstrap code belonging to frameworks
    // this app never instantiates. See the generator's docblock for the measurement.
    //
    // WHY THIS REPEATS composer's autoload_real.php INSTEAD OF REQUIRING IT.
    // `ComposerAutoloaderInit…::getLoader()` runs the FULL `files` list before it
    // returns, so a caller cannot prune afterwards: by then the competitor files have
    // already executed. The steps below are that method's body in the same order, with
    // the `files` loop replaced.

    // Every path below is relative to vendor/composer/, which is TWO levels up from
    // vendor/app-autoload/<app>/, and `files` entries are relative to vendor/.
    require_once __DIR__ . '/../../composer/platform_check.php';

    spl_autoload_register(static function (string \$class): void {
        if ('Composer\\Autoload\\ClassLoader' === \$class) {
            require_once __DIR__ . '/../../composer/ClassLoader.php';
        }
    }, true, true);

    \$loader = new \\Composer\\Autoload\\ClassLoader(\\dirname(__DIR__, 2) . '/..');

    // `autoload_static.php` DECLARES a class, so it can only be loaded once per
    // process. CodeIgniter's own Autoloader `include`s COMPOSER_PATH again during
    // boot (see public/index-codeigniter.php), which would re-declare it fatally —
    // hence `require_once` and this guard rather than composer's plain `require`.
    if (!class_exists(\\Composer\\Autoload\\{$staticClass}::class, false)) {
        require_once __DIR__ . '/../../composer/autoload_static.php';
    }
    \\call_user_func(\\Composer\\Autoload\\{$staticClass}::getInitializer(\$loader));

    \$loader->register(true);

    /** @var array<string,string> the entries THIS app's closure owns */
    \$appFiles = [
    {$keptLines}    ];

    \$requireFile = \\Closure::bind(static function (string \$fileIdentifier, string \$file): void {
        if (empty(\$GLOBALS['__composer_autoload_files'][\$fileIdentifier])) {
            \$GLOBALS['__composer_autoload_files'][\$fileIdentifier] = true;

            require \$file;
        }
    }, null, null);

    foreach (\$appFiles as \$__appFileId => \$__appFile) {
        \$requireFile(\$__appFileId, \$__appFile);
    }

    return \$loader;

    PHP;

    file_put_contents($dir . '/autoload.php', $code);

    return [
        'app'              => $app,
        'file'             => $dir . '/autoload.php',
        'kept'             => count($kept),
        'dropped'          => count($dropped),
        'dropped_packages' => $byPkg,
    ];
}

// --- CLI ---------------------------------------------------------------------

// Guarded on being the ENTRY SCRIPT, not just on the SAPI: `tests/bootstrap.php`
// requires this file for its helpers, and an unguarded CLI block would then read argv
// from PHPUnit and exit(). `realpath($argv[0]) === realpath(__FILE__)` is true only
// when this file was what the interpreter was asked to run.
$isEntryPoint = PHP_SAPI === 'cli'
    && isset($argv[0])
    && is_string($argv[0])
    && realpath($argv[0]) === realpath(__FILE__);

if ($isEntryPoint) {
    $opts = getopt('', ['apps::', 'check', 'stats', 'help']);

    if (isset($opts['help'])) {
        echo "Usage: php scripts/app-autoload.php [--apps=csv] [--check] [--stats]\n";
        exit(0);
    }

    $roots = appRootPackages();
    $apps  = isset($opts['apps'])
        ? array_values(array_filter(array_map('trim', explode(',', (string) $opts['apps']))))
        : array_keys($roots);

    $unknown = array_diff($apps, array_keys($roots));
    if ($unknown !== []) {
        fwrite(STDERR, 'app-autoload: unknown app(s): ' . implode(', ', $unknown) . "\n");
        exit(1);
    }

    $index = lockIndex($root);
    $check = isset($opts['check']);
    $stats = isset($opts['stats']);

    foreach ($apps as $app) {
        $closure = dependencyClosure($roots[$app], $index);

        if ($stats) {
            $owned = array_filter(
                $index,
                static fn(string $k): bool => isset($closure[$k]),
                ARRAY_FILTER_USE_KEY,
            );
            $files = 0;
            foreach ($owned as $name => $pkg) {
                $files += count((array) ($pkg['autoload']['files'] ?? []));
            }
            printf(
                "%-12s closure %3d packages, %2d files entries\n",
                $app,
                count($closure),
                $files,
            );
            continue;
        }

        if ($check) {
            $entries = generatedFilesEntries($root);
            $keep    = 0;
            foreach ($entries as $e) {
                if ($e['package'] !== null && isset($closure[$e['package']])) {
                    $keep++;
                }
            }
            printf("%-12s would keep %2d / %2d files entries\n", $app, $keep, count($entries));
            continue;
        }

        $result = writeAppAutoload($root, $app, $closure);
        printf(
            "%-12s %2d kept, %2d dropped  -> %s\n",
            $app,
            $result['kept'],
            $result['dropped'],
            str_replace($root . '/', '', $result['file']),
        );
        foreach ($result['dropped_packages'] as $pkg => $n) {
            printf("    dropped %-34s %d\n", $pkg, $n);
        }
    }

    exit(0);
}
