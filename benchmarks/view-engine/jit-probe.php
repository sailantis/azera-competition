<?php

declare(strict_types=1);

/**
 * Prints the JIT configuration this process actually ran under, as `key=value;`
 * pairs on one line, for the view-engine harness to stamp into its dataset.
 *
 * WHY THIS IS A CHILD PROCESS AND NOT AN `ini_get()` IN THE PARENT
 *
 * The harness launches every measured child with `-d opcache.enable_cli=1` and
 * nothing else, so the JIT configuration each measured cell ran under comes from
 * the interpreter's php.ini, not from the parent's command line. The parent is
 * also launched by a human or a launcher script, and may carry its own `-d`
 * flags. Reading `ini_get()` in the parent would therefore describe the
 * harness's process, which is not a process any published figure was measured
 * in. Spawning a child with the SAME flags the measured children get is what
 * makes the stamp a fact about the measurement.
 *
 * WHY `opcache_get_status()` AS WELL AS `ini_get()`
 *
 * The two answer different questions, and the published clause depends on both:
 *
 *  - `ini_get('opcache.jit')` and `opcache.jit_buffer_size` say what was
 *    CONFIGURED. `tracing` beside a `0`-byte buffer selects a strategy for a
 *    buffer that does not exist, so nothing is compiled — semantically off, but
 *    a reader who checked php.ini would conclude the opposite.
 *  - `opcache_get_status()['jit']['enabled']` says whether the JIT actually came
 *    up. That is the runtime fact, and it is the one a published figure should
 *    rest on.
 *
 * The harness records all three so the report can state the configuration AND
 * whether it took effect. A host without the opcache extension prints nothing
 * here and the harness omits the keys entirely — "not establishable" is not
 * "off", and a dataset claiming the JIT was off on such a host would be
 * inventing a measurement.
 */

$mode   = ini_get('opcache.jit');
$buffer = ini_get('opcache.jit_buffer_size');

// Absent ini entries (no opcache extension, or an older PHP) leave both false.
// Nothing is printed in that case, so the caller sees an empty reading rather
// than a pair of empty strings it would have to special-case.
if ($mode === false && $buffer === false) {
    exit(1);
}

$enabled = false;
if (function_exists('opcache_get_status')) {
    // `false` disables script-level detail; the JIT block is present either way
    // and is much cheaper to collect without per-script entries.
    $status = opcache_get_status(false);
    if (is_array($status) && isset($status['jit']) && is_array($status['jit'])) {
        $enabled = (bool) ($status['jit']['enabled'] ?? false);

        // `enabled` is the JIT being switched on; `on` is whether it has started
        // compiling. Both must hold for a figure to have been produced by JIT'd
        // code, so both are ANDed. A buffer of 0 reports `enabled` true on some
        // builds, which is exactly the case this closes.
        $enabled = $enabled && (bool) ($status['jit']['on'] ?? true);
    }
}

// `;` separates pairs and `=` separates key from value, matching the shape
// peak-probe.php and first-render-probe.php already print, so the parent's
// parser can be the same defensive one rather than a second format.
printf(
    'opcache_jit=%s;opcache_jit_buffer_size=%s;opcache_jit_enabled=%d',
    $mode === false ? '' : (string) $mode,
    $buffer === false ? '' : (string) $buffer,
    $enabled ? 1 : 0
);
