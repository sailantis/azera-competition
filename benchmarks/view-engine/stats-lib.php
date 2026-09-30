<?php

declare(strict_types=1);

/**
 * The one definition of "a summary of a timing distribution", shared by the
 * harness and its probes.
 *
 * WHY A SHARED FILE
 *
 * The steady-state probe runs in a CHILD process and reports raw per-run
 * summaries back to the harness. Both sides need `median` and `p95`, and the
 * harness also needs `mean` and `min`. If the probe carried its own copy, the
 * two could disagree — a different percentile convention inside the probe and
 * outside it would make a published p95 describe a different index than the one
 * its own label implies, and nothing in the artifact would show it.
 *
 * This is the same reason `engines.php` and `pages.php` are shared rather than
 * per-consumer: one definition cannot drift, and a number that is plausible but
 * describes different work is this harness's recurring failure mode.
 *
 * The index conventions are preserved EXACTLY as the harness had them, because
 * every published figure was produced with them:
 *
 *   median -> $values[(int) floor((count - 1) / 2)]   (lower middle for even counts)
 *   p95    -> $values[floor(count * 0.95) - 1], clamped into range
 */

if (!function_exists('viewEngineStats')) {
    /**
     * Summary statistics for one list of timings, in milliseconds.
     *
     * `min` is the FASTEST SINGLE OBSERVATION and is reported separately: a
     * range chart needs a low end that is a real measurement, and a chart whose
     * left cap fell back to the median would claim every engine was perfectly
     * stable.
     *
     * @param  list<float> $values
     * @return array{count:int,min:float,mean:float,median:float,p95:float}
     */
    function viewEngineStats(array $values): array
    {
        sort($values);
        $count  = count($values);
        $mean   = array_sum($values) / $count;
        $median = $values[(int) floor(($count - 1) / 2)];

        $p95Index = (int) floor($count * 0.95) - 1;
        if ($p95Index < 0) {
            $p95Index = 0;
        }
        if ($p95Index > $count - 1) {
            $p95Index = $count - 1;
        }

        return [
            'count'  => $count,
            'min'    => $values[0],
            'mean'   => $mean,
            'median' => $median,
            'p95'    => $values[$p95Index],
        ];
    }
}
