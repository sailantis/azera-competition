<?php

declare(strict_types=1);

/**
 * Merge mem-only probe results into an existing real-deployment dataset.
 *
 * The exact counterpart of merge-boot.php, and for the same reason. The
 * worker-memory probe is also a measurement that cannot be recovered from the
 * latency rows:
 *
 *   - it is ONE-SHOT — the numbers come from a handful of extra untimed probe
 *     requests per endpoint, not from 1000x10 timed samples;
 *   - mem_heap is ORDER-DEPENDENT — the probe reads the whole resident heap
 *     once per probe request, so it is cumulative in the harness's request
 *     order (ResultStore::residentTrajectory documents this). Re-reading the
 *     latency JSON cannot produce it, because the latency rows never carried
 *     it.
 *
 * So `scripts/http-bench.php --mem-only` walks the endpoints (N probes each, a
 * few seconds per app), and this script grafts the result onto the existing
 * dataset. The 1000x10 timing numbers already in it stay untouched.
 *
 * WHAT IS MERGED, AND WHY ONLY FOR php-fpm.
 *
 * MERGED (php-fpm, and only php-fpm): mem_peak_heap, mem_samples and
 * mem_boot_heap — the PER-REQUEST statistic plus the footprint it is measured
 * against. It is a property of one request, so a dedicated probe pass measures
 * it exactly as the full run would, and repeats only reduce its noise.
 *
 * This is safe for FPM for a measured reason: a fresh process always boots to
 * the same heap. Verified on the bench VM 2026-09-17 — the probe pass's boot
 * matched the dataset's boot to 0.000 MB for all six apps, so merging it cannot
 * contradict anything already in the dataset. Merging it anyway (rather than
 * relying on that equality) is what keeps `peak >= boot` true WITHIN the row
 * even if a future runtime ever drifts.
 *
 * NOT MERGED, and REFUSED: roadrunner. A resident worker's memory is a
 * CUMULATIVE TRAJECTORY belonging to ONE run, and its boot heap is not
 * reproducible:
 *
 *   - its boot is read after the worker's 20 re-boot cycles, i.e. it is a WARM
 *     heap, and it drifts run to run. Measured 2026-09-17: +0.071 (azera),
 *     +0.416 (laravel), +1.925 (symfony), -1.736 (spiral), -0.002 (codeigniter),
 *     +0.071 (cakephp). Merging produced rows with peak BELOW boot — arithmetically
 *     impossible for one worker, and a symptom of mixing two runs, not of a
 *     broken probe.
 *   - its mem_heap is the trajectory boot -> ... -> last, and the probe pass
 *     serves --mem-repeats requests per endpoint (210 over the suite) where the
 *     full run serves one (21). Grafting it replaced cakePHP's genuine
 *     5.3 -> 40.6 MB climb with a flat 3.5 MB line.
 *
 * So an RR payload is SKIPPED, loudly, and the RR page keeps its single-run
 * story. Its own chart draws boot -> end -> worst from the cumulative heap,
 * which is exactly the question a resident worker answers.
 *
 * REFUSED (any mode): a payload measured before the per-request peak existed
 * (no mem_peak_heap in any row). Merging one would write zeros, and a zero
 * renders as "this framework needs no memory" — the same silent partial
 * wrongness the probe's write/read race produced before it was fixed.
 *
 * Usage:
 *   php scripts/merge-mem.php <dataset.json> <mem1.json> <mem2.json> ...
 *
 * Each mem file is the --mem-only output of scripts/http-bench.php for one
 * (server, app) pair. RoadRunner files are accepted and skipped with a reason,
 * so a run that probes both servers needs no filtering by the caller.
 */

$args = array_slice($argv, 1);
if (count($args) < 2) {
    fwrite(STDERR, "Usage: php scripts/merge-mem.php <dataset.json> <mem1.json> [mem2.json ...]\n");
    exit(1);
}
$datasetPath = array_shift($args);

$dataset = json_decode((string) file_get_contents($datasetPath), true);
if (!is_array($dataset) || !isset($dataset['apps'])) {
    fwrite(STDERR, "{$datasetPath}: not a result dataset\n");
    exit(1);
}

// server => mode, matching run-http.php's $modeOf and merge-boot.php.
$modeOf = static fn(string $server): string => $server === 'rr' ? 'roadrunner' : 'php-fpm';

$byApp = [];
foreach ($dataset['apps'] as $i => $app) {
    $byApp[(string) ($app['app'] ?? '')] = $i;
}

// The per-request statistic plus the footprint it is measured against. Both
// belong to the probe pass, and both are safe for php-fpm: a fresh process
// boots to the same heap every time (verified 0.000 MB drift across all six
// apps), so merging boot cannot contradict the dataset — and carrying it keeps
// peak >= boot true WITHIN the row.
$memKeys = ['mem_boot_heap', 'mem_peak_heap', 'mem_samples'];

