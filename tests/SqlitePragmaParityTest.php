<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every entrant must configure SQLite identically.
 *
 * WHY. Two pragmas decide what a commit costs, and they only work as a PAIR.
 * Measured on the bench VM with a bare PDO handle doing the same upsert the apps do
 * (800 iterations), which isolates the storage engine from any framework:
 *
 *   WAL / NORMAL        p50 0.013 ms · p90 0.015 · p99 0.035
 *   delete / NORMAL     p50 0.466 ms · p90 0.539 · p99 0.662
 *   delete / FULL       p50 3.3   ms · p90 3.5   · p99 3.8
 *
 * Under WAL a NORMAL commit is an append to the -wal file with no fsync; rollback
 * journal mode fsyncs on every commit even at NORMAL, and FULL forces additional
 * syncs (measured at 3-5 fdatasync calls per commit). So the value of one pragma
 * depends entirely on the other, and pinning only one of them is worse than pinning
 * neither: it looks deliberate while leaving the pack split.
 *
 * CAVEAT ON THE MAGNITUDE. Those are storage-engine numbers. Through a real
 * framework endpoint the difference shrinks to nothing for the slower entrants,
 * because their own DB layer costs hundreds of microseconds and the commit is lost
 * inside it. Do NOT quote the ratio above as if the benchmark would collapse without
 * WAL: measured through the apps, removing WAL moved CakePHP by 2% and Laravel by 4%,
 * and moved Azera by far more — which is exactly why WAL stays on. The full
 * comparison is in the README's "SQLite pragma parity" section.
 *
 * (An earlier revision of this comment claimed "WAL is worth ~55x" based on a
 * standalone commit micro-benchmark. The direction held; the ratio did not survive
 * being measured through the applications, and it implied a collapse that does not
 * happen.)
 *
 * The outlier-rate split was not hypothetical either. Laravel's entrant pinned
 * `synchronous = NORMAL` from the start while the other six inherited SQLite's `FULL`,
 * and the resulting 14x spread in outlier rate (Phalcon 5/600 vs CakePHP 71/600) read
 * like framework noise. It was a storage-configuration difference.
 *
 * These are SOURCE checks, not runtime ones: the pragmas are applied at connect time
 * inside each framework's own bootstrap, and asserting the declaration is what a unit
 * test can hold. The runtime values were verified separately per entrant.
 */
