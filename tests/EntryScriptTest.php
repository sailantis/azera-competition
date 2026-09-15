<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The FPM entry scripts are the one part of the harness that runs INSIDE the
 * measured server, so a bug there does not fail loudly — it produces numbers.
 *
 * Both scripts below served empty HTTP 500s (azera) or failed to boot at all
 * (codeigniter) while the harness reported healthy rows, because a 500 body is
 * indistinguishable from a fast response once the client only records latency.
 * These tests pin the two invariants that were violated, at the source level:
 * the PSR-4 prefix must match the namespace the app actually declares, and CI4's
 * global helpers must win the function_exists race against Laravel's.
 */
final class EntryScriptTest extends TestCase
{
    private static function read(string $relative): string
    {
        $path = dirname(__DIR__) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** Every `namespace X;` a file declares. */
    private static function namespacesOf(string $source): array
    {
        preg_match_all('/^\s*namespace\s+([^;]+);/m', $source, $m);

        return array_map('trim', $m[1]);
    }

    /**
     * The autoload prefix must resolve each class BACK TO ITS OWN FILE.
     *
     * public/index-azera.php registered `App\` and resolved into apps/azera/,
     * so App\Azera\Bootstrap mapped to apps/azera/Azera/Bootstrap.php — a path
     * that never existed. Every FPM request was an empty 500 from 2026-09-12
     * until this was found, and the published rows measured that 500.
     *
     * The assertion is the round-trip, not a string match on the prefix: strip
     * the prefix from the class' namespace, join what is left onto the app dir,
     * and the result must be the file the class actually lives in. A prefix one
     * level too high (or too low) fails this immediately.
     *
     * @param list<string> $extraRoots additional namespace roots the framework
     *        maps separately (CI4 maps `Config\` alongside APP_NAMESPACE)
     */
    #[DataProvider('entryScripts')]
    public function testAutoloadPrefixResolvesEachClassToItsOwnFile(
        string $relative,
        string $appDir,
        ?string $namespaceConstant,
        array $extraRoots
    ): void {
        $entry  = self::read($relative);
        $prefix = self::prefixOf($entry, $relative, $namespaceConstant);
        // Normalise separators once: the repo root comes from __DIR__ (mixed on
        // Windows) while the suffix is built with '/'.
        $root = str_replace('\\', '/', dirname(__DIR__) . '/' . $appDir);
        $repo = str_replace('\\', '/', dirname(__DIR__));

        $checked    = 0;
        $mismatched = [];
        $iterator   = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $namespaces = self::namespacesOf((string) file_get_contents($file->getPathname()));
            if ($namespaces === []) {
                continue;
            }
            $ns = $namespaces[0];

            // Namespaces this framework maps through a different root
            // (CI4's Config\) or through its own PSR-4 list are out of scope —
            // this test is about the prefix that was broken.
            if (!str_starts_with($ns . '\\', $prefix)) {
                foreach ($extraRoots as $extra) {
                    if ($ns === $extra || str_starts_with($ns, $extra . '\\')) {
                        continue 2;
                    }
                }
                // A namespace outside both the tested prefix and the declared
                // extras is exactly the bug: nothing can autoload it.
                $mismatched[] = sprintf('%s (%s: outside %s)', $ns, $relative, $prefix);
                continue;
            }

            $checked++;
            $suffix   = substr($ns, strlen($prefix)); // "Controllers"
            $expected = rtrim($root . '/' . str_replace('\\', '/', $suffix), '/')
                . '/' . $file->getFilename();
            $actual = str_replace('\\', '/', $file->getPathname());

            if ($actual !== $expected) {
                $mismatched[] = sprintf(
                    'namespace %s lives in %s but %s resolves it to %s',
                    $ns,
                    substr($actual, strlen($repo) + 1),
                    $relative,
                    substr($expected, strlen($repo) + 1)
                );
            }
        }

        self::assertGreaterThan(0, $checked, "{$relative}: no class resolved through {$prefix}");
        self::assertSame([], array_values(array_unique($mismatched)));
    }

