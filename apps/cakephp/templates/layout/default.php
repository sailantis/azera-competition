<?php
/**
 * @var \App\Cake\View\AppView $this
 * @var string $title
 * @var string|null $locale
 * @var string|null $platform
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — Azera Competition (CakePHP 5)</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; }

        h1 { color: #1a73e8; }

        table { border-collapse: collapse; width: 100%; }

        th,
        td { border: 1px solid #ddd; padding: 0.5rem 0.75rem; text-align: left; }

        th { background: #f5f5f5; }

        tr:nth-child(even) { background: #fafafa; }

        .badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 3px; font-size: 0.8rem; background: #e8f0fe; color: #1a73e8; }

        .meta { color: #666; font-size: 0.85rem; margin-top: 1rem; }

        .pagination { display: flex; justify-content: space-between; align-items: center; margin-top: 1rem; padding: 0.5rem 0; }

        .pagination .page-info { color: #666; font-size: 0.85rem; }

        .pagination .page-nav { display: flex; align-items: center; gap: 1rem; }

        .pagination .page-nav a { color: #1a73e8; text-decoration: none; }

        .pagination .page-nav a:hover { text-decoration: underline; }

        .pagination .page-nav .disabled { color: #ccc; }

        .pagination .page-nav .page-current { font-size: 0.85rem; color: #555; }
    </style>
</head>

<body>
    <nav>
        <a href="/">Home</a> |
        <a href="/items">Items</a> |
        <a href="/features">Features</a>
    </nav>
    <hr>
    <div class="content">
        <?= $this->fetch('content') ?>
    </div>
    <footer class="meta">
        Rendered with CakePHP 5 — Azera Competition Benchmark
        <?= isset($locale) ? '| Locale: ' . h($locale) : '' ?>
        <?= isset($platform) ? '| Platform: ' . h($platform) : '' ?>
    </footer>
</body>

</html>