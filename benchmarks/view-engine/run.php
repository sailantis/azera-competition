<?php
// Simple benchmark harness for view engines
require_once __DIR__ . '/../../vendor/autoload.php';

// Version provenance, shared with the framework harness (run.php) so the two
// cannot disagree about how a version is resolved: composer's INSTALLED
// manifest first (that is what actually ran), and a package's own declared
// version for the path-repository case.
require_once dirname(__DIR__, 2) . '/scripts/version-lib.php';

// Engine identity and the penalty conversion, shared with the two probes so a
// probe cannot measure a different engine than the harness did (see engines.php).
require_once __DIR__ . '/engines.php';

// The summary-statistic convention, shared with steady-probe.php. A private copy
// on either side would be a second definition of median/p95 that could drift,
// and a published p95 that describes a different index than its label implies is
// invisible in the artifact.
require_once __DIR__ . '/stats-lib.php';

// CLI options: --engines=comma,separated --iterations-per-run=N --runs=N --out=prefix
//              --items=N (items per render) --no-penalty --penalty-separate
// (No --quick: a smaller budget is stated with --iterations-per-run/--runs.)
$opts = getopt('', ['engines::', 'iterations-per-run::', 'runs::', 'out::', 'no-penalty', 'penalty-separate', 'items::']);
// `clarity-open` (the same engine with the sandbox disabled) is a SUPPORTED key
// but NOT in the default list. It was measured in a full run against every page
// and came out within measurement noise of the sandboxed engine â€” between -2.6%
// and -0.2% depending on the page, with the SIGN flipping between pages, and a
// controlled probe of 200 flat variable accesses (mode-cost-probe.php) finding
// the two indistinguishable. Two rows that differ by less than the noise floor
// invite a comparison the data cannot support, so the published table shows one
// Clarity and states in prose that the open mode was measured and does not
// differ. The key stays so that claim can be re-run and re-checked rather than
// taken on faith: `--engines=clarity,clarity-open`.
//
// `latte` IS in the default list. It is a full peer of the other engines (the
// same pages, layouts and partials, rendered by its own templates), unlike
// `clarity-open`, which is the same engine under a second policy and so cannot
// support a comparison the noise floor does not already decide.
$engines         = isset($opts['engines']) ? explode(',', $opts['engines']) : ['native', 'clarity', 'plates', 'blade', 'twig', 'stempler', 'latte'];
$itersPerRun     = isset($opts['iterations-per-run']) ? (int) $opts['iterations-per-run'] : 10000;
$runs            = isset($opts['runs']) ? (int) $opts['runs'] : 30;
$itemsCount      = isset($opts['items']) ? (int) $opts['items'] : 200; // number of items passed to templates (was 50)
$outPrefix       = $opts['out'] ?? null;                               // optional prefix for results files
$penaltyEnabled  = !isset($opts['no-penalty']);
$penaltySeparate = isset($opts['penalty-separate']);

$viewPath = __DIR__ . '/templates';

// The page table is a SHARED file, not a function defined here: peak-probe.php
// and verify.php need the same definitions, and a private copy in each is how a
// probe ends up measuring a page the harness never chose.
$pages = require __DIR__ . '/pages.php';

function stats(array $values)
{
    // Delegates to the SHARED helper. This wrapper exists only so the rest of
    // this file keeps its historical call shape; the convention itself lives in
    // stats-lib.php, where steady-probe.php can use the identical one.
    return viewEngineStats($values);
}

/**
 * Peak heap for ONE render, measured in a fresh child process.
 *
 * `memory_get_peak_usage()` is a PROCESS high-water mark that never decreases,
 * so within one process every engine measured after the first reports the
 * maximum of all of them â€” not its own footprint. In the 2026-09-22 run that
 * produced four identical readings of 52.44 MB and one honest 23.08 MB, i.e. a
 * column that looked like a measurement and was an ordering artifact. It is the
 * same trap the framework suite hit and documented ("the medians were the 11th
 * of 21 staircase points").
 *
 * `memory_reset_peak_usage()` (PHP >= 8.2) fixes it in-process, but it can only
 * report the CURRENT process's usage, so it cannot answer "what would this
 * engine cost alone" for an engine measured after three others have run. A
 * child is the only honest way to get a per-engine footprint, and it is run
 * once per engine rather than per iteration because a process spawn per render
 * would swamp the thing being measured.
 *
 * Returns null when the child cannot be measured, so a missing reading is
 * visible as absent instead of being silently filled with another engine's â€”
 * which is exactly what a `?? memory_get_peak_usage(...)` fallback did while the
 * child was silently dying on a bad require path: the parent's cumulative
 * staircase came back looking like plausible per-engine numbers.
 *
 * The probe is a COMMITTED script (peak-probe.php) rather than code assembled
 * here. The generated version could not be linted or run by hand, so its only
 * symptom was a missing number â€” and the fallback then hid even that.
 *
 * RETURNS A STRUCT, not a single int, because one number could not carry the
 * question. The old call returned "the peak after R renders" and the harness
 * compared it with "the peak after 1 render", which are the same number (see
 * peak-probe.php): the published `mem_delta` ran 16-304 bytes across every
 * engine and every page. Four figures replace it, and each answers something the
 * others cannot — the floor the engine costs to have loaded, what it retains
 * after the first render, what it retains after the run, and the run's peak.
 *
 * The child prints `key=value;key=value`, so the probe can grow a reading without
 * the harness's argument list or return type changing shape. An absent or
 * unparseable reading yields null for THAT key rather than zero: `null - 0`
 * silently produces 0, which is exactly how the dead delta published "0% change
 * for every engine" and looked like a finding.
 *
 * @return array{base:?int,use1:?int,useN:?int,peakN:?int,cycles:?int}|null
 */
