<?php

declare(strict_types=1);

/**
 * Shared helpers for the HTTP benchmark client (scripts/http-bench.php).
 * stats()/trimmedMean() mirror run.php exactly (same semantics, same field
 * names) so real-deployment numbers are drop-in compatible with the report
 * generator.
 */

// --- Stats (mirrors run.php) -------------------------------------------------

function stats(array $values): array
{
    sort($values);
    $count  = count($values);
    $sum    = array_sum($values);
    $mean   = $sum / $count;
    $median = $values[(int) floor(($count - 1) / 2)];
    $p95Idx = max(0, min($count - 1, (int) floor($count * 0.95) - 1));
    return [
        'count'  => $count,
        'min'    => $values[0],
        'mean'   => $mean,
        'median' => $median,
        'p95'    => $values[$p95Idx],
    ];
}

function trimmedMean(array $values): float
{
    sort($values);
    $drop = max(1, (int) round(count($values) * 0.1));
    $kept = array_slice($values, $drop, count($values) - 2 * $drop);
    if (count($kept) === 0) {
        $kept = $values;
    }
    return array_sum($kept) / count($kept);
}

// --- Measurement budget ------------------------------------------------------

/**
 * The sample one measured mode was taken with: ['iters' => N, 'runs' => N], or
 * null when the mode does not record one.
 *
 * A mode carries the values at mode level (http-bench.php writes them) AND on
 * every request row; the row is the fallback for datasets written before the
 * mode-level fields existed.
 */
function budgetOfMode(array $mode): ?array
{
    $iters = (int) ($mode['iterations_per_run'] ?? 0);
    $runs  = (int) ($mode['runs'] ?? 0);
    if ($iters === 0) {
        $row = $mode['requests'][0] ?? null;
        if (!is_array($row)) {
            return null;
        }
        $iters = (int) ($row['iterations_per_run'] ?? 0);
        $runs  = (int) ($row['runs'] ?? 0);
    }

    return $iters === 0 ? null : ['iters' => $iters, 'runs' => $runs];
}

/** The same, keyed by "<app>/<mode>", across the apps and floor probes of a dataset. */
function budgetsByAppMode(array $dataset): array
{
    $out = [];
    foreach (array_merge($dataset['apps'] ?? [], $dataset['floors'] ?? []) as $app) {
        foreach (($app['modes'] ?? []) as $mode => $m) {
            if (!is_array($m)) {
                continue;
            }
            $b = budgetOfMode($m);
            if ($b !== null) {
                $out["{$app['app']}/{$mode}"] = $b;
            }
        }
    }

    return $out;
}

/**
 * Enforce ONE measurement budget across every mode of a dataset.
 *
 * RoadRunner and PHP-FPM are two ways of running the SAME request, so the
 * comparison between them is only meaningful when both were measured with the
 * same sample. The published dataset broke that (a single-app RoadRunner
 * refresh at 1000x10 while the FPM block stayed at 300x5, because the FPM rows
 * are expensive to re-measure), which left every cross-model caption branching
 * on the mismatch. Both the assembler and the single-app splice therefore
 * refuse to write an asymmetric dataset.
 *
 * Modes that record no budget (older datasets, the in-process harness) are
 * ignored rather than treated as a mismatch — "unknown" is not "different".
 *
 * @param array<string,array{iters:int,runs:int}> $budgets "<app>/<mode>" => budget
 * @return array{ok:bool, budget:?string, byAppMode:array<string,string>}
 */
function assertUniformBudget(array $budgets): array
{
    $labels = [];
    foreach ($budgets as $key => $b) {
        $labels[$key] = "{$b['iters']}x{$b['runs']}";
    }

    $distinct = array_values(array_unique(array_values($labels)));

    return [
        'ok'        => count($distinct) <= 1,
        'budget'    => $distinct[0] ?? null,
        'byAppMode' => $labels,
    ];
}

// --- Request parsing ----------------------------------------------------------

