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

if (file_exists($dbFile) && $reset) {
    unlink($dbFile);
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

echo "Seeded {$count} rows into {$dbFile}\n";