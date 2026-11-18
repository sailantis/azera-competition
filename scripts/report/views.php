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
            // All four servers as of the 2026-11-10 run, which measured
            // RoadRunner, nginx/PHP-FPM and BOTH Volucer arms in one pass. The
            // Volucer views read this same file rather than a separate partial
            // run: a second dataset carrying the same apps invites two pages that
            // silently render different numbers from each other.
            'label' => 'Real deployments — RoadRunner + nginx/FPM + Volucer on the benchmark VM (HTTP over loopback)',
            'file'  => $root . '/results/real-deployments.json',
        ],

        // The 0.5.0 release run: nginx/PHP-FPM and Volucer `attach`, all six
        // frameworks, one budget (1000x10), on the same VM as the file above.
        //
        // WHY THIS IS A SECOND DATASET RATHER THAN A SPLICE INTO THE ONE ABOVE.
        // The run behind it measured TWO modes, and `real-deployments` carries
        // four. merge-modes.php splices per mode correctly, but it also replaces
        // env.frameworks for the WHOLE file - so the RoadRunner and volucer-cgi
        // views would then state "Azera v0.5.0" over numbers measured on
        // v0.4.1-5-gf9b2de0. A provenance stamp that names a build the row was
        // not measured on is the one error the version stamp exists to prevent,
        // so the refreshed modes get their own file and the views that read them
        // are pointed at it explicitly. Every remaining view keeps the stamp of
        // the run its numbers actually came from.
        'real-0.5.0' => [
            'label' => 'Real deployments (0.5.0) - nginx/FPM + Volucer attach on the benchmark VM (HTTP over loopback)',
            'file'  => $root . '/results/real-0.5.0.json',
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
            'dataset'   => 'real-0.5.0',
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

        // --- Volucer ------------------------------------------------------
        //
        // Two pages, one per deployment model, published the same way the
        // RoadRunner and FPM pages are. They exist because the two models are the
        // two answers a reader actually chooses between, and because Volucer is a
        // third server implementing them: a model that only ever showed up in
        // RoadRunner and FPM is a claim about those two implementations.
        //
        // The app lists are deliberately ABSENT: render() intersects a view's
        // `apps` with what the dataset actually carries, so naming them here would
        // pin a run's app set into a page that must also render a later, fuller
        // run. An empty intersection would render a page of dashes, which reads as
        // "these frameworks failed" rather than "this run did not measure them".
        'real-volucer-attach' => [
            'title'     => 'Framework Competition — Volucer (attach)',
            'subtitle'  => 'Volucer\'s `attach` backend: the framework boots ONCE, in a supervisor the deployment owns, and every request is served from that already-built state. `deploy/volucer/attach.php` listens on a unix relay and forks a child per connection; volucer dials it. End-to-end HTTP over loopback — includes the constant webserver overhead; framework differences smaller than that floor are below its noise. Single sequential client. Whether a row also carries part of the worker\'s boot depends on the pool\'s max_jobs, which the dataset records.',
            'dataset'   => 'real-0.5.0',
            'baseline'  => 'azera',
            'mode'      => 'volucer-attach',
            'log_scale' => false,
            'charts'    => ['hero', 'speedup', 'features', 'resident-memory'],
        ],

        'real-volucer-cgi' => [
            'title'     => 'Framework Competition — Volucer (spawn_cgi)',
            'subtitle'  => 'Volucer\'s `spawn_cgi` backend: volucer runs the app\'s entry script through a fresh `php-cgi` for every request, so the framework\'s boot is inside the request clock — the same shape as nginx + PHP-FPM, in a different server. The php-cgi pool is reached over a unix socket, not loopback TCP, so this arm differs from the FPM arm by its SERVER and never by its transport. End-to-end HTTP over loopback — includes the constant webserver overhead. Single sequential client.',
            'dataset'   => 'real-deployments',
            'baseline'  => 'azera',
            'mode'      => 'volucer-cgi',
            'log_scale' => false,
            // No 'resident-memory': this model has no resident state to report.
            // Each request gets its own process, so a "retained heap" chart would
            // describe something that does not exist (see the same reasoning on
            // the cold-start view).
            'charts'    => ['hero', 'speedup', 'features'],
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
        // NOT PUBLISHED, and that is a deliberate decision rather than an
        // oversight (2026-11-10): the framework's docs carry the Volucer summary
        // for the resident model and the FPM summary for the per-request one, and
        // RoadRunner remains an ENTRANT in the full report. Publishing a
        // RoadRunner summary beside Volucer's would put the framework's own
        // server tool in the position of comparing itself to a rival in the
        // framework's own docs, which is not a comparison a reader can be
        // expected to read as neutral. The page still exists on the benchmark
        // site, where all four servers are presented as measurements.
        //
        // What keeps this honest: the removal is a `publish` change ONLY. The
        // data stays, so every RoadRunner row is still measured and still appears
        // in the full report — and the Volucer summary's agreement with it stays
        // checkable at https://sailantis.github.io/azera-competition/benchmarks/.
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
                'The same model in Volucer'                                     => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-volucer.html',
                'The PHP-FPM summary'                                           => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-fpm.html',
            ],
        ],

        'summary-fpm' => [
            'title'     => 'Framework Competition — PHP-FPM Summary',
            'subtitle'  => 'The headline numbers from the measured nginx + PHP-FPM deployment: the cost of one pass over every endpoint, the plain routing request, the two data-access races, and what one request costs in memory. End-to-end HTTP over loopback, so the constant webserver overhead is included (see floor-http/floor-php in the dataset). The worker-recycling setting of the pool is stated with the server floor below, because it changes what each row contains.',
            'dataset'   => 'real-0.5.0',
            'baseline'  => 'azera',
            'mode'      => 'php-fpm',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // The plain routing race first (the endpoint the dropped startup
            // chart measured), then the two data-access races.
            'charts'       => ['speedup', 'features', 'resident-memory'],
            'feature_keys' => ['routing', 'orm', 'rest-api'],
            'links'        => [
                'The complete report (all six frameworks, every feature chart)' => 'https://sailantis.github.io/azera-competition/benchmarks/',
                'This model, measured end to end'                               => 'https://sailantis.github.io/azera-competition/benchmarks/view-real-fpm.html',
                'The same work as a resident worker (Volucer)'                  => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-volucer.html',
            ],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-SUMMARY-FPM.md',
        ],

        // The resident model, on the framework's own server tool.
        //
        // This is the page the framework's docs publish for the resident model,
        // so it is also the page that has to stand alone: it no longer sits beside
        // a RoadRunner summary (that page stopped publishing on 2026-11-10), and
        // nothing here should imply a comparison the docs do not carry.
        //
        // The independent confirmation still exists and is still worth pointing at
        // — RoadRunner remains a measured entrant, and its numbers land within
        // 1.8% on azera / 5.4% at worst across the six frameworks. That is what
        // makes the finding about the deployment MODEL rather than about one
        // server, and it is checkable in the full report, which is where a reader
        // who wants to verify a benchmark should end up anyway.
        'summary-volucer' => [
            'title'     => 'Framework Competition — Volucer Summary',
            'subtitle'  => 'The headline numbers from the measured Volucer deployment. Volucer runs the app as a resident worker under a supervisor the deployment owns: the framework boots ONCE, before the supervisor forks, and every request is served from that already-built state. End-to-end HTTP over loopback, so the constant webserver overhead is included.',
            'dataset'   => 'real-0.5.0',
            'baseline'  => 'azera',
            'mode'      => 'volucer-attach',
            'log_scale' => false,
            'apps'      => ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'],
            // Same narrowing as the other summaries, so the pages are read the
            // same way.
            'charts'       => ['speedup', 'features', 'resident-memory'],
            'feature_keys' => ['routing', 'orm', 'rest-api'],
            'links'        => [
                'The complete report (all six frameworks, every deployment model)' => 'https://sailantis.github.io/azera-competition/benchmarks/',
                'This model, measured end to end'                                  => 'https://sailantis.github.io/azera-competition/benchmarks/view-real-volucer-attach.html',
                'The per-request model (PHP-FPM)'                                  => 'https://sailantis.github.io/azera-competition/benchmarks/view-summary-fpm.html',
            ],
            'publish'    => ['framework'],
            'publish_md' => '19-BENCHMARKS-SUMMARY-VOLUCER.md',
        ],
    ],

    // --- Extra pages ------------------------------------------------------
    //
    // A page on this site that is NOT a view over a framework dataset.
    //
    // The view-engine comparison measures template engines, so it has neither
    // the `apps`/`modes`/`benchmark` shape a view reads nor a dataset that
    // ResultStore can ingest. Registering it as a view would drag the framework
    // shapes onto a dataset they do not describe, and reorder the dashboard's
    // exact-ordered card list. It is generated by its own script from its own
    // JSON, and declared here only so the dashboard can LINK to it.
    //
    // `file` is relative to the output directory. The card is emitted only when
    // that file exists there, so a site rendered without
    // `scripts/view-engine-report.php` has no card rather than a dead link —
    // the same rule the dashboard already applies to a view that was not
    // rendered this run.
    'extra_pages' => [
        [
            'file'     => 'view-engine.html',
            'title'    => 'Template engine benchmark',
            'subtitle' => 'Six template engines rendering one identical page. Not the full-stack suite: this isolates the template layer, comparing Clarity and Native against Twig, Blade, Plates and Stempler.',
            // Kept in step with veEngineMeta() in scripts/view-engine-report.php,
            // which is the table the charts themselves draw from. This list is
            // the dashboard card's legend only, so it cannot be read from that
            // file at runtime — but it CAN silently fall behind it, which is
            // what happened here: Stempler was on the chart for a whole round
            // and absent from this card.
            'chips' => [
                ['label' => 'Clarity', 'color' => '#3459e6'],
                ['label' => 'Native', 'color' => '#777bb3'],
                ['label' => 'Twig', 'color' => '#8044db'],
                ['label' => 'Blade', 'color' => '#e5484d'],
                ['label' => 'Plates', 'color' => '#e8590c'],
                ['label' => 'Stempler', 'color' => '#0b7285'],
            ],
        ],
    ],
];