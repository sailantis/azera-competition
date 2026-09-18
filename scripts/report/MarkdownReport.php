<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

use RuntimeException;

/**
 * Renders a view to Markdown with embedded SVG charts.
 *
 * The charts are written to disk as .svg files and referenced with
 * <img src="...">. That is deliberate: GitHub serves the SVG as an image
 * resource, so its presentation attributes survive untouched — whereas inline
 * <style>/class styling inside Markdown HTML gets sanitised away.
 */
final class MarkdownReport
{
    /**
     * Chart keys (`startup`, `feature-orm`, …) this instance actually WROTE.
     *
     * Recorded at the write site rather than discovered by listing $svgDir.
     * Listing was the old behaviour and it was wrong: the directory persists
     * between renders, so a chart that a view has STOPPED drawing keeps
     * sitting there and gets picked up again as if it were current. That is
     * exactly how docs/benchmarks/view-cold-start.html ended up embedding a
     * "Peak memory footprint" figure that cold-start.md never mentioned — an
     * orphan SVG from an older render (commit 707d495) that outlived the
     * chart-list entry that used to produce it. The list below is the
     * authoritative one.
     *
     * @var list<string>
     */
    private array $written = [];

    public function __construct(
        private readonly ResultStore $store,
        private readonly string $viewKey,
        private readonly array $view
    ) {}

    /**
     * Chart keys written by the most recent render(), in write order.
     *
     * Callers MUST use this instead of scanning the output directory: only
     * charts produced by this render are guaranteed to match the Markdown and
     * the dataset it was rendered from.
     *
     * @return list<string>
     */
    public function writtenCharts(): array
    {
        return $this->written;
    }

    /**
     * Write one chart and record that it exists.
     *
     * The single funnel for every SVG this class emits, so writtenCharts()
     * cannot drift from what is on disk: there is no way to write a chart
     * without registering it.
     */
    private function writeSvg(string $dir, string $file, string $svg): void
    {
        file_put_contents($dir . '/' . $file, $svg);
        $this->written[] = basename($file, '.svg');
    }

    /**
     * Build every chart for the view, write the SVGs under $svgDir, and return
     * the Markdown body.
     *
     * @param string $svgRelPrefix path prefix used in the Markdown <img> src
     */
    public function render(string $svgDir, string $svgRelPrefix = 'svg'): string
    {
        if (!is_dir($svgDir) && !mkdir($svgDir, 0777, true) && !is_dir($svgDir)) {
            throw new RuntimeException("Cannot create SVG dir: {$svgDir}");
        }

        // A re-render of the same view must not accumulate chart keys.
        $this->written = [];

        $apps     = array_values(array_intersect($this->view['apps'] ?? $this->store->apps(), $this->store->apps()));
        $baseline = (string) ($this->view['baseline'] ?? $apps[0] ?? '');
        $mode     = (string) ($this->view['mode'] ?? 'warm');
        $logScale = (bool) ($this->view['log_scale'] ?? false);
        $charts   = $this->view['charts'] ?? ['hero', 'speedup', 'features', 'memory'];

        $l = [];
        $l[] = '# ' . ($this->view['title'] ?? 'Benchmark');
        $l[] = '';
        // The cold-start view's headline claim depends on how the dataset was
        // recorded: since cold_boot_included, cold timings carry the boot in
        // the request clock (the FPM story); older datasets timed the request
        // only. Never render a subtitle the numbers cannot support.
        $subtitle = (string) ($this->view['subtitle'] ?? '');
        if (in_array($mode, ['cold', 'php-fpm'], true) && !$this->store->coldBootIncluded()) {
            $subtitle = str_replace(
                'the application boots for every request (harness cold mode, opcache retained).',
                'the application boots for every request (harness cold mode, opcache retained) — boot is timed separately, the request numbers show post-boot work only.',
                $subtitle
            );
        }
        $l[] = $subtitle;
        $l[] = '';
        $l[] = $this->envBlock($mode);
        $l[] = '';

        foreach ($charts as $chart) {
            $lines = $this->chart($chart, $svgDir, $svgRelPrefix, $apps, $baseline, $mode, $logScale);
            if ($lines !== null) {
                $l[] = $lines;
                $l[] = '';
            }
        }

        // Shared tables so both outputs agree on the numbers.
        $tables     = new Tables($this->store);
        $realServer = $this->store->floors() !== [];
        $l[] = '## Latency by endpoint';
        $l[] = '';
        $l[] = 'Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. '
            . ($this->store->hasBoot()
                ? 'Every number is END-TO-END per-request occupancy for the view\'s deployment model: the '
                    . 'framework boot of that model is part of the cell, not parked in a separate chart. '
                : '')
            . ($realServer
                ? 'These are REAL deployments measured over HTTP: every row carries the constant server '
                    . 'cost, which is why the values cluster — the floor note below states what stands '
                    . 'under them. '
                : '')
            . 'The workload column states what each request reads or writes. Every framework runs the '
            . 'same seeded database and the same page size, so the payload is identical no matter which '
            . 'framework served it; the workload column is the part of the suite that varies.'
            . ($this->store->hasCleanupSplit()
                ? "\n\nEach cell also shows the request's lifecycle split as `total <sub>boot + handle + cleanup</sub>` — "
                    . 'the terms sum to the headline. Cleanup is the post-response teardown a worker performs '
                    . 'between requests (terminate() finalizers, request-scoped resets); handle is the dispatch '
                    . 'itself; boot is the framework startup that request waits for in this deployment model.'
                : '')
            . $this->bootLegend($mode)
            . $this->floorNote($mode);
        $l[] = '';
        $l[] = $tables->latencyMarkdown($mode, $apps);
        $l[] = '';

        $l[] = '---';
        $l[] = '';
        $l[] = '> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:';
        $l[] = '> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --warm --cold --seed --out=results/free-for-all-opcache-iso`';
        $l[] = '> then `php scripts/derive-fpm.php results/free-for-all-opcache-iso` and `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.';
        $l[] = '';
        $l[] = 'Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.';
        $l[] = '';

        return implode("\n", $l);
    }

    private function envBlock(string $mode): string
    {
        $env = $this->store->env();
        // The measured rows record their own budget (http-bench.php writes
        // iterations_per_run/runs), so every caption states what the rows
        // behind THIS chart actually used — a constant is the one value that
        // can contradict a re-run with a different budget. Both servers are
        // measured at the same sample; assemble-real.php and merge-app.php
        // both refuse a dataset where they disagree, so a mode-specific
        // mismatch should no longer be reachable.
        $budget = $this->store->budgetLabelFor($mode)
            ?? $this->store->budgetLabel()
                ?? 'multiple runs';

        return sprintf(
            "**Environment** — PHP %s · %s · OPcache (CLI): %s · %s, lower is better.\n\n_Measured %s%s_",
            $env['php_version'] ?? '?',
            $env['os'] ?? '?',
            !empty($env['opcache']) ? 'yes' : 'no',
            $budget,
            $env['timestamp'] ?? '?',
            isset($env['azera_framework_ref']) ? ' · azera-framework `' . $env['azera_framework_ref'] . '`' : ''
        );
    }

