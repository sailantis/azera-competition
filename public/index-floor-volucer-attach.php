<?php

declare(strict_types=1);

/**
 * Floor entry script: a front controller with NO framework behind it.
 *
 * WHY THIS EXISTS. Every measured server arm gets a floor
 * probe, so the constant server overhead is visible in the dataset instead of
 * being attributed to whichever framework ran on that row. The two Volucer arms
 * had no floor until 2026-11-24, which left their rows — the ones where the floor
 * is LARGEST relative to the work — without a reference point. `/echo` on
 * volucer-attach printed 149 us for Phalcon when the whole framework layer is
 * 4 us; without a floor, that 149 reads as a framework number.
 *
 * WHAT IT IS. The same shape as `index-<app>.php`: an entry script in public/,
 * served by the same arm, through the same `front_controller` setting, with the
 * same `BENCH_PROBE_KIND` env. The only thing it does is echo a constant, so
 * `floor − app` is the framework and `floor` alone is the arm.
 *
 * Deliberately NOT a real app's script with a switch inside it: the floor must
 * not be able to drift into booting something, and a probe that shares a file
 * with a measured app is a probe one edit away from measuring the app.
 *
 * The name `floor-volucer-attach` is what
 * `stampVolucerConfigs()` stamps into `front_controller`, and it is why this file
 * is named `index-floor-volucer-attach.php` — `index-{{APP}}.php` with
 * `{{APP}} = floor-volucer-attach`. The two arms share it because the only
 * difference between them is whether volucer forks a child (attach) or spawns
 * a per-request transport to run this file.
 */

// A constant, and nothing else. No autoloader, no boot, no database: the whole
// point is to leave the framework out of the measurement.
header('Content-Type: text/html; charset=utf-8');
echo 'floor-volucer';
