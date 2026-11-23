<?php

declare(strict_types=1);

namespace AzeraCompetition\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Seeding must leave the database in a state a finished request leaves it, and a reset
 * must really remove the database — including its WAL sidecars.
 *
 * WHY. In WAL mode the database on disk is three files: `bench.sqlite`, `-wal` and
 * `-shm`. `seed.php` used to unlink only the first. That was not corrupting anything,
 * because SQLite validates a WAL against the database header (salt + change counter) and
 * discards one that does not match — verified on the bench VM, both with and without the
 * `-shm` present.
 *
 * Two things made the one-file unlink wrong anyway:
 *
 *   1. The NON-reset path is genuinely exposed. `php seed.php` without `--reset` keeps the
 *      main file and then DROPs and recreates `items`; a WAL left behind by a killed run is
 *      REPLAYED into that file on the next connect. Measured: 2,000 stale rows reappear
 *      before the DDL runs. It is harmless today only because the table is recreated
 *      immediately afterwards.
 *   2. Depending on SQLite's header-salt validation for correctness is invisible. A reader
 *      of `seed.php` has no way to know whether the leftover `-wal` is harmless — the code
 *      looks like it forgot one.
 *
 * These tests run the real script against a scratch data directory, so they pin the
 * behaviour rather than the implementation: run `--reset`, run it twice, and assert the
 * observable outcome (row count, no sidecars, row count stable across runs).
 */
final class SeedWalSidecarTest extends TestCase
{
    private string $scratch = '';

    protected function setUp(): void
    {
        // seed.php hard-codes data/bench.sqlite relative to its own directory, so the
        // script is copied into a scratch tree rather than parameterised: changing the
        // script's path handling only to make it testable would be the tail wagging the dog.
        $this->scratch = sys_get_temp_dir() . '/seed-wal-test-' . getmypid() . '-' . bin2hex(random_bytes(4));
        mkdir($this->scratch . '/data', 0777, true);
        copy(dirname(__DIR__) . '/seed.php', $this->scratch . '/seed.php');
    }

