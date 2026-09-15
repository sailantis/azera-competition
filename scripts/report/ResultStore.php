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

    /**
     * Webserver-overhead pseudo-apps from the real-deployment dataset
     * (`floors` top-level key: floor-http = static file via nginx, floor-php =
     * hello-world via a freshly spawned FPM worker, floor-rr = bare resident
     * RoadRunner worker). These are NOT frameworks and are excluded from
     * apps(); they exist to make the constant server/spawn cost explicit, so
     * a real-FPM number can be read as "floor + framework boot".
     *
     * @var list<array<string,mixed>>
     */
    private array $floors = [];

    /** @var array<string,array<string,array<string,array<string,mixed>>>> app => mode => request => row */
    private array $data = [];

    /**
     * Framework boot cost, when the dataset carries it:
     * app => ['cold_ms' => float, 'warm_ms' => float].
     * Datasets produced before the harness timed bootstrap() have no entry —
     * the startup chart falls back to the warm GET / measurement in that case.
     *
     * @var array<string,array{cold_ms:float,warm_ms:float}>
     */
    private array $boot = [];

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
        foreach (($raw['floors'] ?? []) as $floor) {
            if (is_array($floor) && isset($floor['app'])) {
                $store->floors[] = $floor;
            }
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
            unset($this->boot[$key]);
        }
        if (isset($app['boot']['cold_ms'], $app['boot']['warm_ms'])) {
            $this->boot[$key] = [
                'cold_ms' => (float) $app['boot']['cold_ms'],
                'warm_ms' => (float) $app['boot']['warm_ms'],
            ];
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
     * Whether this dataset's cold-mode request timings INCLUDE the
     * per-iteration boot in the request clock (the FPM story). Datasets
     * recorded before the flag existed timed the request only, with boot
     * measured separately — the report captions must not claim boot is
     * included for those.
     */
    public function coldBootIncluded(): bool
    {
        return (bool) ($this->env['cold_boot_included'] ?? false);
    }

    /**
     * Iterations per run recorded on the measured rows. The captions used to
     * hardcode "1000", which is wrong for the real-deployment dataset (its
     * client defaults to 1000 too, but per-app runs are commonly smaller) and
     * for any smoke run. Returns null when the rows disagree or predate the
     * field, so callers fall back to a budget-free wording.
     */
    public function iterationsPerRun(): ?int
    {
        $seen = [];
        foreach ($this->data as $modes) {
            foreach ($modes as $rows) {
                foreach ($rows as $row) {
                    $n = (int) ($row['iterations_per_run'] ?? 0);
                    if ($n > 0) {
                        $seen[$n] = true;
                    }
                }
            }
        }
        return count($seen) === 1 ? (int) array_key_first($seen) : null;
    }

    /**
     * Iterations per run for ONE mode, when every row of that mode agrees.
     *
     * A dataset can mix budgets per mode: results/real-deployments.json was
     * rebuilt with a fresh RoadRunner block (1000x10) while the PHP-FPM block
     * — valid, expensive to re-measure (~5.3 min/app) — stayed on 300x5. Each
     * real view prints ONE mode, so the mode-aware number is the honest one;
     * iterationsPerRun() alone would return null for the mixed dataset and
     * both views would fall back to a budget-free (or wrong) wording.
     */
    public function iterationsPerRunFor(string $mode): ?int
    {
        $seen = [];
        foreach ($this->data as $modes) {
            foreach (($modes[$mode] ?? []) as $row) {
                $n = (int) ($row['iterations_per_run'] ?? 0);
                if ($n > 0) {
                    $seen[$n] = true;
                }
            }
        }
        return count($seen) === 1 ? (int) array_key_first($seen) : null;
    }

    /**
     * The pm.max_requests the measured FPM pool ran with, or null when the
     * dataset does not say (every dataset written before this was stamped).
     *
     * The real-FPM view must branch on this: at 0 the worker persists and the
     * app boots per request; at 1 the worker is destroyed per request, which
     * adds a process spawn to every row. Same view name, different physics —
     * so prose that assumes a value is prose that can contradict the data.
     */
    public function fpmMaxRequests(): ?int
    {
        $v = $this->env()['fpm_max_requests'] ?? null;
        return $v === null ? null : (int) $v;
    }

    /**
     * Webserver-overhead probes present in the dataset:
     * app => ['mode' => .., 'request' => .., 'ms' => float]. Empty for every
     * dataset produced by the in-process harness.
     *
     * @return array<string,array{mode:string,request:string,ms:float}>
     */
    public function floors(): array
    {
        $out = [];
        foreach ($this->floors as $floor) {
            $app = (string) $floor['app'];
            foreach (($floor['modes'] ?? []) as $mode => $m) {
                $row = $m['requests'][0] ?? null;
                if ($row === null) {
                    continue;
                }
                $out[$app] = [
                    'mode'    => (string) $mode,
                    'request' => (string) ($row['request'] ?? ''),
                    'ms'      => (float) ($row['trimmed_mean_ms'] ?? $row['mean_ms'] ?? 0),
                ];
            }
        }
        return $out;
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

    /**
     * Whether this dataset carries per-framework boot measurements
     * (cold_ms/warm_ms from run-app.php's measureBoot()).
     */
    public function hasBoot(): bool
    {
        return $this->boot !== [];
    }

    /**
     * Boot cost for one app: ['cold_ms' => .., 'warm_ms' => ..], or null when
     * the dataset predates the boot measurement.
     *
     * @return array{cold_ms:float,warm_ms:float}|null
     */
    public function boot(string $app): ?array
    {
        return $this->boot[$app] ?? null;
    }

    /**
     * Median per-request framework rebuild in cold mode — the boot share the
     * harness times inside every cold row (fork-per-iteration: the forked
     * child calls bootstrap() in a genuinely fresh process that inherited
     * warm opcache bytecode via copy-on-write, exactly the master→worker
     * inheritance real PHP-FPM gets). This is the "virtual cold boot": what
     * a recycled FPM worker rebuilds per request, as opposed to the very
     * first boot in a process (boot() cold_ms), which additionally pays
     * one-time compile / FS-cache costs.
     *
     * Null when the dataset's cold rows carry no boot_ms (pre-fork datasets).
     */
    public function fpmBootMedian(string $app): ?float
    {
        $values = [];
        foreach (['cold', 'php-fpm'] as $mode) {
            foreach ($this->data[$app][$mode] ?? [] as $row) {
                if (isset($row['boot_ms'])) {
                    $values[] = (float) $row['boot_ms'];
                }
            }
            if ($values !== []) {
                break; // a dataset never carries both names for the same side
            }
        }
        if ($values === []) {
            return null;
        }
        sort($values);
        return $values[(int) floor((count($values) - 1) / 2)];
    }

    /**
     * Per-request framework boot for one app in one mode, in ms — the boot
     * share a request actually waits for in that deployment model. Null when
     * the dataset carries no boot measurement for that mode.
     *
     * The two modes time their boot in different places, so the two numbers
     * come from different fields:
     *
     *   cold/php-fpm — every iteration runs in a forked child that boots,
     *     dispatches and cleans up INSIDE the request clock (the FPM story).
     *     The measured boot is therefore already part of trimmed_mean_ms;
     *     the row's own boot_ms reports the split, so it is read from there.
     *
     *   warm/roadrunner — a resident worker boots ONCE, so the per-request
     *     rows contain no boot at all (their boot_ms is a guarded no-op
     *     re-boot, ~0.0002 ms). The recycle a worker pays to come back —
     *     measureBoot()'s warm_ms — is the boot this mode's requests carry,
     *     so it is added on top of the measured request time.
     *
     * This is what makes a feature row mean "how long the worker is occupied
     * by one request, boot included" instead of "the request minus the boot".
     */
    public function modeBootMs(string $app, string $mode): ?float
    {
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            $values = [];
            foreach ($this->data[$app][$mode] ?? [] as $row) {
                if (isset($row['boot_ms'])) {
                    $values[] = (float) $row['boot_ms'];
                }
            }
            if ($values === []) {
                return null;
            }
            sort($values);
            return $values[(int) floor((count($values) - 1) / 2)];
        }
        if (in_array($mode, ['warm', 'roadrunner'], true)) {
            $boot = $this->boot[$app] ?? null;
            return $boot['warm_ms'] ?? null;
        }
        return null;
    }

    /**
     * End-to-end per-request latency INCLUDING the mode's boot: the whole
     * time one request occupies (or blocks) the worker for a given app, in
     * the given deployment model — boot + handle + cleanup.
     *
     * Cold/php-fpm rows already carry their boot inside trimmed_mean_ms
     * (fork-per-iteration times bootstrap() in the request clock), so this
     * returns the measured total. Warm/roadrunner rows measure post-boot
     * work only, so the worker's recycle cost (boot().warm_ms) is added.
     *
     * Kept separate from ms() — which is what the charts and every ranking
     * use — so the boot-inclusive figure can be printed next to the measured
     * one without silently changing who wins a race.
     */
    public function msWithBoot(string $app, string $mode, string $request): ?float
    {
        $ms = $this->ms($app, $mode, $request);
        return $ms === null ? null : $ms + $this->bootAddOn($app, $mode);
    }

    /**
     * Boot time to ADD to a mode's measured request times to turn them into
     * per-request worker occupancy.
     *
     *   cold/php-fpm — 0.0: the forked child already times bootstrap() inside
     *     the request clock, so the measured number IS the occupancy and
     *     adding anything would double-count the boot.
     *
     *   warm/roadrunner — boot().warm_ms: the warm rows measure post-boot
     *     work only, and this is what the deployment pays to bring the worker
     *     back between requests. Added to every row so the figure answers
     *     "how long is this worker busy with one request" — which is what a
     *     recycled worker or a queuing pool actually experiences.
     *
     * 0.0 (never null) when the dataset carries no boot numbers, so callers
     * can add it unconditionally.
     */
    private function bootAddOn(string $app, string $mode): float
    {
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            return 0.0;
        }
        return (float) ($this->modeBootMs($app, $mode) ?? 0.0);
    }

    /**
     * The boot term to print for ONE request row of the lifecycle split, so
     * `boot + handle + cleanup` decomposes the headline exactly rather than
     * approximately.
     *
     * The headline contains this row's OWN boot, not the app-wide median: in
     * cold mode the row's boot_ms is precisely the share that is inside
     * trimmed_mean_ms. Printing the median instead (modeBootMs) left the
     * sub-line off by the median-vs-row difference — up to a few 0.01 ms, i.e.
     * visible in the printed precision (2026-09-14).
     *
     * Warm rows measure no per-request boot, so they use the worker's one-time
     * recycle cost (modeBootMs -> boot().warm_ms) — the same value the headline
     * adds. Returns null when the dataset carries no boot for the row.
     */
    public function rowBootMs(string $app, string $mode, string $request): ?float
    {
        if (in_array($mode, ['cold', 'php-fpm'], true)) {
            $row = $this->data[$app][$mode][$request] ?? null;
            return $row !== null && isset($row['boot_ms']) ? (float) $row['boot_ms'] : null;
        }
        return $this->modeBootMs($app, $mode);
    }

    /**
     * The name the dataset uses for its cold side: 'cold' in raw run files,
     * 'php-fpm' in derive-fpm.php's relabelled deployments file. Null when
     * the dataset has no cold-side rows.
     */
    public function coldModeName(): ?string
    {
        foreach (['cold', 'php-fpm'] as $m) {
            foreach ($this->data as $modes) {
                if (($modes[$m] ?? []) !== []) {
                    return $m;
                }
            }
        }
        return null;
    }

    /**
     * Whether this dataset carries the handle/cleanup lifecycle split
     * (handle_ms + cleanup_ms per request row, from the adapters'
     * dispatch()/cleanup() separation).
     */
    public function hasCleanupSplit(): bool
    {
        foreach ($this->data as $modes) {
            foreach ($modes as $requests) {
                foreach ($requests as $row) {
                    return isset($row['handle_ms'], $row['cleanup_ms']);
                }
            }
        }
        return false;
    }

    /**
     * Average cleanup (post-response teardown) share of a request for one app
     * in one mode — mean of cleanup_ms / trimmed_mean_ms across all measured
     * requests. Null when the dataset predates the split or the app's rows
     * carry no split.
     *
     * @return float|null cleanup share in [0..1]
     */
    public function cleanupShare(string $app, string $mode): ?float
    {
        $ratios = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            if ($row === null || !isset($row['handle_ms'], $row['cleanup_ms'], $row['trimmed_mean_ms'])) {
                continue;
            }
            $total = (float) $row['trimmed_mean_ms'];
            if ($total <= 0.0) {
                continue;
            }
            $ratios[] = (float) $row['cleanup_ms'] / $total;
        }
        if ($ratios === []) {
            return null;
        }
        sort($ratios);
        return $ratios[(int) floor((count($ratios) - 1) / 2)];
    }

    /**
     * Median post-response teardown in ms for one app in one mode — median
     * of cleanup_ms across all measured requests. Null when the dataset
     * predates the split.
     */
    public function cleanupMedian(string $app, string $mode): ?float
    {
        $values = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            if ($row === null || !isset($row['cleanup_ms'])) {
                continue;
            }
            $values[] = (float) $row['cleanup_ms'];
        }
        if ($values === []) {
            return null;
        }
        sort($values);
        return $values[(int) floor((count($values) - 1) / 2)];
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
     * These are PER-REQUEST WORKER OCCUPANCY, not bare request time: the
     * deployment model's boot is added (bootAddOn), so a warm/roadrunner dot
     * carries the worker's recycle cost exactly like the cold dot carries its
     * FPM rebuild. Charts and tables therefore agree on what a cell means —
     * the whole time one request keeps the worker busy.
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
        $add  = $this->bootAddOn($app, $mode);
        $low += $add;
        $median += $add;
        $high += $add;
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

    // --- Resident-worker memory (the opt-in RoadRunner probe) --------------
    //
    // deploy/rr/worker.php answers an `X-Mem-Probe: 1` request with the
    // worker's own heap numbers; scripts/http-bench.php makes exactly one such
    // request per endpoint, AFTER its timed loop. Read them with the caveats
    // below in mind — they are not the same kind of measurement as peak_mem.

    /**
     * Framework data structures resident after bootstrap(), in bytes: the
     * PHP heap with the application built but no request served. This is the
     * one clean "how much does this framework need to exist" number — with
     * opcache on, compiled bytecode lives in shared memory and is NOT counted,
     * so what remains is exactly the framework's own objects.
     *
     * Taken from the first probed row: the worker boots once and the value is
     * a property of the worker, not of the request (verified — it is identical
     * across endpoints). Returns null when the dataset predates the probe or
     * was measured under php-fpm (the worker is recycled, so there is no
     * resident state to report).
     */
    public function residentBootHeap(string $app, string $mode): ?int
    {
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            if ($row !== null && (int) ($row['mem_boot_heap'] ?? 0) > 0) {
                return (int) $row['mem_boot_heap'];
            }
        }
        return null;
    }

    /**
     * PHP heap after the LAST probed endpoint of the run, in bytes.
     *
     * CAUTION — this is an ORDER-DEPENDENT cumulative reading, not a
     * footprint. The probe fires once per endpoint in the harness's request
     * order and reads the whole resident heap, so the final value includes
     * every earlier endpoint's retained state. It exists to expose GROWTH
     * (retained bytes per endpoint), not to rank frameworks on a per-request
     * cost. Use residentBootHeap() for the footprint comparison.
     */
    public function residentHeap(string $app, string $mode): ?int
    {
        $last = null;
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            if ($row !== null && (int) ($row['mem_heap'] ?? 0) > 0) {
                $last = (int) $row['mem_heap'];
            }
        }
        return $last;
    }

    /**
     * Largest PHP heap reached at ANY probed endpoint, in bytes — the
     * high-water mark of the resident run. Unlike residentHeap()'s end state
     * this is order-independent, so it is a legitimate "worst it got" number.
     * Null when the probe is absent.
     */
    public function residentPeakHeap(string $app, string $mode): ?int
    {
        $peak = null;
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            $v   = (int) ($row['mem_heap'] ?? 0);
            if ($row !== null && $v > 0 && ($peak === null || $v > $peak)) {
                $peak = $v;
            }
        }
        return $peak;
    }

    /**
     * The raw per-endpoint resident reading, kept SEPARATE from the chart
     * accessors above because this is a trajectory and they are summary
     * scalars. Order is the harness's own (the probe fires once per endpoint,
     * in request order), which is the only order in which the cumulative heap
     * is meaningful — sorting or ranking these rows would produce a series the
     * probe never measured.
     *
     * @return list<array{request:string,heap:int}>
     */
    public function residentTrajectory(string $app, string $mode): array
    {
        $out = [];
        foreach (BenchmarkConfig::requestOrder() as $req) {
            $row = $this->data[$app][$mode][$req] ?? null;
            $v   = (int) ($row['mem_heap'] ?? 0);
            if ($row !== null && $v > 0) {
                $out[] = ['request' => $req, 'heap' => $v];
            }
        }
        return $out;
    }

    /**
     * Retained heap growth across the probed endpoints, in bytes:
     * residentHeap() minus residentBootHeap(). Same caveat as residentHeap()
     * — endpoint-order dependent. Null when the probe is absent or the heap
     * never rose above the boot baseline.
     */
    public function residentGrowth(string $app, string $mode): ?int
    {
        $boot = $this->residentBootHeap($app, $mode);
        $heap = $this->residentHeap($app, $mode);
        if ($boot === null || $heap === null || $heap <= $boot) {
            return null;
        }
        return $heap - $boot;
    }

    /**
     * Whether this dataset carries the resident-worker memory probe at all.
     * Datasets measured before the probe existed, or over php-fpm, do not.
     */
    public function hasResidentMem(string $mode): bool
    {
        foreach ($this->data as $modes) {
            foreach (($modes[$mode] ?? []) as $row) {
                if ((int) ($row['mem_boot_heap'] ?? 0) > 0) {
                    return true;
                }
            }
        }
        return false;
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
            // Boot-inclusive: a race is decided on full per-request worker
            // occupancy, matching the table and the charts.
            $ms = $this->msWithBoot($app, $mode, $request);
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
