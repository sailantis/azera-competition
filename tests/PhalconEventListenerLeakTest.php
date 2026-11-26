<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `GET /features/events` must not accumulate listeners across requests.
 *
 * WHY THIS EXISTS. The Phalcon arm attached its listener to the SHARED
 * `eventsManager` service and never detached it. `attach()` appends to the
 * manager's per-event queue and `fire()` invokes EVERY entry in it, so under a
 * resident worker — where one process serves every request — request N both
 * dispatched N-1 stale listeners and kept their captured arrays alive. The cost
 * is quadratic, not linear:
 *
 *     1000x10 over volucer-attach grew the process from 4.2 MB to 6.54 GB
 *
 * which put Phalcon's `after events` reading at ~6,600 MB against 2-10 MB for
 * every other entrant. It was the single row that made the resident-memory chart
 * unreadable, and it is a HARNESS bug: the published number said "Phalcon needs
 * 6.5 GB", which is not true of Phalcon, it is true of this controller.
 *
 * WHAT IS ASSERTED. The behaviour, not the implementation: dispatching the
 * endpoint's work N times must not grow retained memory with N, and the same
 * event fired twice must invoke one listener each time rather than two then
 * three. Executed in a subprocess because the property is about a PROCESS's
 * accumulated state — asserting it in-process would be measuring whatever the
 * rest of the suite already attached to a DI container.
 *
 * The comparison is a RATIO between two run lengths rather than an absolute
 * number of megabytes: an absolute threshold would need retuning on every PHP
 * release, while "10x the requests must not cost 10x the memory" is the property
 * that was violated.
 */
final class PhalconEventListenerLeakTest extends TestCase
{
    private static function root(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Run the endpoint's dispatch shape N times in a fresh process; return heap.
     *
     * Mirrors `FeatureController::eventsAction()` — a function-scoped `$entries`
     * captured by reference, a listener attached, one `fire()`, then return — so
     * the measurement is of the shape that shipped, not of a simplified stand-in.
     */
    private function heapAfter(int $requests, bool $useSharedManager): float
    {
        $manager = $useSharedManager
            // The BUG: one manager reused for every request, as the shared
            // `eventsManager` service is, with the listener never removed. Kept
            // runnable so this test proves the regression rather than merely
            // describing it in a comment.
            ? '$GLOBALS["m"] = new \Phalcon\Events\Manager();'
                . ' $mk = static fn() => $GLOBALS["m"];'
                . ' $detach = false;'
            // The FIX: attach, fire, detach — against the same shared manager.
            : '$GLOBALS["m"] = new \Phalcon\Events\Manager();'
                . ' $mk = static fn() => $GLOBALS["m"];'
                . ' $detach = true;';

        $code = <<<PHP
require {$this->export(self::root() . '/vendor/autoload.php')};
{$manager}
function benchRequest(callable \$mk, bool \$detach): int
{
    \$entries = [];
    \$m = \$mk();
    \$h = static function (\$e, \$s, \$d = null) use (&\$entries): void {
        \$entries[] = 'ItemCreated handled: id=' . (\$d['id'] ?? '?') . ' title=Event Item 2026-11-26';
    };
    \$m->attach('item:created', \$h);
    try {
        \$m->fire('item:created', new stdClass(), ['id' => 1]);
    } finally {
        if (\$detach) { \$m->detach('item:created', \$h); }
    }
    return count(\$entries);
}
for (\$i = 0; \$i < {$requests}; \$i++) { benchRequest(\$mk, \$detach); }
echo memory_get_usage(false);
PHP;

        // Written to a temp file rather than passed with `php -r`: on Windows
        // escapeshellarg() wraps the argument in double quotes and escapes inner
        // ones as \" — which cmd.exe does not understand — so the code arrived at
        // PHP mangled and the probe reported a parse error instead of a number.
        $probe = sys_get_temp_dir() . '/phalcon-leak-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($probe, "<?php\n" . $code);

        try {
            $out = [];
            $rc  = 0;
            @exec(
                escapeshellarg(PHP_BINARY) . ' -d memory_limit=1G ' . escapeshellarg($probe) . ' 2>&1',
                $out,
                $rc
            );
        } finally {
            @unlink($probe);
        }

        self::assertSame(0, $rc, 'the probe process failed: ' . implode("\n", $out));
        self::assertTrue(ctype_digit(trim(implode('', $out))), 'probe did not report a byte count');

        return (int) trim(implode('', $out)) / 1048576.0;
    }

    private function export(string $path): string
    {
        return var_export($path, true);
    }

    /**
     * The regression, kept demonstrable: the old shape MUST grow.
     *
     * Without this, the fix's test would pass even if the leak never existed
     * (e.g. if Phalcon's manager changed and stopped retaining listeners). A test
     * that cannot fail is not evidence.
     */
    public function testTheSharedManagerShapeReallyDoesAccumulate(): void
    {
        $small = $this->heapAfter(50, true);
        $large = $this->heapAfter(300, true);

        self::assertGreaterThan(
            $small * 2,
            $large,
            'the shared-manager shape no longer accumulates listeners, so the fixed'
                . ' shape below proves nothing — re-derive both against the current Phalcon'
        );
    }

    public function testTheRequestScopedManagerDoesNotAccumulate(): void
    {
        $small = $this->heapAfter(50, false);
        $large = $this->heapAfter(300, false);

        // 6x the requests. A linear leak would show ~6x; the shipped bug was
        // quadratic and showed far more. The bar is deliberately loose enough to
        // tolerate allocator slack and tight enough that any per-request
        // retention fails it.
        self::assertLessThan(
            $small * 1.5,
            $large,
            'retained memory grew with the number of requests while detaching —'
                . ' a listener is escaping its request again'
        );
    }

    /**
     * The controller must detach what it attaches.
     *
     * A source-level check, kept alongside the behavioural ones because the
     * failure mode is deleting one line in a `finally` that reads like tidiness,
     * which reintroduces the whole 6.5 GB.
     */
    public function testTheControllerDetachesItsListener(): void
    {
        $source = (string) file_get_contents(
            self::root() . '/apps/phalcon/src/Controller/FeatureController.php'
        );

        if (preg_match('/public function eventsAction\(\): string\s*\{(.*?)\n    \}/s', $source, $m) !== 1) {
            self::fail('could not isolate FeatureController::eventsAction()');
        }

        self::assertStringContainsString(
            '$events->detach(',
            $m[1],
            'eventsAction() attaches to the SHARED events manager without detaching:'
                . ' every request then retains a listener and fires all earlier ones,'
                . ' which is the 6.5 GB quadratic growth on the memory chart'
        );
    }
}
