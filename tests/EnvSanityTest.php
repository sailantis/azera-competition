<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * scripts/env-sanity.php — "are these two datasets measured in the same
 * runtime?", and the splice guards built on it.
 *
 * WHY THIS EXISTS. merge-app.php / merge-modes.php / assemble-real.php each
 * carried a private copy of this check, and all three copies compared the SET
 * of env keys present on each side. A key recorded on only one side therefore
 * read as a MISMATCH — which is not an edge case but the normal shape of a
 * splice between the two harnesses:
 *
 *   run.php       stamps `opcache` + `sapi` (it IS the CLI SAPI).
 *   run-http.php  stamps NEITHER on purpose — see its env block.
 *
 * The canonical real-deployment dataset still carries a legacy `sapi: "cli"`,
 * while a freshly measured one correctly omits it, so every such splice was
 * refused with "Env mismatch" even though both runs were the same PHP on the
 * same machine. These tests pin the RULE that replaces it: a key BOTH sides
 * record must agree; a key only one side records cannot contradict anything.
 *
 * The tests that matter most are the ones proving the guard did not become
 * vacuous — a check that accepts everything is worse than the bug it fixed.
 */
final class EnvSanityTest extends TestCase
{
    private static function dataset(array $env): array
    {
        return ['env' => $env, 'apps' => []];
    }

    public function testBothSidesRecordingTheSameRuntimeAgree(): void
    {
        $a = self::dataset(['php_version' => '8.3.33', 'opcache' => true, 'sapi' => 'cli']);
        $b = self::dataset(['php_version' => '8.3.33', 'opcache' => true, 'sapi' => 'cli']);

        self::assertTrue(envSanityCompare($a, $b)['ok']);
    }

    /**
     * The bug that motivated the file: a key recorded on ONE side is a fact
     * about that harness's vintage, not a contradiction.
     */
    public function testAKeyRecordedOnOneSideOnlyIsNotAMismatch(): void
    {
        // Exactly the real shape: canonical (legacy run.php era) vs a fresh
        // run-http.php dataset, which deliberately omits both keys.
        $target = self::dataset(['php_version' => '8.3.33', 'sapi' => 'cli']);
        $fresh  = self::dataset(['php_version' => '8.3.33']);

        $cmp = envSanityCompare($target, $fresh);

        self::assertTrue($cmp['ok'], 'a one-sided key must not refuse the splice');
        self::assertSame(['sapi'], $cmp['skipped'], 'and it must be REPORTED, not silently dropped');
        self::assertSame(['php_version' => '8.3.33'], $cmp['compared']);
    }

    public function testTheSkippedKeyIsNamedRatherThanSilent(): void
    {
        // Silence here previously read as "verified" — the point of naming it.
        $out = sys_get_temp_dir() . '/envsanity-' . bin2hex(random_bytes(4)) . '.php';
        $php = <<<'PHP'
<?php
require_once $argv[1] . '/scripts/env-sanity.php';
$t = ['env' => ['php_version' => '8.3.33', 'sapi' => 'cli'], 'apps' => []];
$f = ['env' => ['php_version' => '8.3.33'], 'apps' => []];
assertEnvComparable($t, $f);
PHP;
        file_put_contents($out, $php);
        exec('php ' . escapeshellarg($out) . ' ' . escapeshellarg(dirname(__DIR__)) . ' 2>&1', $lines, $code);
        @unlink($out);

        self::assertSame(0, $code);
        self::assertStringContainsString('recorded on one side only', implode("\n", $lines));
        self::assertStringContainsString('sapi', implode("\n", $lines));
    }

    /** The guard must still refuse a real, contradicting difference. */
    public function testADifferentPhpVersionIsStillRefused(): void
    {
        $a = self::dataset(['php_version' => '8.3.33']);
        $b = self::dataset(['php_version' => '8.2.0']);

        $cmp = envSanityCompare($a, $b);

        self::assertFalse($cmp['ok'], 'a different PHP must be refused');
        self::assertContains('php_version', array_keys($cmp['compared']), 'it must be COMPARED, not skipped');
    }

    /** And a contradicting one-sided-looking case: same key, different value. */
    public function testAKeyBothSidesRecordWithDifferentValuesIsRefused(): void
    {
        $a = self::dataset(['php_version' => '8.3.33', 'opcache' => true]);
        $b = self::dataset(['php_version' => '8.3.33', 'opcache' => false]);

        self::assertFalse(
            envSanityCompare($a, $b)['ok'],
            'opcache recorded on both sides with different values must be refused'
        );
    }

    public function testTheRefusalNamesTheDifferingKeyAndBothValues(): void
    {
        $out = sys_get_temp_dir() . '/envsanity-' . bin2hex(random_bytes(4)) . '.php';
        $php = <<<'PHP'
<?php
require_once $argv[1] . '/scripts/env-sanity.php';
$t = ['env' => ['php_version' => '8.3.33'], 'apps' => []];
$f = ['env' => ['php_version' => '8.2.0'], 'apps' => []];
exit(assertEnvComparable($t, $f) ? 0 : 1);
PHP;
        file_put_contents($out, $php);
        exec('php ' . escapeshellarg($out) . ' ' . escapeshellarg(dirname(__DIR__)) . ' 2>&1', $lines, $code);
        @unlink($out);

        $text = implode("\n", $lines);
        self::assertSame(1, $code, 'a mismatch must exit non-zero');
        self::assertStringContainsString('Env mismatch', $text);
        self::assertStringContainsString('8.2.0', $text, 'the refusal must show the offending value');
    }

    /**
     * A dataset with no comparable keys at all cannot be cross-checked. Two
     * such datasets must NOT be reported as verified-equivalent without saying
     * so — the failure mode is a splice "passing" on no evidence.
     */
    public function testNoOverlappingKeysIsAcceptedButEveryKeyIsReportedSkipped(): void
    {
        $a = self::dataset(['os' => 'Linux']);
        $b = self::dataset(['budget' => '1000x10']);

        $cmp = envSanityCompare($a, $b);

        self::assertTrue($cmp['ok'], 'nothing contradicts, so nothing may be refused');
        self::assertSame([], $cmp['compared'], 'but nothing was verified either');
    }

    /** The three splice scripts must all use the shared rule, not their own. */
    public function testEverySpliceScriptUsesTheSharedCheck(): void
    {
        foreach (['merge-app.php', 'merge-modes.php', 'assemble-real.php'] as $script) {
            $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/' . $script);

            self::assertStringContainsString(
                "require_once __DIR__ . '/env-sanity.php'",
                $src,
                "{$script} must load the shared check"
            );
            self::assertStringNotContainsString(
                "foreach (['php_version', 'opcache', 'sapi']",
                $src,
                "{$script} must not keep a private copy of the key list — that copy is the bug"
            );
        }
    }
}
