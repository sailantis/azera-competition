<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The app must own its AOP proxy cache directory, and create it 0700.
 *
 * WHY THIS IS A TEST AND NOT A COMMENT. Azera\Aop\ProxyFactory refuses to
 * `require` generated code out of a group/world-writable directory
 * (`assertSafeCacheDir()`: mode & 0o022 -> RuntimeException), because a file
 * another user can replace is a file another user can make us execute. The
 * benchmark app pointed `setAopCacheDir()` at `data/aop` and never created it,
 * so who owned that directory decided whether the entrant worked:
 *
 *   - on a clean checkout ProxyFactory created it 0700, and everything passed
 *   - on the bench VM, `data/aop` already existed at 0775 from a run that
 *     predated the framework's check, so EVERY /features/aop-tx and
 *     /features/aop-tx-direct request threw, answered 500, and aborted the run
 *
 * The failure only appeared on Linux: on Windows there are no POSIX mode bits
 * to inspect, so the guard is a no-op and the local suite stayed green. That is
 * the whole reason it survived to a benchmark run.
 *
 * What is asserted is the CONTRACT the framework enforces - the app names a
 * directory it created, at a mode the framework accepts - not the particular
 * chmod call, so the test still holds if the app starts deleting the directory
 * instead of tightening it.
 */
final class AopCacheDirModeTest extends TestCase
{
    private static function bootstrapSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/apps/azera/Bootstrap.php');
    }

    /**
     * The directory the app hands to setAopCacheDir() is one it creates itself.
     *
     * A directory the app does not create is a directory whose mode the app
     * cannot control, which is exactly the state that failed: the path existed,
     * so nothing was created, and the mode was whatever an older run left.
     */
    public function testTheAppCreatesTheAopCacheDirItNames(): void
    {
        $source = self::bootstrapSource();

        self::assertMatchesRegularExpression(
            '/\$aopCacheDir\s*=\s*__DIR__\s*\.\s*[\'"][^\'"]+[\'"]\s*;/',
            $source,
            'setAopCacheDir() must be given a path held in a variable, so the app can create and check it'
        );

        self::assertMatchesRegularExpression(
            '/mkdir\(\s*\$aopCacheDir\s*,\s*0?700\b/',
            $source,
            'the AOP cache dir must be created 0700 — ProxyFactory::assertSafeCacheDir() rejects a group/world-writable directory'
        );

        self::assertStringNotContainsString(
            'setAopCacheDir(__DIR__',
            $source,
            'setAopCacheDir() must not be given a literal path: a literal cannot be created and checked first'
        );
    }

    /**
     * An already-existing directory must be tightened, not trusted.
     *
     * Creating with 0700 is not enough on its own. `mkdir()` is a no-op when the
     * path exists, so a checkout (or a VM) that already has a 0775 `data/aop`
     * keeps failing forever: the app never touches the mode, ProxyFactory keeps
     * refusing, and the only symptom is a 500 on two endpoints.
     */
    public function testAnExistingAopCacheDirIsTightened(): void
    {
        $source = self::bootstrapSource();

        self::assertMatchesRegularExpression(
            '/chmod\(\s*\$aopCacheDir\s*,\s*0?700\s*\)/',
            $source,
            'an existing AOP cache dir must be chmod-ed when it is group/world writable'
        );

        // The chmod has to be gated on the mode actually being wrong, and skipped
        // where modes do not exist — otherwise a Windows run chmods every start.
        self::assertMatchesRegularExpression(
            '/fileperms\(\s*\$aopCacheDir\s*\)/',
            $source,
            'the chmod must be conditional on the directory actually being writable by others'
        );

        self::assertMatchesRegularExpression(
            "/DIRECTORY_SEPARATOR\s*!==\s*'\\\\\\\\'/",
            $source,
            'the mode check must be skipped on Windows, which has no POSIX mode bits'
        );
    }
}