function measureIsolatedPeak(string $engineKey, int $itemsCount, int $renders = 1, string $pageKey = 'sample'): ?array
{
    // A private probe cache is a temp artifact this harness OWNS â€” unlike a
    // shared asset directory, it exists solely for this measurement â€” so
    // sweeping it is safe here. Errors are deliberately not raised: on Windows a
    // file the child still holds open must not turn a completed measurement into
    // a failure, and a directory that is already gone needs no handling.
    //
    // Declared as a local closure rather than a global function so it cannot
    // collide with a same-named helper in another harness file that shares this
    // process (run.php is `require`d by nothing, but scripts/ is loaded into the
    // same interpreter in the report path).
    $removeTree = static function (string $dir) use (&$removeTree): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };

    // A PRIVATE, EMPTY cache directory, so the reading cannot depend on what the
    // machine's shared temp cache holds. Flushing alone is not enough: on the
    // bench VM the shared clarity cache holds hundreds of compiled classes from
    // earlier runs, and the same render measured 47.7 MB there against 1.6 MB on
    // a clean machine.
    //
    // The directory is keyed by PAGE as well as engine and renders, and it is
    // DELETED before and after use. Keying on engine+renders+parent-PID alone
    // was a real defect: WITHIN one run every page of an engine resolved to the
    // SAME path, so page 1 paid the compile and pages 2..N rendered against the
    // cache page 1 had just populated; ACROSS runs nothing ever removed those
    // directories (88 had accumulated in %TEMP% by 2026-09-25), so a recycled
    // parent PID inherited a populated cache and reported a warm number as a
    // cold one. Deleting before use makes a recycled PID harmless; deleting
    // afterwards stops the accumulation. (A child that dies mid-render can leave
    // the directory behind, which the next run removes anyway.)
    //
    // The template cache is only HALF the temperature problem, and the smaller
    // half. A template COMPILE is an ordinary PHP script load, so the CLI OPcache
    // governs it — and the engine's own source is an even larger script load.
    //
    // CORRECTION 2026-09-29: this used to assert that the machine runs a SHARED
    // CLI OPcache segment, so an early child paid a one-time bytecode compile that
    // later children read as hits. The OBSERVATION was real but the EXPLANATION
    // was wrong. A shell_exec'd `php` cannot inherit the parent's CLI segment
    // (PHP's anonymous shared mmap is inherited by fork(), not across exec()), and
    // two consecutive standalone children do not see each other's cached scripts.
    // The earlier order-dependence is therefore NOT explained by a shared segment;
    // its root cause is UNCONFIRMED and must not be re-asserted without
    // re-deriving it.
    //
    // What IS established: with opcache ON the per-process peak is roughly HALF
    // the opcache-OFF peak (measured on the VM, clarity 2,635,808 vs 5,117,216),
    // because much of the compiled representation lives in the segment rather than
    // the heap. Both are per-process here; a long-lived FPM/RR worker is the one
    // that truly shares. The probes run with opcache ON to match the framework
    // pages, which are measured OPcache-ON, and because that is what a deployment
    // does.
    //
    // Determinism comes from the fact that the bench VM's engine source is
    // deployed FROZEN, not from priming. CORRECTION 2026-09-29: the claim that a
    // throwaway child warms a SHARED CLI segment for the measured child is WRONG.
    // PHP's anonymous shared mmap is inherited by fork(), not across exec(), so
    // each shell_exec'd `php` gets its OWN fresh segment and compiles everything
    // it loads. Proven here: child 1 reports num_cached=2/after=true for a file,
    // child 2 in a NEW process reports before=false/num_cached=1. So
    // primeOpcodeCache() does NOTHING to the measured child (verified: "no prime"
    // and "with prime" gave byte-identical readings across four alternating VM
    // pairs). It is kept as harmless, and it would become meaningful only if
    // `opcache.file_cache` were enabled, which IS shared across processes.
    //
    // WHAT MAKES READINGS STABLE ANYWAY: on the VM the engine source carries a
    // normalised old mtime, and `opcache.file_update_protection=2` only refuses
    // to cache a file modified within the last 2 seconds. So the MEASURED child
    // always compiles the engine source itself, every time, and every reading
    // pays the same amount of that work. The memory `peakN` is therefore the
    // real per-process cold-deploy high-water mark (and it is what this column is
    // FOR). The `first render` is the engine compile PLUS the cold TEMPLATE
    // compile — the honest "first request after a deploy" cost. See the basis
    // strings below; `opcache_probe` records the basis.
    $privateCache = sys_get_temp_dir() . '/ve-peak-' . $engineKey . '-r' . $renders
        . '-' . $pageKey . '-' . getmypid();
    $removeTree($privateCache);

    // Prime the segment with this engine's scripts. HARMLESS BUT EFFECTIVELY A
    // NO-OP for the measured child: the CLI segment is per-process (see above),
    // so this throwaway child's cache does not reach the next one. It is kept
    // because it costs one cheap child per engine and it would become real if
    // `opcache.file_cache` were ever enabled.
    primeOpcodeCache($engineKey, $itemsCount, $pageKey, 1);

    // The null device is PLATFORM-SPECIFIC. `2>/dev/null` is valid on POSIX but
    // on Windows cmd.exe it makes the shell try to write to a path named
    // dev\null, fail the redirect, and return NOTHING â€” so a perfectly good child
    // looked like an unmeasurable one. The framework harness already carries this
    // exact guard; this copy did not.
    $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';

    try {
        $out = shell_exec(sprintf(
            '%s -d opcache.enable_cli=1 %s %s %d %s %d %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/peak-probe.php'),
            escapeshellarg($engineKey),
            $itemsCount,
            escapeshellarg($privateCache),
            $renders,
            escapeshellarg($pageKey),
            $null
        ));
    } finally {
        $removeTree($privateCache);
    }

    if (!is_string($out) || trim($out) === '') {
        return null;
    }

    // `key=value` pairs, parsed defensively: a probe that printed a partial line
    // (or a PHP warning that reached stdout) must leave the missing figures
    // ABSENT, so a chart can omit them, rather than present at zero.
    $reading = ['base' => null, 'use1' => null, 'useN' => null, 'peakN' => null, 'cycles' => null];
    foreach (explode(';', trim($out)) as $pair) {
        $parts = explode('=', $pair, 2);
        if (count($parts) !== 2 || !array_key_exists($parts[0], $reading)) {
            continue;
        }
        if (is_numeric(trim($parts[1]))) {
            $reading[$parts[0]] = (int) trim($parts[1]);
        }
    }

    // A reading with no peak at all is not a reading: it means the child died
    // before its first render finished, and presenting the remaining zeros as a
    // measurement is the failure this whole mechanism exists to prevent.
    return $reading['peakN'] === null ? null : $reading;
}