$applied = [];
$skipped = [];
foreach ($args as $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "missing mem file: {$path}\n");
        exit(1);
    }
    $m = json_decode((string) file_get_contents($path), true);
    if (!is_array($m) || empty($m['mem_only'])) {
        fwrite(STDERR, "{$path}: not a --mem-only result (expected mem_only: true)\n");
        exit(1);
    }
    $appKey = (string) ($m['app'] ?? '');
    $server = (string) ($m['server'] ?? '');
    if ($appKey === '' || !isset($byApp[$appKey])) {
        fwrite(STDERR, "{$path}: app '{$appKey}' is not in the dataset\n");
        exit(1);
    }
    $mode = $modeOf($server);
    $idx  = $byApp[$appKey];
    $rows = $m['mem_rows'] ?? [];
    if (!is_array($rows) || $rows === []) {
        fwrite(STDERR, "{$path}: no memory rows collected — refusing to write a partial probe\n");
        exit(1);
    }

    // RoadRunner is SKIPPED, not merged: a resident worker's memory is one
    // run's cumulative trajectory and its boot heap is not reproducible (it is
    // read warm, after the worker's re-boot cycles). Merging a probe pass's
    // peak into a dataset whose boot came from the latency run produced rows
    // with peak BELOW boot — impossible for a single worker, and a symptom of
    // mixing two runs rather than of a bad probe. Reported, so the caller sees
    // why, and so "the RR chart did not change" is never a silent surprise.
    if ($server === 'rr') {
        $skipped[] = "{$appKey}/roadrunner: skipped — a resident worker's memory is one "
            . 'run\'s cumulative trajectory and its warm boot heap is not reproducible '
            . 'between runs, so a probe pass cannot be grafted onto the latency run.';
        continue;
    }

    // A payload from a probe that predates the per-request peak carries no
    // mem_peak_heap. Merging it would write zeros, and a zero renders as "this
    // framework needs no memory" — so refuse rather than publish it. This is
    // also the guard that catches a STALE payload file left on the VM by an
    // earlier run: re-fetching one looks like a successful merge of old data.
    $peakRows = 0;
    foreach ($rows as $mem) {
        if ((int) ($mem['mem_peak_heap'] ?? 0) > 0) {
            $peakRows++;
        }
    }
    if ($peakRows === 0) {
        fwrite(
            STDERR,
            "{$path}: no mem_peak_heap in any row — this payload was measured before the "
                . "per-request peak existed (or is stale). Re-run the mem-only pass.\n"
        );
        exit(1);
    }
    if (!isset($dataset['apps'][$idx]['modes'][$mode])) {
        fwrite(STDERR, "{$path}: dataset has no '{$mode}' block for '{$appKey}'\n");
        exit(1);
    }

    // Index the dataset's timed rows for this app+mode by request label, so a
    // probe row that matches nothing is reported rather than dropped.
    $rowIdx = [];
    foreach ($dataset['apps'][$idx]['modes'][$mode]['requests'] as $ri => $row) {
        $rowIdx[(string) ($row['request'] ?? '')] = $ri;
    }

    $unmatched = [];
    $written   = 0;
    foreach ($rows as $reqLabel => $mem) {
        if (!isset($rowIdx[$reqLabel])) {
            $unmatched[] = (string) $reqLabel;
            continue;
        }
        $ri = $rowIdx[$reqLabel];
        foreach ($memKeys as $k) {
            $dataset['apps'][$idx]['modes'][$mode]['requests'][$ri][$k] = (int) ($mem[$k] ?? 0);
        }
        $written++;
    }

    if ($unmatched !== []) {
        // Refuse rather than silently ship an incomplete chart: an endpoint the
        // probe measured but the dataset never timed means the two were taken
        // against different endpoint sets, so neither is trustworthy.
        fwrite(
            STDERR,
            "{$path}: {$appKey}/{$mode} probed endpoints the dataset does not have: "
                . implode(', ', $unmatched) . "\n"
        );
        exit(1);
    }

    $boot    = 0;
    $reqPeak = 0;
    $samples = 0;
    foreach ($rows as $mem) {
        $b = (int) ($mem['mem_boot_heap'] ?? 0);
        $p = (int) ($mem['mem_peak_heap'] ?? 0);
        $s = (int) ($mem['mem_samples'] ?? 0);
        if ($b > 0) {
            $boot = $b; // identical on every endpoint — a property of the worker
        }
        if ($p > $reqPeak) {
            $reqPeak = $p;
        }
        if ($s > $samples) {
            $samples = $s;
        }
    }
    // The boot footprint is reported alongside the peak: it comes from the
    // probe pass and it MUST agree with the dataset's own boot, so printing it
    // is what makes a drift visible instead of silent.
    $applied[] = sprintf(
        '%s/%s: %d endpoint(s), boot %.3f MB, worst request peak %.3f MB, %d probes/endpoint',
        $appKey,
        $mode,
        $written,
        $boot / 1048576,
        $reqPeak / 1048576,
        $samples
    );
}

file_put_contents($datasetPath, json_encode($dataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo 'Merged ' . count($applied) . " memory block(s) into {$datasetPath}:\n";
foreach ($applied as $line) {
    echo "  {$line}\n";
}

if ($skipped !== []) {
    echo "\nSkipped " . count($skipped) . " block(s):\n";
    foreach ($skipped as $line) {
        echo "  {$line}\n";
    }
}