    protected function tearDown(): void
    {
        if ($this->scratch === '' || !is_dir($this->scratch)) {
            return;
        }
        foreach (glob($this->scratch . '/data/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->scratch . '/data');
        @unlink($this->scratch . '/seed.php');
        @rmdir($this->scratch);
    }

    /** @return list<string> which of the WAL-mode files are on disk */
    private function sidecars(string $scratch): array
    {
        $present = [];
        foreach (['', '-wal', '-shm'] as $suffix) {
            if (file_exists($scratch . '/data/bench.sqlite' . $suffix)) {
                $present[] = $suffix === '' ? '(main)' : $suffix;
            }
        }

        return $present;
    }

    private function seed(string $scratch, string $args = ''): string
    {
        $cmd = sprintf(
            '%s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($scratch . '/seed.php'),
            $args
        );
        exec($cmd, $out, $code);
        self::assertSame(0, $code, 'seed.php failed: ' . implode("\n", $out));

        return implode("\n", $out);
    }

    private function rowCount(string $scratch): int
    {
        $pdo = new \PDO('sqlite:' . $scratch . '/data/bench.sqlite');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    }

    /**
     * A finished seed leaves ONE file.
     *
     * WAL mode checkpoints and deletes the sidecars when the last connection closes, and
     * `seed.php` now drops its handle before reporting. Asserting this pins the intent —
     * "seeded" should look like a settled database, not one with pending frames.
     */
    public function testSeedingLeavesNoWalSidecars(): void
    {
        $this->seed($this->scratch, '--reset --rows=50');

        self::assertSame(
            ['(main)'],
            $this->sidecars($this->scratch),
            'a seeded database must be a single file: the WAL is checkpointed and removed when '
                . 'the last connection closes, and seed.php closes deliberately'
        );
    }

    /** The rows actually land. */
    public function testSeedingWritesTheRequestedRows(): void
    {
        $out = $this->seed($this->scratch, '--reset --rows=50');

        self::assertStringContainsString('Seeded 50 rows', $out);
        self::assertSame(50, $this->rowCount($this->scratch));
    }

    /**
     * A reset really removes the previous database, WAL sidecar included.
     *
     * This is the regression the one-file unlink risked. The stale WAL is created the way
     * a killed worker leaves one: a connection opens, writes, and dies without a clean
     * shutdown.
     */
    public function testResetRemovesAStaleWalLeftByAnAbruptShutdown(): void
    {
        $this->seed($this->scratch, '--reset --rows=10');
        self::assertSame(10, $this->rowCount($this->scratch));

        // Manufacture a stale WAL the way a killed worker leaves one, but
        // deterministically: build the triple in a SEPARATE directory, then copy it into
        // place WITHOUT ever having the main file and the WAL attached to each other. A
        // killed process is hard to reproduce portably (PHP's destructor checkpoints on a
        // normal exit, posix_kill is Unix-only, taskkill needs a wrapper), and what the
        // test needs is only "a mismatched -wal sitting next to bench.sqlite".
        $stale = $this->scratch . '/stale';
        mkdir($stale, 0777, true);
        $script = $stale . '/make.php';
        file_put_contents($script, <<<'PHP'
            <?php
            $db = __DIR__ . '/stale.sqlite';
            $p = new PDO('sqlite:' . $db);
            $p->exec('PRAGMA journal_mode = WAL');
            $p->exec('PRAGMA synchronous = NORMAL');
            $p->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, title TEXT, created_at TEXT)');
            $p->beginTransaction();
            for ($i = 1; $i <= 900; $i++) {
                $p->exec("INSERT OR REPLACE INTO items (id, title, created_at) VALUES ($i, 'STALE', 'x')");
            }
            $p->commit();
            // Copy the triple out while the connection is still OPEN, so the -wal file on
            // disk still holds the committed frames and has not been checkpointed.
            $out = __DIR__ . '/out';
            @mkdir($out, 0777, true);
            copy($db, $out . '/bench.sqlite');
            copy($db . '-wal', $out . '/bench.sqlite-wal');
            copy($db . '-shm', $out . '/bench.sqlite-shm');
            PHP);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1', $out, $code);
        @unlink($script);

        $src = $stale . '/out';
        self::assertFileExists($src . '/bench.sqlite-wal', 'the fixture must produce a WAL to test against');
        self::assertGreaterThan(
            0,
            (int) filesize($src . '/bench.sqlite-wal'),
            'the fixture WAL must be non-empty, or there is nothing to leave behind'
        );

        // Drop the triple in place of the seeded database, exactly as a killed run would.
        foreach (['', '-wal', '-shm'] as $suffix) {
            $target = $this->scratch . '/data/bench.sqlite' . $suffix;
            @unlink($target);
            copy($src . '/bench.sqlite' . $suffix, $target);
        }

        self::assertFileExists(
            $this->scratch . '/data/bench.sqlite-wal',
            'the stale triple must now be sitting where bench.sqlite lives, or the reset has '
                . 'nothing stale to remove'
        );

        // Now reset. If --reset only unlinked the main file, the stale WAL would survive —
        // and SQLite would replay it into the recreated database.
        $this->seed($this->scratch, '--reset --rows=10');

        self::assertSame(
            ['(main)'],
            $this->sidecars($this->scratch),
            '--reset must delete -wal and -shm, not just the main file'
        );
        self::assertSame(
            10,
            $this->rowCount($this->scratch),
            'the recreated database must contain only the freshly seeded rows — a leftover '
                . 'WAL replayed into it would show up here'
        );
    }

    /**
     * Seeding twice in a row is idempotent.
     *
     * The harness re-seeds per block (`run-http.php`), so the second run must not inherit
     * anything from the first — no rows, no sidecars, no accumulated WAL.
     */
    public function testSeedingTwiceGivesTheSameResult(): void
    {
        $this->seed($this->scratch, '--reset --rows=30');
        $firstRows  = $this->rowCount($this->scratch);
        $firstFiles = $this->sidecars($this->scratch);

        $this->seed($this->scratch, '--reset --rows=30');

        self::assertSame($firstRows, $this->rowCount($this->scratch), 'row count must not drift between seeds');
        self::assertSame($firstFiles, $this->sidecars($this->scratch), 'the on-disk file set must not drift');
        self::assertSame(30, $firstRows);
    }
}