/**
 * Run ONE throwaway probe child for an engine, discarding the reading.
 *
 * KEPT FOR SYMMETRY, AND EFFECTIVELY A NO-OP. The name is historical. The
 * original intent was to warm a segment the MEASURED child would then share, but
 * that is not how the CLI OPcache works here: PHP's anonymous shared mmap is
 * inherited by fork(), not across exec(), so each shell_exec'd `php` gets its own
 * fresh segment and compiles whatever it loads. A priming child therefore cannot
 * warm the next one. Proven 2026-09-29: "no prime" and "with prime" produced
 * byte-identical base/use1/useN/peakN readings across four alternating VM pairs,
 * and a second process reports `before=false` for a file the first compiled.
 *
 * It is retained because it costs one cheap child per engine and because it WOULD
 * become meaningful under `opcache.file_cache`, which is a real shared cache (the
 * file cache is read by every process). If the benchmark ever moves to a warm
 * file-cache basis, this is the hook that makes it possible.
 *
 * The probes run with `opcache.enable_cli=1` because a real deployment does; that
 * keeps compiled bytecode out of the worker heap and is what makes the figures
 * comparable with the framework suite's, which has always run OPcache-ON.
 *
 * The child is given its own throwaway template cache because the probe needs a
 * writable cache path; rendering into it is harmless and it is swept afterwards.
 */
function primeOpcodeCache(string $engineKey, int $itemsCount, string $pageKey, int $renders): void
{
    $primeCache = sys_get_temp_dir() . '/ve-prime-' . $engineKey . '-' . $pageKey . '-' . getmypid();
    $null       = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';

    shell_exec(sprintf(
        '%s -d opcache.enable_cli=1 %s %s %d %s %d %s %s',
        escapeshellarg(PHP_BINARY),
        escapeshellarg(__DIR__ . '/peak-probe.php'),
        escapeshellarg($engineKey),
        $itemsCount,
        escapeshellarg($primeCache),
        max(1, $renders),
        escapeshellarg($pageKey),
        $null
    ));

    // Sweep the throwaway template cache the priming child wrote into. Recursive
    // because a compiling engine may nest its output; a child that crashed and
    // left nothing is a no-op.
    if (is_dir($primeCache)) {
        $removeTree = static function (string $dir) use (&$removeTree): void {
            foreach (@scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }
                $path = $dir . '/' . $item;
                is_dir($path) && !is_link($path) ? $removeTree($path) : @unlink($path);
            }
            @rmdir($dir);
        };
        $removeTree($primeCache);
    }
}

/**
 * The FIRST render, measured in a fresh child process with a cold template
 * cache. Returns milliseconds, or null when the probe could not be measured.
 *
 * WHY A CHILD, not the two renders this used to do in-process
 *
 * The in-process version was `render -> flushCache() -> render`, and the flush
 * cannot undo a class declaration. Engines whose compile is guarded by
 * `class_exists()` therefore skipped the compile and published a warm render as
 * a first render; measured on the bench VM that understated Twig by 28x and
 * Stempler by 51x, and made them appear faster than the engines that genuinely
 * compiled. The full account is in first-render-probe.php.
 *
 * The probe is a COMMITTED script rather than generated code, for the reason
 * peak-probe.php documents: a generated child died silently on a bad require
 * path and the harness's fallback turned it into a plausible number. A real file
 * can be linted, run by hand and covered by a test.
 *
 * OPcache is ON for the child, matching measureIsolatedPeak() and the real
 * deployments these figures describe. Note that ON does NOT mean the engine
 * source is served from a shared cache here: the CLI opcode segment is
 * PER-PROCESS (not shared across shell_exec children), so each child compiles the
 * engine's files itself. `primeOpcodeCache()` is therefore a no-op for the
 * measured child, kept only because it would matter under `opcache.file_cache`.
 * The TEMPLATE cache, which is the other temperature, is cold (see the basis
 * string).
 *
 * The cache directory is PRIVATE, keyed by engine+page+PID, and deleted before
 * and after use â€” so a recycled PID cannot inherit a populated cache, and a
 * crashed child cannot leave one behind for the next run.
 */
function measureFirstRender(string $engineKey, string $pageKey, int $itemsCount): ?float
{
    // Same local-closure convention as measureIsolatedPeak(): this file is
    // `require`d nowhere, but scripts/ shares the interpreter in the report
    // path, so a global helper here could collide with another harness file.
    $removeTree = static function (string $dir) use (&$removeTree): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };

    $privateCache = sys_get_temp_dir() . '/ve-first-' . $engineKey . '-' . $pageKey . '-' . getmypid();
    $removeTree($privateCache);

    // Prime, as measureIsolatedPeak() does. Effectively a no-op for the measured
    // child (the CLI segment is per-process), kept because it would become real
    // under `opcache.file_cache`.
    primeOpcodeCache($engineKey, $itemsCount, $pageKey, 1);

    $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';

    try {
        $out = shell_exec(sprintf(
            '%s -d opcache.enable_cli=1 %s %s %s %d %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/first-render-probe.php'),
            escapeshellarg($engineKey),
            escapeshellarg($pageKey),
            $itemsCount,
            escapeshellarg($privateCache),
            $null
        ));
    } finally {
        $removeTree($privateCache);
    }

    if (!is_string($out) || trim($out) === '') {
        return null;
    }

    // "<first_ms> <second_ms> <output_bytes>"; the FIRST is the published figure
    // and the only one used here. The probe prints the second for a reader
    // running it by hand, to separate boot from compile.
    $parts = preg_split('/\s+/', trim($out)) ?: [];

    return isset($parts[0]) && is_numeric($parts[0]) ? (float) $parts[0] : null;
}

