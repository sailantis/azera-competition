<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the Spiral router bootloader's shared-instance bindings.
 *
 * Background. The published RoadRunner "warm recycle" figure for Spiral was
 * dominated by per-route container autowiring that threw the result away:
 *
 *  - `RouteGroup::register()` runs once per route on EVERY re-boot and calls
 *    `$factory->make(UriHandler::class)`. Nothing was bound, so that went
 *    through reflection-based autowiring (~24 us/route) and the object was
 *    discarded immediately — `register()` calls `->withPrefix(...)`, which
 *    clones.
 *  - `Router::configure()` runs per route and calls
 *    `$route->withContainer(...)`, which does
 *    `$this->container->get(LazyPipeline::class)` (~14 us/route).
 *
 * Measured on the bench VM (n=20, 2026-09-18): 6.94 ms -> 4.91 ms with the
 * UriHandler binding alone, -> 5.70 ms with LazyPipeline alone, -> 3.66 ms with
 * both (a 47% cut).
 *
 * Both objects are safe to share ONLY because every `with*()` method on them
 * clones before mutating. That is the invariant worth pinning: if a Spiral
 * upgrade ever makes one of them mutate in place, the singleton bindings stop
 * being equivalent to per-route instances and every route would silently share
 * one another's prefix/pattern/constraints. These tests fail loudly in that
 * case rather than letting it reach a benchmark run.
 */
final class SpiralRouterBindingsTest extends TestCase
{
    private const BOOTLOADER = '/apps/spiral/src/Bootloader/AppRoutesBootloader.php';
    private const URI_HANDLER = '/vendor/spiral/framework/src/Router/src/UriHandler.php';
    private const LAZY_PIPELINE = '/vendor/spiral/framework/src/Http/src/LazyPipeline.php';

    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function read(string $relative): string
    {
        $path = self::repoRoot() . $relative;
        self::assertFileExists($path, "expected source file {$relative}");

        return (string) file_get_contents($path);
    }

    /**
     * The bootloader must keep binding all three shared services.
     *
     * Slugify is the oldest of the three (a per-route `new Slugify()` inside
     * UriHandler's constructor, worth ~16 ms with an unbound interface).
     */
    public function testBindingsAreDeclared(): void
    {
        $src = self::read(self::BOOTLOADER);

        // Scope the scan to defineSingletons() so an unrelated mention of the
        // class elsewhere in the file cannot make this pass.
        $start = strpos($src, 'function defineSingletons');
        self::assertNotFalse($start, 'AppRoutesBootloader must declare defineSingletons()');

        $next = strpos($src, 'protected function defineRoutes', $start);
        self::assertNotFalse($next, 'defineSingletons() must be followed by defineRoutes()');

        $body = substr($src, $start, $next - $start);

        foreach ([
            'Cocur\\Slugify\\SlugifyInterface',
            'Spiral\\Router\\UriHandler',
            'Spiral\\Http\\LazyPipeline',
        ] as $class) {
            self::assertMatchesRegularExpression(
                '/' . preg_quote($class, '/') . '::class\s*=>/',
                $body,
                "defineSingletons() must bind {$class} to a shared instance"
            );
        }
    }

    /**
     * Every `with*()` method on the shared services must clone before mutating.
     *
     * This is what makes one shared instance equivalent to one per route.
     */
    public function testSharedServicesCloneBeforeMutating(): void
    {
        foreach ([self::URI_HANDLER, self::LAZY_PIPELINE] as $file) {
            $src   = self::read($file);
            $flaws = self::withMethodsWithoutClone($src);

            self::assertNotSame(
                [],
                self::withMethodNames($src),
                "{$file} should declare with*() methods — the scan found none, "
                    . 'so this test would pass vacuously'
            );
            self::assertSame(
                [],
                $flaws,
                sprintf(
                    '%s has with*() method(s) that mutate without cloning: %s. '
                        . 'Sharing one instance across routes is no longer safe — '
                        . 'remove the singleton binding in AppRoutesBootloader or '
                        . 'stop sharing.',
                    $file,
                    implode(', ', $flaws)
                )
            );
        }
    }

    /**
     * UriHandler's one genuinely mutating method must have no caller.
     *
     * `setStrict()` assigns to `$this->strict` instead of cloning. Sharing the
     * instance is only safe while nothing calls it, so a future framework
     * version (or an app) starting to call it would silently turn the shared
     * handler into a cross-route channel. That is worth failing on, because the
     * symptom would be a routing bug on one endpoint caused by another.
     */
    public function testUriHandlerHasNoMutatingSetterCallSite(): void
    {
        $root     = self::repoRoot();
        $scanDirs = ['/apps', '/adapters', '/deploy', '/scripts', '/vendor/spiral'];

        $callers = [];
        foreach ($scanDirs as $dir) {
            $base = $root . $dir;
            if (!is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = '/' . ltrim(
                        str_replace('\\', '/', substr($file->getPathname(), strlen($root))),
                        '/'
                    );
                if ($relative === self::URI_HANDLER) {
                    continue; // the declaration itself
                }

                $source = (string) file_get_contents($file->getPathname());
                if (preg_match('/->setStrict\s*\(/', $source) === 1) {
                    $callers[] = $relative;
                }
            }
        }

        self::assertSame(
            [],
            $callers,
            'UriHandler::setStrict() mutates in place; the shared UriHandler '
                . 'binding is only correct while nothing calls it. New caller(s): '
                . implode(', ', $callers)
        );
    }

    /**
     * @return list<string> names of `public function with...` methods
     */
    private static function withMethodNames(string $src): array
    {
        preg_match_all('/public function (with[A-Za-z0-9_]*)\s*\(/', $src, $m);

        return array_values($m[1]);
    }

    /**
     * @return list<string> `with*()` methods whose body contains no `clone $this`
     */
    private static function withMethodsWithoutClone(string $src): array
    {
        $flaws = [];

        foreach (self::withMethodNames($src) as $name) {
            $decl = strpos($src, 'public function ' . $name . '(');
            if ($decl === false) {
                continue;
            }

            // Body runs from the declaration to the next method declaration.
            $nextDecl = strpos($src, 'public function ', $decl + 1);
            $body     = substr($src, $decl, $nextDecl === false ? null : $nextDecl - $decl);

            if (!str_contains($body, 'clone $this')) {
                $flaws[] = $name . '()';
            }
        }

        return $flaws;
    }
}