/**
 * Parse the --requests CSV ("METHOD URI,METHOD URI") into [method, uri]
 * pairs; falls back to the same default list as run.php.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function parseRequestsArg(?string $raw): array
{
    $labels = $raw !== null && trim($raw) !== ''
        ? array_map('trim', explode(',', $raw))
        : [
            'GET /',
            'GET /items',
            'GET /items/1',
            'POST /items',
            'GET /items-qb',
            'GET /items-qb/1',
            'POST /items-qb',
            'GET /api/items',
            'GET /api/items/1',
            'POST /api/items',
            'GET /features/aop',
            'GET /features/cache',
            'GET /features/log',
            'GET /features/retry',
            'GET /features/pipeline',
            'GET /features/db-events',
            'GET /features/events',
            'GET /features/validation',
            'GET /features/config',
            'GET /features/request-scoped',
            'GET /features/rate-limit',
        ];

    return array_map(static function (string $r): array {
        $parts = explode(' ', $r, 2);
        return [strtoupper(trim($parts[0])), trim($parts[1] ?? '/')];
    }, $labels);
}

// --- HTTP layer (curl, keep-alive) ---------------------------------------------

/**
 * One request against the real server. Uses ONE reusable curl handle so the
 * TCP connection is kept alive across iterations — the client must add as
 * little overhead as possible (no per-request connect handshakes).
 *
 * @return string response body
 */
function httpRequest(string $baseUrl, string $method, string $uri, array $headers = []): string
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL           => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER    => $headers,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP request failed ({$method} {$baseUrl}{$uri}): {$err}");
    }

    return (string) $body;
}

/**
 * One TIMED request. $connectMs (by ref) receives curl's connect/setup time
 * so the client overhead can be reported separately. Timing covers the full
 * round trip: send -> server -> full body received.
 *
 * @param-out float $connectMs
 * @return array{total_ms: float, connect_ms: float, status: int, body: string}
 */
function httpTimedRequest(string $baseUrl, string $method, string $uri, float &$connectMs = 0.0): array
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL           => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    $t0   = hrtime(true);
    $body = curl_exec($ch);
    $t1   = hrtime(true);

    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP request failed ({$method} {$baseUrl}{$uri}): {$err}");
    }

    $info      = curl_getinfo($ch);
    $connectMs = (float) ($info['connect_time'] ?? 0.0) * 1000.0;

    return [
        'total_ms'   => ($t1 - $t0) / 1e6,
        'connect_ms' => $connectMs,
        'status'     => (int) ($info['http_code'] ?? 0),
        'body'       => (string) $body,
    ];
}

/**
 * Read the LAST memory sample written by an FPM entry script, or null.
 *
 * The FPM probe cannot answer through response headers the way the RoadRunner
 * worker does (deploy/rr/worker.php): an FPM worker has no request loop, so it
 * appends a line to temp/mem-fpm-<app>.jsonl from a shutdown hook instead (see
 * mem_probe_arm() in boot-probe.php). This reads the newest line back.
 *
 * LAST, not a statistic: the harness fires one probe per endpoint immediately
 * after that endpoint's timed loop, so the newest line is the sample for the
 * endpoint just measured. Averaging would blend endpoints together and the
 * probe's whole point is the per-endpoint trajectory.
 *
 * Returns null when the file is missing or the last line is unreadable — the
 * caller must treat "no sample" as no data rather than as zero memory.
 *
 * The field names are the SAME four the RR worker answers with
 * (boot/heap/rss/hwm), so the caller needs no per-server translation.
 *
 * @return array{boot:int,heap:int,rss:int,hwm:int}|null
 */
function memProbeReadLast(string $file): ?array
{
    if (!is_file($file)) {
        return null;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false || $lines === []) {
        return null;
    }
    $row = json_decode((string) end($lines), true);
    if (!is_array($row) || !isset($row['boot'])) {
        return null;
    }
    // 'peak' arrived with the per-request change (2026-09-17). A file written
    // by the older probe has only boot/heap, so fall back to heap rather than
    // reporting a zero peak — the caller then knows the row predates the
    // statistic instead of printing "0 MB".
    $peak = (int) ($row['peak'] ?? $row['heap'] ?? 0);

    return [
        'boot' => (int) ($row['boot'] ?? 0),
        'peak' => $peak,
        'heap' => (int) ($row['heap'] ?? 0),
        'rss'  => (int) ($row['rss'] ?? 0),
        'hwm'  => (int) ($row['hwm'] ?? 0),
    ];
}