/**
 * The directory a `--out` prefix is relative to.
 *
 * Two conventions, both already in use elsewhere in this repository:
 *  - a `results/...` prefix means the REPO's results directory, so a dataset
 *    sits beside every other dataset and a fetch path can be derived from it;
 *  - anything else stays beside the harness, which is where the historical
 *    `--out=results-<date>` artifacts were written.
 *
 * One helper rather than the expression inlined twice, so the write and the
 * confirmation message cannot disagree about where the file actually went.
 */
function resultsBase(string $prefix): string
{
    $root = dirname(__DIR__, 2);

    return str_starts_with($prefix, 'results/') ? $root : __DIR__;
}

/**
 * The STEADY-STATE render loop for ONE (engine, page) cell, measured in a fresh
 * child process against a warm cache.
 *
 * THIS IS THE THIRD COLUMN TO MOVE INTO A CHILD, and it is the same defect that
 * moved the other two.
 *
 * The render loop used to run in the harness's own process, walking every cell in
 * sequence and calling `flushCache()` before each. The flush deletes cache FILES;
 * it cannot un-declare a PHP CLASS. An engine whose compile branch is guarded by
 * `class_exists()` therefore SKIPPED THE COMPILE on the second and every later
 * measurement in that process â€” and, crucially, skipped the CACHE WRITE with it.
 *
 * Stempler is where this became measurable rather than theoretical. Its
 * `StemplerEngine::compile()` calls `StemplerCache::isFresh()` on EVERY `get()`,
 * and that call costs ~0.75 us when the cache MAP file is absent (a `file_exists`
 * early-out) but ~13 us when it is present (`include` of the map, a `filemtime`
 * per dependency, and `include_once` of the class). The map is written only
 * inside the compile branch, so:
 *
 *   - the FIRST cell measured in a process wrote the map and then paid ~13 us per
 *     render for its whole timed loop;
 *   - the SECOND measurement of that cell found the class already declared, never
 *     rewrote the map, and paid ~0.75 us per render.
 *
 * On the bench VM, one warmed object measured back to back gave 0.1161 ms then
 * 0.1022 ms â€” the MAP FILE'S EXISTENCE decided the published figure, and the
 * harness's fixed walk order decided which engine landed on which side. Only
 * Stempler was affected: Clarity's `Cache::load()` is registry-first and its
 * `flush()` clears that registry, so its steady state does no per-render
 * filesystem work at all.
 *
 * A fresh process cannot be gamed this way, and it is also the only state a
 * server is ever in: one long-lived process that compiled once and has been
 * rendering from cache since. The probe's untimed warm-up render performs that
 * compile, so the timed loop below runs against exactly that state.
 *
 * The cache directory is PRIVATE, keyed by engine+page+PID, so a recycled PID
 * cannot inherit a populated cache and one cell cannot see another's artifact.
 *
 * Returns null when the child could not be measured, so a missing row is ABSENT
 * rather than filled with a plausible substitute.
 *
 * @return array{samples:list<float>, run_summaries:list<array>, penalty_samples:list<float>, output_bytes:int}|null
 */
function measureSteadyState(
    string $engineKey,
    string $pageKey,
    int $itemsCount,
    int $itersPerRun,
    int $runs,
    bool $penaltyEnabled,
    bool $penaltySeparate,
    string $privateCache
): ?array {
    $removeTree = static function (string $dir) use (&$removeTree): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path) && !is_link($path)) {
                $removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    };

    // An EMPTY directory, created here and removed with the child. Left to itself
    // the render reads whatever the machine's shared temp cache holds â€” on the
    // bench VM that is hundreds of classes from earlier runs, so the same job
    // measures differently depending on what ran before it.
    $removeTree($privateCache);
    if (!is_dir($privateCache)) {
        @mkdir($privateCache, 0777, true);
    }

    // The null device is PLATFORM-SPECIFIC: `2>/dev/null` is valid on POSIX, but
    // on Windows cmd.exe it makes the shell try to write to a path named
    // dev\null and return NOTHING â€” so a good child looked like an unmeasurable
    // one.
    $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';

    $flags = [];
    if ($penaltySeparate) {
        $flags[] = 'penalty-separate';
    }
    if (!$penaltyEnabled) {
        $flags[] = 'no-penalty';
    }

    try {
        $out = shell_exec(sprintf(
            '%s -d opcache.enable_cli=1 %s %s %s %d %d %d %s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/steady-probe.php'),
            escapeshellarg($engineKey),
            escapeshellarg($pageKey),
            $itemsCount,
            $itersPerRun,
            $runs,
            escapeshellarg($privateCache),
            $flags === [] ? "''" : escapeshellarg(implode(' ', $flags)),
            $null
        ));
    } finally {
        $removeTree($privateCache);
    }

    if (!is_string($out) || trim($out) === '') {
        return null;
    }

    // The probe prints ONLY the JSON object on stdout, so the first `{` starts
    // it. Slicing rather than `json_decode(trim(...))` directly: a PHP notice
    // from anything the engine loads would otherwise land in front of the payload
    // and turn a good reading into an unparseable one.
    $start = strpos($out, '{');
    if ($start === false) {
        return null;
    }
    $decoded = json_decode(substr($out, $start), true);

    return is_array($decoded) && isset($decoded['run_summaries']) ? $decoded : null;
}

/**
 * Write results as JSON and CSV when $outPrefix is provided.
 *
 * The JSON is an ENVELOPE â€” `{ env, results }` â€” deliberately NOT a bare list.
 *
 * A bare list does not read as a measurement, only as a set of numbers: it says
 * nothing about which PHP ran it, whether OPcache was on, how many items the
 * templates rendered, or WHICH VERSION of each engine produced them. That is
 * not hypothetical â€” every figure published in the clarity-engine and framework
 * docs was hand-transcribed from such a file, and the caption shipped beside
 * them quotes PHP 8.3.6, a value this harness never recorded (it was borrowed
 * from the FRAMEWORK suite's dataset). An environment that is not stamped has
 * to be reconstructed from memory, and that is how a caption outlives the run
 * it claims to describe. The stamp closes that hole at the source.
 *
 * The CSV keeps its flat shape â€” one row per engine â€” so a spreadsheet import
 * still works; the environment lives in the JSON, which is the artifact a
 * generator reads.
 */
