<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Three separate decisions hinge on ONE question about a benchmark arm: does its PHP
 * process stay alive between requests?
 *
 *   - which transport the memory probe uses (response headers vs a sample file)
 *   - which file the boot distribution is read from
 *   - whether a timed row carries boot_ms
 *
 * All three used to be expressed as `$server === 'rr'`, which was correct while the
 * only resident arm was RoadRunner. Adding volucer's two resident arms made that test
 * silently wrong in every place it appeared: the cold arm would have been treated as
 * resident (reading a file nobody wrote) and the resident ones as cold (charging a
 * one-time boot to every row).
 *
 * The question is therefore named once, in bench-lib.php, and these tests pin the
 * answer for every arm — the failure mode is a plausible-looking number, not an error.
 */
final class ProbeTransportTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__) . '/scripts/bench-lib.php';
    }

    /**
     * Resident arms answer in headers; per-request arms write a file.
     */
    public function testResidentArmsAreDistinguishedFromPerRequestArms(): void
    {
        // Resident: one PHP process lives for the whole block.
        self::assertTrue(probeUsesHeaders('rr'), 'RoadRunner holds a resident worker');
        self::assertTrue(probeUsesHeaders('volucer'), 'the volucer attach arm is resident');

        // Per-request: the entry script re-runs for every request.
        self::assertFalse(probeUsesHeaders('fpm'), 'php-fpm re-runs the entry script');
        self::assertFalse(
            probeUsesHeaders('volucer-cgi'),
            'the volucer spawn_cgi arm re-runs the entry script, so it must use the file transport'
        );
    }

    /**
     * An UNKNOWN kind is not silently treated as either.
     *
     * Both defaults are wrong in a way nothing catches: true makes the harness wait for
     * headers that never arrive (zeros), false makes it wait for a file nobody writes
     * (also zeros). Returning false for unknown input is the smaller lie — it is the
     * historical behaviour for anything that is not RoadRunner — but the mapping
     * functions upstream are what prevent an unknown name reaching here at all, and
     * those throw.
     */
    public function testAnUnknownKindDoesNotClaimToBeResident(): void
    {
        self::assertFalse(probeUsesHeaders('nonsense'));
        self::assertFalse(probeUsesHeaders(''));
    }

    /**
     * Every arm's probe kind is mapped, and the two mappings agree.
     *
     * Duplicated deliberately: run-http.php CLEARS boot-<kind>-<app>.jsonl before a
     * block and http-bench.php READS it. If they disagree the block completes and the
     * chart says "no samples" — a wrong chart with a green run.
     */
    public function testTheTwoProbeKindMappingsCoverEveryArmAndAgree(): void
    {
        $runHttp   = (string) file_get_contents(dirname(__DIR__) . '/scripts/run-http.php');
        $httpBench = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');

        $expected = [
            'rr'             => 'rr',
            'fpm'            => 'fpm',
            'volucer-attach' => 'volucer',
            'volucer-cgi'    => 'volucer-cgi',
        ];

        foreach ($expected as $server => $kind) {
            foreach (['run-http.php' => $runHttp, 'http-bench.php' => $httpBench] as $label => $src) {
                self::assertMatchesRegularExpression(
                    "/'" . preg_quote($server, '/') . "'\s*=>\s*'" . preg_quote($kind, '/') . "'/",
                    $src,
                    "{$label} must map server '{$server}' to probe kind '{$kind}'"
                );
            }
        }
    }

    /**
     * A row must carry boot_ms only for a PER-REQUEST arm.
     *
     * For a resident arm the boot happened before the first request, so stamping it on
     * every row would charge the same one-time cost to each latency number.
     */
    public function testBootMillisIsGatedOnTheProbeKindAndNotTheServerName(): void
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');

        self::assertStringContainsString(
            "\$bootKind === 'fpm' && \$bootMs !== null",
            $src,
            'boot_ms must be gated on the per-request kind'
        );
        self::assertStringNotContainsString(
            "\$server === 'rr'",
            $src,
            'a two-way rr/fpm test is wrong once a third arm exists — use the probe kind'
        );
    }
}
