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
    public function __construct(
        private readonly ResultStore $store,
        private readonly string $viewKey,
        private readonly array $view
    ) {}

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

        $apps     = array_values(array_intersect($this->view['apps'] ?? $this->store->apps(), $this->store->apps()));
        $baseline = (string) ($this->view['baseline'] ?? $apps[0] ?? '');
        $mode     = (string) ($this->view['mode'] ?? 'warm');
        $logScale = (bool) ($this->view['log_scale'] ?? false);
        $charts   = $this->view['charts'] ?? ['hero', 'speedup', 'features', 'memory', 'wins'];

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
                    . 'cost, which is why the values cluster — the floor note below states it explicitly. '
                : '')
            . 'The workload column states what each request reads or writes — the shared SQLite database '
            . 'holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, '
            . 'and every write upserts exactly one sentinel row.'
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
            'wins'            => $this->wins($apps, $baseline, $mode),
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
        $lo = null;
        $hi = null;
        foreach ($this->store->apps() as $app) {
            $boot = $this->store->modeBootMs($app, $mode);
            if ($boot === null) {
                continue;
            }
            $lo = $lo === null ? $boot : min($lo, $boot);
            $hi = $hi === null ? $boot : max($hi, $boot);
        }
        return $lo === null
            ? ''
            : 'boot included (worker recycle ' . SvgChart::fmt($lo) . '–' . SvgChart::fmt($hi) . ' ms)';
    }

    /**
     * True when this dataset measured a REAL server (its rows carry the
     * floor-* probes). Only then may the prose blame a web server for a flat
     * ranking — the in-process harness has none.
     */
    private function isRealDeployment(): bool
    {
        return $this->store->floors() !== [];
    }

    /**
     * Closing clause for a set of frameworks that all land within 5% of each
     * other. The honest reason depends on the dataset: on a real deployment the
     * constant nginx/FPM/RR cost swamps the framework, while in the in-process
     * harness a shared fixed cost (e.g. cache round-trips) does. Never blame a
     * web server that was not measured.
     */
    private function withinNoiseReason(): string
    {
        return $this->isRealDeployment()
            ? 'the deployment is server-bound, so the ranking says more about the web server than about the frameworks'
            : 'a shared fixed cost dominates it, so the endpoint does not separate the frameworks';
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
            $php  = $ms('floor-php');
            $http = $ms('floor-http');
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
                1 => 'That worker spawn + FastCGI handshake is the floor every row below stands on — '
                    . 'subtract it and the remainder is the framework\'s own per-request boot.',
                0 => 'That FastCGI handshake + minimal-script cost is the floor every row below stands on — '
                    . 'subtract it and the remainder is the framework\'s own per-request boot, which FPM '
                    . 'still pays for every request even though its worker survives.',
                default => 'Subtracting it leaves the framework\'s own per-request boot, but how much of '
                    . 'this floor is a process spawn cannot be recovered from the dataset.'
            };

            return "\n\n**Server floor** — measured nginx + PHP-FPM with `pm.max_requests="
                . ($maxReqs ?? '?') . "`: " . $model
                . '. A hello-world endpoint that boots nothing but PHP costs '
                . '**' . SvgChart::fmt($php) . ' ms** (`floor-php`), and a static file through nginx '
                . SvgChart::fmt($http ?? 0.0) . ' ms (`floor-http`). ' . $advice;
        }

        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            $rr = $ms('floor-rr');
            if ($rr === null) {
                return '';
            }
            return "\n\n**Server floor** — real RoadRunner over loopback: a bare resident worker that renders a "
                . 'fixed string costs **' . SvgChart::fmt($rr) . ' ms** (`floor-rr`) — the IPC + server floor '
                . 'every row below also pays. Only differences larger than this floor are framework differences.';
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
            $values = [];
            foreach ($this->store->apps() as $app) {
                $boot = $this->store->modeBootMs($app, $mode);
                if ($boot !== null) {
                    $values[] = $boot;
                }
            }
            if ($values === []) {
                return '';
            }
            sort($values);
            $lo = $values[0];
            $hi = $values[count($values) - 1];
            return "\n\nThese rows are end-to-end for a resident worker: every headline and every chart point adds the "
                . 'worker\'s boot (warm recycle, ' . SvgChart::fmt($lo) . '–' . SvgChart::fmt($hi) . ' ms here) to the '
                . 'measured request, so a cell is the time one request keeps that worker busy — the number to read '
                . 'when workers are recycled per request or requests queue behind one pool.';
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
        $bands    = [
            'cold' => ['label' => 'Cold boot — real, first boot in a process (CGI/CLI model)', 'key' => 'cold_ms'],
        ];
        // The FPM band only exists when the cold rows carry their per-request
        // boot share (fork-mode datasets). On the derived deployments file the
        // cold side is named 'php-fpm'.
        if ($this->store->fpmBootMedian($apps[0] ?? '') !== null || $this->store->fpmBootMedian($apps[1] ?? '') !== null) {
            $bands['fpm'] = ['label' => 'FPM rebuild — per-request boot on shared opcache', 'key' => 'fpm_boot'];
        }
        $bands['warm'] = ['label' => 'Warm recycle — resident worker (RoadRunner model)', 'key' => 'warm_ms'];

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
                    $boot = $this->store->boot($app);
                    if ($boot === null) {
                        continue;
                    }
                    $bootVal = $boot[$cfg['key']];
                }
                // Boot and teardown are the same kind of time — the worker
                // cannot serve another request during either — so each band
                // shows their sum: boot cost + the cold side's median
                // per-request cleanup.
                $cleanup = $this->store->cleanupMedian($app, $coldMode) ?? 0.0;
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
            'Framework startup — boot + teardown',
            'boot + median per-request teardown — both block the worker between requests',
            $factors,
            'x = median ÷ the fastest working boot of that kind (no-op re-boots excluded)',
            true
        );
        $file = 'startup.svg';
        file_put_contents($dir . '/' . $file, $svg);

        // Prose: the cold race (first boot is what a deploy/server start pays),
        // plus the FPM-rebuild story when that band exists.
        $cold = $metrics['cold'] ?? [];
        asort($cold);
        $keys  = array_keys($cold);
        $best  = $keys[0] ?? null;
        $worst = $keys[count($keys) - 1] ?? null;
        if ($best === null || $worst === null) {
            return '';
        }
        $bestMs  = $cold[$best]['median'];
        $worstMs = $cold[$worst]['median'];

        $warm = $metrics['warm'] ?? [];
        asort($warm);
        $warmKeys = array_keys($warm);
        $warmBest = $warmKeys[0] ?? null;

        // FPM band prose: who rebuilds least per request, and how it compares
        // to the real first boot — the gap between the two bands is exactly
        // the one-time cost (compile + FS cache) shared bytecode eliminates.
        $fpmSentence = '';
        $fpm         = $metrics['fpm'] ?? [];
        if ($fpm !== [] && $cold !== []) {
            $fpmSorted = $fpm;
            asort($fpmSorted);
            $fKeys       = array_keys($fpmSorted);
            $fBest       = $fKeys[0];
            $fWorst      = $fKeys[count($fKeys) - 1];
            $fpmSentence = '**FPM rebuild** — a recycled PHP-FPM worker never pays the first band: sharing opcache bytecode '
                . 'with the master, it only rebuilds the application (container, routes, DB connect) — '
                . SvgChart::fmt($fpmSorted[$fBest]['median']) . ' ms for ' . $fBest . ' at the low end, '
                . SvgChart::fmt($fpmSorted[$fWorst]['median']) . ' ms for ' . $fWorst . ' at the high end. '
                . 'The gap between the cold and FPM bands is the one-time compile cost shared bytecode removes.';
        }

        // Per-request teardown share, when the dataset carries the split.
        // Guarded against a collapsed range: with the boot now inside the
        // total, teardown is a rounding error in most apps, and
        // "ranges from 0% up to 0%" reads as a bug rather than a finding.
        $cleanupSentence = '';
        if ($this->store->hasCleanupSplit()) {
            $shares = [];
            foreach ($apps as $app) {
                $share = $this->store->cleanupShare($app, $mode);
                if ($share !== null) {
                    $shares[$app] = $share;
                }
            }
            if ($shares !== []) {
                asort($shares);
                $cKeys = array_keys($shares);
                $cLo   = $shares[$cKeys[0]];
                $cHi   = $shares[$cKeys[count($cKeys) - 1]];
                if ($cHi - $cLo >= 0.005) {
                    $cleanupSentence = ' The teardown share of a full request ranges from '
                        . number_format($cLo * 100, 0) . '% (' . BenchmarkConfig::appLabel($cKeys[0]) . ') up to '
                        . number_format($cHi * 100, 0) . '% (' . BenchmarkConfig::appLabel($cKeys[count($cKeys) - 1]) . ').';
                } else {
                    $cleanupSentence = ' Post-response teardown stays under 1% of a full request for every framework here.';
                }
            }
        }

        return "## Framework startup\n\n"
            . "Three boot models, timed directly by the harness — each band shows boot + median teardown, because "
            . "during both the worker cannot serve another request:\n\n"
            . '- **Cold boot** — the very first bootstrap in a fresh PHP process (autoloader + compile + FS cache): '
            . "what a CLI run, CGI request, or newly spawned worker pays once. **{$best}** pays "
            . SvgChart::fmt($bestMs) . ' ms against ' . SvgChart::fmt($worstMs) . " ms for {$worst}"
            . ' — x ' . SvgChart::fmtFactor($worstMs / max($bestMs, 1e-9)) . " slower.\n"
            . ($fpmSentence !== '' ? '- ' . $fpmSentence . "\n" : '')
            . ($warmBest !== null && isset($warm[$warmBest])
                ? '- **Warm recycle** — worker restart with opcache warm: '
                    . SvgChart::fmt($warm[$warmBest]['median']) . ' ms for ' . $warmBest
                    . " at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild.\n"
                : '')
            . "\n"
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
        $means   = [];
        foreach ($apps as $app) {
            $spread = $this->store->spread($app, $mode, $startup);
            if ($spread === null) {
                continue;
            }
            $label = BenchmarkConfig::appLabel($app);
            $metrics[$label] = $spread;
            $colors[$label] = BenchmarkConfig::appColor($app);
            $medians[$label] = $spread['median'];
            $means[$label] = $this->store->msWithBoot($app, $mode, $startup) ?? $spread['median'];
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
        file_put_contents($dir . '/' . $file, $svg);

        asort($medians);
        $keys    = array_keys($medians);
        $best    = $keys[0];
        $bestMs  = $medians[$best];
        $bestTm  = $means[$best] ?? $bestMs;
        $worst   = $keys[count($keys) - 1];
        $worstMs = $medians[$worst];
        $worstTm = $means[$worst] ?? $worstMs;

        // When a real server's constant cost dwarfs the framework, the spread
        // is noise on the floor — do not phrase it as a framework difference.
        $floorBound = $bestMs > 0 && ($worstMs / $bestMs) < 1.05;
        $closing    = $floorBound
            ? '. Every framework lands within 5% of the fastest: ' . $this->withinNoiseReason() . '.'
            : ' — x ' . SvgChart::fmtFactor($worstMs / max($bestMs, 1e-9)) . ' slower.';

        return "## Framework startup\n\n"
            . "Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: "
            . "**{$best}** responds in " . SvgChart::fmt($bestMs) . ' ms (median; ' . SvgChart::fmt($bestTm) . " ms trimmed mean) "
            . 'against ' . SvgChart::fmt($worstMs) . " ms (median) for {$worst}"
            . $closing . "\n\n"
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
        file_put_contents($dir . '/' . $file, $svg);

        // Fastest/slowest app for the prose, ranked on the same sum of medians
        // the dot shows. The server-bound wording requires EVERY framework to
        // be within 5% (worst factor) — checking only the closest app claimed
        // "within 5%" for datasets whose slowest app needed 2.4x the baseline.
        $totals = array_map(static fn(array $m): float => $m['median'], $metrics);
        asort($totals);
        $fastest   = array_key_first($totals);
        $slowest   = array_key_last($totals);
        $maxFactor = $factors[$slowest] ?? 1.0;
        $extra     = '';
        if ($maxFactor < 1.05) {
            $extra = " Every framework lands within 5% of {$base} on the total: " . $this->withinNoiseReason() . '.';
        } elseif ($fastest === $base) {
            $rivals = $totals;
            unset($rivals[$base]);
            $closest = array_key_first($rivals);
            $extra   = " The closest rival is {$closest}, needing x " . SvgChart::fmtFactor($factors[$closest]) . ' the same total.';
        } else {
            $extra = sprintf(
                ' %s is fastest overall at x %s of %s\'s total; %s is slowest at x %s.',
                $fastest,
                SvgChart::fmtFactor($factors[$fastest]),
                $base,
                $slowest,
                SvgChart::fmtFactor($maxFactor)
            );
        }

        return "## Total response times\n\n"
            . 'Total time to serve one of each of the ' . count($requests) . " endpoints — the sum of the endpoints' "
            . "medians, not a single response time — relative to {$base} (1.0 = the baseline's own total, "
            . "higher = slower). Each endpoint's median is boot-inclusive occupancy for this view's "
            . "deployment model, so the total is the worker time one pass over every endpoint costs.{$extra}\n\n"
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
            file_put_contents($dir . '/' . $file, $svg);
            $charts[] = "### {$title}\n\n![{$title}](" . $rel . '/' . $file . ')';

            // Winner row for the feature's first request (the canonical one).
            // Uses the same median the dot shows, so text and chart agree.
            $race = $this->store->race($feature, $mode, $cats[0], $participants);
            if ($race !== null) {
                $medians = [];
                foreach ($participants as $p) {
                    $spread = $this->store->spread($p, $mode, $cats[0]);
                    if ($spread !== null) {
                        $medians[$p] = $spread['median'];
                    }
                }
                asort($medians);
                $pKeys    = array_keys($medians);
                $winner   = $pKeys[0] ?? $race['winner'];
                $runner   = $pKeys[1] ?? null;
                $winnerMs = (float) ($medians[$winner] ?? $race['winner_ms']);
                $runnerMs = $runner !== null ? (float) $medians[$runner] : null;
                // Below the server floor's own noise a "x 1.0 faster" claim is
                // meaningless — say the endpoint is floor-bound instead.
                $floorBound = $runnerMs !== null && $winnerMs > 0
                    && ($runnerMs / $winnerMs) < 1.05;
                $sections[] = sprintf(
                    '- **%s** (`%s`): %s at %sms median%s.',
                    $title,
                    $cats[0],
                    BenchmarkConfig::appLabel($winner),
                    SvgChart::fmt($winnerMs),
                    $runner === null
                        ? ''
                        : ($winnerMs <= 0
                            ? ', x ' . SvgChart::fmtFactor($runnerMs / max($winnerMs, 1e-9)) . ' faster than ' . BenchmarkConfig::appLabel($runner)
                            : ($floorBound
                                ? ' — every framework lands within 5% of it: ' . $this->withinNoiseReason()
                                : ', x ' . SvgChart::fmtFactor($runnerMs / $winnerMs) . ' faster than ' . BenchmarkConfig::appLabel($runner)))
                );
            }
        }

        if ($charts === []) {
            return '';
        }

        return "## Feature benchmarks\n\n" . implode("\n", $sections) . "\n\n" . implode("\n\n", $charts);
    }

    /**
     * @param list<string> $apps
     */
    private function memory(string $dir, string $rel, array $apps, string $mode): string
    {
        $metrics = [];
        $colors  = [];
        $rows    = [];
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
            $rows[$label] = $range;
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
        file_put_contents($dir . '/' . $file, $svg);

        // Rank on the worst endpoint — the safe planning number.
        $ranked = [];
        foreach ($rows as $label => $r) {
            $ranked[$label] = $r['high'];
        }
        asort($ranked);
        $bestLabel = array_key_first($ranked);
        $bestMb    = ($ranked[$bestLabel] ?? 0) / 1048576;
        $worstMb   = (max($ranked)) / 1048576;

        return "## Peak memory\n\n"
            . "Peak memory reached on any endpoint. **{$bestLabel}** stays under "
            . SvgChart::fmt($bestMb) . " MB, against " . SvgChart::fmt($worstMb) . " MB for the heaviest framework "
            . '(x ' . SvgChart::fmtFactor($worstMb / max($bestMb, 1e-9)) . " more). Each dot is the median endpoint and the "
            . "whisker spans the lightest to the heaviest endpoint.\n\n"
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
     * cost; only the left cap ranks frameworks. That distinction is why the
     * chart carries a dot and a right cap instead of collapsing the run to one
     * number.
     *
     * @param list<string> $apps
     */
    private function residentMemory(string $dir, string $rel, array $apps, string $mode): string
    {
        if (!$this->store->hasResidentMem($mode)) {
            return '';
        }

        $series = [];
        $rows   = [];
        foreach ($apps as $app) {
            $boot = $this->store->residentBootHeap($app, $mode);
            if ($boot === null) {
                continue;
            }
            $last  = (int) ($this->store->residentHeap($app, $mode) ?? $boot);
            $peak  = (int) ($this->store->residentPeakHeap($app, $mode) ?? $boot);
            $traj  = $this->store->residentTrajectory($app, $mode);
            $label = BenchmarkConfig::appLabel($app);
            $series[$label] = [
                'color' => BenchmarkConfig::appColor($app),
                'boot'  => $boot / 1048576,
                'last'  => $last / 1048576,
                'peak'  => $peak / 1048576,
            ];
            $rows[$label] = ['boot' => $boot, 'last' => $last, 'peak' => $peak, 'traj' => $traj];
        }
        if (count($series) < 2) {
            return '';
        }

        // Rows are RANKED on the footprint (cheapest first), not left in the
        // view's app order: the left cap is the one number here that is a
        // property of the worker rather than of the endpoint order, so it is
        // the only column that can carry a ranking — and a chart that claims a
        // ranking should read as one. The prose below ranks on the same key.
        uasort($series, static fn(array $a, array $b): int => $a['boot'] <=> $b['boot']);

        $svg = SvgChart::memoryRange(
            $series,
            'Resident worker memory — footprint, end state and worst endpoint',
            'bar spans boot (left cap) → largest endpoint (right cap) · dot = heap after the last endpoint',
            'lightest footprint',
            'largest peak',
            960
        );
        $file = 'resident-memory.svg';
        file_put_contents($dir . '/' . $file, $svg);

        // Rank on the footprint end — boot is endpoint-independent and
        // therefore the only legitimately comparable number here.
        $byBoot = $rows;
        uasort($byBoot, static fn(array $a, array $b): int => $a['boot'] <=> $b['boot']);
        $keys    = array_keys($byBoot);
        $light   = $keys[0];
        $heavy   = $keys[count($keys) - 1];
        $lightMb = $byBoot[$light]['boot'] / 1048576;
        $heavyMb = $byBoot[$heavy]['boot'] / 1048576;

        // Name the largest accumulator, if any framework actually accumulates.
        $byGrowth = $rows;
        uasort(
            $byGrowth,
            static fn(array $a, array $b): int =>
                ($b['peak'] - $b['boot']) <=> ($a['peak'] - $a['boot'])
        );
        $gTop    = (string) array_key_first($byGrowth);
        $gRow    = $byGrowth[$gTop];
        $gBootMb = $gRow['boot'] / 1048576;
        $gPeakMb = $gRow['peak'] / 1048576;
        $gLastMb = $gRow['last'] / 1048576;
        $gTopMb  = $gPeakMb - $gBootMb;

        // A trajectory-only fact: on how many steps did the heap rise? Stated
        // instead of "it climbs steadily" because the sequence is NOT
        // monotone — it dips whenever an endpoint releases what the previous
        // one held. Counting the rises claims only what was measured.
        $rises = 0;
        $traj  = $gRow['traj'];
        for ($i = 1; $i < count($traj); $i++) {
            if ($traj[$i]['heap'] > $traj[$i - 1]['heap']) {
                $rises++;
            }
        }
        $steps = max(1, count($traj) - 1);

        $growthSentence = $gTopMb < 0.5
            ? 'No framework holds more than half a megabyte more at its worst endpoint than at '
                . 'boot — state is released between requests.'
            : '**' . $gTop . '** is the exception: it reaches ' . SvgChart::fmt($gPeakMb)
                . ' MB against ' . SvgChart::fmt($gBootMb) . ' MB at boot (x '
                . SvgChart::fmtFactor($gPeakMb / max($gBootMb, 1e-9)) . ' more), and its heap '
                . 'is still higher than at the previous endpoint on ' . $rises . ' of the '
                . $steps . ' steps through the suite'
                // The end state is worth naming only when it differs from the
                // peak: printing the same number twice reads as a mistake.
                . ($gLastMb < $gPeakMb
                    ? ', ending the run at ' . SvgChart::fmt($gLastMb) . ' MB after releasing '
                        . SvgChart::fmt($gPeakMb - $gLastMb) . ' MB from that worst endpoint'
                    : '')
                . ' — its cost grows with the number of distinct endpoints served, not with the '
                . 'request count.';

        return "## Resident worker memory\n\n"
            . "Read from inside the live RoadRunner worker after each endpoint, on an extra "
            . "untimed request that never touches the latency numbers. All six frameworks are "
            . "drawn on one shared MB axis. The **left cap** is the PHP heap with the application "
            . "booted and **no request served** — the framework's own data structures, with "
            . "opcache bytecode excluded because it lives in shared memory. The **dot** is the "
            . "heap after the last endpoint, and the **right cap** is the largest heap any "
            . "endpoint reached. A narrow-left range that reaches far right is the shape worth "
            . "watching: cheap to exist, expensive at its worst.\n\n"
            . "**{$light}** needs " . SvgChart::fmt($lightMb) . ' MB to exist, against '
            . SvgChart::fmt($heavyMb) . ' MB for ' . $heavy
            . ' (x ' . SvgChart::fmtFactor($heavyMb / max($lightMb, 1e-9)) . " more). "
            . $growthSentence
            . "\n\nOnly the left cap ranks frameworks: it is a property of the worker, identical "
            . "on every endpoint. The dot and the right cap are both endpoint-order dependent — "
            . "the probe reads the whole heap once per endpoint, so it cannot say what one request "
            . "costs on its own — which is why they are drawn as a range and the dot marks the end "
            . "of the run rather than a lighter reading.\n\n"
            . '![Resident worker memory](' . $rel . '/' . $file . ')';
    }

    /**
     * How much this dataset separates the frameworks on a given mode: the
     * MEDIAN across-endpoint relative spread (max/min over the frameworks),
     * plus how many endpoints keep every framework within 5% of each other.
     *
     * This is the honest replacement for an all-or-nothing "is floor bound"
     * test: on the real nginx/FPM run the per-request worker spawn (~9.3 ms) is
     * ~93% of a ~10 ms request, so the median endpoint spread is ~3% — smaller
     * than the run-to-run noise — while a handful of endpoints reach 5-7%.
     *
     * @param list<string> $apps
     * @return array{median_pct:float,within5:int,total:int}|null
     */
    private function spreadProfile(string $mode, array $apps): ?array
    {
        $common  = $this->store->commonRequests($mode, $apps);
        $spreads = [];
        $within5 = 0;
        foreach ($common as $request) {
            $vals = [];
            foreach ($apps as $app) {
                $ms = $this->store->ms($app, $mode, $request);
                if ($ms !== null && $ms > 0) {
                    $vals[] = $ms;
                }
            }
            if (count($vals) < 2) {
                continue;
            }
            $ratio = max($vals) / min($vals);
            $spreads[] = ($ratio - 1.0) * 100.0;
            if ($ratio < 1.05) {
                $within5++;
            }
        }
        if ($spreads === []) {
            return null;
        }
        sort($spreads);
        return [
            'median_pct' => $spreads[intdiv(count($spreads), 2)],
            'within5'    => $within5,
            'total'      => count($spreads),
        ];
    }

    /**
     * @param list<string> $apps
     */
    private function wins(array $apps, string $baseline, string $mode): string
    {
        $table = (new Tables($this->store))->winsMarkdown($mode, $apps);
        if ($table === '') {
            return '';
        }
        $profile = $this->spreadProfile($mode, $apps);
        if ($profile !== null && $profile['median_pct'] < 5.0) {
            return "## Wins per framework\n\n"
                . 'On this deployment the server floor dominates: the median endpoint puts every framework '
                . 'within ' . SvgChart::fmt($profile['median_pct']) . '% of the fastest, so the counts below '
                . 'record measurement noise rather than framework advantages. The honest reading is that the '
                . 'server, not the framework, decides the response time here.'
                . "\n\n"
                . $table;
        }
        return "## Wins per framework\n\n"
            . 'Number of endpoint races won (lowest boot-inclusive per-request time) per framework.'
            . ($this->isRealDeployment()
                ? ' This is a real server: a race won by less than the server floor and the run-to-run '
                    . 'jitter is a tie, so read small leads cautiously.'
                : '')
            . "\n\n"
            . $table;
    }

}