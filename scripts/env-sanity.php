<?php

declare(strict_types=1);

/**
 * "Are these two datasets measured in the same runtime?" — one answer, shared
 * by every script that splices one dataset into another
 * (merge-app.php, merge-modes.php, assemble-real.php).
 *
 * WHY THIS IS A LIBRARY. The three scripts each carried their own copy of this
 * check, and all three copies had the same defect: they compared the SET of
 * keys present on each side. That makes a missing key indistinguishable from a
 * different one, and it is not an edge case — it is the normal shape of a
 * splice between datasets written by DIFFERENT HARNESSES:
 *
 *   run.php       stamps `opcache` and `sapi`, because it IS the CLI SAPI and
 *                 `opcache.enable_cli` is the switch that governs it.
 *   run-http.php  deliberately stamps NEITHER (see its env block): it is only
 *                 the ORCHESTRATOR, so its own SAPI is not either server's,
 *                 and `opcache` lives per-mode in `opcache_by_mode`.
 *
 * So the canonical real-deployment dataset carries a legacy `sapi: "cli"` left
 * over from the run.php era, while a freshly measured one correctly omits it —
 * and a key-set comparison refused every such splice even though the two runs
 * were the same PHP on the same machine. The check was answering "do these
 * files have the same SHAPE?" when the question is "do these files describe
 * the same RUNTIME?".
 *
 * THE RULE, therefore: a key that BOTH sides record must agree. A key only one
 * side records cannot contradict anything — it is either a newer harness
 * having retired a field, or an older one that predates it — so it is SKIPPED
 * and reported, never treated as a mismatch. `php_version` is stamped by both
 * harnesses and remains the real comparability anchor.
 */

/**
 * The keys that describe the runtime a dataset was measured in.
 *
 * @return list<string>
 */
function envSanityKeys(): array
{
    return ['php_version', 'opcache', 'sapi'];
}

/**
 * The comparable subset of a dataset's env block: only the keys it records.
 *
 * @param array<string,mixed> $dataset
 * @return array<string,mixed>
 */
function envSanity(array $dataset): array
{
    $out = [];
    foreach (envSanityKeys() as $key) {
        if (array_key_exists($key, $dataset['env'] ?? [])) {
            $out[$key] = $dataset['env'][$key];
        }
    }

    return $out;
}

/**
 * Do two datasets describe the same runtime?
 *
 * @param array<string,mixed> $a
 * @param array<string,mixed> $b
 * @return array{ok:bool,compared:array<string,mixed>,skipped:list<string>,mismatches:list<string>}
 */
function envSanityCompare(array $a, array $b): array
{
    $ea = envSanity($a);
    $eb = envSanity($b);

    $mismatches = [];
    $compared   = [];
    foreach ($ea as $key => $value) {
        if (!array_key_exists($key, $eb)) {
            continue; // recorded on one side only — nothing to contradict
        }
        $compared[$key] = $value;
        if ($eb[$key] !== $value) {
            $mismatches[] = sprintf(
                '%s: %s vs %s',
                $key,
                json_encode($value),
                json_encode($eb[$key])
            );
        }
    }

    // A key that was compared (agreeing or not) is not "skipped"; everything
    // else was recorded on exactly one side.
    $skipped = [];
    foreach (array_unique(array_merge(array_keys($ea), array_keys($eb))) as $key) {
        if (!array_key_exists($key, $compared)) {
            $skipped[] = $key;
        }
    }

    return [
        'ok'         => $mismatches === [],
        'compared'   => $compared,
        'skipped'    => array_values($skipped),
        'mismatches' => $mismatches,
    ];
}

/**
 * Assert comparability, printing the standard refusal to STDERR.
 *
 * @param array<string,mixed> $target
 * @param array<string,mixed> $fresh
 */
function assertEnvComparable(array $target, array $fresh, string $what = 'boot numbers'): bool
{
    $cmp = envSanityCompare($target, $fresh);

    if (!$cmp['ok']) {
        fwrite(STDERR, "Env mismatch (php/opcache/sapi differ) — {$what} would not be comparable:\n");
        fwrite(STDERR, '  target: ' . json_encode(envSanity($target)) . "\n");
        fwrite(STDERR, '  fresh : ' . json_encode(envSanity($fresh)) . "\n");
        foreach ($cmp['mismatches'] as $line) {
            fwrite(STDERR, "    ! {$line}\n");
        }

        return false;
    }

    // A key recorded on only one side is not a contradiction, but it means the
    // two files cannot be fully cross-checked — say so rather than staying
    // silent, because silence here previously read as "verified".
    if ($cmp['skipped'] !== []) {
        fwrite(STDERR, 'Note: env key(s) recorded on one side only, not compared: '
            . implode(', ', $cmp['skipped']) . "\n");
    }

    return true;
}