    private static function prefixOf(string $entry, string $relative, ?string $namespaceConstant): string
    {
        if ($namespaceConstant !== null) {
            preg_match("/define\('" . $namespaceConstant . "',\s*'([^']+)'\)/", $entry, $m);
            self::assertNotEmpty($m, "{$relative} must define {$namespaceConstant}");

            return $m[1] . '\\';
        }

        preg_match("/\\\$prefix\s*=\s*'([^']+)'/", $entry, $m);
        self::assertNotEmpty($m, "{$relative} must register a PSR-4 prefix");
        $prefix = str_replace('\\\\', '\\', $m[1]);
        self::assertStringEndsWith('\\', $prefix, 'a namespace root ends with a separator');

        return $prefix;
    }

    public static function entryScripts(): array
    {
        return [
            'azera'       => ['public/index-azera.php', 'apps/azera', null, []],
            'codeigniter' => ['public/index-codeigniter.php', 'apps/codeigniter', 'APP_NAMESPACE', ['Config']],
        ];
    }

    /**
     * CI4's global helpers must be loaded BEFORE composer's.
     *
     * Composer's `files` autoload eagerly includes Laravel's helpers (config(),
     * app(), env(), e()...), and CI4 guards its own with function_exists — so
     * loading composer first silently leaves CI4 calling Laravel's. The failure
     * is an empty 500 ("Target class [config] does not exist"), not an error at
     * the point of the mistake.
     */
    public function testCodeigniterHelpersAreLoadedBeforeComposer(): void
    {
        $entry = self::read('public/index-codeigniter.php');

        $helpers  = strpos($entry, 'system/Common.php');
        $composer = strpos($entry, "'/../vendor/autoload.php'");

        self::assertNotFalse($helpers, 'the entry script must preload CI4\'s Common.php');
        self::assertNotFalse($composer, 'the entry script must load composer');
        self::assertLessThan(
            $composer,
            $helpers,
            'Common.php must be required BEFORE vendor/autoload.php, or Laravel\'s helpers shadow CI4\'s'
        );
    }

    /**
     * The connection release must be a shutdown hook.
     *
     * CI4 connects DURING the request, so closing connections inline at the end
     * of the entry script closes nothing (the cache is still empty) and the
     * leak continues — 2 fds per request, unbounded. Only a shutdown hook runs
     * late enough to see the live connection.
     */
    public function testCodeigniterClosesConnectionsInAShutdownHook(): void
    {
        $entry = self::read('public/index-codeigniter.php');

        self::assertStringContainsString('register_shutdown_function', $entry);
        self::assertMatchesRegularExpression(
            '/register_shutdown_function\(static function \(\): void \{(?:.*\n)*?.*Config::getConnections\(\)(?:.*\n)*?.*->close\(\)(?:.*\n)*?.*\}\);/',
            $entry,
            'the shutdown hook must close every cached connection'
        );

        // The hook must be registered before the request runs. The comparison is
        // LINE-based on purpose: bootWeb() is mentioned in six places (the
        // docblock, the boot-sequence comment, the class name), so only the
        // call — a line that STARTS with `exit(Boot::bootWeb` — identifies the
        // request itself.
        $hookLine = null;
        $bootLine = null;
        foreach (explode("\n", $entry) as $i => $line) {
            if ($hookLine === null && str_contains($line, 'register_shutdown_function(')) {
                $hookLine = $i;
            }
            if ($bootLine === null && preg_match('/^\s*exit\(Boot::bootWeb\(/', $line) === 1) {
                $bootLine = $i;
            }
        }

        self::assertNotNull($bootLine, 'the entry script must run the request through Boot::bootWeb()');
        self::assertNotNull($hookLine, 'the shutdown hook must be registered');
        self::assertLessThan(
            $bootLine,
            $hookLine,
            'the hook must be registered before the request runs, or the connection is never seen'
        );
    }

