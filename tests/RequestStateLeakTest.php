<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A demo must behave the SAME on the 1000th request as on the first.
 *
 * WHY. Warm mode boots once and reuses the app, and even the php-fpm arm serves
 * a whole 1000-iteration block from one process. A demo that keeps state on the
 * service therefore stops demonstrating what it claims a few requests in, while
 * its single-shot verification keeps passing.
 *
 * The concrete bug this once pinned: Azera's `flakyOperation()` counted attempts
 * in a `static` local. `#[Retry]` only has something to retry while the method
 * fails, and after the first request the counter was already past the threshold,
 * so every later call succeeded on its FIRST attempt — the interceptor was
 * bypassed entirely and `/features/retry` measured a plain method call under a
 * "retries a failing method" label. That row has since been removed outright
 * (it measured bootstrap, not retry), so the retry-specific half of the test
 * went with it. What remains is the general rule it was derived from: no
 * benchmarked demo may keep its demonstration in a function-local `static`.
 */
final class RequestStateLeakTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * The FEATURE DEMO controllers and services may not hold state in a
     * function-local `static`.
     *
     * Scoped to the /features/* implementations on purpose. Elsewhere a static is
     * legitimate: Symfony's `static $counter` builds a unique benchmark title,
     * CakePHP's `static $table` is a table-locator memo, and Phalcon's
     * `private static $dispatchHook` is a probe hook. Those are caches and
     * monotonic counters whose value never changes what the request MEASURES.
     * In a feature demo the opposite is true: the state IS the demonstration, so a
     * static there means the demo stops demonstrating after the first request.
     */
    public function testNoFeatureDemoKeepsStateInAFunctionLocalStatic(): void
    {
        $files = [
            'apps/azera/Controllers/FeatureController.php',
            'apps/azera/Services/FeatureService.php',
            'apps/laravel/app/Http/Controllers/FeatureController.php',
            'apps/laravel/app/Service/AopService.php',
            'apps/symfony/src/Controller/FeatureController.php',
            'apps/symfony/src/Service/AopService.php',
            'apps/spiral/src/Controller/FeatureController.php',
            'apps/spiral/src/Service/AopService.php',
            'apps/codeigniter/Controllers/Feature.php',
            'apps/cakephp/src/Controller/FeatureController.php',
            'apps/phalcon/src/Controller/FeatureController.php',
        ];

        $offenders = [];

        foreach ($files as $relative) {
            $path = self::repoRoot() . '/' . $relative;
            if (!is_file($path)) {
                continue;
            }

            $source = (string) file_get_contents($path);

            if (preg_match('/\bstatic\s+\$[A-Za-z_]/', $source) === 1) {
                $offenders[] = $relative;
            }
        }

        self::assertSame(
            [],
            $offenders,
            "function-local static state in a feature demo survives every request:\n  "
                . implode("\n  ", $offenders)
                . "\nKeep the state on the service and clear it from resetState()."
        );
    }

    /**
     * A demo that holds per-request state must clear it through the framework's
     * RequestScoped contract, not a bespoke hook.
     *
     * This used to assert the retry demo's attempt counter, then the
     * `RequestCounter` behind `/features/request-scoped`. Both demos are gone
     * (each measured bootstrap rather than its own facility), but the rule they
     * were derived from is not: `DbEventLog` accumulates an entry per executed
     * statement and would leak every request's queries into the next one in a
     * persistent worker. Keeping the assertion on a live service is what stops
     * the contract from being quietly dropped — without it, `resetState()` could
     * be deleted from every service and this file would still pass its
     * static-state check.
     */
    public function testTheDbEventLogClearsItsStateThroughTheContract(): void
    {
        $source = (string) file_get_contents(
            self::repoRoot() . '/apps/azera/Services/DbEventLog.php'
        );

        self::assertMatchesRegularExpression(
            '/class\s+DbEventLog\s+implements\s+RequestScoped/',
            $source,
            'DbEventLog holds per-request state, so it must implement RequestScoped — '
                . 'clearRequestScope() is what the worker loop calls between requests'
        );
        self::assertMatchesRegularExpression(
            '/public function resetState\(\): void\s*\{[^}]*\$this->events\s*=\s*\[\];/s',
            $source,
            'resetState() must empty the event list, or the demo leaks state across requests '
                . 'and the db-events payload grows without bound'
        );
    }

    /**
     * The AOP demo pair still exists and does NOT move its transaction into the
     * advised method.
     *
     * Direct consequence of the same refactor risk: the advised twin must stay
     * advice-only, so this asserts the invariant rather than the implementation
     * (see AdvicePairTest for the full same-work contract).
     */
    public function testTheAdvisedTwinStillCarriesNoTransactionOfItsOwn(): void
    {
        $source = (string) file_get_contents(
            self::repoRoot() . '/apps/azera/Services/FeatureService.php'
        );

        self::assertMatchesRegularExpression(
            '/#\[Transactional\]\s*public function createItemTransactional\(/',
            $source
        );
        self::assertMatchesRegularExpression('/public function createItemDirect\(/', $source);
    }
}
