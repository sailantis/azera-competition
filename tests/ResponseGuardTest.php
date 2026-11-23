<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The harness must recognise EVERY spelling of an error response, and the
 * transports must turn one into the matching HTTP status.
 *
 * What this pins. On 2026-11-21 a real run against the VM produced no dataset
 * at all: the Phalcon 6 arm had no feature routes, php-fpm aborted on
 * `GET /features/aop` (`HTTP status 404`), and the volucer arm of the same block
 * reported "250201 requests, 0 failed" — because a `404 Not Found` BODY went out
 * as **200 OK**, and the client guard only rejected `body === 'Not Found'` and
 * bodies prefixed `'500 '`.
 *
 * That is not a Phalcon bug: `404 Not Found` is the convention AzeraAdapter
 * also uses. The guard simply only knew ONE framework's spelling, so any
 * adapter returning a status-shaped body through a resident arm was invisible
 * to it. Eleven of 25 endpoints were 404s and the guard never fired once.
 *
 * The rule now lives in one place (response-guard.php) and both halves are
 * asserted here: the classification itself, and the fact that the two guard
 * sites and the two transports actually call it rather than re-deriving it.
 */
final class ResponseGuardTest extends TestCase
{
    private static function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__));
    }

    /**
     * Every error spelling any entrant uses, mapped to the status it means.
     *
     * The list is deliberately taken from the apps' own return statements
     * (`'404 Not Found'` in AzeraAdapter and Phalcon's Bootstrap, `'Not Found'`
     * in six controllers, `'500 ' . get_class($e)` in every entry script), not
     * invented here.
     *
     * @return array<string,int>
     */
    private static function errorBodies(): array
    {
        return [
            'Not Found'                 => 404,
            '404 Not Found'             => 404,
            '404 Not Found '            => 404,
            '403 Forbidden'             => 403,
            '500 Error: boom'           => 500,
            '500 Internal Server Error' => 500,
            '503 Service Unavailable'   => 503,
        ];
    }

    /**
     * Bodies that are legitimate responses and must stay 200.
     *
     * `{"error":"Not Found"}` is in here on purpose: the JSON API controllers
     * return exactly that for a missing row, and treating it as a failed
     * request would abort a healthy run. Only a body that IS the error page
     * counts, never one that merely mentions the words.
     *
     * @return list<string>
     */
    private static function okBodies(): array
    {
        return [
            '<!doctype html><html><body>items</body></html>',
            'bench',
            '["item"]',
            '{"id":1,"title":"x"}',
            '{"error":"Not Found"}',
            'Welcome to Azera Competition',
            'NotFound is a word',
            '',
        ];
    }

    public static function errorProvider(): array
    {
        $cases = [];
        foreach (self::errorBodies() as $body => $status) {
            $cases[$body] = [$body, $status];
        }

        return $cases;
    }

    public static function okProvider(): array
    {
        $cases = [];
        foreach (self::okBodies() as $body) {
            $cases['body: ' . substr($body, 0, 24)] = [$body];
        }

        return $cases;
    }

    #[DataProvider('errorProvider')]
    public function testErrorBodiesAreClassifiedWithTheirStatus(string $body, int $expected): void
    {
        self::assertSame(
            $expected,
            \benchStatusFromBody($body),
            'the guard must recognise this error spelling'
        );
        self::assertTrue(\isBenchErrorBody($body), 'an error body must abort the run');
    }

    #[DataProvider('okProvider')]
    public function testHealthyBodiesAreNotMistakenForErrors(string $body): void
    {
        self::assertSame(200, \benchStatusFromBody($body));
        self::assertFalse(\isBenchErrorBody($body), 'a healthy body must not abort the run');
    }

    /**
     * The exact body that slipped through: `404 Not Found` was served as 200.
     */
    public function testTheBodyThatHidTheFailureIsNowFatal(): void
    {
        // The old rule, reproduced: it is what the transports used to send.
        $oldTransportStatus = str_starts_with('404 Not Found', '500 ') ? 500 : 200;
        self::assertSame(200, $oldTransportStatus, 'precondition: the old rule sent 200 — this is the bug');

        self::assertSame(404, \benchStatusFromBody('404 Not Found'));
        self::assertTrue(\isBenchErrorBody('404 Not Found'));
    }

    /**
     * Both guard sites and both transports call the shared rule.
     *
     * Asserted on the source because a duplicated rule is exactly how the two
     * halves drifted apart in the first place: a future edit that re-inlines
     * `str_starts_with($body, '500 ')` would silently restore the hole.
     */
    public function testEveryGuardSiteUsesTheSharedRule(): void
    {
        $root = self::repoRoot();

        $sites = [
            'scripts/bench-lib.php'      => 'abortIfBroken',        // HTTP client, real deployments
            'run-app.php'                => 'in-process harness',   // warm/cold loop + fork children
            'smoke-app.php'              => 'smoke harness',        // the cheap pre-flight
            'deploy/volucer/app.php'     => 'volucer transport',    // body -> status
            'deploy/rr/worker.php'       => 'roadrunner transport', // body -> status
            'public/index-phalcon.php'   => 'phalcon FPM entry',    // body -> status
        ];

        foreach ($sites as $relative => $what) {
            $source = (string) file_get_contents($root . '/' . $relative);

            self::assertStringContainsString(
                'response-guard.php',
                $source,
                "{$relative} ({$what}) must load the shared response rule"
            );

            // The re-derived checks are the bug: one of them recognises only
            // 'Not Found', the other only '500 '.
            self::assertDoesNotMatchRegularExpression(
                '~str_starts_with\s*\(\s*\$?(?:body|t\[\'body\'\])\s*,\s*\'500 \'~',
                $source,
                "{$relative} ({$what}) re-derives the failure rule instead of using isBenchErrorBody()/"
                    . 'benchStatusFromBody(); those two spellings are what disagreed'
            );
        }
    }

    /**
     * The two guards that DO classify use the shared helper, not a literal.
     *
     * The transports now call `benchStatus($body, $status)` — the variant that
     * prefers the status the adapter's framework actually set — instead of
     * `benchStatusFromBody()`. Both live in response-guard.php, so the rule is
     * still shared; asserting the family rather than one spelling keeps this test
     * about "does not re-derive it locally".
     */
    public function testTheClassifyingSitesCallTheHelper(): void
    {
        $root = self::repoRoot();

        $callers = [
            'scripts/bench-lib.php'  => 'isBenchErrorBody',
            'run-app.php'            => 'isBenchErrorBody',
            'smoke-app.php'          => 'isBenchErrorBody',
            'deploy/volucer/app.php' => 'benchStatus',
            'deploy/rr/worker.php'   => 'benchStatus',
        ];

        foreach ($callers as $relative => $helper) {
            self::assertStringContainsString(
                $helper . '(',
                (string) file_get_contents($root . '/' . $relative),
                "{$relative} must call {$helper}()"
            );
        }
    }
}
