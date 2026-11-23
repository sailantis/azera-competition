<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * `/features/rate-limit` must answer 200 for every request, and say so in the body.
 *
 * WHY THIS IS A CONTRACT AND NOT A STYLE CHOICE. Every app's limiter cache is
 * process-lifetime (Azera's ArrayCache, Laravel's `array` store, CI4's
 * ArrayHandler, Cake's `bench` pool, Symfony's pool, Spiral's, Phalcon's), so the
 * 5-hit budget is exhausted within the first few iterations of a 1000x10 block —
 * in the FPM arm too, where one process serves every request the harness sends.
 * Four apps answered `$allowed ? 200 : 429`, which means roughly 99.9% of the
 * samples in that row were DENIED requests: the row measured "reject a request"
 * and was published as "checks a cache-backed rate limiter".
 *
 * It also broke the run. `abortIfBroken()` refuses a non-2xx body — correctly, it
 * is what stops a benchmark publishing numbers for error pages — so the resident
 * arm aborted on this endpoint as soon as real statuses started being reported.
 *
 * The limiter still RUNS: it reads the counter, increments it, and computes
 * `allowed` / `remaining`. What changed is that the TRANSPORT status no longer
 * encodes the verdict; the body does, where a reader can still find it. CakePHP
 * and CodeIgniter always answered 200, so this also makes all seven agree
 * instead of four differing from two.
 *
 * Pinned as a source contract rather than by dispatching, because dispatching
 * every app requires all seven frameworks booted in one process — which is
 * exactly what the harness isolates per app.
 */
final class RateLimitStatusTest extends TestCase
{
    /** Every app that implements the endpoint. */
    private const CONTROLLERS = [
        'azera'       => 'apps/azera/Controllers/FeatureController.php',
        'laravel'     => 'apps/laravel/app/Http/Controllers/FeatureController.php',
        'symfony'     => 'apps/symfony/src/Controller/FeatureController.php',
        'spiral'      => 'apps/spiral/src/Controller/FeatureController.php',
        'cakephp'     => 'apps/cakephp/src/Controller/FeatureController.php',
        'codeigniter' => 'apps/codeigniter/Controllers/Feature.php',
        'phalcon'     => 'apps/phalcon/src/Controller/FeatureController.php',
    ];

    /**
     * No app may turn the limiter verdict into a 429 (or any non-2xx).
     */
    public function testNoAppReturnsANon2xxForTheRateLimiter(): void
    {
        foreach (self::CONTROLLERS as $app => $relative) {
            $source = self::read($relative);

            self::assertDoesNotMatchRegularExpression(
                '/\?\s*200\s*:\s*429/',
                $source,
                "{$app} turns the rate-limiter verdict into a 429; that status aborts the run "
                . 'and makes the row measure a denied request'
            );

            self::assertDoesNotMatchRegularExpression(
                '/429/',
                self::stripComments(self::rateLimitMethod($source, $app)),
                "{$app}'s rate-limit action still returns 429"
            );
        }
    }

    /**
     * The verdict must still be readable: `allowed` and `remaining` in the body.
     *
     * Removing the 429 must not remove the DEMO. The endpoint has to show that the
     * limiter denied the request; it just may not express that as a status.
     */
    public function testTheVerdictStaysInTheBody(): void
    {
        foreach (self::CONTROLLERS as $app => $relative) {
            $method = self::rateLimitMethod(self::read($relative), $app);

            foreach (['allowed', 'hits'] as $key) {
                self::assertMatchesRegularExpression(
                    "/'{$key}'|&quot;{$key}&quot;|\"{$key}\"/",
                    $method,
                    "{$app}'s rate-limit response must still carry '{$key}'"
                );
            }
        }
    }

    /**
     * Drop `//` and `/* *\/` comments, so the rationale (which names the old
     * status) is not mistaken for the code.
     */
    private static function stripComments(string $source): string
    {
        $stripped = (string) preg_replace('#/\*.*?\*/#s', '', $source);

        return (string) preg_replace('#//[^\n]*#', '', $stripped);
    }

    private static function read(string $relative): string
    {
        $path = dirname(__DIR__) . '/' . $relative;
        self::assertFileExists($path, "missing rate-limit controller: {$relative}");

        return (string) file_get_contents($path);
    }

    /**
     * The body of the rate-limit action, so a stray 429 elsewhere in the file
     * does not fail this and a 429 inside it cannot hide.
     */
    private static function rateLimitMethod(string $source, string $app): string
    {
        $matched = preg_match(
            '/function\s+rateLimit\w*\s*\([^)]*\)[^{]*\{(.*?)\n    \}/s',
            $source,
            $m,
        );

        self::assertSame(1, $matched, "could not isolate {$app}'s rate-limit action");

        return $m[1];
    }
}
