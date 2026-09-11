<?php

declare(strict_types=1);

/**
 * Report manifest — the registry that makes "different benchmarks, integrated
 * in different ways" possible.
 *
 * A *dataset* is a raw result JSON (one combined run, or a merge of the legacy
 * per-pair files). A *view* is a presentation over a dataset: which frameworks
 * take part, which one is the baseline, which charts to draw, and where (if
 * anywhere) the result is published.
 *
 * Adding a new comparison is a matter of adding a view here — no code change.
 *
 * @return array{datasets: array<string,array<string,mixed>>, views: array<string,array<string,mixed>>}
 */

$root = dirname(__DIR__, 2);

return [
    // --- Datasets ---------------------------------------------------------
    'datasets' => [
        // The canonical dataset: one combined run of every framework (single
        // env block, one measurement per framework — no duplicate-Azera
        // ambiguity). The legacy per-pair azera-vs-* files were superseded by
        // this and removed; there is no longer a merge fallback.
        'free-for-all' => [
            'label' => 'Free-for-all — all six frameworks in one run',
            'file'  => $root . '/results/free-for-all-opcache.json',
        ],
    ],

    // --- Views ------------------------------------------------------------
    // Latency charts are drawn on a LINEAR axis. That is a correctness choice,
    // not a cosmetic one: on a logarithmic axis the drawn width of a range is
    // its *ratio*, so Azera's 0.005→0.027 ms (5x) renders four times longer
    // than Symfony's 0.166→0.241 ms (1.4x) — even though Symfony's spread is
    // 3.4x larger in absolute milliseconds. Log made the fastest framework
    // look like the most volatile one. Linear keeps drawn width proportional
    // to real spread. (Peak memory was always linear.)
    'views' => [
        // The headline comparison. Published into the framework docs + README.
        'azera-vs-all' => [
            'title'     => 'Azera vs All Frameworks',
            'subtitle'  => 'A full-stack request lifecycle benchmark: routing → controller → ORM query (SQLite) → template render → response.',
            'dataset'   => 'free-for-all',
            'baseline'  => 'azera',
            'mode'      => 'warm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'    => ['hero', 'speedup', 'features', 'memory', 'wins'],
            'publish'   => ['framework'],
        ],

        // Proof that a view can be anything: two frameworks, no Azera.
        'laravel-vs-symfony' => [
            'title'     => 'Laravel vs Symfony',
            'subtitle'  => 'The same dataset, narrowed to two frameworks. Any subset works — no code change.',
            'dataset'   => 'free-for-all',
            'baseline'  => 'laravel',
            'mode'      => 'warm',
            'log_scale' => false,
            'apps'      => ['laravel', 'symfony'],
            'charts'    => ['hero', 'speedup', 'features', 'memory'],
            'publish'   => [], // not published anywhere
        ],

        // A memory-focused cut of the same dataset.
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