final class SqlitePragmaParityTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * The file(s) each entrant configures its SQLite connection in.
     *
     * @return array<string,list<string>>
     */
    private static function configFiles(): array
    {
        return [
            'azera'       => ['apps/azera/Bootstrap.php'],
            'cakephp'     => ['apps/cakephp/src/Db.php'],
            'codeigniter' => ['apps/codeigniter/Config/Database.php'],
            'laravel'     => ['apps/laravel/config/database.php'],
            'spiral'      => ['apps/spiral/src/Bootloader/AppBootloader.php'],
            // Symfony's pragmas live in Kernel::boot(), not the YAML: DBAL has no
            // post-connect hook, so the driver config can only carry the PDO
            // option, and the PRAGMA needs the live connection.
            'symfony'     => [
                'apps/symfony/config/packages/doctrine.yaml',
                'apps/symfony/src/Kernel.php',
            ],
            'phalcon'     => ['apps/phalcon/src/Bootstrap.php'],
        ];
    }

    /**
     * Each app names NORMAL explicitly — no entrant may inherit SQLite's FULL.
     *
     * The spellings differ by framework and all are legitimate:
     *   `PRAGMA synchronous = NORMAL` (azera, cakephp, spiral, phalcon)
     *   `'synchronous' => 1`          (codeigniter — the driver validates int 0-3)
     *   `synchronous: NORMAL`         (symfony/doctrine dbal option)
     *   `'synchronous' => 'NORMAL'`   (laravel)
     */
    public function testEveryEntrantPinsSynchronousNormal(): void
    {
        $missing = [];

        foreach (self::configFiles() as $app => $files) {
            $source = '';
            foreach ($files as $file) {
                $path = self::root() . '/' . $file;
                self::assertFileExists($path, "{$app}'s config file moved");
                $source .= (string) file_get_contents($path) . "\n";
            }

            $pinsNormal = preg_match('/synchronous\s*=\s*NORMAL/i', $source) === 1
                || preg_match("/'synchronous'\s*=>\s*1\b/", $source) === 1
                || preg_match('/synchronous\s*=>\s*1\b/', $source) === 1
                || preg_match("/'synchronous'\s*=>\s*'NORMAL'/i", $source) === 1
                || preg_match('/^\s*synchronous:\s*NORMAL/im', $source) === 1
                || preg_match('/synchronous\s*=>\s*NORMAL/i', $source) === 1;

            if (!$pinsNormal) {
                $missing[] = $app;
            }
        }

        self::assertSame(
            [],
            $missing,
            "These entrants would inherit SQLite's synchronous=FULL and pay a WAL checkpoint fsync:\n  "
                . implode("\n  ", $missing)
                . "\nPin NORMAL so the pack measures frameworks, not two storage configurations."
        );
    }

    /**
     * WAL stays on everywhere — NORMAL only helps under WAL.
     *
     * `synchronous=NORMAL` in rollback-journal mode still fsyncs far more often
     * than in WAL, and the whole comparison assumes the shared file is in WAL.
     *
     * TWO EXEMPTIONS, both about where WAL can be expressed rather than whether
     * it is on:
     *
     *   - CI4's SQLite3 connection exposes no `journalMode` option; its
     *     initialize() applies only foreign_keys, busyTimeout and synchronous. WAL
     *     comes from `seed.php` having put the shared FILE into WAL mode, which is a
     *     persistent property of the file and therefore holds for every entrant.
     *   - CI4 also carries a `$tests` group pointing at `:memory:`; that array is
     *     stripped before the check so an in-memory fixture cannot satisfy it.
     */
    public function testEveryEntrantUsesWal(): void
    {
        // Entrants whose driver cannot state journal_mode; WAL is a property of
        // the database file itself (set by seed.php) and applies to them anyway.
        $fileProvided = ['codeigniter'];

        foreach (self::configFiles() as $app => $files) {
            $source = '';
            foreach ($files as $file) {
                $source .= (string) file_get_contents(self::root() . '/' . $file) . "\n";
            }

            // Drop a dedicated in-memory test group before looking for WAL.
            $source = (string) preg_replace('/\$tests\s*=\s*\[.*?\];/s', '', $source);

            if (in_array($app, $fileProvided, true)) {
                self::assertStringContainsString(
                    'journal_mode',
                    $source,
                    "{$app} cannot set journal_mode in config, so it must SAY that WAL comes from the "
                        . 'database file — otherwise the next editor will assume it was forgotten'
                );

                continue;
            }

            self::assertMatchesRegularExpression(
                '/journal_mode[\s\'"]*(?:=|=>|:)[\s\'"]*wal/i',
                $source,
                "{$app} must keep the shared database in WAL mode"
            );
        }
    }

    /**
     * No entrant may pin synchronous to anything else.
     *
     * An explicit `FULL`, `EXTRA` or `OFF` would be worse than inheriting a
     * default: it would look deliberate while still splitting the pack.
     *
     * Comments are stripped first, and the numeric match is anchored to a value
     * position: the pragma text itself contains "synchronous = NORMAL", and a
     * naive scan also matched the digit in neighbouring prose ("ATTR_...2").
     */
    public function testNoEntrantPinsAnotherSynchronousLevel(): void
    {
        $offenders = [];

        foreach (self::configFiles() as $app => $files) {
            foreach ($files as $file) {
                $source = (string) file_get_contents(self::root() . '/' . $file);
                $source = self::stripComments($source);

                if (
                    preg_match('/synchronous\s*=\s*(FULL|EXTRA|OFF)\b/i', $source) === 1
                    || preg_match("/'synchronous'\s*=>\s*'?(FULL|EXTRA|OFF)'?/i", $source) === 1
                    || preg_match('/^\s*synchronous:\s*(FULL|EXTRA|OFF)\s*$/im', $source) === 1
                    || preg_match("/'synchronous'\s*=>\s*[023]\b/", $source) === 1
                ) {
                    $offenders[] = $app . ' (' . $file . ')';
                }
            }
        }

        self::assertSame([], $offenders, 'these pin a non-NORMAL synchronous level: ' . implode(', ', $offenders));
    }

    /** Strip PHP block/line comments and YAML `#` comments. */
    private static function stripComments(string $source): string
    {
        $source = (string) preg_replace('~/\*.*?\*/~s', '', $source);

        $kept = [];
        foreach (preg_split('~\R~', $source) ?: [] as $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '#')) {
                continue;
            }
            // Drop a trailing // comment on a code line.
            $kept[] = (string) preg_replace('~\s//.*$~', '', $line);
        }

        return implode("\n", $kept);
    }
}
