<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Guards the two provenance claims a published benchmark makes about its build:
 * WHICH build it was, and WHETHER that build was clean.
 *
 * Both were wrong on the 2026-11-10 report, in opposite directions, and neither
 * produced an error — the page rendered plausibly and said:
 *
 *     Azera dev-main (377d0f6+dirty)
 *
 * Two separate faults:
 *
 * 1. `dev-main` names no build at all. Azera is consumed through a Composer
 *    `path` repository, which records the BRANCH rather than a release, and the
 *    `declaredVersion()` override that exists for exactly this case reads a
 *    composer.json `version` field that was deliberately deleted on 2026-09-28
 *    (`bf98479`: Composer derives it from the tag, and the field made
 *    clarity-engine unresolvable as dev-main). So the branch passed through.
 *    The launcher HAD the tag question right but asked it with
 *    `--exact-match`, which fails whenever HEAD is not exactly on a tag — the
 *    normal state while the framework is being worked on — and fell back to a
 *    bare sha. `v0.4.1-4-g377d0f6` states the release AND the distance;
 *    `0.4.1` alone would have claimed a tag the code is not at.
 *
 * 2. `+dirty` said the measured code contained uncommitted changes. It did not:
 *    `git diff HEAD -- src/` was empty and the only modified files were
 *    README.md and the published benchmark docs, all under paths `--exclude`
 *    keeps off the VM. The mark was true about the CHECKOUT and false about the
 *    MEASUREMENT, and a published figure only makes the second claim.
 *
 * These are asserted against the launcher SOURCE rather than by running it,
 * because both are properties of the flags it passes and nothing else: a run
 * needs a VM, a dirty tree and a tag topology to reproduce, and the failure mode
 * of each is a silently-plausible string rather than an exception.
 */
final class ProvenanceRefTest extends TestCase
{
    private static function launcher(): string
    {
        return (string) file_get_contents(
            str_replace('\\', '/', dirname(__DIR__)) . '/scripts/run-remote.ps1'
        );
    }

    /**
     * The launcher with its comments removed.
     *
     * These assertions are about the FLAGS the script passes, and the script
     * documents each one extensively — including by NAMING the wrong flag it no
     * longer uses, because "we do not use --exact-match, and here is why" is the
     * thing that stops it coming back. Asserting against the raw text made the
     * explanation itself fail the test, which would push exactly the wrong way:
     * the fix for a red test would be to delete the rationale.
     *
     * Only whole-line `#` comments are stripped. A `#` inside a string (a URL
     * fragment, a colour) is left alone, and no assertion here depends on text
     * that lives in one.
     */
    private static function launcherCode(): string
    {
        $lines = explode("\n", self::launcher());

        return implode("\n", array_filter(
            $lines,
            static fn(string $l): bool => preg_match('/^\s*#/', $l) !== 1
        ));
    }

    /**
     * The ref is read with plain `describe`, never `--exact-match`.
     *
     * This is the fix for `dev-main`/bare-sha: `--exact-match` answers only when
     * HEAD IS the tag, so it silently reduced every in-progress build to a sha
     * that names no release. `describe --tags --always` answers in all three
     * states — on a tag (`v0.4.2`), past it (`v0.4.1-4-g377d0f6`), and with no
     * tags at all (the short sha).
     */
    public function testTheRefComesFromTagAwareDescribe(): void
    {
        $code = self::launcherCode();

        self::assertStringNotContainsString(
            '--exact-match',
            $code,
            '--exact-match reduces every post-tag build to a bare sha, which names no release '
                . 'and is what published "dev-main (377d0f6)" for a v0.4.1-4 build'
        );

        $describeCalls = substr_count($code, 'describe --tags --always');
        self::assertSame(
            2,
            $describeCalls,
            'azera-framework AND clarity-engine both need the tag-aware form; '
                . "found {$describeCalls} call(s)"
        );

        self::assertStringNotContainsString(
            'rev-parse --short HEAD',
            $code,
            'the bare-sha fallback is what --always already provides; keeping it re-opens the '
                . 'path that produced an unreadable stamp'
        );
    }

    /**
     * The version-tag glob survives, because a non-release tag would otherwise win.
     *
     * `git describe` breaks a tie between tags on one commit by NAME, so a
     * `bench-...` marker or a hand-made alias can be stamped as the measured
     * build. `--match 'v*'` restricts the answer to releases.
     */
    public function testTheDescribeCallIsRestrictedToReleaseTags(): void
    {
        self::assertSame(
            2,
            substr_count(self::launcherCode(), "--match 'v*'"),
            'both lookups must stay restricted to version tags, or a marker tag can be '
                . 'published as the measured build'
        );
    }

    /**
     * The dirty check consults shipped paths, not the whole working tree.
     *
     * `docs` and `results` are excluded from every sync, so an edit there cannot
     * reach a timed request. Marking a build dirty for one is a false claim about
     * the measurement — and a false POSITIVE here is not harmless: it teaches a
     * reader to ignore the mark, which is the whole value of the mark.
     */
    public function testTheDirtyMarkOnlyConsidersShippedPaths(): void
    {
        $ps1  = self::launcher();
        $code = self::launcherCode();

        self::assertStringContainsString(
            'function Get-SyncedDirtyEntry',
            $ps1,
            'the dirty check must go through the shipped-path filter'
        );
        self::assertStringContainsString('function Test-PathIsSynced', $ps1);

        // Both repos must use it. A fix applied to only the framework half would
        // leave clarity's stamp claiming dirtiness from a docs edit.
        $uses = substr_count($code, 'Get-SyncedDirtyEntry $');
        self::assertSame(
            2,
            $uses,
            "azera-framework AND clarity-engine both stamp a ref; found {$uses} filtered check(s)"
        );

        self::assertStringNotContainsString(
            'git -C $fwRepo status --porcelain',
            $code,
            'the raw porcelain check is the bug: it reports paths the VM never receives'
        );
        self::assertStringNotContainsString(
            'git -C $clRepo status --porcelain',
            $code,
            'same for clarity-engine'
        );
    }

    /**
     * The excluded-path list is DERIVED from $Excludes, not restated.
     *
     * A hand-kept second copy is the kind of thing that silently stops matching
     * when an exclude is added — and the failure is invisible, because the result
     * is just a dirty mark that comes back for a path nobody ships.
     */
    public function testTheShippedPathFilterDerivesItsExclusions(): void
    {
        $ps1 = self::launcher();

        self::assertStringContainsString(
            '$SyncedExcludedPaths = @(',
            $ps1,
            'the filter must build its exclusion list from $Excludes'
        );
        self::assertStringContainsString('$Excludes |', $ps1);

        // docs and results are the two that actually caused the false mark, so
        // the mechanism is pinned on them by name in the reason it reads.
        self::assertStringContainsString("'--exclude=docs'", $ps1);
        self::assertStringContainsString("'--exclude=results'", $ps1);
    }

    /**
     * A rename is judged by its TARGET, which is the path that ships.
     *
     * Porcelain v1 writes `XY old -> new`. Testing the whole line would test the
     * source path, so moving a file OUT of docs/ into src/ would stay unmarked.
     */
    public function testTheDirtyScanReadsTheRenameTarget(): void
    {
        $ps1 = self::launcher();

        self::assertStringContainsString(
            "IndexOf(' -> ')",
            $ps1,
            'a rename must be judged by its target path, not its source'
        );
    }
}