function writeResults(string $prefix, array $results, array $env): void
{
    // RESOLVED AGAINST THE REPO ROOT, and the parent directory is created.
    //
    // `--out` used to be joined to __DIR__ (this directory) with no check, so
    // `--out=results/view-engine-<date>` tried to write
    // benchmarks/view-engine/results/... â€” a directory that does not exist. The
    // failure was silent for the JSON (a warning) and then FATAL inside
    // fputcsv(), which threw away an ENTIRE completed run: every measurement
    // had finished and the results were dropped at the last statement. A
    // benchmark that can lose its own output is worse than one that refuses to
    // start, so this creates what it needs and fails loudly if it cannot.
    $jsonFile = resultsBase($prefix) . "/{$prefix}.json";
    $csvFile  = resultsBase($prefix) . "/{$prefix}.csv";

    foreach ([$jsonFile, $csvFile] as $file) {
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create output directory: {$dir}");
        }
    }

    file_put_contents(
        $jsonFile,
        json_encode(['env' => $env, 'results' => $results], JSON_PRETTY_PRINT)
    );

    $fp = fopen($csvFile, 'w');
    if ($fp === false) {
        throw new RuntimeException("Cannot open CSV for writing: {$csvFile}");
    }
    // The memory columns, in the order they are read: the floor, what the first
    // render leaves behind, what a whole run leaves behind, the run's peak, and
    // the growth between the last two retained readings. The growth column
    // replaced `mem_delta`/`mem_delta_pct`, which measured the gap between a
    // figure and itself (see peak-probe.php) and therefore always read 0.
    fputcsv($fp, ['engine', 'page', 'first_render_ms', 'base_mem', 'use1_mem', 'retained_mem', 'peak_mem', 'retained_growth_bytes', 'iterations_per_run', 'runs', 'trimmed_mean_ms', 'mean_ms', 'min_ms', 'median_ms', 'p95_ms']);
    foreach ($results as $r) {
        // BLANK when a reading is unavailable, never 0. A missing measurement
        // must be absent: `null - 0` silently yields 0 here, which is exactly
        // how the dead in-process delta published "0% change for every engine"
        // and looked like a finding.
        $use1   = $r['use1_mem'];
        $useN   = $r['retained_mem'];
        $growth = ($use1 === null || $useN === null) ? '' : $useN - $use1;
        fputcsv($fp, [
            $r['engine'],
            $r['page'] ?? '',
            $r['first_render_ms'],
            $r['base_mem'] ?? '',
            $r['use1_mem'] ?? '',
            $r['retained_mem'] ?? '',
            $r['peak_mem'] ?? '',
            $growth,
            $r['iterations_per_run'],
            $r['runs'],
            $r['trimmed_mean_ms'] ?? '',
            $r['mean_ms'],
            $r['min_ms'],
            $r['median_ms'],
            $r['p95_ms'],
        ]);
    }
    fclose($fp);
}

/**
 * The engine that each measured adapter actually exercises, and the Composer
 * package that IS that engine.
 *
 * Two of the five are not separate packages at all: `native` and `clarity` are
 * engines INSIDE the Azera framework (src/Core/Engines), so their version is the
 * framework's and their package is the path repository. Naming a package per
 * engine is what makes the version lookup answerable at all â€” `TwigAdapter`
 * wrapping Twig is a fact about the adapter, not about the measured thing.
 *
 * Blade resolves through `laravel/framework`, NOT `illuminate/view`, even though
 * the adapter's own docblock says `composer require illuminate/view`. The
 * adapter imports the `Illuminate\View` NAMESPACE, and in this repository those
 * files ship inside laravel/framework: `illuminate/view` is not installed at
 * all, and the classes resolve out of
 * `vendor/laravel/framework/src/Illuminate/View/...`. Naming the split package
 * therefore listed a package that was not present, with a version borrowed from
 * another tree's manifest (10.49.0, which no run ever used). The version stamp
 * must name the package the classes actually came from.
 *
 * @return array<string,array{package:string,label:string}>
 */
function viewEnginePackages(): array
{
    return [
        'native'  => ['package' => 'sailantis/azera-framework', 'label' => 'NativeEngine (Azera)'],
        'clarity' => ['package' => 'sailantis/clarity-engine', 'label' => 'Clarity'],
        // The SAME package as `clarity`: open mode is a configuration of that
        // engine, not a second one, so a version stamp that named two packages
        // would claim a dependency that does not exist.
        'clarity-open' => ['package' => 'sailantis/clarity-engine', 'label' => 'Clarity (open mode)'],
        'plates'       => ['package' => 'league/plates', 'label' => 'Plates'],
        'blade'        => ['package' => 'laravel/framework', 'label' => 'Blade'],
        'twig'         => ['package' => 'twig/twig', 'label' => 'Twig'],
        // Stempler is Spiral's engine. The Stempler classes ship INSIDE
        // spiral/framework, which Composer "replaces" spiral/stempler and
        // spiral/stempler-bridge â€” so there is no separate package to name, just
        // as Blade ships inside laravel/framework.
        'stempler' => ['package' => 'spiral/framework', 'label' => 'Stempler'],
        // Latte is its own package (unlike Blade/Stempler, which ship inside a
        // framework), so the version lookup resolves from the installed
        // manifest directly.
        'latte'    => ['package' => 'latte/latte', 'label' => 'Latte'],
    ];
}

/**
 * engine key => ['package' => .., 'label' => .., 'version' => .., 'ref' => ..]
 * for the engines this run measured, in the order it measured them.
 *
 * Version resolution follows scripts/version-lib.php, in the same order for the
 * same reasons: the INSTALLED manifest is the truth about what ran, and a
 * path-repository package records the BRANCH (`dev-main`) rather than a
 * version, so the package's own declared version is the answer in that case.
 * Anything unresolvable is reported as null rather than omitted, so a missing
 * version is visible on the page as "unknown" instead of silently absent.
 *
 * @param list<string> $engines
 * @return array<string,array{package:string,label:string,version:?string,ref:?string}>
 */