/**
 * Count the sample lines in a probe file, or 0 when it does not exist.
 *
 * Used to detect that a probe request's sample has actually LANDED before
 * reading it back — see memProbeWaitForNewSample().
 */
function memProbeLineCount(string $file): int
{
    if (!is_file($file)) {
        return 0;
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    return $lines === false ? 0 : count($lines);
}

/**
 * Wait for ONE new sample to appear in $file, then return it.
 *
 * WHY THIS EXISTS — a write/read race, caught on the bench VM 2026-09-17.
 *
 * The FPM probe writes from a shutdown function (mem_probe_arm() in
 * boot-probe.php), and PHP runs shutdown functions AFTER the response has been
 * flushed to the client. curl therefore returns as soon as the body is
 * complete, which can be BEFORE the write happens. Reading the file straight
 * after the probe request raced that write and produced a zero row for the
 * first endpoint of laravel, cakephp and symfony — the file had not been
 * created yet, so memProbeReadLast() correctly returned null and the caller
 * recorded "no data" as 0.
 *
 * The symptom is nasty because it is partial: the run looks successful, the
 * report renders, and only a handful of rows are wrong. Waiting for the line
 * count to increase makes the read deterministic instead of racy.
 *
 * Bounded: $timeoutMs in 20 ms steps, returning null if no sample arrives. A
 * server that never writes (a framework that forgot to arm the probe) must
 * still fail fast rather than hang the run.
 *
 * @param int $before line count observed BEFORE the probe request
 * @return array{boot:int,heap:int,rss:int,hwm:int}|null
 */
function memProbeWaitForNewSample(string $file, int $before, int $timeoutMs = 1000): ?array
{
    $waited = 0;
    while ($waited <= $timeoutMs) {
        $sample = memProbeReadLast($file);
        if ($sample !== null && memProbeLineCount($file) > $before) {
            return $sample;
        }
        usleep(20000);
        $waited += 20;
    }

    // Timed out: return whatever the last line is (possibly null). The caller
    // must record that as missing data, not as a memory measurement.
    return memProbeLineCount($file) > $before ? memProbeReadLast($file) : null;
}

/**
 * Fire $repeats memory probes at ONE endpoint and return every sample.
 *
 * WHY REPEATS EXIST (added 2026-09-17). Until the probe reported a per-request
 * PEAK, one probe per endpoint was provably enough: the number was the
 * resident heap, which grows with the number of DISTINCT endpoints served, so
 * probing the same endpoint N times added nothing but N identical readings.
 * A per-request peak is different — it is a measurement that varies run to
 * run — so N repeats of one endpoint now carry real information, and the
 * aggregate can distinguish "this endpoint is heavy" from "this run was
 * noisy". The two levels are kept distinct on purpose: see the aggregation in
 * scripts/http-bench.php.
 *
 * FPM samples are read from the probe file, one line per request, so the file
 * must be counted before each request: the write happens in a shutdown hook
 * AFTER the response is flushed and the count-then-wait dance is the only
 * deterministic way to read it (see memProbeWaitForNewSample).
 *
 * A sample that never lands is counted as MISSING, not as zero — the caller
 * gets fewer than $repeats entries rather than a row of zeros, so an
 * unarmed probe fails loudly instead of looking like a tiny heap.
 *
 * @return list<array{boot:int,peak:int,heap:int,rss:int,hwm:int}>
 */
function memProbeRepeated(
    string $baseUrl,
    string $method,
    string $uri,
    string $server,
    string $file,
    int $repeats
): array {
    $samples = [];
    for ($i = 0; $i < $repeats; $i++) {
        try {
            if ($server === 'rr') {
                $probe  = httpProbeRequest($baseUrl, $method, $uri);
                $sample = [
                    'boot' => (int) ($probe['x-bench-boot'] ?? 0),
                    'peak' => (int) ($probe['x-bench-peak'] ?? 0),
                    'heap' => (int) ($probe['x-bench-heap'] ?? 0),
                    'rss'  => (int) ($probe['x-bench-rss'] ?? 0),
                    'hwm'  => (int) ($probe['x-bench-hwm'] ?? 0),
                ];
            } else {
                $before = memProbeLineCount($file);
                httpHeaderProbe($baseUrl, $method, $uri, 'X-Mem-Probe: 1');
                $read   = memProbeWaitForNewSample($file, $before);
                $sample = $read === null ? null : $read;
            }
        } catch (RuntimeException $e) {
            // A failed probe request must not kill the pass: the endpoint is
            // still worth reporting from whatever samples did land.
            continue;
        }

        if ($sample === null || ($sample['boot'] ?? 0) <= 0) {
            continue; // missing, never recorded as zero
        }
        $samples[] = $sample;
    }

    return $samples;
}

/**
 * Collapse one endpoint's repeated samples into a single per-endpoint row.
 *
 * The statistic is the MEDIAN of the endpoint's samples for every field, so
 * every number the report draws is a reading the endpoint actually produced —
 * not an average of readings, which for a high-water mark could exceed the
 * highest value observed. Returns null when no sample landed.
 *
 * @param list<array{boot:int,peak:int,heap:int,rss:int,hwm:int}> $samples
 * @return array{boot:int,peak:int,heap:int,rss:int,hwm:int,samples:int}|null
 */
function memProbeAggregateEndpoint(array $samples): ?array
{
    if ($samples === []) {
        return null;
    }

    $out = [];
    foreach (['boot', 'peak', 'heap', 'rss', 'hwm'] as $field) {
        $vals = array_map(static fn(array $s): int => (int) ($s[$field] ?? 0), $samples);
        sort($vals);
        $out[$field] = $vals[(int) floor((count($vals) - 1) / 2)];
    }
    $out['samples'] = count($samples);

    return $out;
}

/**
 * ONE untimed probe request carrying X-Mem-Probe: 1, returning the response
 * headers lower-cased. Used once per endpoint AFTER the timed loop, so memory
 * accounting never touches the latency path.
 *
 * @return array<string,string>
 */
function httpProbeRequest(string $baseUrl, string $method, string $uri): array
{
    $ch      = curl_init();
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_URL            => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => ['X-Mem-Probe: 1'],
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) {
        return [];
    }
    return $headers;
}

