<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * An adapter must not depend on its CALLER having loaded a helper.
 *
 * WHY. `CodeIgniterAdapter::bootFramework()` resolves COMPOSER_PATH through
 * `benchAppAutoload()`, which lives in `bench-app-autoload.php`. Only ONE of the
 * four boot paths required that file (run-app.php); the other two — the parity
 * gate `verify-app.php` and the smoke gate `smoke-app.php` — did not, so both
 * died with
 *
 *     Fatal error: Call to undefined function benchAppAutoload()
 *
 * instead of reporting anything. The consequence was silence, not a failure:
 * CodeIgniter was the single entrant with no verification at all between the
 * pruned-autoloader change and this fix, and `verify.php` reported "256 parity
 * failures" for a checkout where six apps were fine.
 *
 * A require inside the adapter is the right shape: the adapter owns the call, so
 * it must own the dependency. Requiring it is idempotent (require_once) and free
 * after the first boot.
 */
final class AdapterBootDependencyTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Any adapter that calls benchAppAutoload()/benchRequireAutoload() must load
     * the file that defines them.
     */
    public function testAdaptersThatUseTheResolverRequireIt(): void
    {
        $offenders = [];

        foreach (glob(self::repoRoot() . '/adapters/*Adapter.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            if (!preg_match('/\bbench(App|Require)Autoload\s*\(/', $source)) {
                continue;
            }

            if (!str_contains($source, 'bench-app-autoload.php')) {
                $offenders[] = basename($file);
            }
        }

        self::assertSame(
            [],
            $offenders,
            'these adapters call the autoload resolver without loading the file that defines it, '
                . 'so any caller that does not preload it dies with a fatal error: '
                . implode(', ', $offenders)
        );
    }

    /**
     * The parity gate requires the shared autoloader helper.
     *
     * Belt and braces: the adapter fix above is the real guard, but the gate
     * should also be honest about what it needs, so a future adapter that
     * reaches for the resolver without a require is still reported as a failure
     * rather than a fatal.
     */
    public function testTheParityGateLoadsTheSharedHelpers(): void
    {
        $source = (string) file_get_contents(self::repoRoot() . '/verify-app.php');

        self::assertStringContainsString('BenchmarkConfig.php', $source);
    }
}
