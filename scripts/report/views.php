<?php

declare(strict_types=1);

/**
 * Report manifest ??? the registry that makes "different benchmarks, integrated
 * in different ways" possible.
 *
 * A *dataset* is a raw result JSON (one combined run, or a merge of the legacy
 * per-pair files). A *view* is a presentation over a dataset: which frameworks
 * take part, which one is the baseline, which charts to draw, and where (if
 * anywhere) the result is published.
 *
 * Adding a new comparison is a matter of adding a view here ??? no code change.
 *
 * @return array{datasets: array<string,array<string,mixed>>, views: array<string,array<string,mixed>>}
 */

$root = dirname(__DIR__, 2);

return [
    // --- Datasets ---------------------------------------------------------
    'datasets' => [
        // The canonical dataset: one combined run of every framework (single
        // env block, one measurement per framework ??? no duplicate-Azera
        // ambiguity). The legacy per-pair azera-vs-* files were superseded by
        // this and removed; there is no longer a merge fallback.
        'free-for-all' => [
            'label' => 'Free-for-all — all six frameworks in one run (warm + cold)',
            'file'  => $root . '/results/free-for-all-opcache.json',
        ],

        // The same run, relabelled by DEPLOYMENT MODEL instead of harness
        // mode: warm -> roadrunner (resident worker), cold -> php-fpm (fresh
        // boot per request). Produced by scripts/derive-fpm.php from the
        // canonical file — regenerate it after every new run.
        'deployments' => [
            'label' => 'Deployment models — the free-for-all run, warm/cold relabelled as roadrunner/php-fpm',
            'file'  => $root . '/results/free-for-all-opcache-deployments.json',
        ],
    ],

    // --- Views ------------------------------------------------------------
    // Latency charts are drawn on a LINEAR axis. That is a correctness choice,
    // not a cosmetic one: on a logarithmic axis the drawn width of a range is
    // its *ratio*, so Azera's 0.005???0.027 ms (5x) renders four times longer
    // than Symfony's 0.166???0.241 ms (1.4x) ??? even though Symfony's spread is
    // 3.4x larger in absolute milliseconds. Log made the fastest framework
    // look like the most volatile one. Linear keeps drawn width proportional
    // to real spread. (Peak memory was always linear.)
    'views' => [
        // The headline comparison, ROADRUNNER story: resident worker, boot
        // paid once. Published into the framework docs + README.
        'warm-start' => [
            'title'     => 'Framework Competition — Warm Start',
            'subtitle'  => 'RoadRunner/Octane-style resident worker: the framework boots once, then serves every request. Full-stack request lifecycle benchmark (routing → controller → ORM query (SQLite) → template render → response).',
            'dataset'   => 'free-for-all',
            'baseline'  => 'azera',
            'mode'      => 'warm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'    => ['hero', 'speedup', 'features', 'memory', 'wins'],
            'publish'   => ['framework'],
        ],

        // The same benchmark as most PHP apps actually run it: PHP-FPM, a
        // fresh application boot per request (the harness' cold mode with
        // opcache retained — the bytecode-sharing real FPM workers enjoy).
        // Published alongside the warm story so readers pick their
        // deployment; the two views never blend modes.
        'cold-start' => [
            'title'      => 'Framework Competition — Cold Start',
            'subtitle'   => 'PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM worker management itself is not simulated — these are lower bounds for real FPM latency.',
            'dataset'    => 'deployments',
            'baseline'   => 'azera',
            'mode'       => 'php-fpm',
            'log_scale'  => false,
            'apps'       => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'     => ['hero', 'speedup', 'features', 'memory', 'wins'],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-FPM.md',
        ],

        // A memory-focused cut of the same dataset (peak memory is
        // mode-independent up to measurement noise).
        'memory' => [
            'title'    => 'Peak Memory Footprint',
            'subtitle' => 'Highest peak memory per framework. Low memory is what makes Azera cheap to run at scale.',
            'dataset'  => 'free-for-all',
            'baseline' => 'azera',
            'mode'     => 'warm',
            'apps'     => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'   => ['memory'],
            'publish'  => [],
        ],

        // The scale-free "second axis": everything divided by the baseline.
        'relative' => [
            'title'     => 'Relative to Azera',
            'subtitle'  => 'The same dataset with Azera pinned at 1.0, so each framework reads as a multiple of the baseline instead of an absolute time.',
            'dataset'   => 'free-for-all',
            'baseline'  => 'azera',
            'mode'      => 'warm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'    => ['speedup'],
            'publish'   => [],
        ],
    ],
];
