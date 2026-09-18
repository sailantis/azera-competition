<?php

declare(strict_types=1);

/**
 * Boot probe — measures "PHP start → framework ready" for the REAL servers.
 *
 * Why this exists: the in-process harness (run.php / run-app.php) times boot
 * with measureBoot(), but that number never reaches the real-deployment
 * pipeline (scripts/run-http.php), which drives a server over HTTP. Without
 * it the real views fall back to the legacy startup proxy, which plots warm
 * GET / and duplicates the routing chart (2026-09-16).
 *
 * Cost model — the reason every append is GATED. Measured on the bench VM:
 *
 *   append (file_put_contents FILE_APPEND|LOCK_EX)   3.124 us/op
 *   is_file() gate                                   0.035 us/op
 *   header gate ($_SERVER lookup)                    0.026 us/op
 *
 * The fastest FPM row is 794 us (azera GET /), so an UNGATED append would
 * charge azera 0.39% while charging spiral only 0.016% — a systematic bias
 * against the fastest framework. That is the same failure mode as the 50 ms
 * cache sleep removed on 2026-09-16, so the write is gated on a request
 * header that the timed loop never sends. With the gate, the probe costs
 * 0.026 us/op = 0.003% (indistinguishable from noise) and the samples come
 * from a dedicated probe pass instead of the hot path.
 *
 * Boundaries per framework are documented at each call site in
 * public/index-<app>.php; the two that are easy to get wrong:
 *
 *   - Laravel does its real booting INSIDE handle() (lazy), so the entry
 *     script must call $kernel->bootstrap() explicitly before recording —
 *     otherwise the number is only the app.php container build.
 *   - CodeIgniter's Boot::bootWeb() fuses boot and dispatch; its official
 *     boot-complete hook is the 'pre_system' EVENT (fired after initialize(),
 *     before filters/routing).
 */

const BOOT_PROBE_HEADER = 'X-Boot-Probe';

/**
 * Header that turns on the FPM memory probe.
 *
 * Deliberately a DIFFERENT header from BOOT_PROBE_HEADER: the boot probe and
 * the memory probe are collected in separate passes, so one can be fixed
 * without re-measuring the other, and a reader of the file can tell which
 * probe produced a line.
 */
const MEM_PROBE_HEADER = 'X-Mem-Probe';

/**
 * Start the clock. Must be the FIRST statement of the entry script so the
 * measurement covers the whole "PHP start → framework ready" span.
 */
function boot_probe_start(): void
{
    $GLOBALS['__boot_probe_t0'] = hrtime(true);
}

/**
 * Record one sample into temp/boot-<kind>-<app>.jsonl.
 *
 * $kind is 'fpm' (a per-request boot: the entry script re-runs every request)
 * or 'rr' (a one-time worker boot). Returns true when a sample was written.
 *
 * Gated on BOOT_PROBE_HEADER unless $force: the RoadRunner worker boots
 * BEFORE it can see any request, so its call site forces the write (it runs
 * once per worker lifetime, never in a hot path).
 */
