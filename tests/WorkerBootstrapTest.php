<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The out-of-process worker must be able to find the adapters it is told to load.
 *
 * WHY THIS EXISTS. `deploy/worker-bootstrap.php` used to live in `deploy/rr/`, where
 * `dirname(__DIR__, 2)` was the bench root. Moving it to `deploy/` -- so that removing
 * the RoadRunner arm would not take the Volucer arm's adapter registry with it -- left
 * the depth unchanged, and `dirname(__DIR__, 2)` then resolved to the PARENT of the
 * repository. Nothing in the suite touched that expression, so the first a real run
 * heard about it was the Volucer supervisor dying before it created its socket:
 *
 *     require_once(/home/competition/workspace/adapters/AzeraAdapter.php):
 *     Failed to open stream: No such file or directory
 *
 * THE DEPTH IS THE UNIT UNDER TEST, not the literal text. `__DIR__` is the directory of
 * the file that uses it, so `dirname(__DIR__, $n)` resolves to $n levels above
 * `deploy/`. That is computed here against the real filesystem and compared with the
 * directory this test runs from, which is the bench root by construction. Moving the
 * file again without re-deriving the depth therefore fails here instead of on the VM.
 *
 * ALSO ASSERTED: every adapter class the registry names exists on disk, and the registry
 * covers every measured app. The registry is a second copy of run.php's map (the worker
 * is a separate process and must not include run.php), so a rename in either place would
 * otherwise surface only as a fatal in the supervisor log after the pool had started.
 */
final class WorkerBootstrapTest extends TestCase
{
    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    private static function bootstrapFile(): string
    {
        return self::root() . '/deploy/worker-bootstrap.php';
    }

    /**
     * The bench root the factory computes, derived the way PHP derives it.
     *
     * `__DIR__` is the directory of the file that MENTIONS it, so the expression is
     * interpreted against `deploy/` — never against `tests/`, which is where this code
     * runs. That distinction is the whole bug: the same text means different things in
     * different files.
     *
     * Evaluated arithmetically rather than through `eval` or a subprocess, because both
     * of those answer for the evaluator's location instead of the worker's. An
     * unrecognised spelling fails here deliberately: silently accepting a reshape would
     * let the next move of the file go unchecked, which is exactly how this broke.
     */
    private function factoryRoot(): string
    {
        $source = (string) file_get_contents(self::bootstrapFile());
        if (preg_match('/\$root\s*=\s*([^;]+);/', $source, $m) !== 1) {
            self::fail(
                'deploy/worker-bootstrap.php no longer assigns a `$root` — update this'
                    . ' test to follow the new form rather than deleting the check',
            );
        }
        $expression = trim($m[1]);

        if (preg_match('/^dirname\(\s*__DIR__\s*\)$/', $expression) === 1) {
            $up = 1;
        } elseif (preg_match('/^dirname\(\s*__DIR__\s*,\s*(\d+)\s*\)$/', $expression, $d) === 1) {
            $up = (int) $d[1];
        } else {
            self::fail(
                "unrecognised root expression `{$expression}` in deploy/worker-bootstrap.php"
                    . ' — teach this test the new form so a later move of the file cannot'
                    . ' silently point the worker outside the repository',
            );
        }

        $dir = str_replace('\\', '/', dirname(self::bootstrapFile()));
        for ($i = $up; $i > 0; $i--) {
            $dir = str_replace('\\', '/', dirname($dir));
        }

        return $dir;
    }

    public function testTheComputedRootIsTheBenchRoot(): void
    {
        self::assertSame(
            self::root(),
            $this->factoryRoot(),
            'deploy/worker-bootstrap.php resolves the wrong bench root — a worker that'
                . ' includes it cannot reach adapters/, so the supervisor never starts',
        );
    }

    public function testTheComputedRootContainsTheAdaptersDirectory(): void
    {
        self::assertDirectoryExists(
            $this->factoryRoot() . '/adapters',
            'the root the worker computes has no adapters/ directory beside it',
        );
    }

    /** @return array<string,array{string}> */
    public static function adapterProvider(): array
    {
        return array_map(static fn(string $k): array => [$k], array_keys(self::registry()));
    }

    /**
     * The registry as the worker sees it: app key => adapter class.
     *
     * Requiring the file is safe here: it declares no global helpers and has no
     * top-level side effects beyond the two function definitions (see its own comments
     * on why a `function_exists('env')` fallback was removed).
     *
     * @return array<string,string>
     */
    private static function registry(): array
    {
        static $registry = null;
        if ($registry === null) {
            require_once self::bootstrapFile();
            $registry = benchAdapterClasses();
        }

        return $registry;
    }

    #[DataProvider('adapterProvider')]
    public function testEveryRegisteredAdapterExists(string $app): void
    {
        $class = self::registry()[$app];
        self::assertFileExists(
            self::root() . '/adapters/' . $class . '.php',
            "{$app} maps to {$class}, which is not in adapters/ — the worker would"
                . ' require a missing file and exit before serving a request',
        );
    }

    public function testTheRegistryCoversEveryMeasuredApp(): void
    {
        // The in-process harness is the single source of truth for which apps exist;
        // the worker's map is a copy and must not fall behind it.
        $apps = array_keys(appRootPackages());
        sort($apps);

        $registered = array_keys(self::registry());
        sort($registered);

        self::assertSame(
            $apps,
            $registered,
            'deploy/worker-bootstrap.php and scripts/app-autoload.php disagree about the'
                . ' measured apps — the worker would exit on an unknown BENCH_APP',
        );
    }
}
