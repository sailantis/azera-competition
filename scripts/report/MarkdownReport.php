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
        $l[] = (string) ($this->view['subtitle'] ?? '');
        $l[] = '';
        $l[] = $this->envBlock();
        $l[] = '';

        foreach ($charts as $chart) {
            $lines = $this->chart($chart, $svgDir, $svgRelPrefix, $apps, $baseline, $mode, $logScale);
            if ($lines !== null) {
                $l[] = $lines;
                $l[] = '';
            }
        }

        // Shared tables so both outputs agree on the numbers.
        $tables = new Tables($this->store);
        $l[] = '## Latency by endpoint';
        $l[] = '';
        $l[] = 'Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint. '
            . 'The workload column states what each request reads or writes — the shared SQLite database '
            . 'holds 1,000 item rows (re-seeded per app × mode), every list endpoint serves page 1 of 20, '
            . 'and every write upserts exactly one sentinel row.'
            . ($this->store->hasCleanupSplit()
                ? "\n\nEach cell also shows the request's lifecycle split as `total <sub>handle + cleanup</sub>` — "
                    . 'cleanup is the post-response teardown a long-lived worker performs between requests '
                    . '(terminate() finalizers, request-scoped resets), which the headline number includes.'
                : '');
        $l[] = '';
        $l[] = $tables->latencyMarkdown($mode, $apps);
        $l[] = '';

        $l[] = '---';
        $l[] = '';
        $l[] = '> **Auto-generated.** This page and its charts are produced by the `azera-competition` repository:';
        $l[] = '> `php run.php --apps=azera,laravel,symfony,spiral,codeigniter,cakephp --out=results/free-for-all-opcache --report`';
        $l[] = '> then `php scripts/report.php --publish=framework`. Do not edit by hand — re-run the benchmark to update it.';
        $l[] = '';
        $l[] = 'Every chart is a plain SVG generated from the result JSON, so the numbers and the diagrams can never disagree.';
        $l[] = '';

        return implode("\n", $l);
    }

    private function envBlock(): string
    {
        $env = $this->store->env();
        return sprintf(
            "**Environment** — PHP %s · %s · OPcache (CLI): %s · 1000 iterations per run over multiple runs, lower is better.\n\n_Measured %s%s_",
            $env['php_version'] ?? '?',
            $env['os'] ?? '?',
            !empty($env['opcache']) ? 'yes' : 'no',
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
            'hero'     => $this->hero($svgDir, $rel, $apps, $mode, $logScale),
            'speedup'  => $this->speedup($svgDir, $rel, $apps, $baseline, $mode),
            'features' => $this->features($svgDir, $rel, $apps, $mode, $logScale),
            'memory'   => $this->memory($svgDir, $rel, $apps, $mode),
            'wins'     => $this->wins($apps, $baseline, $mode),
            default    => null
        };
    }

    /**
     * Caption for the latency charts. The lower bound of the whisker is only
     * labelled "fastest observation" when the dataset actually carries a
     * per-request minimum; otherwise it is honestly called what it is
     * (median to p95).
     */
    private function spreadCaption(): string
    {
        return $this->store->hasMin()
            ? 'bar = median · dot = median · whisker = fastest observation → p95 (ms)'
            : 'bar = median · dot = median · whisker = median → p95 (ms)';
    }

    /**
     * @param list<string> $apps
     */
    /**
     * Framework startup = the framework's real load cost, measured directly
     * by the harness (run-app.php's measureBoot): a cold boot is the very
     * first bootstrap() in a fresh PHP process (autoloader, kernel/container
     * build, routes, DB connect), a warm boot is a re-bootstrap with
     * classes/opcache already loaded — what a RoadRunner-style worker pays
     * per recycle.
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
     * Cold + warm bootstrap cost, one band each, per-band anchored at the
     * fastest framework on that band. Bands carry boot + median teardown —
     * both block the worker between requests.
     *
     * @param list<string> $apps
     */
    private function bootChart(string $dir, string $rel, array $apps, bool $logScale, string $mode): string
    {
        $metrics = [];
        $colors  = [];
        $bands   = [
            'cold' => ['label' => 'Cold boot + teardown — fresh process', 'key' => 'cold_ms'],
            'warm' => ['label' => 'Warm recycle + teardown — resident worker', 'key' => 'warm_ms'],
        ];
        foreach (array_keys($bands) as $band) {
            foreach ($apps as $app) {
                $boot = $this->store->boot($app);
                if ($boot === null) {
                    continue;
                }
                // Boot and teardown are the same kind of time — the worker
                // cannot serve another request during either — so each band
                // shows their sum: boot cost + this mode's median per-request
                // cleanup.
                $cleanup = $this->store->cleanupMedian($app, $mode) ?? 0.0;
                $total   = $boot[$bands[$band]['key']] + $cleanup;
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
        // WORKS (>= 0.1 ms). Every row still gets a factor — the no-op rows
        // read "x 0.0" and the anchor row reads "x 1.0" — so the chart states
        // the calculation on each line and the reader can see exactly which
        // boot the numbers are measured against.
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
            foreach ($metrics[$band] as $label => $m) {
                $factors[$bands[$band]['label']][$label] = $m['median'] / max($fastest, 1e-9);
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

        // Prose: the cold race (first boot is what a deploy/server start pays).
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

        // Per-request teardown share, when the dataset carries the split.
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
                $cKeys           = array_keys($shares);
                $cBest           = BenchmarkConfig::appLabel($cKeys[0]);
                $cWorst          = BenchmarkConfig::appLabel($cKeys[count($cKeys) - 1]);
                $cleanupSentence = ' The teardown share of a full request ranges from '
                    . number_format($shares[$cKeys[0]] * 100, 0) . '% (' . $cBest . ') up to '
                    . number_format($shares[$cKeys[count($cKeys) - 1]] * 100, 0) . '% (' . $cWorst . ').';
            }
        }

        return "## Framework startup\n\n"
            . "The framework's own load cost, timed directly: autoloader + kernel/container build + routes + DB connect, "
            . "with no request processed — plus the median post-response teardown, because boot and cleanup are the same "
            . "kind of time: during both, the worker cannot serve another request. **{$best}** pays "
            . SvgChart::fmt($bestMs) . " ms cold against " . SvgChart::fmt($worstMs) . " ms for {$worst}"
            . ' — x ' . SvgChart::fmtFactor($worstMs / max($bestMs, 1e-9)) . " slower. "
            . ($warmBest !== null && isset($warm[$warmBest])
                ? 'A warm recycle (worker restart with opcache warm) is cheaper for everyone: '
                    . SvgChart::fmt($warm[$warmBest]['median']) . ' ms for ' . $warmBest
                    . " at the low end — CodeIgniter and CakePHP re-bootstrap is a state reset there, not a kernel rebuild."
                : '')
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
            $means[$label] = $this->store->ms($app, $mode, $startup) ?? $spread['median'];
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
            $this->spreadCaption(),
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

        return "## Framework startup\n\n"
            . "Router + dispatcher + plain response, no database. The gap here is pure framework bootstrap and dispatch cost: "
            . "**{$best}** responds in " . SvgChart::fmt($bestMs) . " ms (median; " . SvgChart::fmt($bestTm) . " ms trimmed mean) "
            . "against " . SvgChart::fmt($worstMs) . " ms (median) for {$worst} "
            . '— x ' . SvgChart::fmtFactor($worstMs / max($bestMs, 1e-9)) . " slower.\n\n"
            . '![Framework startup — GET /](' . $rel . '/' . $file . ')';
    }

    /**
     * "× the baseline's total time" plot. This is the readable stand-in for a
     * dual axis: every framework is divided by the baseline's own total, so
     * the comparison is scale-free and the baseline is pinned at exactly 1.0×.
     *
     * @param list<string> $apps
     */
    private function speedup(string $dir, string $rel, array $apps, string $baseline, string $mode): string
    {
        $requests = $this->store->commonRequests($mode, $apps);
        $ratios   = $this->store->aggregateTimeRatioVs($baseline, $mode, $requests, $apps);
        if (count($ratios) < 2) {
            return '';
        }
        $values = [];
        $colors = [];
        foreach ($ratios as $app => $mult) {
            $label = BenchmarkConfig::appLabel($app);
            $values[$label] = $mult;
            $colors[$label] = BenchmarkConfig::appColor($app);
        }
        asort($values);
        $base = BenchmarkConfig::appLabel($baseline);
        $svg  = SvgChart::horizontalBars(
            $values,
            $colors,
            'Total response times',
            '1.0 = ' . $base . "'s own total · higher = slower",
            960,
            32
        );
        $file = 'speedup.svg';
        file_put_contents($dir . '/' . $file, $svg);

        // Fastest non-baseline app for the prose.
        $others = $values;
        unset($others[$base]);
        $extra = '';
        if ($others !== []) {
            $closest = array_key_first($others);
            $extra   = " The closest rival is {$closest}, needing x " . SvgChart::fmtFactor($others[$closest]) . ' the same total.';
        }

        return "## Total response times\n\n"
            . 'Total time to serve one of each of the ' . count($requests) . " endpoints, relative to {$base} "
            . "(1.0 = the baseline's own total, higher = slower).{$extra}\n\n"
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
                $this->spreadCaption(),
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
                $pKeys  = array_keys($medians);
                $winner = $pKeys[0] ?? $race['winner'];
                $runner = $pKeys[1] ?? null;
                $sections[] = sprintf(
                    '- **%s** (`%s`): %s at %sms median%s.',
                    $title,
                    $cats[0],
                    BenchmarkConfig::appLabel($winner),
                    SvgChart::fmt((float) ($medians[$winner] ?? $race['winner_ms'])),
                    $runner !== null && ($medians[$winner] ?? 0) > 0
                        ? ', x ' . SvgChart::fmtFactor($medians[$runner] / $medians[$winner]) . ' faster than ' . BenchmarkConfig::appLabel($runner)
                        : ''
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
     * @param list<string> $apps
     */
    private function wins(array $apps, string $baseline, string $mode): string
    {
        $table = (new Tables($this->store))->winsMarkdown($mode, $apps);
        if ($table === '') {
            return '';
        }
        return "## Wins per framework\n\n"
            . "Number of endpoint races won (lowest trimmed mean) per framework.\n\n"
            . $table;
    }

}