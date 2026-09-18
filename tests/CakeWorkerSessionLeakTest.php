<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * CakePHP's resident worker must not leak the engine's shutdown-handler list.
 *
 * THE BUG (found 2026-09-18). `ServerRequestFactory::fromGlobals()` builds a
 * fresh `Cake\Http\Session` on every call, and `Session::__construct()` ends
 * with `session_register_shutdown()`. That appends to a process-level list PHP
 * only frees at exit, so a resident worker retains ~186 bytes per request,
 * linearly and with no ceiling. Over the real benchmark's 210,000 requests that
 * WAS the resident-memory chart's 0.85 -> 40.6 MB climb for CakePHP — the only
 * framework that builds a Session per request (Symfony's `NativeSessionStorage`
 * calls the same function but is only constructed when a session is STARTED,
 * which the bench app never does). Re-measured after the fix on the bench VM
 * (2026-09-18, 1000x10): 0.923 -> 5.96 MB end state, 8.14 MB worst endpoint.
 *
 * THE FIX under test: the adapter builds requests through
 * `App\Cake\Support\WorkerRequestFactory`, which hands every request one
 * process-wide Session, and clears `$_SESSION` in `cleanup()` so request
 * scoping is preserved.
 *
 * These are SOURCE-LEVEL assertions. The leak itself is a runtime property and
 * is pinned separately by the measurement in
 * temp/ (see the memory note); what belongs in the suite is the invariant that
 * must not silently regress: nobody may call the stock factory on behalf of a
 * resident worker, and the reused session must stay wiped between requests.
 */
final class CakeWorkerSessionLeakTest extends TestCase
{
    private static function read(string $relative): string
    {
        $path = dirname(__DIR__) . '/' . $relative;
        self::assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * The adapter must NOT build requests with the stock factory.
     *
     * A plain `fromGlobals()` call is exactly the leak: it looks harmless (it
     * *is* the documented API), and nothing about a latency number would reveal
     * that it is wrong. Reverting the adapter to it must fail this test.
     */
    public function testAdapterDoesNotUseStockRequestFactory(): void
    {
        $adapter = self::read('adapters/CakePhpAdapter.php');

        self::assertStringContainsString(
            'WorkerRequestFactory::fromGlobals(',
            $adapter,
            'The CakePHP adapter must build requests through WorkerRequestFactory'
        );

        self::assertStringNotContainsString(
            '\Cake\Http\ServerRequestFactory::fromGlobals(',
            $adapter,
            'CakePhpAdapter must not call the stock factory: it creates a Session '
                . 'per request, whose constructor leaks the shutdown-handler list in a '
                . 'resident worker (~186 B/request, measured linear to 80k iterations)'
        );
    }

    /**
     * The worker factory must actually reuse the Session, not rebuild it.
     *
     * The whole fix is the `'session' =>` line. A future edit that drops it (or
     * reverts the class to extending the stock behaviour of creating one)
     * reintroduces the leak while still looking like "a factory".
     */
    public function testWorkerFactoryReusesOneSession(): void
    {
        $factory = self::read('apps/cakephp/src/Support/WorkerRequestFactory.php');

        // Matched with \s+ rather than the literal alignment padding: the
        // array's key alignment is the formatter's to decide (a comment line
        // inside the literal makes the formatter treat 'session' as its own
        // group), and pinning the exact number of spaces would fail on a
        // cosmetic reformat of the very line the test exists to protect.
        self::assertMatchesRegularExpression(
            "/'session'\s*=>\s*static::session\(\),/",
            $factory,
            "WorkerRequestFactory must inject the shared Session into the request"
        );

        self::assertStringContainsString(
            'self::$session ??=',
            $factory,
            'The Session must be memoised once per process, not created per request'
        );

        // A Session constructed inside fromGlobals() would be the leak again.
        self::assertStringNotContainsString(
            'Session::create(',
            $this->methodBody($factory, 'fromGlobals'),
            'fromGlobals() must not build a Session of its own'
        );
    }

    /**
     * cleanup() must wipe the shared session, keeping request scoping.
     *
     * Reusing one object is only equivalent to a per-request one if its state
     * ($_SESSION) is cleared — otherwise request-scoped session data would
     * survive into the next request, which is a correctness bug rather than a
     * memory one.
     */
    public function testCleanupClearsTheSharedSession(): void
    {
        $adapter = self::read('adapters/CakePhpAdapter.php');

        self::assertStringContainsString(
            'WorkerRequestFactory::session()->clear()',
            $this->methodBody($adapter, 'cleanup'),
            'cleanup() must clear the shared Session between requests'
        );
    }

    /**
     * The Session has to be a REAL Session instance, and the request factory has
     * to keep delegating the rest of the marshalling to Cake — otherwise the
     * fix would be trading a memory leak for a behavioural difference.
     */
    public function testFactoryDelegatesTheRestOfTheMarshalling(): void
    {
        $factory = self::read('apps/cakephp/src/Support/WorkerRequestFactory.php');

        foreach (['marshalBodyAndRequestMethod', 'marshalFiles'] as $helper) {
            self::assertStringContainsString(
                $helper . '(',
                $factory,
                "WorkerRequestFactory must keep calling Cake's {$helper}()"
            );
        }

        self::assertStringContainsString(
            'use Cake\Http\ServerRequestFactory;',
            $factory,
            'WorkerRequestFactory must import the stock factory'
        );
        self::assertStringContainsString(
            'extends ServerRequestFactory',
            $factory,
            'WorkerRequestFactory must extend the stock factory, so the protected marshalling helpers stay available'
        );
    }

    /** The body of a named method in a class source file. */
    private function methodBody(string $source, string $method): string
    {
        $start = strpos($source, "function {$method}(");
        if ($start === false) {
            self::fail("method {$method}() not found");
        }
        // To the next method at the same brace level, or end of file.
        $rest = substr($source, $start);

        return substr($rest, 0, (int) (strpos($rest, "\n    public function") ?: strlen($rest)));
    }
}
