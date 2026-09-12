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
     * Win counts per framework, fastest first.
     *
     * @param list<string> $apps
     * @return array<string,int> app => wins
     */
    public function wins(string $mode, array $apps): array
    {
        $counts = $this->store->winCounts($mode, $apps);
        arsort($counts);
        return $counts;
    }

    /**
     * Per-request latency table: rows = requests, columns = apps, winner bolded.
     * Each cell carries the handle/cleanup lifecycle split when the dataset
     * has it (handle_ms + cleanup_ms, which sum to the trimmed mean).
     *
     * @param list<string> $apps
     * @return list<array{request:string,feature:string,cells:array<string,array{ms:?float,winner:bool,handle:?float,cleanup:?float}>}>
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
                    $cells[$app] = ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null];
                    continue;
                }
                $ms = $this->store->ms($app, $mode, $req);
                $cells[$app] = [
                    'ms'      => $ms,
                    'winner'  => false,
                    'handle'  => $this->store->ms($app, $mode, $req, 'handle_ms'),
                    'cleanup' => $this->store->ms($app, $mode, $req, 'cleanup_ms'),
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
     * Markdown win-count table.
     *
     * @param list<string> $apps
     */
    public function winsMarkdown(string $mode, array $apps): string
    {
        $counts = $this->wins($mode, $apps);
        $total  = array_sum($counts);
        if ($total === 0) {
            return '';
        }
        $l = [];
        $l[] = '| Framework | Wins | Share |';
        $l[] = '|---|---:|---:|';
        foreach ($counts as $app => $n) {
            $l[] = sprintf(
                '| %s | %d | %s%% |',
                BenchmarkConfig::appLabel($app),
                $n,
                number_format($n / $total * 100, 0)
            );
        }
        return implode("\n", $l);
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
                $c  = $row['cells'][$app] ?? ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null];
                $ms = $c['ms'];
                if ($ms === null) {
                    $cells[] = ' — |';
                    continue;
                }
                $label = SvgChart::fmt($ms);
                if ($split && $c['handle'] !== null && $c['cleanup'] !== null) {
                    $label .= ' <sub>' . SvgChart::fmt($c['handle']) . '+' . SvgChart::fmt($c['cleanup']) . '</sub>';
                }
                $cells[] = ($c['winner'] ? ' **' . $label . '** |' : ' ' . $label . ' |');
            }
            $l[] = implode('', $cells);
        }
        return implode("\n", $l);
    }

    /**
     * HTML win-count table.
     *
     * @param list<string> $apps
     */
    public function winsHtml(string $mode, array $apps): string
    {
        $counts = $this->wins($mode, $apps);
        $total  = array_sum($counts);
        if ($total === 0) {
            return '';
        }
        $rows = '';
        foreach ($counts as $app => $n) {
            $rows .= sprintf(
                "<tr><td>%s</td><td>%d</td><td>%s%%</td></tr>\n",
                htmlspecialchars(BenchmarkConfig::appLabel($app), ENT_QUOTES, 'UTF-8'),
                $n,
                number_format($n / $total * 100, 0)
            );
        }
        return "<h2>Wins per framework</h2>\n<table>\n<thead><tr><th>Framework</th><th>Wins</th><th>Share</th></tr></thead>\n<tbody>\n{$rows}</tbody>\n</table>";
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
                $c  = $row['cells'][$app] ?? ['ms' => null, 'winner' => false, 'handle' => null, 'cleanup' => null];
                $ms = $c['ms'];
                if ($ms === null) {
                    $body .= '<td class="muted">—</td>';
                    continue;
                }
                $label = SvgChart::fmt($ms);
                if ($split && $c['handle'] !== null && $c['cleanup'] !== null) {
                    $label .= ' <sub>' . SvgChart::fmt($c['handle']) . '+' . SvgChart::fmt($c['cleanup']) . '</sub>';
                }
                $body .= '<td class="' . ($c['winner'] ? 'win' : '') . '">' . $label . '</td>';
            }
            $body .= "</tr>\n";
        }

        $sub = $this->store->hasCleanupSplit()
            ? ' <span class="unit">(sub-line: handle + post-response cleanup, which sum to the total)</span>'
            : '';
        return "<h2>Latency by endpoint <span class=\"unit\">(ms, trimmed mean — lower is better)</span>{$sub}</h2>\n<table class=\"matrix\">\n<thead>{$head}</thead>\n<tbody>\n{$body}</tbody>\n</table>";
    }
}