/**
 * N probe requests carrying X-Bench-Split: 1, each answering with the time the
 * RESIDENT WORKER spent in dispatch() (handle) and in cleanup() separately.
 *
 * Why this exists: a resident worker does real work between requests —
 * request-scoped service teardown, ORM/heap resets, driver disconnects — and
 * the timed loop cannot see it because both terms are inside the same
 * end-to-end HTTP measurement. This splits them so "little things need
 * cleanup or initialization" becomes a measurement instead of a guess.
 *
 * Gated on the header, so the timed path pays nothing (the worker does not
 * even call hrtime() without it). Samples come back in microseconds via
 * X-Bench-Handle-Us / X-Bench-Cleanup-Us.
 *
 * @return list<array{handle:float,cleanup:float}> milliseconds
 */
function httpSplitProbe(string $baseUrl, string $method, string $uri, int $samples = 20): array
{
    $out = [];
    for ($i = 0; $i < $samples; $i++) {
        $headers = httpHeaderProbe($baseUrl, $method, $uri, 'X-Bench-Split: 1');
        if (!isset($headers['x-bench-handle-us'], $headers['x-bench-cleanup-us'])) {
            break; // server gone, or an entry script that does not implement it
        }
        $out[] = [
            'handle'  => (float) $headers['x-bench-handle-us'] / 1000,
            'cleanup' => (float) $headers['x-bench-cleanup-us'] / 1000,
        ];
    }
    return $out;
}

/**
 * ONE untimed probe request carrying an arbitrary request header, returning
 * the response headers lower-cased. Shared by the memory and split probes so
 * the header-parsing lives in one place.
 *
 * @return array<string,string>
 */
function httpHeaderProbe(string $baseUrl, string $method, string $uri, string $header): array
{
    $ch      = curl_init();
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_URL            => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => [$header],
        CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$headers): int {
            $pos = strpos($line, ':');
            if ($pos !== false) {
                $headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
            }
            return strlen($line);
        },
    ]);
    $body = curl_exec($ch);
    curl_close($ch);
    if ($body === false) {
        return [];
    }
    return $headers;
}