    /**
     * @param list<string> $apps
     */
    private function chart(
        string $chart,
        string $svgDir,
        string $rel,
        array $apps,
        string $baseline,
        string $mode,
        bool $logScale
    ): ?string {
        return match ($chart) {
            'hero'            => $this->hero($svgDir, $rel, $apps, $mode, $logScale),
            'speedup'         => $this->speedup($svgDir, $rel, $apps, $baseline, $mode),
            'features'        => $this->features($svgDir, $rel, $apps, $mode, $logScale),
            'memory'          => $this->memory($svgDir, $rel, $apps, $mode),
            'resident-memory' => $this->residentMemory($svgDir, $rel, $apps, $mode),
            default           => null
        };
    }

    /**
     * Caption for the latency charts. The lower bound of the whisker is only
     * labelled "fastest observation" when the dataset actually carries a
     * per-request minimum; otherwise it is honestly called what it is
     * (median to p95). Charts plot boot-inclusive occupancy, so the caption
     * names the boot the mode adds.
     */
    private function spreadCaption(string $mode): string
    {
        $base = $this->store->hasMin()
            ? 'bar = median · dot = median · whisker = fastest observation → p95 (ms)'
            : 'bar = median · dot = median · whisker = median → p95 (ms)';
        $boot = $this->bootPerPoint($mode);
        return $boot === '' ? $base : $base . ' · ' . $boot;
    }

