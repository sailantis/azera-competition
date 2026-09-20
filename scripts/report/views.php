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
        // php-fpm with pm.max_requests=0 (mode php-fpm) — the real servers,
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
            'charts'    => ['hero', 'speedup', 'features', 'memory'],
        ],

        // The same benchmark as most PHP apps actually run it: PHP-FPM, a
        // fresh application boot per request (the harness' cold mode with
        // opcache retained — the bytecode-sharing real FPM workers enjoy).
        // Published alongside the warm story so readers pick their
        // deployment; the two views never blend modes.
        'cold-start' => [
            'title'     => 'Framework Competition — Cold Start',
            'subtitle'  => 'PHP-FPM simulation, fresh boot per request: the application boots for every request (harness cold mode, opcache retained). FPM\'s own worker management is not simulated — real FPM keeps its worker alive and adds nginx + FastCGI overhead on top of this boot, so these are lower bounds for real FPM latency (see the real-fpm view for the measured version).',
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
            //  2. This deployment model has no resident state to report: the
            //     in-process cold mode forks a child per iteration, and real
            //     FPM (pm.max_requests=0) runs the entry script per request.
            //     Resident memory — the question a memory chart answers —
            //     belongs to the roadrunner views.
            //
            // Timing is unaffected by any of this (residue is unreachable;
            // boot_ms and run means stay flat).
            'charts' => ['hero', 'speedup', 'features'],
        ],

        // REAL RoadRunner: actual RoadRunner server + resident worker over
        // HTTP (loopback). End-to-end numbers include the constant
        // webserver/IPC overhead — sub-0.1 ms framework features are expected
        // to disappear into that floor; heavy features stay meaningful.
        'real-roadrunner' => [
            'title' => 'Framework Competition — Real RoadRunner',
            // The boot a row carries depends on the pool's max_jobs, which is
            // a DATASET fact — so this static subtitle states the rule only and
            // leaves the measured model to the generated prose below the table
            // (bootLegend/bootBasis read env.rr_max_jobs). Asserting a value
            // here would be a claim the view cannot check.
            'subtitle'  => 'Real RoadRunner server, resident PHP worker: the framework boots once, then serves every request. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-rr in the dataset); framework differences smaller than that floor are below its noise. Single sequential client. Whether a row also carries part of the worker\'s boot depends on the pool\'s max_jobs, which the dataset records: the boot is amortised across the jobs a worker serves between recycles, and a pool that never recycles puts no boot into a row at all. The deployment model actually measured is stated with the table.',
            'dataset'   => 'real-deployments',
            'baseline'  => 'azera',
            'mode'      => 'roadrunner',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // 'resident-memory' rather than 'memory': these rows DO carry the
            // probe (mem_boot_heap/mem_heap), and a worker's retained heap is
            // the memory question this deployment model actually raises.
            // peak_mem is 0 here — run-http.php refuses to present the
            // client's own footprint as the framework's.
            'charts' => ['hero', 'speedup', 'features', 'resident-memory'],
        ],

        // REAL PHP-FPM + nginx: FPM runs the app's entry script for every
        // request, so the framework's boot stays inside the request clock —
        // the honest real-world counterpart of the simulated cold-start view.
        // Whether the pool also tears down its worker per request is a pool
        // setting, not a property of FPM, so the subtitle deliberately does
        // not assert it: floorNote() reads `env.fpm_max_requests` and states
        // the model that was actually measured.
        'real-fpm' => [
            'title'     => 'Framework Competition — Real PHP-FPM',
            'subtitle'  => 'Real nginx + PHP-FPM serving over HTTP: the framework boots for every request, which is what PHP actually runs in production. The pool\'s worker-recycling setting is stated with the server floor below, since it changes what each row contains. End-to-end HTTP over loopback — includes the constant webserver overhead (see floor-http/floor-php in the dataset). Single sequential client.',
            'dataset'   => 'real-deployments',
            'baseline'  => 'azera',
            'mode'      => 'php-fpm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // 'resident-memory' here too, for the same reason as the RR view.
            // This used to be FPM's distinguishing feature: the pool is
            // pm=static/max_children=1/max_requests=0, so its ONE worker is
            // resident for the whole block and does retain what it built. The
            // probe only existed on the RoadRunner side until 2026-09-17, so
            // these rows carried zeros and the chart rendered nothing.
            'charts' => ['hero', 'speedup', 'features', 'resident-memory'],
        ],

        // --- Published summaries ------------------------------------------
        //
        // These two are the ONLY views that publish, and each is a strict
        // SUBSET of the full view it summarises: the headline chart, the races
        // that answer "which framework do I pick", the endpoint table, and a
        // link to the complete report.
        //
        // The startup section is deliberately ABSENT (2026-09-18) while the
        // `GET /` race is present. A boot chart is the one figure that cannot
        // be read on its own: it only means something next to the deployment
        // model in the same page's prose, and its own note block carries the
        // no-op rule ("several frameworks memoise their re-bootstrap"). The
        // latency table states the same cost inline, in the unit a reader is
        // choosing on. What replaces it is `routing` — the `GET /` race, the
        // same endpoint the boot was measured on, drawn on the shared feature
        // axis so the startup cost lands where a reader can compare it.
        //
        // Why a subset rather than the full view: `--publish=framework` copies a
        // publishing view VERBATIM, so while all four full views declared
        // `publish => ['framework']` a single run wrote four markdown files and
        // 59 SVGs into the framework repository. The framework docs want one page
        // per deployment model that carries the claim, not a second dashboard.
        //
        // Why TWO pages and never one: RoadRunner and PHP-FPM are different
        // transports, and the memory chart measures a different THING in each (a
        // retained worker heap vs a per-request peak). One page would have to
        // caption both, which is the one thing the report never does — the models
        // stay apart and cross-link instead (see `links`).
        //
        // `links` are printed at the foot of the page and must be ABSOLUTE: the
        // same .md is written to this repo's docs/benchmarks and to the framework
        // repo's docs/, so a relative path would resolve in one and dangle in the
        // other.
        'summary-roadrunner' => [
            'title'     => 'Framework Competition — RoadRunner Summary',
            'subtitle'  => 'The headline numbers from the measured RoadRunner deployment: the cost of one pass over every endpoint, the plain routing request, the two data-access races, and the worker memory that survives a request. End-to-end HTTP over loopback, so the constant webserver overhead is included (see floor-rr in the dataset). The deployment model actually measured is stated with the table.',
            'dataset'   => 'real-deployments',
            'baseline'  => 'azera',
            'mode'      => 'roadrunner',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // 'features' is the CHART (it draws the feature races); 'feature_keys'
            // narrows which races it draws. Routing leads the list: it is the
            // lightest workload in the suite and the one the dropped startup
            // chart was measured on, so it is what keeps the boot question on
            // the page — as a race to compare, not a chart to interpret. The
            // two data-access races follow, since those are what a reader
            // choosing a framework weighs.
            'charts'       => ['speedup', 'features', 'resident-memory'],
            'feature_keys' => ['routing', 'orm', 'rest-api'],
            'links'        => [
                'The complete report (all six frameworks, every feature chart)' => 'https://sailantis.github.io/azera-competition/benchmarks/',
                'This model, measured end to end'                               => 'https://sailantis.github.io/azera-competition/benchmarks/view-real-roadrunner.html',
                'The PHP-FPM summary'                                           => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-fpm.html',
            ],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-SUMMARY-ROADRUNNER.md',
        ],

        'summary-fpm' => [
            'title'        => 'Framework Competition — PHP-FPM Summary',
            'subtitle'     => 'The headline numbers from the measured nginx + PHP-FPM deployment: the cost of one pass over every endpoint, the plain routing request, the two data-access races, and what one request costs in memory. End-to-end HTTP over loopback, so the constant webserver overhead is included (see floor-http/floor-php in the dataset). The worker-recycling setting of the pool is stated with the server floor below, because it changes what each row contains.',
            'dataset'      => 'real-deployments',
            'baseline'     => 'azera',
            'mode'         => 'php-fpm',
            'log_scale'    => false,
            'apps'         => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // Same narrowing as the RoadRunner summary: the plain routing race
            // first (the endpoint the dropped startup chart measured), then the
            // two data-access races.
            'charts'       => ['speedup', 'features', 'resident-memory'],
            'feature_keys' => ['routing', 'orm', 'rest-api'],
            'links'        => [
                'The complete report (all six frameworks, every feature chart)' => 'https://sailantis.github.io/azera-competition/benchmarks/',
                'This model, measured end to end'                               => 'https://sailantis.github.io/azera-competition/benchmarks/view-real-fpm.html',
                'The RoadRunner summary'                                        => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-roadrunner.html',
            ],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-SUMMARY-FPM.md',
        ],
    ],
];