// --- Abort guard (mirrors benchRequest) ----------------------------------------
/**
 * A benchmark against a broken deployment must never produce numbers:
 * identical guard to run.php's benchRequest() — "Not Found" bodies or "500 "
 * prefixes abort the whole client with exit 1.
 */
function abortIfBroken(string $server, string $appKey, string $reqLabel, string $body, int $status = 200): void
{
    if ($status < 200 || $status >= 300) {
        fwrite(STDERR, "[ABORT] {$server}/{$appKey} {$reqLabel}: HTTP status {$status}\n"
            . "  body head: " . substr($body, 0, 200) . "\n");
        exit(1);
    }
    if ($body === 'Not Found' || str_starts_with($body, '500 ')) {
        fwrite(STDERR, "[ABORT] {$server}/{$appKey} {$reqLabel}: broken response body\n");
        exit(1);
    }
}

// --- Result writer ---------------------------------------------------------------

/**
 * Read boot-probe samples written by the server-side entry script.
 *
 * Kept in bench-lib (not boot-probe.php) because this side runs on the client
 * and must not require the server's helper: bench-lib is the shared library of
 * the harness, boot-probe.php is loaded by the app entry scripts. Samples are
 * one JSON number per line, so a partially written file still yields the
 * samples that landed.
 *
 * @return list<float>
 */
function bootProbeRead(string $file): array
{
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

/**
 * Boot-probe statistics over the sample set. Same shapes as stats(), plus a
 * trimmed mean (10% off each end) so one scheduler hiccup cannot tilt it.
 *
 * @param list<float> $samples
 * @return array{count:int,min:float,mean:float,median:float,p95:float,trimmed_mean:float}
 */
function bootProbeStat(array $samples): array
{
    $s = stats($samples);
    return [
        'count'        => $s['count'],
        'min'          => $s['min'],
        'mean'         => $s['mean'],
        'median'       => $s['median'],
        'p95'          => $s['p95'],
        'trimmed_mean' => trimmedMean($samples),
    ];
}

/**
 * Write the per-(server, app) result JSON in the run.php shape:
 * { app, server, boot: {...}, modes: { <mode>: { requests: [ ...stats ] } } }
 * run-http.php merges these into the combined real-deployments dataset.
 *
 * $boot is the boot-probe stat block (null when the probe produced no
 * samples); it becomes the app-level `boot` field the report reads through
 * ResultStore::boot()/hasBoot().
 */
function writeHttpResults(
    ?string $outJsonPath,
    string $server,
    string $appKey,
    string $modeName,
    int $itersPerRun,
    int $runs,
    array $measured,
    ?array $boot = null,
): void {
    if ($outJsonPath === null) {
        return;
    }

    $payload = [
        'app'    => $appKey,
        'server' => $server,
        'modes'  => [
            $modeName => [
                'iterations_per_run' => $itersPerRun,
                'runs'               => $runs,
                'requests'           => array_values($measured),
            ],
        ],
    ];

    // Field mapping, chosen so the EXISTING report plumbing renders the right
    // number without special-casing, and so the semantics stay true:
    //
    //   php-fpm -> the entry script re-runs per request, so this boot IS part
    //              of every timed row (rows also carry boot_ms for the FPM band).
    //   rr      -> the worker appended N warm recycle samples before serving
    //              anything, so this boot is NOT inside the timed rows; it is
    //              the cost of re-initialising the framework in a warm process.
    //
    // Both fields carry the measured median because a real dataset has exactly
    // ONE kind of boot: whichever band a view looks for must find a number
    // rather than render blank. Which kind it actually is gets recorded in
    // boot_kind, and the report picks its band label and prose from that
    // (MarkdownReport::bootChart) instead of guessing from the field name.
    if ($boot !== null) {
        $median = (float) $boot['median'];
        $payload['boot']         = ['cold_ms' => $median, 'warm_ms' => $median];
        $payload['boot_samples'] = $boot;
        $payload['boot_kind']    = ($server === 'rr') ? 'warm_recycle' : 'per_request_boot';
    }

    $dir = dirname($outJsonPath);
    if ($dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($outJsonPath, json_encode($payload, JSON_PRETTY_PRINT));
}