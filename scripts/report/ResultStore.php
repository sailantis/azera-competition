<?php

declare(strict_types=1);

namespace AzeraCompetition\Report;

use RuntimeException;

/**
 * Loads benchmark result JSON and exposes it as a queryable dataset.
 *
 * Two ways in:
 *   - load():  a single combined run (all frameworks in one file). Preferred.
 *   - merge(): several per-framework "azera-vs-X" files. When the same app
 *              appears in more than one file, the measurement from the file
 *              with the newest env.timestamp wins.
 *
 * Everything the renderers need (ordering, feature participation, winners,
 * speed-ups, peaks) hangs off here so the renderers stay dumb.
 */
final class ResultStore
{
    /** @var array<string,mixed> */
    private array $env;

    /** @var array<string,array<string,array<string,array<string,mixed>>>> app => mode => request => row */
    private array $data = [];

    /** @var list<string> */
    private array $apps = [];

    /** @var list<string> */
    private array $modes = [];

    private function __construct(array $env)
    {
        $this->env = $env;
    }

    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Result file not found: {$path}");
        }
        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw) || !isset($raw['apps'])) {
            throw new RuntimeException("Malformed result file (no \"apps\"): {$path}");
        }
        $store = new self($raw['env'] ?? []);
        foreach ($raw['apps'] as $app) {
            $store->ingest($app, (string) ($raw['env']['timestamp'] ?? ''));
        }
        return $store;
    }

    /**
     * Merge several result files. Newest env.timestamp per app wins.
     *
     * @param list<string> $paths
     */
    public static function merge(array $paths): self
    {
        // Sort files oldest-first so later ingests (newer) overwrite.
        $ordered = [];
        foreach ($paths as $p) {
            $raw = json_decode((string) file_get_contents($p), true);
            $ts  = (string) ($raw['env']['timestamp'] ?? '');
            $ordered[] = ['path' => $p, 'ts' => $ts, 'raw' => $raw];
        }
        usort($ordered, static fn($a, $b) => strcmp($a['ts'], $b['ts']));

        $env   = [];
        $store = null;
        /** @var array<string,string> $seen app => timestamp already applied */
        $seen = [];
        foreach ($ordered as $file) {
            $raw = $file['raw'];
            if (!is_array($raw) || !isset($raw['apps'])) {
                continue;
            }
            // Env from the newest file overall (last we see).
            $env = $raw['env'] ?? $env;
            if ($store === null) {
                $store = new self($env);
            }
            foreach ($raw['apps'] as $app) {
                $key = (string) ($app['app'] ?? '');
                $ts  = (string) ($raw['env']['timestamp'] ?? '');
                // Newest timestamp for this app wins.
                if (isset($seen[$key]) && strcmp($ts, $seen[$key]) < 0) {
                    continue;
                }
                $seen[$key] = $ts;
                $store->ingest($app, $ts, true);
            }
        }
        if ($store === null) {
            throw new RuntimeException('No usable result files to merge.');
        }
        $store->env = $env;
        return $store;
    }

    /**
     * @param array<string,mixed> $app
     */
    private function ingest(array $app, string $timestamp, bool $replace = false): void
    {
        $key = (string) ($app['app'] ?? '');
        if ($key === '') {
            return;
        }
        if ($replace) {
            $this->data[$key] = [];
        }
        foreach (($app['modes'] ?? []) as $modeName => $mode) {
            foreach (($mode['requests'] ?? []) as $row) {
                $this->data[$key][$modeName][(string) $row['request']] = $row;
                if (!in_array($modeName, $this->modes, true)) {
                    $this->modes[] = $modeName;
                }
            }
        }
        if (!in_array($key, $this->apps, true)) {
            $this->apps[] = $key;
        }
    }

    /** @return array<string,mixed> */
    public function env(): array
    {
        return $this->env;
    }

    /**
     * Apps present in the dataset, ordered by BenchmarkConfig::appOrder(),
     * then any unknown extras alphabetically.
     *
     * @return list<string>
     */
    public function apps(): array
    {
        $order = BenchmarkConfig::appOrder();
        $known = array_values(array_filter($order, fn($a) => in_array($a, $this->apps, true)));
        $extra = array_values(array_diff($this->apps, $order));
        sort($extra);
        return array_merge($known, $extra);
    }

    /** @return list<string> */
    public function modes(): array
    {
        $order = ['warm', 'cold'];
        $known = array_values(array_filter($order, fn($m) => in_array($m, $this->modes, true)));
        $extra = array_values(array_diff($this->modes, $order));
        return array_merge($known, $extra);
    }

    public function has(string $app, string $mode, string $request): bool
    {
        return isset($this->data[$app][$mode][$request]);
    }

    /**
     * Trimmed-mean latency in ms for one measurement.
     */
    public function ms(string $app, string $mode, string $request, string $metric = 'trimmed_mean_ms'): ?float
    {
        $row = $this->data[$app][$mode][$request] ?? null;
        if ($row === null || !isset($row[$metric])) {
            return null;
        }
        return (float) $row[$metric];
    }

    /**
     * Full row (median, p95, runs, etc.) for one measurement.
     *
     * @return array<string,mixed>|null
     */
    public function row(string $app, string $mode, string $request): ?array
    {
        return $this->data[$app][$mode][$request] ?? null;
    }

    /**
     * Whether this dataset carries a true per-request minimum. Datasets
     * recorded before the harness emitted `min_ms` cannot draw a
     * fastest-observation whisker, and the report says so rather than
     * mislabelling the range.
     */
    public function hasMin(): bool
    {
        foreach ($this->data as $modes) {
            foreach ($modes as $requests) {
                foreach ($requests as $row) {
                    return array_key_exists('min_ms', $row);
                }
            }
        }
        return false;
    }

    /**
     * The three numbers a dot-and-range mark needs: typical, best and tail.
     *
     * `min_ms` is only present in datasets produced after it was added to the
     * harness; older files fall back to the median so the whisker degrades to
     * a point rather than to nonsense.
     *
     * @return array{median:float,low:float,high:float}|null
     */
    public function spread(string $app, string $mode, string $request): ?array
    {
        $row = $this->data[$app][$mode][$request] ?? null;
        if ($row === null) {
            return null;
        }
        $median = $this->ms($app, $mode, $request, 'median_ms')
            ?? $this->ms($app, $mode, $request);
        if ($median === null) {
            return null;
        }
        $high = $this->ms($app, $mode, $request, 'p95_ms') ?? $median;
        $low  = $this->ms($app, $mode, $request, 'min_ms') ?? min($median, $high);
        // Guard against an inverted or empty whisker.
        $low  = min($low, $median);
        $high = max($high, $median);
        return ['median' => $median, 'low' => $low, 'high' => $high];
    }

    public function peakMem(string $app, string $mode, string $request): ?int
    {
        $row = $this->data[$app][$mode][$request] ?? null;
        return $row === null ? null : (int) $row['peak_mem'];
    }

    /**
     * Highest peak memory across all requests for an app/mode — the number
     * that matters for "how much does this framework need".
     */
    public function maxPeakMem(string $app, string $mode): ?int
    {
        $max = null;
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $mem = $this->peakMem($app, $mode, $req);
            if ($mem !== null && ($max === null || $mem > $max)) {
                $max = $mem;
            }
        }
        return $max;
    }

    /**
     * Peak memory expressed as a spread across the endpoints, in bytes:
     * lightest endpoint, median endpoint, heaviest endpoint. Some frameworks
     * only spike on the ORM routes, so the range says more than the single
     * worst number.
     *
     * @return array{median:int,low:int,high:int}|null
     */
    public function peakMemRange(string $app, string $mode): ?array
    {
        $vals = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $mem = $this->peakMem($app, $mode, $req);
            if ($mem !== null) {
                $vals[] = $mem;
            }
        }
        if ($vals === []) {
            return null;
        }
        sort($vals);
        return [
            'low'    => $vals[0],
            'median' => $vals[(int) floor((count($vals) - 1) / 2)],
            'high'   => $vals[count($vals) - 1],
        ];
    }

    /**
     * Requests present for a mode, in canonical order.
     *
     * @return list<string>
     */
    public function requests(string $mode): array
    {
        $present = [];
        foreach ($this->data as $modes) {
            foreach (($modes[$mode] ?? []) as $req => $_) {
                $present[$req] = true;
            }
        }
        $canonical = array_values(array_filter(
            BenchmarkConfig::requestOrder(),
            fn($r) => isset($present[$r])
        ));
        $extra = array_keys(array_diff_key($present, array_flip($canonical)));
        sort($extra);
        return array_merge($canonical, $extra);
    }

    public function featuresFor(string $app): array
    {
        return BenchmarkConfig::adapterFeatures()[$app] ?? [];
    }

    public function supports(string $app, string $feature): bool
    {
        return in_array($feature, $this->featuresFor($app), true);
    }

    /**
     * Requests belonging to a feature, in canonical order.
     *
     * @return list<string>
     */
    public function requestsForFeature(string $feature, string $mode): array
    {
        $map = BenchmarkConfig::featureMap();
        return array_values(array_filter(
            $this->requests($mode),
            fn($r) => ($map[$r] ?? null) === $feature
        ));
    }

    /**
     * Apps that both support the feature and have a measurement, mapped to
     * their trimmed-mean ms, sorted fastest first.
     *
     * @param list<string>|null $restrict optional whitelist of apps
     * @return array<string,float>
     */
    public function participants(string $feature, string $mode, string $request, ?array $restrict = null): array
    {
        $out = [];
        foreach ($this->apps() as $app) {
            if ($restrict !== null && !in_array($app, $restrict, true)) {
                continue;
            }
            if (!$this->supports($app, $feature)) {
                continue;
            }
            $ms = $this->ms($app, $mode, $request);
            if ($ms !== null) {
                $out[$app] = $ms;
            }
        }
        asort($out);
        return $out;
    }

    /**
     * Winner + runner-up for a request. Returns null when fewer than two
     * frameworks compete (no meaningful race).
     *
     * @param list<string>|null $restrict
     * @return array{winner:string,runner:?string,winner_ms:float,runner_ms:?float,margin:?float,speedup:?float}|null
     */
    public function race(string $feature, string $mode, string $request, ?array $restrict = null): ?array
    {
        $p = $this->participants($feature, $mode, $request, $restrict);
        if (count($p) < 2) {
            return null;
        }
        $keys   = array_keys($p);
        $winner = $keys[0];
        $runner = $keys[1];
        return [
            'winner'    => $winner,
            'runner'    => $runner,
            'winner_ms' => $p[$winner],
            'runner_ms' => $p[$runner],
            'margin'    => $p[$runner] - $p[$winner],
            'speedup'   => $p[$winner] > 0 ? $p[$runner] / $p[$winner] : null,
        ];
    }

    /**
     * Win counts per app across every feature race in a mode.
     *
     * @param list<string>|null $restrict
     * @return array<string,int>
     */
    public function winCounts(string $mode, ?array $restrict = null): array
    {
        $counts = [];
        $apps   = $restrict ?? $this->apps();
        foreach ($apps as $app) {
            $counts[$app] = 0;
        }
        foreach (BenchmarkConfig::featureOrder() as $feature) {
            foreach ($this->requestsForFeature($feature, $mode) as $req) {
                $race = $this->race($feature, $mode, $req, $restrict);
                if ($race === null) {
                    continue;
                }
                $counts[$race['winner']] = ($counts[$race['winner']] ?? 0) + 1;
            }
        }
        return $counts;
    }

    /**
     * How many times slower an app is than the baseline, per request.
     * Returns null when either side has no measurement.
     *
     * @param list<string>|null $restrict
     * @return array<string,float> app => multiplier (baseline itself = 1.0)
     */
    public function speedupVs(string $baseline, string $mode, string $request, ?array $restrict = null): array
    {
        $base = $this->ms($baseline, $mode, $request);
        if ($base === null || $base <= 0) {
            return [];
        }
        $out = [];
        foreach ($this->apps() as $app) {
            if ($restrict !== null && !in_array($app, $restrict, true)) {
                continue;
            }
            $ms = $this->ms($app, $mode, $request);
            if ($ms !== null) {
                $out[$app] = $ms / $base;
            }
        }
        return $out;
    }

    /**
     * Geometric-free aggregate speed-up vs baseline across several requests:
     * sum of baseline ms / sum of app ms ("total time to serve one of each").
     *
     * @param list<string> $requests
     * @param list<string>|null $restrict
     * @return array<string,float> app => multiplier
     */
    public function aggregateSpeedupVs(string $baseline, string $mode, array $requests, ?array $restrict = null): array
    {
        $baseTotal = 0.0;
        foreach ($requests as $req) {
            $baseTotal += (float) ($this->ms($baseline, $mode, $req) ?? 0.0);
        }
        if ($baseTotal <= 0) {
            return [];
        }
        $out = [];
        foreach ($this->apps() as $app) {
            if ($restrict !== null && !in_array($app, $restrict, true)) {
                continue;
            }
            $total = 0.0;
            $ok    = true;
            foreach ($requests as $req) {
                $ms = $this->ms($app, $mode, $req);
                if ($ms === null) {
                    $ok = false;
                    break;
                }
                $total += $ms;
            }
            if ($ok && $total > 0) {
                $out[$app] = $baseTotal / $total;
            }
        }
        arsort($out);
        return $out;
    }

    /**
     * Inverse of aggregateSpeedupVs, expressed as "× the baseline's total
     * time" — the baseline is exactly 1.0 and higher means slower, which is
     * the natural reading for a chart. Only apps that measured *every* request
     * take part, so no framework is flattered by a shorter route list.
     *
     * @param list<string> $requests
     * @param list<string>|null $restrict
     * @return array<string,float> app => multiple of baseline total time
     */
    public function aggregateTimeRatioVs(string $baseline, string $mode, array $requests, ?array $restrict = null): array
    {
        $speedups = $this->aggregateSpeedupVs($baseline, $mode, $requests, $restrict);
        $out      = [];
        foreach ($speedups as $app => $speedup) {
            if ($speedup > 0) {
                $out[$app] = 1 / $speedup;
            }
        }
        asort($out);
        return $out;
    }

    /**
     * Requests that every app in $apps has a measurement for (fair comparison).
     *
     * @param list<string> $apps
     * @return list<string>
     */
    public function commonRequests(string $mode, array $apps): array
    {
        $out = [];
        foreach ($this->requests($mode) as $req) {
            $all = true;
            foreach ($apps as $app) {
                if ($this->ms($app, $mode, $req) === null) {
                    $all = false;
                    break;
                }
            }
            if ($all) {
                $out[] = $req;
            }
        }
        return $out;
    }
}
