<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards what the source sync SHIPS to the bench VM.
 *
 * The sync exists to move source, but the local checkout also holds build and
 * state caches produced by running the apps on Windows. Shipping them is not a
 * size problem, it is a correctness problem: Spiral's
 * `runtime/cache/views/*-map.php` is a compiled view map that records the
 * COMPILER's absolute paths, so after a sync the VM tried to open
 * `C:/Users/christian/Projekte/.../app.dark.php`, served a 500, never became
 * ready and aborted the run at that app — after re-measuring everything
 * before it.
 *
 * The trap in fixing that is over-correcting: several cache-NAMED paths are
 * real source. `apps/codeigniter/Cache/` holds CI4's cache handler class, and
 * `apps/azera/Views/`, `apps/codeigniter/Views/`, `apps/spiral/views/` and
 * `apps/laravel/resources/views/` are templates. A blanket wildcard on the
 * directory name silently deletes them and the app 500s for a different
 * reason. `.gitignore` draws the same distinction, so it is the reference.
 */
final class SyncExcludeTest extends TestCase
{
    /** @return list<string> the --exclude values declared by run-remote.ps1 */
    private static function excludes(): array
    {
        $src = (string) file_get_contents(
            str_replace('\\', '/', dirname(__DIR__)) . '/scripts/run-remote.ps1'
        );

        $open = strpos($src, '$Excludes = @(');
        self::assertNotFalse($open, 'run-remote.ps1 must declare an $Excludes array');
        $open += strlen('$Excludes = @');

        // Balanced-paren scan rather than "first ) after the opening": the
        // array's own inline comments contain parentheses ("(holds abs
        // paths)"), so a naive search stops inside a comment and silently
        // parses only the first few excludes — which is how this test first
        // failed against a correct exclude list.
        $depth = 0;
        $end   = null;
        for ($i = $open, $len = strlen($src); $i < $len; $i++) {
            if ($src[$i] === '(') {
                $depth++;
            } elseif ($src[$i] === ')') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        self::assertNotNull($end, 'the $Excludes array must be closed');

        preg_match_all(
            '/--exclude=([^\'"\s]+)/',
            substr($src, $open, $end - $open),
            $m
        );

        return array_map('trim', $m[1]);
    }

    /**
     * Local state that must never reach the VM.
     *
     * @return list<string>
     */
    public static function statePaths(): array
    {
        return array_map(
            static fn(string $p): array => [$p],
            [
                'runtime',         // spiral kernel cache — held the Windows paths
                'writable',        // laravel logs + compiled views
                'bootstrap/cache', // laravel compiled packages/config
                'var/cache',       // symfony compiled container
                'storage',         // laravel storage (sessions, compiled views)
                'vendor',          // composer install runs on the VM
                'temp',            // run scratch (stamped configs point at the VM)
            ]
        );
    }

    /**
     * Cache-NAMED paths that are actually source and must survive.
     *
     * @return list<string>
     */
    public static function sourcePathsThatLookLikeCaches(): array
    {
        return array_map(
            static fn(string $p): array => [$p],
            [
                'apps/codeigniter/Cache',       // CI4's cache handler class
                'apps/azera/Views',             // templates
                'apps/codeigniter/Views',       // templates
                'apps/laravel/resources/views', // templates
            ]
        );
    }

    /**
     * @dataProvider statePaths
     */
    public function testLocalStateIsExcludedFromTheSync(string $path): void
    {
        self::assertContains(
            $path,
            self::excludes(),
            "{$path} holds machine-generated state and must not be synced — " .
                'compiled artifacts record the local absolute paths and break the app on the VM.'
        );
    }

    /**
     * @dataProvider sourcePathsThatLookLikeCaches
     */
    public function testSourceThatLooksLikeACacheIsNotExcluded(string $path): void
    {
        $excludes = self::excludes();

        foreach ($excludes as $pattern) {
            self::assertNotSame(
                $path,
                $pattern,
                "{$path} is SOURCE, not a cache — excluding it deletes a class/template the app needs."
            );
            // A blanket wildcard is the same mistake with a wider blast radius:
            // `*/cache` would also swallow apps/codeigniter/Cache.
            self::assertDoesNotMatchRegularExpression(
                '#^(apps/)?\*(/|$)#',
                $pattern,
                "the exclude '{$pattern}' uses a wildcard that can match source directories"
            );
        }
    }

    public function testArchiveSizeIsGuardedBeforeTransfer(): void
    {
        $src = (string) file_get_contents(
            str_replace('\\', '/', dirname(__DIR__)) . '/scripts/run-remote.ps1'
        );

        // A silent regression to a 3.9 GB archive costs an hour over the VPN
        // (that happened: a 3.6 GB phpthunder cache shipped inside
        // azera-framework). The guard turns it into an immediate failure.
        self::assertStringContainsString('$MaxTarBytes', $src);
        self::assertMatchesRegularExpression(
            '/if\s*\(\s*\$bytes\s*-gt\s*\$MaxTarBytes\s*\)/',
            $src,
            'the sync must abort when an archive is unexpectedly large'
        );
    }

    public function testSyncVerifiesNoHostPathReachedTheVm(): void
    {
        $src = (string) file_get_contents(
            str_replace('\\', '/', dirname(__DIR__)) . '/scripts/run-remote.ps1'
        );

        // The exclude list is a claim; this is the check that the claim held.
        // Without it the failure surfaces hours later as an opaque 500 from one
        // app, which reads like a framework bug rather than a sync bug.
        self::assertStringContainsString('Assert-NoHostPathsLeaked', $src);
        self::assertStringContainsString(
            'C:/Users/',
            $src,
            'the leak check must look for absolute Windows paths'
        );

        // tests/ must be excluded from the scan, because THIS test contains the
        // literal pattern it searches for. Leaving it in makes the guard fail
        // on every sync with a false positive that looks exactly like the real
        // bug it exists to catch. Tests are never served, so nothing can leak
        // through them.
        self::assertStringContainsString(
            "grep -v '/tests/'",
            $src,
            'the leak scan must skip tests/, or it matches its own pattern'
        );
    }
}
