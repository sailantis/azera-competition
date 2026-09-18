<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

/**
 * Shared result tables, so the Markdown and HTML renderers can never disagree
 * about the numbers.
 */
final class Tables
{
    public function __construct(private readonly ResultStore $store) {}

    /**
     * Per-request latency table: rows = requests, columns = apps, winner bolded.
     *
     * The headline is END-TO-END for the mode: it is the time one request
     * occupies (or blocks) a worker, boot included — in cold/FPM the measured
     * request already pays a fresh boot, in warm/RoadRunner the worker's
     * recycle cost is added to the post-boot request. Each cell also carries
     * the lifecycle split `boot + handle + cleanup`, which sums exactly to the
     * headline, so the reader can see where the time goes instead of taking
     * the total on faith.
     *
     * @param list<string> $apps
     * @return list<array{request:string,feature:string,cells:array<string,array{ms:?float,winner:bool,handle:?float,cleanup:?float,boot:?float}>}>
     */
    public function latencyMatrix(string $mode, array $apps): array
    {
        $rows = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $feature = BenchmarkConfig::featureMap()[$req] ?? '';
            $cells   = [];
            $best    = null;
            foreach ($apps as $app) {
                if ($feature !== '' && !$this->store->supports($app, $feature)) {
                    $cells[$app] = ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null, 'boot' => null];
                    continue;
                }
                $ms = $this->store->msWithBoot($app, $mode, $req);
                $cells[$app] = [
                    'ms'      => $ms,
                    'winner'  => false,
                    'handle'  => $this->store->ms($app, $mode, $req, 'handle_ms'),
                    'cleanup' => $this->store->ms($app, $mode, $req, 'cleanup_ms'),
                    'boot'    => $this->store->rowBootMs($app, $mode, $req),
                ];
                if ($ms !== null && ($best === null || $ms < $best)) {
                    $best = $ms;
                }
            }
            if ($best !== null) {
                foreach ($cells as $app => $c) {
                    if ($c['ms'] !== null && abs($c['ms'] - $best) < 1e-9) {
                        $cells[$app]['winner'] = true;
                    }
                }
            }
            $rows[] = ['request' => $req, 'feature' => $feature, 'cells' => $cells];
        }
        return $rows;
    }

    /**
     * Markdown latency matrix.
     *
     * @param list<string> $apps
     */
    public function latencyMarkdown(string $mode, array $apps): string
    {
        $rows = $this->latencyMatrix($mode, $apps);
        if ($rows === []) {
            return '';
        }
        $header = ['| Request | Workload |'];
        $sep    = ['|---|---|'];
        foreach ($apps as $app) {
            $header[] = ' ' . BenchmarkConfig::appLabel($app) . ' |';
            $sep[] = '---:|';
        }
        $l = [];
        $l[] = implode('', $header);
        $l[] = implode('', $sep);
        $split = $this->store->hasCleanupSplit();
        foreach ($rows as $row) {
            $workload = BenchmarkConfig::workloadFor($row['request']);
            $cells    = ['| `' . $row['request'] . '` | ' . ($workload !== '' ? $workload : '—') . ' |'];
            foreach ($apps as $app) {
                $c  = $row['cells'][$app] ?? ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null, 'boot' => null];
                $ms = $c['ms'];
                if ($ms === null) {
                    $cells[] = ' — |';
                    continue;
                }
                $label = SvgChart::fmt($ms);
                $sub   = self::splitLabel($c);
                if ($split && $sub !== '') {
                    $label .= ' <sub>' . $sub . '</sub>';
                }
                $cells[] = ($c['winner'] ? ' **' . $label . '** |' : ' ' . $label . ' |');
            }
            $l[] = implode('', $cells);
        }
        return implode("\n", $l);
    }

    /**
     * The lifecycle split printed under a headline latency: `boot + handle +
     * cleanup`. Each term is omitted when the dataset does not carry it, so a
     * pre-split dataset renders a bare number and a dataset without boot
     * measurements renders `handle+cleanup`.
     *
     * The three terms are rounded to the headline's own precision and any
     * rounding residue is carried by the largest term, so the printed terms
     * sum to the printed headline EXACTLY — a decomposition the reader can
     * check by adding it up, not one that is off by a display unit
     * (23.4 = 17.3 + 5.97 + 0.025 reads as a bug; it is one).
     *
     * @param array{ms:?float,winner:bool,handle:?float,cleanup:?float,boot:?float} $c
     */
    private static function splitLabel(array $c): string
    {
        $terms = [];
        foreach (['boot', 'handle', 'cleanup'] as $k) {
            if ($c[$k] !== null) {
                $terms[$k] = (float) $c[$k];
            }
        }
        if ($terms === []) {
            return '';
        }
        $total = (float) $c['ms'];
        // Same precision rule as SvgChart::fmt(), so the terms are printed at
        // the precision of the headline they decompose.
        $dec   = $total >= 100 ? 0 : ($total >= 10 ? 1 : ($total >= 1 ? 2 : 3));
        $scale = 10 ** $dec;

        $units    = [];
        $residual = (int) round($total * $scale);
        foreach ($terms as $k => $v) {
            $units[$k] = (int) round($v * $scale);
            $residual -= $units[$k];
        }
        if ($residual !== 0) {
            // Park the residue on the biggest term: it absorbs the most
            // display units with the least relative error.
            $bySize = array_keys($terms);
            usort($bySize, static fn($a, $b) => $terms[$b] <=> $terms[$a]);
            $units[$bySize[0]] = max(0, $units[$bySize[0]] + $residual);
        }

        $out = [];
        foreach (array_keys($terms) as $k) {
            $out[] = number_format($units[$k] / $scale, $dec);
        }
        return implode('+', $out);
    }

    /**
     * HTML latency matrix.
     *
     * @param list<string> $apps
     */
    public function latencyHtml(string $mode, array $apps): string
    {
        $rows = $this->latencyMatrix($mode, $apps);
        if ($rows === []) {
            return '';
        }
        $head = '<tr><th>Request</th><th>Workload</th>';
        foreach ($apps as $app) {
            $head .= sprintf(
                '<th><span class="chip" style="--c:%s">%s</span></th>',
                BenchmarkConfig::appColor($app),
                htmlspecialchars(BenchmarkConfig::appLabel($app), ENT_QUOTES, 'UTF-8')
            );
        }
        $head .= '</tr>';

        $body  = '';
        $split = $this->store->hasCleanupSplit();
        foreach ($rows as $row) {
            $workload = BenchmarkConfig::workloadFor($row['request']);
            $body .= '<tr><td><code>' . htmlspecialchars($row['request'], ENT_QUOTES, 'UTF-8') . '</code></td>'
                . '<td class="muted">' . htmlspecialchars($workload !== '' ? $workload : '—', ENT_QUOTES, 'UTF-8') . '</td>';
            foreach ($apps as $app) {
                $c  = $row['cells'][$app] ?? ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null, 'boot' => null];
                $ms = $c['ms'];
                if ($ms === null) {
                    $body .= '<td class="muted">—</td>';
                    continue;
                }
                $label = SvgChart::fmt($ms);
                $sub   = self::splitLabel($c);
                if ($split && $sub !== '') {
                    $label .= ' <sub>' . $sub . '</sub>';
                }
                $body .= '<td class="' . ($c['winner'] ? 'win' : '') . '">' . $label . '</td>';
            }
            $body .= "</tr>\n";
        }

        $sub = $this->store->hasCleanupSplit()
            ? ' <span class="unit">(sub-line: boot + handle + post-response cleanup, which sum to the total)</span>'
            : '';
        if ($this->store->hasBoot()) {
            if (in_array($mode, ['cold', 'php-fpm'], true) && $this->store->coldBootIncluded()) {
                $sub .= ' <span class="unit">— cold rows and charts are end-to-end: each iteration pays a fresh '
                    . 'framework boot inside the request clock (FPM story)</span>';
            } elseif (in_array($mode, ['warm', 'roadrunner'], true)) {
                // Follow the stamped pool model: a pool that never recycles puts
                // no boot into a row, so claiming one here would be wrong.
                $sub .= match ($this->store->rrRecycleModel()) {
                    'never' => ' <span class="unit">— the worker is never recycled (max_jobs=0), so each '
                        . 'number is the measured request with no boot in it</span>',
                    'every' => ' <span class="unit">— every row and chart point adds this request\'s share of '
                        . 'the worker boot (recycle ÷ max_jobs)</span>',
                    default => ' <span class="unit">— every row and chart point adds the worker\'s boot (warm '
                        . 'recycle), so each number is the full time one request keeps that worker busy</span>'
                };
            }
        }
        return "<h2>Latency by endpoint <span class=\"unit\">(ms, trimmed mean — lower is better)</span>{$sub}</h2>\n<table class=\"matrix\">\n<thead>{$head}</thead>\n<tbody>\n{$body}</tbody>\n</table>";
    }
}