    /**
     * One-line note on the boot each dot carries, for the mode being drawn:
     * the FPM rebuild in cold mode (already inside the measured request), the
     * worker recycle in warm mode (added on top of the measured request).
     *
     * The sentence states the MODEL, never the recycle's duration — that
     * number is the startup chart's, and copying it here would be a figure no
     * re-measure can update.
     */
    private function bootPerPoint(string $mode): string
    {
        if (!$this->store->hasBoot()) {
            return '';
        }
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            return $this->store->coldBootIncluded()
                ? 'boot included (fresh per-request rebuild)'
                : '';
        }
        // A pool that never recycles puts no boot on a dot at all, so the
        // note must not claim one — the boot it would refer to is the one the
        // startup chart measures, not a term inside these points.
        if ($this->store->rrRecycleModel() === 'never') {
            return 'worker never recycled (max_jobs=0) — no boot inside these points';
        }
        return 'boot included (worker recycle, sized in the startup chart)';
    }

    /**
     * Real-deployment datasets measure a REAL server, so every headline carries
     * a constant cost that has nothing to do with the framework — the floor-*
     * probes in the dataset state it explicitly:
     *
     *   FPM  — the pool does NOT recycle its worker (pm.max_requests=0), so
     *          no process is spawned per request; what remains is the FastCGI
     *          handshake plus running a minimal script. Because that floor no
     *          longer includes a worker spawn, the framework's own per-request
     *          boot is the visible signal on this axis: a real-FPM row is the
     *          framework building itself again for every request.
     *   RR   — requests cross a socket to a resident worker, so a bare worker
     *          (floor-rr, ~0.2 ms) is the IPC + server floor.
     *
     * Without this line the FPM rows read as if the framework itself cost
     * 10 ms. Empty for every in-process dataset (no `floors` key).
     */
    private function floorNote(string $mode): string
    {
        $floors = $this->store->floors();
        if ($floors === []) {
            return '';
        }
        $ms = static fn(string $key): ?float => $floors[$key]['ms'] ?? null;

        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            $php = $ms('floor-php');
            if ($php === null) {
                return '';
            }
            // Describe the deployment model that was ACTUALLY measured. A
            // dataset from a recycled pool (max_requests=1) has a per-request
            // process spawn inside its floor; a persistent pool
            // (max_requests=0) does not. Claiming the wrong one misattributes
            // ~9 ms per row, so branch on the stamp.
            $maxReqs = $this->store->fpmMaxRequests();
            $model   = match (true) {
                $maxReqs === 1 => 'the pool destroys the worker after every request, so each row '
                    . 'includes a fresh process spawn plus the FastCGI handshake',
                $maxReqs === 0 => 'the pool never recycles its worker, so no process is spawned '
                    . 'per request — what remains is the FastCGI handshake plus a minimal script',
                default => 'this dataset does not record whether the pool recycled its worker, so the '
                    . 'per-request process-spawn share of this floor is unknown'
            };
            // Only a known-recycled pool lets us subtract a spawn; the other
            // two cases must not imply we know where the floor comes from.
            $advice = match ($maxReqs) {
                1 => 'Subtracting that floor leaves the framework\'s own per-request boot — what it costs '
                    . 'to build itself again for every request.',
                0 => 'Subtracting that floor leaves the framework\'s own per-request boot, which FPM still '
                    . 'pays for every request even though its worker survives.',
                default => 'Subtracting it leaves the framework\'s own per-request boot, but how much of '
                    . 'this floor is a process spawn cannot be recovered from the dataset.'
            };

            // The floor's own VALUES deliberately stay out of this sentence:
            // the `floor-php` and `floor-http` rows in the dataset carry them,
            // and a figure repeated in prose is the one thing a re-run cannot
            // update. The note explains what the floor IS and where to read it.
            return "\n\n**Server floor** — measured nginx + PHP-FPM with `pm.max_requests="
                . ($maxReqs ?? '?') . "`: " . $model
                . '. A hello-world endpoint that boots nothing but PHP (`floor-php`) and a static file '
                . 'through nginx (`floor-http`) measure exactly that cost — the floor every row below '
                . 'stands on. ' . $advice;
        }

        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            $rr = $ms('floor-rr');
            if ($rr === null) {
                return '';
            }
            return "\n\n**Server floor** — real RoadRunner over loopback: a bare resident worker that renders a "
                . 'fixed string (`floor-rr`) measures the IPC + server floor every row below also pays. '
                . 'Only differences larger than this floor are framework differences.';
        }

        return '';
    }

    /**
     * Per-mode note on where each row's boot comes from, printed under the
     * latency table. Both deployment models now put the boot INTO the row, so
     * a cell is the whole time one request occupies or blocks the worker:
     *   cold/php-fpm — the boot is already inside the measured request (each
     *     fork-per-iteration child boots in its own request clock), so the
     *     headline is what a user waits for end-to-end.
     *
     *   warm/roadrunner — the rows measure post-boot work only, so the
     *     worker's recycle cost is ADDED to every row and to every chart
     *     point. That is what the deployment pays to bring a worker back
     *     between requests, and it is the only way a warm cell can answer
     *     "how long is this worker busy with one request" when a pool is
     *     recycled or requests queue behind it.
     */
    private function bootLegend(string $mode): string
    {
        if (!$this->store->hasBoot()) {
            return '';
        }
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            if (!$this->store->coldBootIncluded()) {
                return '';
            }
            return "\n\nThis dataset times cold requests END-TO-END: every iteration pays a fresh framework boot "
                . 'inside the request clock, exactly like a real PHP-FPM worker building the app before serving. '
                . 'The boot share is therefore inside both the headline and the `boot` term of the sub-line — '
                . 'the totals here are the numbers a user waits for.';
        }
        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            // The boot each row carries is the pool's recycle divided by its
            // max_jobs, so the sentence has to follow the stamped model — the
            // recycle is a different quantity once the pool recycles on a
            // schedule (or never). The recycle's own DURATION is deliberately
            // not quoted here: the startup chart measures it directly, and a
            // figure repeated in prose is the one thing a re-run cannot update.
            return match ($this->store->rrRecycleModel()) {
                'never' => "\n\nThese rows are end-to-end for a resident worker with a pool that never "
                    . 'recycles it (max_jobs=0): the worker booted once before the first request and '
                    . 'serves the whole run, so a cell is the measured request itself and carries no '
                    . 'boot. The one-time recycle cost is measured in the startup chart — read that when '
                    . 'sizing a pool that is recycled or restarted, or when requests queue behind one worker.',
                'every' => "\n\nThese rows are end-to-end for a resident worker whose pool recycles it "
                    . 'every ' . $this->store->rrMaxJobs() . ' jobs: the recycle is divided across those '
                    . 'jobs, so a cell is the measured request plus its share of the boot.',
                default => "\n\nThese rows are end-to-end for a resident worker: every headline and every chart "
                    . 'point adds the worker\'s boot (warm recycle, timed in the startup chart) to the '
                    . 'measured request, so a cell is the time one request keeps that worker busy — the '
                    . 'number to read when workers are recycled per request or requests queue behind one '
                    . 'pool. This dataset does not record the pool\'s max_jobs, so the boot is charged per '
                    . 'request.'
            };
        }
        return '';
    }

    /**
     * @param list<string> $apps
     */
    /**
     * Framework startup = the framework's real load cost, measured directly
     * by the harness (run-app.php's measureBoot), now in THREE bands:
     *
     *   1. Cold boot — the very first bootstrap() in a fresh PHP process:
     *      autoloader + classes/opcache compile + FS cache. What a CLI run,
     *      CGI request, or freshly spawned FPM worker pays ONCE.
     *   2. FPM per-request rebuild — the boot share the harness times inside
     *      every cold request (fork-per-iteration): a genuinely fresh child
     *      process rebuilding the app (container, routes, DB connect) on top
     *      of opcache bytecode inherited from the parent via copy-on-write —
     *      the same inheritance a real FPM master→worker fork gets. The
     *      "virtual cold boot" a recycled FPM worker pays per request.
     *   3. Warm recycle — a re-bootstrap with everything already loaded;
     *      what a RoadRunner-style worker pays per recycle. Guarded no-op
     *      re-boots (CodeIgniter/CakePHP state resets) read ~0 here.
     *
     * Each band shows boot + the mode's median per-request teardown: both
     * block the worker while no request is being served, so they are the
     * same kind of time and belong together.
     *
     * Datasets recorded before boot was timed have no per-boot numbers; the
     * chart then degrades to the legacy proxy (warm GET / dispatch) so old
     * result files keep rendering.
     *
     * @param list<string> $apps
     */
    private function hero(string $dir, string $rel, array $apps, string $mode, bool $logScale): string
    {
        $hasBoot = $this->store->hasBoot();
        if ($hasBoot) {
            return $this->bootChart($dir, $rel, $apps, $logScale, $mode);
        }
        return $this->legacyStartup($dir, $rel, $apps, $mode, $logScale);
    }

    /**
     * Cold + FPM-rebuild + warm bootstrap cost, one band each, per-band
     * anchored at the fastest framework on that band. Bands carry boot +
     * median teardown — both block the worker between requests.
     *
     * @param list<string> $apps
     */
    private function bootChart(string $dir, string $rel, array $apps, bool $logScale, string $mode): string
    {
        $metrics  = [];
        $colors   = [];
        $coldMode = $this->store->coldModeName() ?? 'cold';
        $probed   = $this->store->isProbedBoot();

        // REAL datasets (boot-probe) get exactly ONE band: a real deployment
        // has one boot kind, decided by the server measured — not the CLI
        // harness's three modes. The band is chosen from THIS view's mode:
        //
        //   php-fpm    -> the entry script re-runs per request, so the probe
        //                 measures the boot each request waits for.
        //   roadrunner -> the worker re-inits the framework in an already-warm
        //                 process N times, so the probe measures the cycle
        //                 reset: opcache and every class are loaded, only the
        //                 kernel/container is rebuilt. The process-start cost
        //                 is the server supervisor's, not the framework's, and
        //                 is deliberately NOT the reported number.
        //
        // Without this branch a real dataset would be drawn with the CLI band
        // labels ("FPM rebuild — per-request boot on shared opcache", "Warm
        // recycle — resident worker"), which describe things that were never
        // measured here.
        if ($probed) {
            $bands = ($mode === 'roadrunner')
                ? ['warm' => ['label' => 'Warm recycle — framework re-init in a resident worker (RoadRunner)', 'key' => 'warm_ms']]
                : ['cold' => ['label' => 'Framework boot — per request (PHP-FPM)', 'key' => 'cold_ms']];
        } else {
            $bands = [
                'cold' => ['label' => 'Cold boot — real, first boot in a process (CGI/CLI model)', 'key' => 'cold_ms'],
            ];
            // The FPM band only exists when the cold rows carry their
            // per-request boot share (fork-mode datasets). On the derived
            // deployments file the cold side is named 'php-fpm'.
            if ($this->store->fpmBootMedian($apps[0] ?? '') !== null || $this->store->fpmBootMedian($apps[1] ?? '') !== null) {
                $bands['fpm'] = ['label' => 'FPM rebuild — per-request boot on shared opcache', 'key' => 'fpm_boot'];
            }
            $bands['warm'] = ['label' => 'Warm recycle — resident worker (RoadRunner model)', 'key' => 'warm_ms'];
        }

        foreach ($bands as $band => $cfg) {
            foreach ($apps as $app) {
                // The FPM band reads its per-request boot_ms from the cold
                // rows; the other bands read measureBoot()'s direct timings.
                if ($cfg['key'] === 'fpm_boot') {
                    $fpmBoot = $this->store->fpmBootMedian($app);
                    if ($fpmBoot === null) {
                        continue;
                    }
                    $bootVal = $fpmBoot;
                } else {
                    $boot = $this->store->boot($app, $probed ? $mode : null);
                    if ($boot === null) {
                        continue;
                    }
                    $bootVal = $boot[$cfg['key']];
                }
                // Boot and teardown are the same kind of time — the worker
                // cannot serve another request during either — so each band
                // shows their sum: boot cost + the cold side's median
                // per-request cleanup. On a REAL dataset the probe already
                // timed the whole "PHP start → framework ready" span inside
                // the request, and teardown is a separate phase the probe
                // never covered, so adding a cleanup median measured by a
                // different harness would invent a number rather than report
                // one. The probe's own measurement stands alone there.
                $cleanup = $probed ? 0.0 : ($this->store->cleanupMedian($app, $coldMode) ?? 0.0);
                $total   = $bootVal + $cleanup;
                $label   = BenchmarkConfig::appLabel($app);
                $metrics[$band][$label] = [
                    'median' => $total,
                    'low'    => $total,
                    'high'   => $total,
                ];
                $colors[$label] = BenchmarkConfig::appColor($app);
            }
        }
        $cats = [];
        foreach ($metrics as $band => $bySeries) {
            if (count($bySeries) >= 2) {
                $cats[] = $band;
            }
        }
        if ($cats === []) {
            return '';
        }

        // The chart is keyed by the band LABEL (it becomes the band heading
        // inside the SVG); $metrics stays keyed by band key for the prose.
        $chartMetrics = [];
        $chartCats    = [];
        foreach ($cats as $band) {
            $chartMetrics[$bands[$band]['label']] = $metrics[$band];
            $chartCats[] = $bands[$band]['label'];
        }

        // Per-band anchor at the fastest framework on that band (same
        // convention as the feature charts). One wrinkle: some re-boots are
        // guarded no-ops (CodeIgniter/CakePHP reset state only — classes are
        // already loaded; azera re-boots in ~0.001 ms once opcache holds the
        // bytecode). Anchoring such a band at a no-op would print absurd
        // "x 3000+" factors, so the anchor is the fastest BOOT THAT ACTUALLY
        // WORKS (>= 0.1 ms) and the no-op rows get NO factor at all — with
        // $labelAllFactors they stay unlabelled (their ~0 ms median says
        // everything), while the anchor row reads "x 1.0" because it does
        // not lead the band there and the reader could not otherwise tell
        // which boot the other rows are measured against.
        $factors = [];
        foreach ($cats as $band) {
            $working = [];
            foreach ($metrics[$band] as $label => $m) {
                if ($m['median'] >= 0.1) {
                    $working[$label] = $m['median'];
                }
            }
            if ($working === []) {
                continue; // every row in this band is a no-op — nothing to anchor
            }
            $fastest = min($working);
            foreach ($working as $label => $median) {
                $factors[$bands[$band]['label']][$label] = $median / max($fastest, 1e-9);
            }
        }

        $svg = SvgChart::dotRange(
            $chartCats,
            $chartMetrics,
            $colors,
            'ms',
            $logScale,
            960,
            360,
            $probed ? 'Framework startup — boot' : 'Framework startup — boot + teardown',
            $probed
                ? 'PHP start → framework ready, measured in the running deployment'
                : 'boot + median per-request teardown — both block the worker between requests',
            $factors,
            $probed
                ? 'x = median ÷ the fastest boot on this server'
                : 'x = median ÷ the fastest working boot of that kind (no-op re-boots excluded)',
            true
        );
        $file = 'startup.svg';
        $this->writeSvg($dir, $file, $svg);

        // --- Real-deployment prose -------------------------------------------
        // A probed dataset has ONE band, so the three-model story below does
        // not apply: there is no "cold vs FPM vs warm" contrast to narrate,
        // only the measured spread across frameworks on the server that was
        // actually run.
        if ($probed) {
            $race = $metrics[array_key_first($bands)] ?? [];
            if ($race === []) {
                return '';
            }

            $intro = ($mode === 'roadrunner')
                ? 'Time to re-initialise the framework in an already-warm RoadRunner worker — the cycle reset a '
                    . 'recycled worker pays, with opcache and every class already loaded. This is NOT process start: '
                    . 'spawning the process is the server supervisor\'s cost, not the framework\'s.'
                : 'Time from PHP start until the framework is ready to serve, measured inside the FPM entry '
                    . 'script — the boot every request waits for on this deployment, because nginx/PHP-FPM '
                    . 're-runs the entry script per request.';

            // The anchor must be the fastest boot that actually REBUILDS
            // something, exactly as the chart's factor logic does. Several
            // frameworks memoise their re-bootstrap (azera returns its wired
            // AppContext, CodeIgniter/CakePHP reset state only) and land at a
            // few microseconds — 0.001 ms. Anchoring on one of those prints
            // "x 1000+ slower" for a legitimate redesign, which reads as a
            // broken measurement rather than a design difference. The CHART
            // partitions them and leaves them out of its factor column; the
            // prose states the rule rather than reprinting the readings.
            $rebuilt = [];
            $noop    = [];
            foreach ($race as $label => $m) {
                if ($m['median'] >= 0.1) {
                    $rebuilt[$label] = $m['median'];
                } else {
                    $noop[$label] = $m['median'];
                }
            }
            asort($rebuilt);

            // Every figure lives in the chart; the prose explains what the
            // chart's reading MEANS. A sentence that reprints medians, minima
            // and p95s is a second copy of the chart that a re-measure can
            // leave behind, so the section states the measurement model and
            // the no-op rule only.
            $rebuiltNote = $rebuilt !== []
                ? '- The fastest and slowest framework on this band are named by the chart below; the '
                    . "multiplier beside each row states how many times the fastest boot it needed.\n"
                : '- Every framework re-bootstraps in microseconds on this deployment — the wiring '
                    . "survives the recycle, so the band measures no meaningful rebuild cost.\n";
            $noopNote = $rebuilt !== [] && $noop !== []
                ? '- ' . implode(', ', array_keys($noop))
                    . ($mode === 'roadrunner'
                        ? ' keep their compiled wiring across a recycle and hand it straight back, so '
                            . 'there is no rebuild to time.'
                        : ' re-run the entry script but had nothing left to build.')
                    . " They are left out of the chart's factor column.\n"
                : '';

            return "## Framework startup GET /\n\n"
                . $intro . "\n\n"
                . $rebuiltNote
                . $noopNote
                . "- Each framework's boot is reduced to one statistic (median of the probe samples), "
                . "and the samples per framework are recorded in the dataset.\n"
                . "\n"
                . '![Framework startup — boot](' . $rel . '/' . $file . ')';
        }

        // Prose: the cold race (first boot is what a deploy/server start pays),
        // plus the FPM-rebuild story when that band exists. Every figure lives
        // in the startup chart, which draws all bands on one shared axis and
        // prints each row's own reading; the prose describes the MODELS only,
        // so a re-measure can never leave a stale number behind in a sentence.
        $cold = $metrics['cold'] ?? [];
        if ($cold === []) {
            return '';
        }
        $fpm = $metrics['fpm'] ?? [];

        // Per-request teardown share, when the dataset carries the split.
        //
        // ONE static sentence, not a computed range: the numbers used to be
        // printed here, and a share that collapsed to "0% up to 0%" read as a
        // bug rather than a finding. The per-cell sub-line in the table below
        // already states each row's own split, so the prose only has to say
        // what the term IS and where the real figures live.
        $cleanupSentence = $this->store->hasCleanupSplit()
            ? "\nPost-response teardown is the third term of each row's sub-line below — the work a worker "
                . 'does between requests (terminate() finalizers, request-scoped resets) and the smallest of '
                . 'the three terms in every row here.'
            : '';

        return "## Framework startup GET /\n\n"
            . "Three boot models, timed directly by the harness — each band shows boot + median teardown, because "
            . "during both the worker cannot serve another request:\n\n"
            . '- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): '
            . "what a CLI run, CGI request, or newly spawned worker pays once. This is the most expensive band, "
            . "and the chart's multiplier states by how much.\n"
            . ($fpm !== [] && $cold !== []
                ? '- **FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache '
                    . 'bytecode with the master, it only rebuilds the application (container, routes, DB '
                    . 'connect). The gap between the cold and FPM bands is the one-time compile cost shared '
                    . "bytecode removes.\n"
                : '')
            . '- **Warm recycle** — worker restart with opcache warm: a re-bootstrap with every class already '
            . "loaded. CodeIgniter's and CakePHP's re-bootstrap is a state reset there, not a kernel rebuild, "
            . "so they read near zero.\n"
            . $cleanupSentence
            . "\n\n"
            . '![Framework startup — boot + teardown](' . $rel . '/' . $file . ')';
    }

    /**
     * Legacy fallback: warm GET / dispatch as a startup proxy. Kept for
     * datasets recorded before run-app.php timed bootstrap() directly.
     *
     * @param list<string> $apps
     */
    private function legacyStartup(string $dir, string $rel, array $apps, string $mode, bool $logScale): string
    {
        $startup = 'GET /';
        $metrics = [];
        $colors  = [];
        $medians = [];
        foreach ($apps as $app) {
            $spread = $this->store->spread($app, $mode, $startup);
            if ($spread === null) {
                continue;
            }
            $label = BenchmarkConfig::appLabel($app);
            $metrics[$label] = $spread;
            $colors[$label] = BenchmarkConfig::appColor($app);
            $medians[$label] = $spread['median'];
        }
        if (count($metrics) < 2) {
            return '';
        }
        // Anchor the factor labels at the fastest framework on this endpoint,
        // so the winner reads 1.0× and every other row states how many times
        // longer it took. Built from the medians, not the trimmed means, so the
        // number agrees with the dot it stands behind.
        $fastest = min($medians);
        $factors = [];
        foreach ($medians as $label => $median) {
            $factors[$label] = $median / max($fastest, 1e-9);
        }
        $svg = SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'ms',
            $logScale,
            960,
            0,
            'Framework startup — GET /',
            $this->spreadCaption($mode),
            $factors,
            'x = median ÷ fastest (Azera = 1.0)'
        );
        $file = 'startup.svg';
        $this->writeSvg($dir, $file, $svg);

        // The chart prints every framework's own median, its spread and its
        // multiplier, so the prose states only WHAT this endpoint measures.
        // Reprinting the winner's number here would be a second copy of the
        // chart that a re-measure cannot keep in sync.
        return "## Framework startup GET /\n\n"
            . 'Router + dispatcher + plain response, no database. This endpoint measures pure framework '
            . 'bootstrap and dispatch cost, with no ORM or template work to hide behind — the chart '
            . 'names the fastest framework and how many times longer each other one took.'
            . "\n\n"
            . '![Framework startup — GET /](' . $rel . '/' . $file . ')';
    }

    /**
     * "× the baseline's total time" plot, drawn with the same dot-and-range
     * mark as every other time chart. Each framework's total is the sum of
     * its per-endpoint spreads over the common endpoints — the dot is the
     * sum of medians, the whisker spans sum-of-fastest → sum-of-p95 — so the
     * chart's numbers decompose into exactly the latencies the table prints
     * instead of being a second, unverifiable aggregate. Like every latency
     * chart, the spreads are boot-inclusive per-request occupancy.
     *
     * @param list<string> $apps
     */
    private function speedup(string $dir, string $rel, array $apps, string $baseline, string $mode): string
    {
        $requests = $this->store->commonRequests($mode, $apps);

        $metrics = [];
        $colors  = [];
        foreach ($apps as $app) {
            $total = ['median' => 0.0, 'low' => 0.0, 'high' => 0.0];
            foreach ($requests as $req) {
                $spread = $this->store->spread($app, $mode, $req);
                if ($spread === null) {
                    $total = null;
                    break;
                }
                $total['median'] += $spread['median'];
                $total['low'] += $spread['low'];
                $total['high'] += $spread['high'];
            }
            if ($total === null) {
                continue; // only frameworks that measured every endpoint take part
            }
            $label = BenchmarkConfig::appLabel($app);
            $metrics[$label] = $total;
            $colors[$label] = BenchmarkConfig::appColor($app);
        }
        $base      = BenchmarkConfig::appLabel($baseline);
        $baseTotal = $metrics[$base]['median'] ?? 0.0;
        if (count($metrics) < 2 || $baseTotal <= 0) {
            return '';
        }

        // The baseline is pinned at exactly 1.0× and every other row states
        // how many of the baseline's own totals it needed — the same
        // scale-free reading the chart had as a bar chart, now carried by
        // the dot the factor label stands behind.
        $factors = [];
        foreach ($metrics as $label => $m) {
            $factors[$label] = $m['median'] / max($baseTotal, 1e-9);
        }
        $svg = SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'ms',
            false,
            960,
            0,
            'Total response times',
            'dot = sum of the ' . count($requests) . ' endpoint medians · whisker = sum of fastest → sum of p95 (ms)',
            $factors,
            "x = total ÷ {$base}'s total · 1.0 = {$base}",
            false,
            'total'
        );
        $file = 'speedup.svg';
        $this->writeSvg($dir, $file, $svg);

        // The chart ranks every framework on the shared axis, names its own
        // reference in the caption and prints each row's multiplier, so the
        // prose states only what the total IS. Naming the baseline — or
        // reprinting the closest rival — would put a view setting and a reading
        // into a sentence that no re-measure regenerates.
        return "## Total response times\n\n"
            . 'Total time to serve one pass over every benchmarked endpoint — each framework\'s sum of its '
            . 'endpoint medians, not a single response time — drawn relative to the baseline, so a row '
            . 'states how many times the baseline\'s own total it needed. Each endpoint\'s median is '
            . 'boot-inclusive occupancy for this view\'s deployment model, so the total is the worker time '
            . 'one pass over every endpoint costs. The chart orders the frameworks by that total and prints '
            . "each one's multiplier beside its row.\n\n"
            . '![Total response times](' . $rel . '/' . $file . ')';
    }

    /**
     * @param list<string> $apps
     */
    private function features(string $dir, string $rel, array $apps, string $mode, bool $logScale): string
    {
        $sections = [];
        $charts   = [];
        foreach (BenchmarkConfig::featureOrder() as $feature) {
            $reqs = $this->store->requestsForFeature($feature, $mode);
            if (count($reqs) === 0) {
                continue;
            }
            $participants = [];
            foreach ($apps as $app) {
                if ($this->store->supports($app, $feature)) {
                    $participants[] = $app;
                }
            }
            if (count($participants) < 2) {
                continue;
            }

            // Only requests where at least two of the participants measured.
            $cats    = [];
            $metrics = [];
            $colors  = [];
            $labels  = [];
            $factors = [];
            foreach ($reqs as $req) {
                $measured = [];
                foreach ($participants as $app) {
                    $spread = $this->store->spread($app, $mode, $req);
                    if ($spread !== null) {
                        $measured[$app] = $spread;
                    }
                }
                if (count($measured) < 2) {
                    continue;
                }
                $cats[] = $req;
                // Each request band gets its own anchor: the fastest framework
                // on *that* endpoint reads 1.0×. A chart spanning several
                // endpoints therefore shows who wins each race, which a single
                // chart-wide anchor would hide.
                $fastest = null;
                foreach ($measured as $spread) {
                    if ($fastest === null || $spread['median'] < $fastest) {
                        $fastest = $spread['median'];
                    }
                }
                foreach ($measured as $app => $spread) {
                    $label = BenchmarkConfig::appLabel($app);
                    // Grouped by display label so the shared series list works
                    // across every request in this feature.
                    $metrics[$req][$label] = $spread;
                    $colors[$label] = BenchmarkConfig::appColor($app);
                    $labels[$label] = $label;
                    $factors[$req][$label] = $spread['median'] / max((float) $fastest, 1e-9);
                }
            }
            if ($cats === []) {
                continue;
            }

            $title = BenchmarkConfig::featureLabel($feature);
            $svg   = SvgChart::dotRange(
                $cats,
                $metrics,
                $colors,
                'ms',
                $logScale,
                960,
                0,
                $title,
                $this->spreadCaption($mode),
                $factors,
                'x = median ÷ the fastest on that endpoint'
            );
            $file = 'feature-' . $feature . '.svg';
            $this->writeSvg($dir, $file, $svg);
            $charts[] = "### {$title}\n\n![{$title}](" . $rel . '/' . $file . ')';

            // The feature's own WORKLOAD, stated once as static text. Every
            // measured number — the winner, its median and the margin over the
            // runner-up — is drawn in the chart below, which anchors each
            // endpoint at its own fastest framework and prints the multiplier
            // beside every row. A prose copy of those readings is the thing a
            // re-measure leaves behind, so this section names no figures.
            $what = BenchmarkConfig::featureDescriptionFor($feature);
            $sections[] = '- **' . $title . '** (`' . $cats[0] . '`) — '
                . ($what !== '' ? $what : 'runs the feature\'s request against every framework that supports it.');
        }

        if ($charts === []) {
            return '';
        }

        return "## Feature benchmarks\n\n"
            . "One race per framework feature, each run as a real request against a real database. "
            . "Every figure — the winner of each race and the margin over the runner-up — is in that "
            . "feature's own chart below, which anchors each endpoint at its fastest framework.\n\n"
            . implode("\n", $sections) . "\n\n" . implode("\n\n", $charts);
    }

    /**
     * @param list<string> $apps
     */
    private function memory(string $dir, string $rel, array $apps, string $mode): string
    {
        $metrics = [];
        $colors  = [];
        foreach ($apps as $app) {
            $range = $this->store->peakMemRange($app, $mode);
            if ($range === null) {
                continue;
            }
            $label = BenchmarkConfig::appLabel($app);
            $metrics[$label] = [
                'low'    => $range['low'] / 1048576,
                'median' => $range['median'] / 1048576,
                'high'   => $range['high'] / 1048576,
            ];
            $colors[$label] = BenchmarkConfig::appColor($app);
        }
        if (count($metrics) < 2) {
            return '';
        }
        // The lightest footprint on a typical endpoint is the baseline: it
        // reads 1.0× and the rest state how many times more memory they need.
        $lightest = min(array_column($metrics, 'median'));
        $factors  = [];
        foreach ($metrics as $label => $m) {
            $factors[$label] = $m['median'] / max($lightest, 1e-9);
        }
        $svg = SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'MB',
            false,
            960,
            0,
            'Peak memory footprint',
            'bar = median · dot = median endpoint · whisker = lightest → heaviest endpoint (MB)',
            $factors,
            'x = median ÷ the lightest framework'
        );
        $file = 'memory.svg';
        $this->writeSvg($dir, $file, $svg);

        return "## Peak memory\n\n"
            . 'Peak memory reached on any endpoint, in MB. Each row is one framework: the **bar** and '
            . 'the **dot** are its median endpoint, the **whisker** spans its lightest to its heaviest '
            . 'endpoint, and the multiplier beside the row states how many times the lightest '
            . "framework's median it needed.\n\n"
            . '![Peak memory footprint](' . $rel . '/' . $file . ')';
    }

    /**
     * Resident-worker memory from the opt-in RoadRunner probe (see
     * deploy/rr/worker.php and scripts/bench-lib.php).
     *
     * This is a DIFFERENT measurement from the peak_mem chart above, and the
     * difference is the reason both exist:
     *
     *   peak_mem       — per-process allocator high-water mark from
     *                    memory_get_peak_usage(true). Quantised to 2 MiB
     *                    chunks, and in a recycled-worker harness it is a
     *                    fresh process per endpoint.
     *   resident heap  — exact PHP heap (memory_get_usage(false)) read from
     *                    inside the RESIDENT worker, after the request. This
     *                    is the only number that says what a long-lived
     *                    process still holds.
     *
     * ONE axis, each framework drawn as a range whose two ends answer the two
     * questions a single bar would conflate: the left cap is the heap with the
     * app booted and no request served (a footprint — endpoint-independent, so
     * legitimately rankable), the dot is the heap once the last endpoint has
     * been served, and the right cap is the largest heap any endpoint reached.
     * The dot is always BETWEEN the caps because the end state is bounded by
     * them — it marks where the run stopped, and it is not a free third
     * position.
     *
     * The end state and the peak are endpoint-ORDER dependent (the probe fires
     * once per endpoint and reads the whole heap), so neither is a per-request
     * cost — none of the three is.
     *
     * THE RANKING KEY IS THE DOT — see residentWorkerMemory(), which owns the
     * reasoning and does the sorting. This docblock used to assert that 'only
     * the left cap ranks frameworks' because it is endpoint-INDEPENDENT; that
     * was self-consistent but it ordered the table by a number the page is not
     * about, and it put the row order at odds with the multiplier printed
     * beside each row. The distinction it was reaching for is still real and
     * still worth keeping: a mark's SUITABILITY as a ranking key (is it a
     * property of the worker, or of the endpoint order?) is a different
     * question from which mark the page is ABOUT. On this page the answer to
     * the second is the dot, and the reasoning is there rather than here so
     * the two cannot drift apart.
     *
     * @param list<string> $apps
     */
    private function residentMemory(string $dir, string $rel, array $apps, string $mode): string
    {
        if (!$this->store->hasResidentMem($mode)) {
            return '';
        }

        // TWO DIFFERENT QUESTIONS, so two different charts — one code path,
        // because the chart itself only knows "lowest / typical / highest" and
        // the difference is entirely in what those readings are.
        //
        //   roadrunner — ONE worker serves every endpoint. boot -> last ->
        //     worst is a real cumulative sequence, and its GROWTH is the
        //     finding. cakePHP's was 39.7 MB on the 2026-09-15 run (the leak
        //     documented below); after the adapter fix the re-measured
        //     2026-09-18 run puts it at 5.0 MB, in band with the others.
        //     Drawn as a sequence.
        //   php-fpm — every request is a FRESH process, so there is no end
        //     state and no trajectory. The three marks are the lightest,
        //     median and heaviest PER-REQUEST PEAK across the endpoints —
        //     "how much memory serving one request needed", which is the only
        //     thing an FPM worker can legitimately be ranked on.
        //
        // Before 2026-09-17 the FPM page drew the RR shape anyway, which made
        // its dot the heap of whichever endpoint happened to sort LAST and its
        // right cap a third unrelated request. Arithmetically the chart was
        // right; every reading in it was wrong.
        return $mode === 'php-fpm'
            ? $this->fpmMemory($dir, $rel, $apps, $mode)
            : $this->residentWorkerMemory($dir, $rel, $apps, $mode);
    }

    /**
     * Per-request memory for a fresh-process deployment (nginx + php-fpm).
     *
     * The statistic is the peak heap one request needed, measured with
     * memory_reset_peak_usage() at the entry script's boot-complete boundary
     * and memory_get_peak_usage() in the shutdown hook (see boot-probe.php).
     * The harness takes N probes per endpoint and reduces them to one median
     * (memProbeAggregateEndpoint), so every value here is an endpoint's own
     * reading. The chart prints all three readings beside each row and names
     * nothing, so the prose below describes the STATISTIC only.
     */
    private function fpmMemory(string $dir, string $rel, array $apps, string $mode): string
    {
        $series  = [];
        $rows    = [];
        $samples = null;
        foreach ($apps as $app) {
            $range = $this->store->requestPeakRange($app, $mode);
            if ($range === null) {
                continue;
            }
            $label = BenchmarkConfig::appLabel($app);
            $series[$label] = [
                'color' => BenchmarkConfig::appColor($app),
                'low'   => $range['low'] / 1048576,
                'mid'   => $range['median'] / 1048576,
                'high'  => $range['high'] / 1048576,
            ];
            // How many probes this endpoint's median came from. Read per app
            // from the dataset, never assumed: it is provenance.
            if ($samples === null) {
                $samples = $this->store->requestPeakSamples($app, $mode);
            }
            $rows[$label] = [
                'low'    => $range['low'],
                'median' => $range['median'],
                'high'   => $range['high'],
                'count'  => $range['count'],
            ];
        }
        if (count($series) < 2) {
            return '';
        }

        // Ranked on the MEDIAN reading — the typical request, which is what a
        // framework's memory cost actually is; the high end is one route's
        // worst case and ranking on it lets a single heavy endpoint decide the
        // whole ordering. Every framework's median is a real endpoint reading,
        // and on this transport the median is also the mark the chart
        // emphasises (the dot) and anchors its bar at.
        //
        // The footprint is still drawn, as context. It is NOT the ranking key
        // here even though it is endpoint-independent: it says what a process
        // costs to exist, not what serving a request costs, which is the
        // question this section answers.
        $byMid = $rows;
        uasort(
            $byMid,
            static fn(array $a, array $b): int =>
                $a['median'] <=> $b['median'] ?: $a['high'] <=> $b['high']
        );

        // The chart is sorted on the same key the rows are described in, so the
        // picture reads as the ranking the introduction states — cheapest
        // typical request first.
        $ranked = [];
        foreach (array_keys($byMid) as $label) {
            $ranked[$label] = $series[$label];
        }
        $series = $ranked;

        // The two left-hand columns are sized from their own longest string
        // inside memoryRange(), so the three-value readings and the framework
        // names cannot collide however the numbers get wider.
        //
        // The multiplier column is enabled HERE and not on the resident-worker
        // page: this chart's dot is a per-request peak that resets every
        // request, so comparing two frameworks' medians is comparing the same
        // quantity.
        $svg = SvgChart::memoryRange(
            $series,
            'Per-request memory — lightest, typical and heaviest endpoint',
            'bar spans lightest (left cap) → heaviest (right cap) · dot = median endpoint',
            'lightest request',
            'heaviest request',
            'low / median / high',
            ' · ',
            960,
            'x = median ÷ the lightest median'
        );
        $file = 'resident-memory.svg';
        $this->writeSvg($dir, $file, $svg);

        // No figure is named here, so no provenance count is needed either: the
        // table below lists every probed endpoint, and the chart prints each
        // row's own three readings.
        return "## Per-request memory\n\n"
            . "How much memory a single request needs, for every framework, measured "
            . "inside the FPM worker that served it. " . $this->workerMemoryTransport($mode)
            . "A request's high-water mark is taken from the framework-ready boundary of "
            . "the entry script to the moment the response is finished, with the mark reset "
            . "at that boundary — so it counts exactly what serving the request cost, and "
            . "never bleeds into the next one. "
            . $this->probeRepeatNote($samples)
            . "\n\n"
            . "All six frameworks are drawn on one shared MB axis. The **left cap** is the "
            . "lightest probed endpoint, the "
            . "**dot** is the median endpoint, and the **right cap** is the heaviest. Every "
            . "mark is a measured endpoint rather than an interpolation, so each can be named — "
            . "the three numbers printed beside each bar are those same three readings. The "
            . "faint bar behind each mark runs from zero to the median, so a row's length is "
            . "read against the axis rather than estimated from the caps. The multiplier beside "
            . "a row divides its median by the lightest median on the page; the reference row "
            . "carries none.\n\n"
            . "Rows are ordered by the **median** request — a framework's typical cost — so "
            . "one heavy route cannot reorder the table on its own. A row that stays flat and "
            . "a row that reaches far right therefore say different things: the first is cheap "
            . "on every route, the second is cheap on a typical request until one heavy route "
            . "sets the worst case a pool has to be sized for.\n\n"
            . '![Per-request memory](' . $rel . '/' . $file . ')';
    }

    /**
     * One sentence naming how many probes each endpoint's numbers came from.
     *
     * The count is dataset provenance, not a constant: it is whatever
     * --mem-repeats the run used. When the dataset predates the field (null)
     * the sentence is omitted rather than guessed — a page that claims "probed
     * 10 times" for a run that probed once would be a fabricated methodology.
     */
    private function probeRepeatNote(?int $samples): string
    {
        if ($samples === null || $samples <= 0) {
            return '';
        }

        if ($samples === 1) {
            return 'Each endpoint was probed once, so the range shows how much the '
                . 'endpoints themselves differ — a property of the workload rather than '
                . 'of the measurement.';
        }

        return 'Each endpoint is probed ' . $samples . ' times and reduced to its median, so a '
            . 'single outlier cannot move a row; the range shows how much the endpoints '
            . 'themselves differ, which is a property of the workload rather than of the '
            . 'measurement.';
    }

    /**
     * Memory of a RESIDENT worker (RoadRunner), where one process serves every
     * endpoint — so the three marks are that one worker at three moments and
     * form a genuine cumulative sequence:
     *
     *   LEFT CAP  = boot heap, before any request. Endpoint-independent, so it
     *               is a footprint.
     *   DOT       = heap after the LAST probed endpoint — where the run ended.
     *   RIGHT CAP = largest heap any endpoint reached.
     *
     * The growth from left to right is the finding here, which is why this
     * view keeps the sequence and its own prose rather than sharing the
     * per-request wording of fpmMemory().
     *
     * ROWS ARE RANKED ON THE DOT, and the objection to that is worth stating
     * because it is not stupid: the dot depends on the endpoint ORDER, so it is
     * not a reproducible property of the framework the way the boot heap is.
     * Reorder the suite and every row can move.
     *
     * It is still the right key, for three reasons:
     *
     *   1. WHAT THE PAGE IS ABOUT. This is resident-worker memory: the question
     *      is what a long-lived process is left holding, and that is the dot.
     *      The boot heap answers 'what does this framework cost to exist',
     *      which is a different and much less interesting number here.
     *   2. THE COUNTER-EVIDENCE IS CONCRETE. Ranking on the boot heap puts
     *      cakePHP at the TOP — its 0.923 MB is the lightest of all six —
     *      while printing 'x 3.5' beside it and drawing its dot fourth-heaviest
     *      down the chart. A table headed by the 4th-heaviest steady state, and
     *      contradicted by its own annotation column, is worse than an
     *      order-dependent one.
     *   3. IT IS THE MARK THE CHART ALREADY EMPHASISES. The faint bar runs to
     *      the dot, the caption names the dot, and the prose is about growth
     *      across endpoints. Ordering by anything else makes the row order the
     *      only part of the figure that disagrees with the rest of it.
     *
     * The order-dependence is real but SHARED: all six frameworks are read from
     * the same single fixed sequence of endpoints, so their dots are comparable
     * to each other even though none of them would survive a reordered suite.
     * That is exactly the caveat the prose states rather than hides, which is
     * why it can be said out loud here and printed under the chart.
     *
     * Tiebreak on the left cap, so the order is total and does not fall back on
     * the order the dataset happened to list the apps in.
     *
     * HISTORY, because the chart is the reason this was ever noticed: the
     * 2026-09-15 run drew cakePHP at 0.851 -> 40.556 MB (growth 39.7 MB) while
     * every other framework stayed between 0.7 and 1.5 MB, and that outlier
     * was a genuine bug — a fresh Cake Session per request (built by the stock
     * request factory) leaked the engine's shutdown-handler list, ~186 B per
     * request, unbounded. Fixed in the adapter; RE-MEASURED 2026-09-18 on the
     * bench VM at the same 1000x10 budget: 0.923 -> 5.96 MB, worst 8.14 MB,
     * growth 5.0 MB.
     *
     * The shape changed, not just the magnitude, and the shape is the real
     * evidence: pre-fix the series climbed monotonically (+~2.4 MB every
     * endpoint and never came down, i.e. retained); post-fix it oscillates and
     * returns to baseline (released). A chart that merely looked less steep
     * could still hide a slower leak — a chart that comes back down cannot.
     */
    private function residentWorkerMemory(string $dir, string $rel, array $apps, string $mode): string
    {
        $series = [];
        foreach ($apps as $app) {
            $boot = $this->store->residentBootHeap($app, $mode);
            if ($boot === null) {
                continue;
            }
            $last  = (int) ($this->store->residentHeap($app, $mode) ?? $boot);
            $peak  = (int) ($this->store->residentPeakHeap($app, $mode) ?? $boot);
            $label = BenchmarkConfig::appLabel($app);
            $series[$label] = [
                'color' => BenchmarkConfig::appColor($app),
                'low'   => $boot / 1048576,
                'mid'   => $last / 1048576,
                'high'  => $peak / 1048576,
            ];
        }
        if (count($series) < 2) {
            return '';
        }

        // Rows are RANKED on the DOT — the heap the worker was left holding
        // after each framework's last endpoint — because that is the reading a
        // long-lived worker's steady state is judged on, and it is the mark the
        // page's prose and its multiplier column are both built around. The
        // left cap used to rank these rows; it is a footprint, measured before
        // any request, so a framework could be drawn cheapest-to-exist while
        // being the heaviest one to keep running, and the row order then
        // disagreed with the x factor printed beside it. Tiebreak on the left
        // cap so the order is total and stable rather than dependent on
        // whatever order the dataset happened to list the apps in.
        uasort(
            $series,
            static fn(array $a, array $b): int =>
                [$a['mid'], $a['low']] <=> [$b['mid'], $b['low']]
        );

        $svg = SvgChart::memoryRange(
            $series,
            'Resident worker memory — footprint, end state and worst endpoint',
            'bar spans boot (left cap) → largest endpoint (right cap) · dot = heap after the last endpoint',
            'lightest footprint',
            'largest peak',
            'boot / end / worst',
            ' → ',
            960,
            'x = end state ÷ the lightest end state'
        );
        $file = 'resident-memory.svg';
        $this->writeSvg($dir, $file, $svg);

        return "## Resident worker memory\n\n"
            . "Read from inside the live worker after each endpoint, on an extra "
            . "untimed request that never touches the latency numbers. All six frameworks are "
            . "drawn on one shared MB axis. The **left cap** is the PHP heap with the application "
            . "booted and **no request served** — the framework's own data structures, with "
            . "opcache bytecode excluded because it lives in shared memory. The **dot** is the "
            . "heap after the last endpoint, and the **right cap** is the largest heap any "
            . "endpoint reached. A narrow-left range that reaches far right is the shape worth "
            . "watching: cheap to exist, expensive at its worst.\n\n"
            . $this->workerMemoryTransport($mode)
            . "Rows are ordered by the **dot** — the heap the worker was left holding after its "
            . "last endpoint — so the table reads as one ranking from lightest steady state to "
            . "heaviest. The multiplier beside a row divides its dot by the lightest dot on the "
            . "page; the reference row carries none. A row can therefore sit high while having "
            . "the lightest left cap: that is a framework that is cheap to boot and expensive to "
            . "keep running, which is exactly the distinction the three marks exist to draw.\n\n"
            . "The dot and the right cap are both endpoint-order dependent — the probe reads the "
            . "whole heap once per endpoint, so it cannot say what one request costs on its own — "
            . "which is why they are drawn as a range and the dot marks the end of the run rather "
            . "than a lighter reading. That caveat bounds what the numbers MEAN; it does not "
            . "invalidate the comparison, because every row is read from the same single sequence "
            . "of endpoints and therefore at the same moment. What it rules out is reading any one "
            . "of them as a per-request cost. The distance from the left cap to the right one, and "
            . "the number of steps over which the heap rises, are the growth a long-lived worker "
            . "accumulates.\n\n"
            . '![Resident worker memory](' . $rel . '/' . $file . ')';
    }

    /**
     * How the worker-memory numbers were collected on the server this view
     * measured — which is a fact about the SERVER, not about the view.
     *
     * The two transports are genuinely different, and a reader comparing the
     * real-roadrunner and real-fpm pages has to know that:
     *
     *   roadrunner — the worker is resident in a request loop, so it answers
     *                in RESPONSE HEADERS (deploy/rr/worker.php) whenever a
     *                probe request carries X-Mem-Probe: 1.
     *   php-fpm    — there is no loop to ask: the entry script runs, serves
     *                one request and is torn down. It appends a sample from a
     *                shutdown hook (mem_probe_arm() in boot-probe.php) and the
     *                harness reads the newest line back.
     *
     * Both sides call the same PHP functions for the same four quantities, so
     * the numbers are comparable; only the route out of the process differs.
     * Claiming "read from inside the RoadRunner worker" on an FPM page would
     * simply be false, which is why this is a branch and not a constant.
     */
    private function workerMemoryTransport(string $mode): string
    {
        if ($mode === 'roadrunner') {
            return 'The numbers are read from the resident RoadRunner worker, which answers '
                . "them directly in response headers.\n\n";
        }

        return 'The numbers come from the FPM worker process itself: because the entry '
            . 'script is torn down when the request ends, it appends one sample as it exits, and '
            . 'the harness reads that back. The pool is `pm = static` with `max_children = 1` and '
            . '`max_requests = 0`, so this is ONE worker that stays alive for the whole block — '
            . "which is why it has retained memory worth reporting at all.\n\n";
    }
}