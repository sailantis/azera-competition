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
        $charts   = $this->view['charts'] ?? ['hero', 'speedup', 'features', 'memory', 'wins'];

        $l = [];
        $l[] = '# ' . ($this->view['title'] ?? 'Benchmark');
        $l[] = '';
        $l[] = (string) ($this->view['subtitle'] ?? '');
        $l[] = '';
        $l[] = $this->envBlock();
        $l[] = '';

        foreach ($charts as $chart) {
            $lines = $this->chart($chart, $svgDir, $svgRelPrefix, $apps, $baseline, $mode);
            if ($lines !== null) {
                $l[] = $lines;
                $l[] = '';
            }
        }

        // Shared tables so both outputs agree on the numbers.
        $tables = new Tables($this->store);
        $l[] = '## Latency by endpoint';
        $l[] = '';
        $l[] = 'Trimmed mean in milliseconds, lower is better. **Bold** = fastest for that endpoint.';
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
    private function chart(string $chart, string $svgDir, string $rel, array $apps, string $baseline, string $mode): ?string
    {
        return match ($chart) {
            'hero'     => $this->hero($svgDir, $rel, $apps, $mode),
            'speedup'  => $this->speedup($svgDir, $rel, $apps, $baseline, $mode),
            'features' => $this->features($svgDir, $rel, $apps, $mode),
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
            ? 'dot = median · whisker = fastest observation → p95 (ms)'
            : 'dot = median · whisker = median → p95 (ms)';
    }

    /**
     * @param list<string> $apps
     */
    private function hero(string $dir, string $rel, array $apps, string $mode): string
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
        $svg = SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'ms',
            true,
            960,
            0,
            'Framework startup — GET /',
            $this->spreadCaption()
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
            . "— " . SvgChart::fmt($worstMs / max($bestMs, 1e-9)) . "× slower.\n\n"
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
            'Total time vs ' . $base,
            '1.0× = ' . $base . "'s own total · higher = slower",
            '×',
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
            $extra   = " The closest rival is {$closest}, needing " . SvgChart::fmt($others[$closest]) . '× the same total.';
        }

        return "## Total time vs {$base}\n\n"
            . 'Total time to serve one of each of the ' . count($requests) . " endpoints, relative to {$base} "
            . "(1.0× = the baseline's own total, higher = slower).{$extra}\n\n"
            . '![Total time vs ' . $base . '](' . $rel . '/' . $file . ')';
    }

    /**
     * @param list<string> $apps
     */
    private function features(string $dir, string $rel, array $apps, string $mode): string
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
                foreach ($measured as $app => $spread) {
                    $label = BenchmarkConfig::appLabel($app);
                    // Grouped by display label so the shared series list works
                    // across every request in this feature.
                    $metrics[$req][$label] = $spread;
                    $colors[$label] = BenchmarkConfig::appColor($app);
                    $labels[$label] = $label;
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
                true,
                960,
                0,
                $title,
                $this->spreadCaption()
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
                        ? ', ' . SvgChart::fmt($medians[$runner] / $medians[$winner]) . '× faster than ' . BenchmarkConfig::appLabel($runner)
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
        $svg = SvgChart::dotRange(
            [''],
            ['' => $metrics],
            $colors,
            'MB',
            false,
            960,
            0,
            'Peak memory footprint',
            'dot = median endpoint · whisker = lightest → heaviest endpoint (MB)'
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
            . "(" . SvgChart::fmt($worstMb / max($bestMb, 1e-9)) . "× more). Each dot is the median endpoint and the "
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