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
            'label' => 'Free-for-all — all six frameworks in one run (warm + cold, per-endpoint-block fresh process)',
            'file'  => $root . '/results/free-for-all-opcache-iso.json',
        ],

        // The same run, relabelled by DEPLOYMENT MODEL instead of harness
        // mode: warm -> roadrunner (resident worker), cold -> php-fpm (fresh
        // boot per request). Produced by scripts/derive-fpm.php from the
        // canonical file — regenerate it after every new run.
        'deployments' => [
            'label' => 'Deployment models — the free-for-all run, warm/cold relabelled as roadrunner/php-fpm',
            'file'  => $root . '/results/free-for-all-opcache-iso-deployments.json',
        ],

        // REAL deployment measurements (scripts/run-http.php on the benchmark
        // VM): RoadRunner resident worker (mode roadrunner) and nginx +
        // php-fpm with pm.max_requests=1 (mode php-fpm) — the real servers,
        // not the harness simulation. End-to-end HTTP over loopback, so the
        // constant webserver overhead is INCLUDED in every number; the
        // floor-* pseudo-apps in the dataset expose that overhead explicitly.
        'real-deployments' => [
            'label' => 'Real deployments — RoadRunner + nginx/FPM on the benchmark VM (HTTP over loopback)',
            'file'  => $root . '/results/real-deployments.json',
        ],
    ],

    // --- Views ------------------------------------------------------------
    // Latency charts are drawn on a LINEAR axis. That is a correctness choice,
    // not a cosmetic one: on a logarithmic axis the drawn width of a range is
    // its *ratio*, so Azera's 0.005???0.027 ms (5x) renders four times longer
    // than Symfony's 0.166???0.241 ms (1.4x) ??? even though Symfony's spread is
    // 3.4x larger in absolute milliseconds. Log made the fastest framework
    // look like the most volatile one. Linear keeps drawn width proportional
    // to real spread. (Peak memory is linear too. The cold-start view omits
    // the memory chart — see the note on that view for why, including the
    // stale "boot ratchet" rationale it used to carry.)
    'views' => [
        // The headline comparison, ROADRUNNER story: resident worker, boot
        // paid once. Published into the framework docs + README.
        'warm-start' => [
            'title'     => 'Framework Competition — Warm Start',
            'subtitle'  => 'RoadRunner/Octane-style resident worker: the framework boots once, then serves every request. Each number charges that boot per request, so a cell is the whole time one request keeps a worker busy — what a recycled pool or a queue behind one worker actually experiences. Full-stack request lifecycle benchmark (routing → controller → ORM query (SQLite) → template render → response).',
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
            'title'     => 'Framework Competition — Cold Start',
            'subtitle'  => 'PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM worker management itself is not simulated — these are lower bounds for real FPM latency.',
            'dataset'   => 'deployments',
            'baseline'  => 'azera',
            'mode'      => 'php-fpm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // No 'memory' chart here, for two reasons that outlive the
            // original one:
            //
            //  1. The historical justification ("cold peak_mem is a boot
            //     ratchet") described the IN-PROCESS cold loop, which re-boots
            //     the framework 50x30 times in one process and ratchets ~0.3 MB
            //     per boot. Fork-per-iteration (7bf5189) removed that ratchet
            //     an hour after this chart was dropped (384f236) — and a later
            //     fix (77ae7a5) had to make the forked child actually REPORT
            //     its peak, which it had never done. So for a while cold
            //     peak_mem was neither a ratchet nor a measurement: it was the
            //     PARENT's priming-boot high-water mark, identical on every
            //     endpoint. Now it is real, but it is still a 2 MiB-quantised
            //     allocator mark whose meaningful content is "one boot + one
            //     request", which the startup chart already states directly in
            //     milliseconds.
            //
            //  2. This deployment model recycles the worker after every
            //     request, so there is no resident state to report. Resident
            //     memory — the question a memory chart answers — belongs to
            //     the roadrunner views.
            //
            // Timing is unaffected by any of this (residue is unreachable;
            // boot_ms and run means stay flat).
            'charts'     => ['hero', 'speedup', 'features', 'wins'],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-FPM.md',
        ],

        // REAL RoadRunner: actual RoadRunner server + resident worker over
        // HTTP (loopback). End-to-end numbers include the constant
        // webserver/IPC overhead — sub-0.1 ms framework features are expected
        // to disappear into that floor; heavy features stay meaningful.
        'real-roadrunner' => [
            'title'      => 'Framework Competition — Real RoadRunner',
            'subtitle'   => 'Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); sub-0.1 ms framework differences are below this floor. Single sequential client.',
            'dataset'    => 'real-deployments',
            'baseline'   => 'azera',
            'mode'       => 'roadrunner',
            'log_scale'  => false,
            'apps'       => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // 'resident-memory' rather than 'memory': these rows DO carry the
            // probe (mem_boot_heap/mem_heap), and a resident worker's retained
            // heap is the memory question this deployment model actually
            // raises. peak_mem is 0 here — run-http.php refuses to present the
            // client's own footprint as the framework's.
            'charts'     => ['hero', 'speedup', 'features', 'resident-memory', 'wins'],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-REAL.md',
        ],

        // REAL PHP-FPM + nginx: the pool recycles the worker after every
        // request (pm.max_requests=1) — a real fresh boot per request with
        // opcache retained. The honest real-world counterpart of the
        // simulated cold-start view.
        'real-fpm' => [
            'title'      => 'Framework Competition — Real PHP-FPM',
            'subtitle'   => 'Real nginx + PHP-FPM, worker recycled after every request (pm.max_requests=1): a genuine fresh boot per request with opcache retained. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-http/floor-php in the dataset). Single sequential client.',
            'dataset'    => 'real-deployments',
            'baseline'   => 'azera',
            'mode'       => 'php-fpm',
            'log_scale'  => false,
            'apps'       => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            'charts'     => ['hero', 'speedup', 'features', 'wins'],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-REAL-FPM.md',
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