    /**
     * A failed close must not turn a served response into a 500.
     */
    public function testCodeigniterCloseFailureIsSwallowed(): void
    {
        self::assertMatchesRegularExpression(
            '/try\s*\{\s*(?:\/\/[^\n]*\n\s*)*\$connection->close\(\);/',
            self::read('public/index-codeigniter.php'),
            'close() must be guarded'
        );
        self::assertStringContainsString(
            'catch (\Throwable $e)',
            self::read('public/index-codeigniter.php')
        );
    }

    /**
     * Spiral's finalizer must not reach back into the container.
     *
     * Spiral runs finalizers from AbstractKernel::__destruct(), when the
     * container is already torn down, so `$container->get()` there throws
     * "Typed property ... must not be accessed before initialization" during
     * destructor processing — unrecoverable, and fatal to the request. Under
     * RoadRunner the kernel lives for the whole block so the container stays
     * intact and the bug never showed.
     */
    public function testSpiralFinalizerClosesOverTheServiceNotTheContainer(): void
    {
        $source = self::read('apps/spiral/src/Bootloader/AppBootloader.php');

        self::assertMatchesRegularExpression(
            '/\$dbEventLog\s*=\s*\$container->get\(DbEventLog::class\);/',
            $source,
            'the service must be resolved eagerly'
        );
        self::assertStringContainsString(
            'addFinalizer(static function () use ($dbEventLog): void',
            $source,
            'the finalizer must close over the instance, not the container'
        );
        self::assertStringNotContainsString(
            'addFinalizer(static function () use ($container): void',
            $source,
            'a finalizer capturing $container cannot run: the container is gone by then'
        );
    }

    /**
     * The FPM pool must not recycle its worker.
     *
     * pm.max_requests = 1 destroys the worker after every request — CGI, not
     * FastCGI — which puts a process spawn plus handshake on EVERY row
     * (measured 9.23 ms, identical for all six frameworks) and buries the
     * framework signal the benchmark exists to measure.
     */
    public function testFpmPoolDoesNotRecycleItsWorker(): void
    {
        $template = self::read('deploy/fpm/pool.conf.template');

        preg_match('/^\s*pm\.max_requests\s*=\s*(\d+)/m', $template, $m);
        self::assertNotEmpty($m, 'the pool template must set pm.max_requests');
        self::assertSame(
            '0',
            $m[1],
            'max_requests must be 0: any other value puts a per-request process spawn into every row'
        );
    }

    /**
     * The pool template must be the ONLY place the setting is defined.
     *
     * stampAllDeployConfigs() renders it per app AND for the floor-php probe,
     * and hand-edits to /etc/php/8.3/fpm/pool.d/ are overwritten on every
     * run-http invocation. A second definition — a stale template, a copy in
     * the repo root — would silently invalidate the deployment model the report
     * claims to have measured, because nothing in the dataset would change.
     */
    public function testPoolTemplateIsTheOnlySourceOfTheSetting(): void
    {
        $root  = dirname(__DIR__);
        $owner = 'deploy/fpm/pool.conf.template';

        self::assertSame(0, fpmMaxRequestsFromTemplate($root), 'the template drives the stamp');

        $offenders = [];
        $iterator  = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            // Only source/config, never the build or scratch areas.
            if (
                preg_match('#^(vendor|temp|data|results|writable|runtime|\.git|docs)/#', $relative) === 1
                    || $file->getExtension() !== 'template'
            ) {
                // The template is the only .template; also scan plain .conf.
                if ($file->getExtension() !== 'conf' || str_starts_with($relative, 'vendor/')) {
                    continue;
                }
            }
            foreach (explode("\n", (string) file_get_contents($file->getPathname())) as $line) {
                $trimmed = ltrim($line);
                // Ignore comments/prose: only an ACTIVE assignment counts.
                if (str_starts_with($trimmed, ';') || str_starts_with($trimmed, '#')) {
                    continue;
                }
                if (preg_match('/^\s*pm\.max_requests\s*=\s*\d+/', $line) === 1 && $relative !== $owner) {
                    $offenders[] = $relative;
                }
            }
        }

        self::assertSame(
            [],
            array_values(array_unique($offenders)),
            "{$owner} is the single source of truth for pm.max_requests; these files define it too"
        );
    }
}