function viewEngineVersions(string $root, array $engines): array
{
    $packages  = viewEnginePackages();
    $installed = installedVersions($root);
    $refs      = [
        'native'       => azeraFrameworkRef($root),
        'clarity'      => pathRepoRef($root . '/vendor/sailantis/clarity-engine'),
        'clarity-open' => pathRepoRef($root . '/vendor/sailantis/clarity-engine'),
    ];

    $out = [];
    foreach ($engines as $engine) {
        $engine = trim($engine);
        $meta   = $packages[$engine] ?? null;
        if ($meta === null) {
            // An engine the harness does not know (a new adapter) is still
            // recorded, with no version claim attached to it.
            $out[$engine] = ['package' => '', 'label' => $engine, 'version' => null, 'ref' => null];
            continue;
        }

        $version = normaliseVersion($installed[$meta['package']] ?? null);
        if ($version === null || str_starts_with($version, 'dev-')) {
            // A path repository (or an unresolved install) records the branch.
            // The declared version is what the reader needs to see.
            $dir     = $root . '/vendor/' . $meta['package'];
            $version = declaredVersion($dir . '/composer.json') ?? $version;
        }

        $out[$engine] = [
            'package' => $meta['package'],
            'label'   => $meta['label'],
            'version' => $version,
            'ref'     => $refs[$engine] ?? null,
        ];
    }

    return $out;
}

/**
 * Short git ref of a checkout, or null when there is none.
 *
 * Same caveat as azeraFrameworkRef(): a checkout must EXIST for this to answer,
 * and the bench VM has none (the sync excludes `.git`), so a ref recorded there
 * comes from the environment instead â€” see the override below.
 */
function pathRepoRef(string $dir): ?string
{
    $envKey   = 'CLARITY_ENGINE_REF';
    $envValue = getenv($envKey);
    if ($envValue !== false && $envValue !== '') {
        return $envValue;
    }
    if (!is_dir($dir . '/.git') && !is_file($dir . '/.git')) {
        return null;
    }
    // Same platform-specific null device as measureIsolatedPeak(): on Windows
    // `2>/dev/null` fails the redirect and the command returns nothing, which
    // would make every ref silently null.
    $null = DIRECTORY_SEPARATOR === '\\' ? '2>NUL' : '2>/dev/null';
    $ref  = shell_exec('git -C ' . escapeshellarg($dir) . ' rev-parse --short HEAD ' . $null);

    return is_string($ref) && trim($ref) !== '' ? trim($ref) : null;
}

/**
 * The environment this run was measured in.
 *
 * `penalty_enabled` and `items` are part of the environment, not trivia: the
 * penalty is a conversion done per iteration and excluded from the recorded
 * time, and `items` sets how much work every iteration does. A run at 50 items
 * and one at 200 are not comparable, and nothing in the old artifact said which
 * it was.
 *
 * Named `viewEngineEnv` rather than `envInfo` on purpose: this file shares
 * scripts/version-lib.php with the framework harness (run.php), and two
 * unguarded global functions of the same name cannot coexist in one process.
 *
 * @param list<string> $engines
 */
function viewEngineEnv(int $itemsCount, int $itersPerRun, int $runs, array $engines, bool $penaltyEnabled): array
{
    $root = dirname(__DIR__, 2);

    return [
        'php_version' => PHP_VERSION,
        'os'          => PHP_OS . ' ' . php_uname('r'),
        // The CLI gate, stated as such: opcache.enable_cli is the switch that
        // governs THIS sapi. opcache.enable governs fpm/apache and is not
        // what this script reads.
        'opcache'            => (bool) ini_get('opcache.enable_cli'),
        'sapi'               => PHP_SAPI,
        'timestamp'          => date('c'),
        'items'              => $itemsCount,
        'iterations_per_run' => $itersPerRun,
        'runs'               => $runs,
        'engines'            => array_values($engines),
        // Which pages ran. A row names its own page, but the ENV should say what
        // the dataset as a whole covers, so a reader does not have to scan rows
        // to discover that a page is missing from a partial run.
        'pages'           => array_keys(require __DIR__ . '/pages.php'),
        'penalty_enabled' => $penaltyEnabled,
        // How the render loop was measured. This used to say how many passes the
        // figures were averaged over; it now says the opposite, because the
        // reason for averaging is gone. Every cell is measured in its OWN fresh
        // process, so no cell can inherit a class another cell declared or a
        // cache artifact another cell wrote — and there is therefore no order to
        // alternate and nothing to average. `steady_probe_ms_basis` states what
        // the timing columns mean; the account of why a process per cell is
        // required is in steady-probe.php.
        'steady_probe_ms_basis' => 'the render loop for each (engine, page) cell runs in its own fresh process against a warm cache: one untimed warm-up render, then runs x iterations-per-run timed renders',
        'steady_probe'          => 'one fresh process per cell (steady-probe.php); a shared process lets an already-declared class skip both the compile and the cache write, which changes a per-render cache check',
        'run_order'             => 'no order: each (engine, page) cell is measured in its own process, so measurement order cannot affect a cell',
        // What the memory and first-render columns MEAN, because none is
        // self-evident and each has been wrong before being stated:
        //  - `first_render_ms` is ONE render in a fresh child process with a
        //    cold cache: engine class loading + compile + cache write + one
        //    render. It used to be an in-process compile-only figure, which is
        //    not a measurement any engine comparison can use â€” see
        //    first-render-probe.php for why (the flush cannot un-declare a
        //    class, so two of the five engines were not compiling at all);
        //  - `base_mem` is the heap with the engine loaded and nothing rendered;
        //  - `use1_mem` is the heap RETAINED after one render, gc collected;
        //  - `retained_mem` is the heap RETAINED after a run of
        //    iterations_per_run renders, gc collected â€” the headline figure;
        //  - `peak_mem` is the run's high-water mark.
        //
        // The retired `mem_delta` compared two PEAK readings and therefore
        // always read 0 (see peak-probe.php): a high-water mark is set by the
        // compile in render 1, so no later render can raise it. The retained
        // figures replace it, and they CAN move, which is what makes `0% growth
        // over 10,000 renders` a finding rather than a restatement.
        'first_render_cold'     => true,
        'first_render_ms_basis' => 'one render in a fresh process with a cold template cache: engine class loading, template compile, cache write and one render',
        'first_render_probe'    => 'a fresh process with opcache.enable_cli=1 but a per-process CLI segment, so the engine source is compiled in that process; template cache cold',
        'base_mem_basis'        => 'memory_get_usage(false) with the engine constructed and configured, nothing rendered',
        'use1_mem_basis'        => 'memory_get_usage(false) after one render in a fresh process, gc_collect_cycles() called first',
        'retained_mem_basis'    => 'memory_get_usage(false) after a whole run of iterations_per_run renders in a fresh process, gc_collect_cycles() called first',
        'peak_mem_basis'        => 'memory_get_peak_usage(false) actual bytes over a whole run of iterations_per_run renders in a fresh process',
        // The probe's OWN opcache state, stamped because it decides what the two
        // memory columns mean — and it CHANGED on 2026-09-29 from cold to warm.
        // A reading is only comparable with another reading taken the same way,
        // so this belongs in the environment rather than in a comment. A dataset
        // carrying the old value was measured with bytecode in the heap. Note
        // that "opcache on" does NOT mean shared here: the CLI segment is
        // per-process, so the engine source is still compiled in each child.
        'opcache_probe'       => 'a fresh process with opcache.enable_cli=1 but a per-process CLI segment, so engine source is compiled in that process',
        'azera_framework_ref' => azeraFrameworkRef($root),
        // Which Clarity MODE each Clarity row measured. The row key states it
        // (`clarity-open`), but a reader of a dataset that no longer has its
        // harness should be told, rather than have to infer it from a naming
        // convention that could change.
        'clarity_modes' => in_array('clarity-open', array_map('trim', $engines), true)
            ? ['clarity' => 'sandboxed', 'clarity-open' => 'open']
            : null,
        'engine_versions' => viewEngineVersions($root, $engines),
    ];
}

