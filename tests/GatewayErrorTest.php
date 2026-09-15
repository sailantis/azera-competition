<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The readiness poll must only give up on a body an APP produced.
 *
 * nginx answers 502/503/504 itself while PHP-FPM replaces its workers
 * asynchronously after a reload, and such a page carries no app fingerprint.
 * Treating it as "WRONG WORKER" aborted a whole run on a race that resolved a
 * moment later; treating it as ready would benchmark nothing. It has to be
 * retried.
 */
final class GatewayErrorTest extends TestCase
{
    public static function gatewayPages(): array
    {
        return [
            '502 Bad Gateway'      => ['<html><title>502 Bad Gateway</title></html>'],
            '503 Service Unavailable' => ['<html><h1>503 Service Unavailable</h1></html>'],
            '504 Gateway Time-out' => ['<html><center><h1>504 Gateway Time-out</h1></center></html>'],
            'nginx default body'   => ["<html>\r\n<head><title>502 Bad Gateway</title></head>\r\n<body>\r\n<center><h1>502 Bad Gateway</h1></center>\r\n<hr><center>nginx/1.24.0 (Ubuntu)</center>\r\n</body>\r\n</html>"],
        ];
    }

    #[DataProvider('gatewayPages')]
    public function testRecognisesNginxGatewayPages(string $body): void
    {
        self::assertTrue(isGatewayError($body));
    }

    public static function appBodies(): array
    {
        return [
            'served page'     => ['<!doctype html><html><body>items</body></html>'],
            'not found'       => ['Not Found'],
            '500 prefix'      => ['500 Internal Server Error'],
            'empty'           => [''],
            'fingerprint'     => ['/features/config — Dot-notation access to a nested configuration'],
            'word in prose'   => ['The gateway timed out earlier in the run, but this page is fine.'],
        ];
    }

    #[DataProvider('appBodies')]
    public function testDoesNotMistakeAppBodiesForGatewayPages(string $body): void
    {
        self::assertFalse(isGatewayError($body));
    }

    public function testGatewayErrorsAreRetriedNotFatal(): void
    {
        // The classification is only useful if waitForServer() consults it: a
        // 502 during an FPM reload must not be reported as a stale worker.
        $source = (string) file_get_contents(dirname(__DIR__) . '/scripts/deploy-lib.php');

        self::assertMatchesRegularExpression(
            '/\$ready\s*=.*\n(?:.*\n)*?.*isGatewayError\(/',
            $source,
            'the readiness test must exclude gateway pages'
        );
        self::assertStringContainsString(
            '!isGatewayError($body)',
            $source,
            'the fingerprint probe must not run against an nginx error page'
        );
    }
}
