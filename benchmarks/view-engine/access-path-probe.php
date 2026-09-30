<?php

declare(strict_types=1);

/**
 * What the removed eager conversion cost: an A/B of the two access mechanisms.
 *
 * WHY THIS IS COMMITTED
 *
 * The strict syntax removed `ClarityEngineTrait::castToArray()` — the traversal
 * that converted the ENTIRE variable scope to arrays before every render. That
 * change is only observable as a time difference, so nothing in a correctness
 * suite can guard it, and a future "simplification" that reinstates the
 * conversion (for example by calling `Access::iterate()` on the whole scope in
 * `renderPartial()`) would render identical pages and look like benchmark noise.
 *
 * This probe is the measurement that makes the change falsifiable. It is
 * deliberately NOT a template comparison: both arms are hand-written PHP over
 * the same data so the difference is the conversion and the access mechanism
 * rather than the compiler.
 *
 * WHAT IT COMPARES
 *
 *   OLD  cast the scope (the deleted implementation, kept here) then read array
 *        keys: `$vars['user']['address']['city']`
 *   NEW  keep objects and read properties: `$vars['user']->address->city`
 *
 * The read pattern is the `entities` page: a loop over the item list reading two
 * properties each, one nested-object read, one nullable property behind a
 * default, and a loop over an array-inside-an-object.
 *
 * Both arms MUST produce the same page; it exits non-zero if they do not, so
 * "the new path is faster" can never be reported for a page that differs.
 *
 * THE ARMS RUN IN ALTERNATING ORDER. A fixed order is a bias, not a comparison:
 * whichever arm runs second benefits from a warmer heap, and an earlier probe
 * of this shape reported the opposite of the truth for exactly that reason.
 *
 * Usage: php access-path-probe.php [items] [iterations]
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/templates/fixtures.php';

$items      = (int) ($argv[1] ?? 200);
$iterations = (int) ($argv[2] ?? 3000);

/**
 * The removed conversion, in its original precedence order:
 * array -> DateTimeInterface -> scalar -> toArray() -> JsonSerializable
 * -> Traversable -> get_object_vars (+ Stringable fallback) -> recurse.
 *
 * Kept verbatim so the "before" arm measures the code that was actually
 * shipped, not an idealised version of it.
 */
function accessProbeCast(mixed $value): mixed
{
    if (\is_array($value)) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = accessProbeCast($v);
        }
        return $result;
    }

    if ($value instanceof \DateTimeInterface) {
        return $value->format(\DateTimeInterface::ATOM);
    }

    if (!\is_object($value)) {
        return $value;
    }

    if (\is_callable([$value, 'toArray'])) {
        return accessProbeCast($value->toArray());
    }

    if ($value instanceof \JsonSerializable) {
        $data = $value->jsonSerialize();
        return accessProbeCast(\is_object($data) ? get_object_vars($data) : $data);
    }

    if ($value instanceof \Traversable) {
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = accessProbeCast($v);
        }
        return $result;
    }

    $vars = get_object_vars($value);
    if ($vars === [] && $value instanceof \Stringable) {
        return (string) $value;
    }

    return accessProbeCast($vars);
}

/** The entities page read from ARRAY data (what the OLD path produced). */
function accessProbeOld(int $items): string
{
    $vars = accessProbeCast(benchmarkEntityVars($items) + ['title' => 'Benchmark']);

    $out = '';
    foreach ($vars['items'] as $item) {
        $out .= $item['label'] . $item['id'];
    }
    $out .= $vars['user']['address']['city'] . $vars['user']['address']['country'];
    $out .= $vars['user']['nickname'] ?? 'anonymous';
    foreach ($vars['user']['roles'] as $role) {
        $out .= $role;
    }

    return $out;
}

/** The entities page read from OBJECT data (the NEW path). */
function accessProbeNew(int $items): string
{
    $vars = benchmarkEntityVars($items) + ['title' => 'Benchmark'];

    $out = '';
    foreach ($vars['items'] as $item) {
        $out .= $item->label . $item->id;
    }
    $out .= $vars['user']->address->city . $vars['user']->address->country;
    $out .= $vars['user']->nickname ?? 'anonymous';
    foreach ($vars['user']->roles as $role) {
        $out .= $role;
    }

    return $out;
}

$oldOut = accessProbeOld($items);
$newOut = accessProbeNew($items);

if ($oldOut !== $newOut) {
    fwrite(STDERR, "MISMATCH: the two access paths do not produce the same page\n");
    fwrite(STDERR, "  old: " . strlen($oldOut) . " bytes, new: " . strlen($newOut) . " bytes\n");
    exit(1);
}

printf("items=%d iterations=%d\n", $items, $iterations);
printf("outputs identical: %d bytes\n\n", strlen($oldOut));

$rounds = 9;
$tOld   = 0.0;
$tNew   = 0.0;

for ($r = 0; $r < $rounds; $r++) {
    // Alternate which arm goes first so neither always enjoys a warm heap.
    if ($r % 2 === 0) {
        $s = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            accessProbeOld($items);
        }
        $tOld += microtime(true) - $s;

        $s = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            accessProbeNew($items);
        }
        $tNew += microtime(true) - $s;
    } else {
        $s = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            accessProbeNew($items);
        }
        $tNew += microtime(true) - $s;

        $s = microtime(true);
        for ($i = 0; $i < $iterations; $i++) {
            accessProbeOld($items);
        }
        $tOld += microtime(true) - $s;
    }
}

$old = $tOld / $rounds / $iterations * 1000;
$new = $tNew / $rounds / $iterations * 1000;

printf("OLD  conversion + array reads : %7.4f ms/page\n", $old);
printf("NEW  no conversion + property : %7.4f ms/page\n", $new);
printf("delta                         : %+7.4f ms  (%+.1f%%)\n", $new - $old, ($new - $old) / $old * 100);

// How much of the OLD arm is the conversion itself, so a regression can be
// attributed without re-deriving the split.
$tCast = 0.0;
for ($r = 0; $r < 5; $r++) {
    $s = microtime(true);
    for ($i = 0; $i < $iterations; $i++) {
        accessProbeCast(benchmarkEntityVars($items));
    }
    $tCast += microtime(true) - $s;
}
$cast = $tCast / 5 / $iterations * 1000;
printf("\nconversion alone              : %7.4f ms/page  (%.0f%% of the OLD arm)\n", $cast, $cast / $old * 100);