$results = [];

// ONE PASS, ONE FRESH PROCESS PER CELL.
//
// Every (engine, page) cell is measured in its OWN child process, so no cell can
// observe a class another cell declared or a cache artifact another cell wrote.
// That is the whole fix. The render loop used to run here, in this process, with
// `flushCache()` before each cell â€” and a flush deletes cache FILES without
// un-declaring a CLASS, so an engine guarded by `class_exists()` skipped both its
// compile AND its cache write on every cell after the first. Stempler's per-render
// cache check then cost ~0.75 us instead of ~13 us, purely because a MAP FILE
// happened not to exist, and which cell landed on which side depended on the walk
// order. measureSteadyState() carries the full account.
//
// The earlier mitigation was to run everything TWICE with the order reversed and
// AVERAGE the two passes. That diluted the artifact instead of removing it, and it
// averaged two states â€” one of which no application is ever in â€” into a third that
// is reachable by neither. A process per cell removes the coupling, and then there
// is nothing left to average and no order to alternate.
$jobs = [];
foreach ($engines as $engineName) {
    foreach (array_keys($pages) as $pageName) {
        $jobs[] = [trim($engineName), $pageName];
    }
}

foreach ($jobs as [$key, $pageKey]) {
    $page     = $pages[$pageKey];
    $template = $page['template'];
    $vars     = $page['vars']($itemsCount);

    echo "\n=== Engine: $key  (page: $pageKey)\n";
    try {
        ['engine' => $engine, 'penalty' => $penalty] = benchmarkEngine($key, $template);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage() . "\n";
        continue;
    }

    $engine->setViewPath($viewPath);
    $engine->addNamespace('benchmarks', $viewPath);

    // EVERY MEASUREMENT BELOW IS TAKEN IN A CHILD PROCESS. NOTHING IS TIMED HERE.
    //
    // This process only BUILDS an engine and RENDERS once, to prove the page
    // renders at all before anything is measured. That render is not a
    // measurement and is not reported: it exists so a broken template fails
    // loudly and names its engine, instead of the run reporting a row of numbers
    // for a page that cannot be rendered.
    //
    // The three probes then each answer their own question in their own process:
    //
    //   measureFirstRender  - one render, cold cache: load + compile + write +
    //                         render. The cost of the first request after a
    //                         deploy. (first-render-probe.php)
    //   measureSteadyState  - the render loop, warm cache. The cost a server
    //                         pays per request. (steady-probe.php)
    //   measureIsolatedPeak - memory, one render and one run's worth.
    //                         (peak-probe.php)
    //
    // They are kept apart deliberately. Each was once folded into this process,
    // and each produced a column that looked like a measurement and was an
    // artifact of measurement ORDER â€” a class that stayed declared, a map file
    // that stayed absent, a high-water mark that never fell.
    echo "Render check...\n";
    try {
        $engine->render($template, $vars);
    } catch (Exception $e) {
        echo "Error during render check: " . $e->getMessage() . "\n";
        continue;
    }

    $firstMs = measureFirstRender($key, $pageKey, $itemsCount);
    if ($firstMs === null) {
        echo "Error during first render: the fresh-process probe returned nothing\n";
        continue;
    }

    // The ISOLATED footprint, not this process's high-water mark: engines
    // measured after this one would otherwise inherit its peak (see
    // measureIsolatedPeak()). NO fallback to the in-process figure â€” that is the
    // value this exists to replace, and using it on failure is how a broken child
    // came back looking like a plausible per-engine measurement.
    //
    // The PAGE is passed on. It used to be omitted, and the probe therefore
    // defaulted to `sample` for every row of every multi-page run: a three-page
    // dataset carried three copies of `sample`'s single-render footprint,
    // differing only where the SUSTAINED probe (which does take the page) leaked
    // through. A wrong number that looks plausible is the failure this whole
    // mechanism exists to prevent, and it survived because the two probes
    // disagreed and nobody had compared them page by page.
    //
    // TWO CHILDREN, TWO QUESTIONS.
    //
    //  - ONE render: what the first request after a deploy leaves behind, and
    //    the peak it briefly reached. This is the only place the compile is
    //    paid, so it is also the only place the transient spike is separable.
    //  - ONE RUN's worth of renders: what the process retains while serving,
    //    and how much (if anything) it grew over the run. The render count
    //    matches the timed loop's iteration count, so the retained figure and
    //    the timings describe the same work.
    //
    // They cannot be one child. `memory_get_usage()` in the first would be the
    // figure the second exists to produce, and in the second the compile is
    // already paid — which is exactly the conflation that made the old
    // single "sustained peak" column a restatement of the first-render one.
    $warmMem      = measureIsolatedPeak($key, $itemsCount, 1, $pageKey);
    $sustainedMem = measureIsolatedPeak($key, $itemsCount, max(1, $itersPerRun), $pageKey);

    echo sprintf(
        "First render: %.3f ms, retained after compile: %s, peak: %s\n",
        $firstMs,
        $warmMem === null || $warmMem['use1'] === null
            ? 'UNAVAILABLE'
            : $warmMem['use1'] . ' bytes',
        $warmMem === null || $warmMem['peakN'] === null
            ? 'UNAVAILABLE (chart will omit it)'
            : $warmMem['peakN'] . ' bytes'
    );
    if ($sustainedMem === null || $sustainedMem['useN'] === null) {
        echo "  (sustained memory probe unavailable for {$key}; retained memory will be blank)\n";
    } else {
        printf(
            "  After a run: base %d, retained %d, peak %d, growth %d bytes, gc collected %d\n",
            $sustainedMem['base'] ?? 0,
            $sustainedMem['useN'],
            $sustainedMem['peakN'] ?? 0,
            ($sustainedMem['use1'] ?? 0) === 0 ? 0 : $sustainedMem['useN'] - $sustainedMem['use1'],
            $sustainedMem['cycles'] ?? 0
        );
    }

    // THE TIMED LOOP, in its own process with its own empty cache directory.
    // Keyed by engine+page+PID so no two cells can collide, and removed by
    // measureSteadyState() when the child exits.
    $steadyCache = sys_get_temp_dir() . '/ve-steady-' . $key . '-' . $pageKey . '-' . getmypid();
    $steady      = measureSteadyState(
        $key,
        $pageKey,
        $itemsCount,
        $itersPerRun,
        $runs,
        $penaltyEnabled,
        $penaltySeparate,
        $steadyCache
    );

    if ($steady === null) {
        echo "Error during steady-state measurement: the probe returned nothing\n";
        continue;
    }

    $runSummaries = $steady['run_summaries'];
    foreach ($runSummaries as $index => $rs) {
        echo sprintf(
            "  Run %d â€” mean: %.3f ms, median: %.3f ms, p95: %.3f ms\n",
            $index + 1,
            $rs['mean'],
            $rs['median'],
            $rs['p95']
        );
    }

    // aggregate across runs, by the SHARED convention (stats-lib.php) so a
    // trimmed mean computed here cannot differ from one computed in a probe.
    $runMeans = array_map(static fn(array $rs): float => (float) $rs['mean'], $runSummaries);
    sort($runMeans);
    $drop    = max(1, (int) round(count($runMeans) * 0.1));
    $trimmed = array_slice($runMeans, $drop, count($runMeans) - 2 * $drop);
    if (count($trimmed) === 0) {
        $trimmed = $runMeans;
    }
    $trimmedMean = array_sum($trimmed) / count($trimmed);

    // The aggregate statistics are computed from the child's RAW SAMPLES, not
    // from its per-run medians: a median of medians is not a median of the
    // distribution, and every published column was measured as a summary of all
    // renders. min_ms is therefore the fastest single render of the whole cell.
    $sAll             = viewEngineStats($steady['samples']);
    $penaltyAggregate = $steady['penalty_samples'] === [] ? null : viewEngineStats($steady['penalty_samples']);

    echo sprintf("Summary across %d runs â€” trimmed mean: %.3f ms (dropped %d/%d)\n", $runs, $trimmedMean, $drop, $runs);

    $results[] = [
        'engine' => $key,
        // Which page this row measured. A dataset holding several pages is
        // otherwise unreadable: the same engine appears more than once and the
        // numbers differ because the WORK differs.
        'page'            => $pageKey,
        'first_render_ms' => $firstMs,
        // THE FLOOR: the engine loaded and configured, nothing rendered. The
        // axis the other figures are differences from — most of the gap between
        // two engines is this plus one compile, not a per-render cost.
        'base_mem' => $sustainedMem['base'] ?? null,
        // What RENDER 1 leaves behind. The compile, the compiler and the
        // compiled class are all retained by now, so this equals the run's
        // retained figure on every engine measured here (see peak-probe.php).
        'use1_mem'           => $warmMem['use1'] ?? null,
        'iterations_per_run' => $itersPerRun,
        'runs'               => $runs,
        'run_summaries'      => $runSummaries,
        'trimmed_mean_ms'    => $trimmedMean,
        'mean_ms'            => $sAll['mean'],
        'min_ms'             => $sAll['min'],
        'median_ms'          => $sAll['median'],
        'p95_ms'             => $sAll['p95'],
        // The HEADLINE memory figure: what the process still holds after a whole
        // run, gc collected. `peak_mem` beside it is the run's high-water mark,
        // which is 5-11% higher and is where the transient compile allocation
        // lives.
        'retained_mem'      => $sustainedMem['useN'] ?? null,
        'peak_mem'          => $sustainedMem['peakN'] ?? null,
        'penalty_aggregate' => $penaltyAggregate,
    ];
}

if ($outPrefix !== null) {
    writeResults(
        $outPrefix,
        $results,
        viewEngineEnv($itemsCount, $itersPerRun, $runs, $engines, $penaltyEnabled)
    );
    // The RESOLVED path, not the raw prefix: `--out=results/<date>` does not
    // say where the file went, and a message that names the wrong directory is
    // how a completed run gets looked for in the wrong place (or copied from
    // one).
    $base = resultsBase($outPrefix);
    echo "\nWrote results to: " . str_replace('\\', '/', $base) . "/{$outPrefix}.json and .csv\n";
}