function boot_probe_record(string $kind, string $app, bool $force = false): bool
{
    if (!$force && !boot_probe_wanted()) {
        return false;
    }
    $t0 = $GLOBALS['__boot_probe_t0'] ?? null;
    if ($t0 === null) {
        return false; // start() was not called — miswired entry script
    }

    $file = boot_probe_file($kind, $app);
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    // One JSON number per line: a reader can compute any statistic over the
    // whole sample set, and a partial/failed write can only cost one line.
    $ms = (hrtime(true) - $t0) / 1e6;

    return @file_put_contents($file, json_encode(round($ms, 4)) . "\n", FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Whether this request asked for a boot sample.
 *
 * Reads $_SERVER directly: the entry scripts run per request under FPM, and
 * the RR worker passes PSR-7 headers through to $_SERVER for parity (see
 * deploy/rr/worker.php). $_SERVER is populated before any framework code.
 */
function boot_probe_wanted(): bool
{
    return ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', BOOT_PROBE_HEADER))] ?? '') === '1';
}

/**
 * Whether this request asked for a FPM memory sample.
 *
 * Same $_SERVER read as boot_probe_wanted(), for the same reason.
 */
function mem_probe_wanted(): bool
{
    return ($_SERVER['HTTP_' . strtoupper(str_replace('-', '_', MEM_PROBE_HEADER))] ?? '') === '1';
}

/**
 * Arm the FPM memory probe for this request; returns true if it was armed.
 *
 * Writes NOTHING here. It resets the heap high-water mark and registers a
 * shutdown hook to write one JSON line when the request has finished:
 *
 *   {"boot":<bytes>,"peak":<bytes>,"heap":<bytes>,"rss":<bytes>,"hwm":<bytes>}
 *
 *   boot — PHP heap (memory_get_usage(false)) at THIS call site: the framework
 *          built, no request served. The comparable footprint number.
 *   peak — PHP heap high-water mark (memory_get_peak_usage(false)) reached
 *          while serving THIS request — how much memory the request needed.
 *   heap — PHP heap after the request finished (what was still held).
 *   rss  — VmRSS from /proc/self/status (0 off Linux); context, not a
 *          framework comparison (see mem_probe_proc_mem()).
 *   hwm  — VmHWM, the process's lifetime peak RSS. Monotonic, never resets.
 *
 * WHY peak IS THE NUMBER THE REPORT RANKS ON, AND WHY IT NEEDS A RESET.
 *
 * "How much memory did this request need" is a PER-REQUEST question, and the
 * only PHP primitive that answers it is the heap high-water mark while the
 * request runs. Two things make that measurable here:
 *
 *   - memory_reset_peak_usage() is called at THIS call site, so the high-water
 *     mark starts AT the boot heap. peak is therefore an ABSOLUTE number
 *     (boot <= peak), comparable across frameworks without subtracting
 *     anything, and it cannot be polluted by framework boot — which the entry
 *     script already completed above this line.
 *   - resetting at the call site (rather than relying on the process default)
 *     makes peak self-consistent with boot no matter WHERE in a framework's
 *     lifecycle the call site sits. That matters because the boundary is not
 *     the same for every framework: Laravel's call site is after an explicit
 *     $kernel->bootstrap(), Spiral's is before $kernel->run().
 *
 * The previous probe recorded only `heap` (the heap left at shutdown, sampled
 * in the shutdown hook AFTER the response and after terminate()). Measured on
 * the bench VM that number was unusable: it is allocator steady state, not
 * request cost, and for Laravel it came out BELOW boot on 14 of 21 endpoints
 * (0.885-0.918 MB against a 0.902 MB boot), because whatever the request
 * allocated had already been unwound. A statistic that can go negative and
 * anti-correlates with framework weight is not a measurement of anything.
 * `heap` is kept in the line for context; `peak` is what the report uses.
 *
 * EXACTLY the same five fields deploy/rr/worker.php reports (X-Bench-Boot/
 * Peak/Heap/Rss/Hwm), with the same meanings — only the transport differs,
 * because the two servers have genuinely different shapes. Keeping the field
 * sets identical is what lets the report compare the two deployment models
 * with one code path instead of a per-server translation that could drift.
 *
 * ONE PROBE REQUEST PER ENDPOINT IS NOT ENOUGH. Because peak is per-request,
 * repeated probes of the SAME endpoint now carry information (run-to-run
 * variance) — unlike the cumulative heap they replace, for which one request
 * per endpoint was provably equivalent to N. Repeat the probe N times per
 * endpoint and aggregate per endpoint first (median of its N samples) before
 * taking min/median/max across endpoints; see scripts/http-bench.php.
 *
 * WHY A SHUTDOWN HOOK. The numbers that matter are known only at the END of the
 * request, but the six entry scripts have no common "end": Spiral's last
 * statement is $kernel->run(), CakePHP's is $server->run(), CodeIgniter hands
 * control to the framework's own front controller, and the others exit inside
 * a catch. register_shutdown_function fires after all of them, so one call
 * site placed next to boot_probe_record() produces a sample for every
 * framework without each entry script having to grow an epilogue.
 *
 * WHY FPM NEEDS ITS OWN PROBE AT ALL. The RoadRunner worker answers through
 * X-Bench-* response headers (deploy/rr/worker.php) because it lives in a
 * request loop and can be asked at any time. An FPM worker has no such loop —
 * the entry script runs per request and is torn down after it — so the only
 * place these numbers exist is inside that script, and the only way out is a
 * file. Without this the real-fpm view has no memory data at all, which was
 * the state until 2026-09-17.
 *
 * WHAT THE NUMBERS ARE, PRECISELY. The FPM pool is
 * pm=static/max_children=1/max_requests=0: ONE worker alive for the whole
 * block (deploy/fpm/pool.conf.template). So:
 *
 *   - boot/heap are exact heaps (memory_get_usage(false), the same call the RR
 *     probe uses), so this series is directly comparable with the RR view's
 *     mem_boot_heap.
 *   - rss/hwm are process totals that every worker on the host shares through
 *     the PHP binary, its extensions and opcache. Context, never a framework
 *     ranking — the same caveat the RR worker documents.
 *   - hwm is monotonic for the life of the process, so the value after N
 *     requests is the high-water mark of all N. The harness fires the probe
 *     once per endpoint, right after that endpoint's timed loop, and the
 *     report must describe the series as cumulative rather than as a
 *     per-request figure. The same is true of the RR probe's hwm.
 *
 * GATING. The write costs 3.124 us/op (measured on the bench VM), which would
 * charge the fastest framework ~0.39% and the slowest ~0.016% — a systematic
 * bias rather than noise, the same failure mode the boot probe avoids. Timed
 * requests never carry the header.
 */
function mem_probe_arm(string $app): bool
{
    if (!mem_probe_wanted()) {
        return false;
    }

    // Read BEFORE registering: this is the framework-built, no-request-served
    // heap, and it must not be taken after anything else has run.
    $bootHeap = memory_get_usage(false);

    // Anchor the high-water mark at the boot heap, so the peak read back in
    // mem_probe_write() covers exactly this request's work and nothing else.
    // Guarded: a runtime without it keeps the cumulative peak, which is still
    // a valid absolute reading (>= boot) rather than a fatal.
    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    register_shutdown_function(static function () use ($app, $bootHeap): void {
        mem_probe_write($app, $bootHeap);
    });

    return true;
}

/**
 * Append one memory sample. Split out from mem_probe_arm() so it is testable:
 * a shutdown hook cannot be invoked directly from a unit test, but this can.
 */
function mem_probe_write(string $app, int $bootHeap): bool
{
    $file = mem_probe_file($app);
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    [$rss, $hwm] = mem_probe_proc_mem();

    // peak is read BEFORE heap: memory_get_usage() cannot lower the high-water
    // mark, but reading them in the documented order (peak = what the request
    // needed, heap = what it still held) keeps the two from ever disagreeing.
    $peak = memory_get_peak_usage(false);
    // Defensive: peak must be a real absolute reading and can never be below
    // boot — the reset in mem_probe_arm() anchored it there. A runtime without
    // the reset reports a cumulative peak, which still satisfies this.
    if ($peak < $bootHeap) {
        $peak = $bootHeap;
    }

    return @file_put_contents(
        $file,
        json_encode([
            'boot' => $bootHeap,
            'peak' => $peak,
            'heap' => memory_get_usage(false),
            'rss'  => $rss,
            'hwm'  => $hwm,
        ]) . "\n",
        FILE_APPEND | LOCK_EX
    ) !== false;
}

/**
 * [VmRSS, VmHWM] in bytes from /proc/self/status, or [0, 0] where there is no
 * /proc. Mirrors readProcMem() in deploy/rr/worker.php line for line, so the
 * two probes report the same quantity from the same source.
 *
 * Context only, never a framework comparison: both values include the shared
 * PHP binary, loaded extensions and opcache's shared memory, all of which every
 * worker on the host shares.
 *
 * @return array{0:int,1:int}
 */
function mem_probe_proc_mem(): array
{
    $s = @file_get_contents('/proc/self/status');
    if ($s === false) {
        return [0, 0];
    }
    $rss = preg_match('/^VmRSS:\s+(\d+) kB/m', $s, $m) ? (int) $m[1] * 1024 : 0;
    $hwm = preg_match('/^VmHWM:\s+(\d+) kB/m', $s, $m) ? (int) $m[1] * 1024 : 0;
    return [$rss, $hwm];
}

/**
 * Sample file for one app. temp/ is gitignored and excluded from the benchmark
 * sync, so samples never leak between machines — the same rule boot_probe_file()
 * documents.
 */
function mem_probe_file(string $app): string
{
    return __DIR__ . "/temp/mem-fpm-{$app}.jsonl";
}

/**
 * Sample file for one (kind, app) pair. temp/ is gitignored and excluded from
 * the benchmark sync, so samples never leak between machines.
 *
 * The repo root is the PARENT of this file's directory (boot-probe.php lives
 * at the repo root, NOT under temp/). Deriving it as `dirname(__DIR__)` would
 * point one level ABOVE the repo — on the bench VM that directory is not
 * writable, so every write silently failed and the probe returned zero
 * samples, which makes the report fall back to the legacy startup proxy with
 * no error at all. Hence: __DIR__ IS the repo root.
 */
function boot_probe_file(string $kind, string $app): string
{
    return __DIR__ . "/temp/boot-{$kind}-{$app}.jsonl";
}

/**
 * Read every sample back, in ms. Missing file → [].
 *
 * @return list<float>
 */
function boot_probe_samples(string $kind, string $app): array
{
    $file = boot_probe_file($kind, $app);
    if (!is_file($file)) {
        return [];
    }
    $out = [];
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $v = json_decode(trim($line), true);
        if (is_numeric($v)) {
            $out[] = (float) $v;
        }
    }
    return $out;
}
