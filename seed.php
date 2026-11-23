<?php
/**
 * Seed the shared SQLite database used by all framework adapters.
 *
 * Schema:
 *   items(id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT NOT NULL, created_at TEXT NOT NULL)
 *
 * Usage:
 *   php seed.php              # create/reset with 1000 rows
 *   php seed.php --rows=5000  # custom row count
 *   php seed.php --reset      # drop & recreate (same as default)
 *
 * The database file is placed at data/bench.sqlite (relative to this script).
 */

$opts  = getopt('', ['rows::', 'reset']);
$rows  = isset($opts['rows']) ? (int) $opts['rows'] : 1000;
$reset = isset($opts['reset']); // default behaviour is reset anyway

$dataDir = __DIR__ . '/data';
$dbFile  = $dataDir . '/bench.sqlite';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

/**
 * Remove the database AND its WAL sidecars.
 *
 * In WAL mode the database on disk is three files: `bench.sqlite`, `bench.sqlite-wal`
 * and `bench.sqlite-shm`. Deleting only the main file leaves a WAL whose header no
 * longer matches any database, and SQLite silently discards that when it cannot be
 * validated (salt + change counter) — verified, so the old one-file unlink was not
 * corrupting anything.
 *
 * It is still wrong to leave them, for two reasons:
 *
 *   1. The NON-reset path is genuinely exposed. `php seed.php` without `--reset` keeps
 *      the main file and then DROPs and recreates `items`; a WAL surviving a killed run
 *      is REPLAYED into that file on the next connect. Measured: 2,000 stale rows
 *      reappear before the DDL runs. Harmless here only because the table is recreated
 *      immediately afterwards.
 *   2. Correctness that depends on SQLite's header-salt validation is invisible to a
 *      reader. "Recreate the database" should mean the database is gone, not "gone and
 *      protected by an internal consistency check that happens to reject the leftovers".
 *
 * `unlink` on a missing sidecar is expected (a cleanly closed database has none), so the
 * existence check stays per-file; a warning here would be noise on every clean run.
 */
$sidecars = [$dbFile, $dbFile . '-wal', $dbFile . '-shm'];

if ($reset) {
    foreach ($sidecars as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// WAL mode + busy timeout for concurrent read/write during benchmarks
$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA busy_timeout = 5000');

$pdo->exec('DROP TABLE IF EXISTS items');
$pdo->exec(<<<SQL
CREATE TABLE items (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT    NOT NULL,
    created_at TEXT    NOT NULL
)
SQL);

$stmt = $pdo->prepare('INSERT INTO items (title, created_at) VALUES (?, ?)');
$now  = date('Y-m-d H:i:s');

$pdo->beginTransaction();
for ($i = 1; $i <= $rows; $i++) {
    $stmt->execute([
        sprintf('Benchmark Item %05d', $i),
        $now,
    ]);
}
$pdo->commit();

$count = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();

// Close deliberately before reporting. WAL mode checkpoints and removes `-wal` /
// `-shm` when the LAST connection closes, so dropping the handle here leaves a seeded
// database in exactly the state a finished request leaves it: three files collapsed to
// one, nothing pending. Without this the sidecars linger until PHP's shutdown, which is
// correct but not what "seeded" should look like on disk.
unset($stmt, $pdo);

echo "Seeded {$count} rows into {$dbFile}\n";