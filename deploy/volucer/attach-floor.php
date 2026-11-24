<?php

declare(strict_types=1);

/**
 * Floor supervisor for the volucer `attach` arm: a prefork server with NO framework.
 *
 * WHY THIS IS A SEPARATE FILE FROM attach.php. attach.php requires app.php, which
 * selects a framework adapter from BENCH_APP and exits on an unknown name — by
 * design, because a typo there must not silently measure the wrong app. The floor
 * has no app, so it cannot go through that path, and teaching app.php a
 * "no framework" mode would put a branch in the worker that every measured request
 * pays for, in service of a probe that must not measure anything.
 *
 * WHAT IT PRESERVES. Everything that makes the attach arm the attach arm: the same
 * relay protocol, the same `Supervisor::serve()`, the same fork-a-child-per-connection
 * behaviour, the same one-connection rule, the same relay address and the same
 * `max_requests = 0`. The only difference is that the handler echoes a constant
 * instead of dispatching through a framework, so `floor − app` is the framework and
 * `floor` alone is the arm's constant cost.
 *
 * WHY THE ATTACH FLOOR IS NOT ~150 us. The children are forked from this already-
 * running master, and volucer holds one connection for the life of the slot, so the
 * fork happens once per run: per-request, this arm pays a unix-socket round trip and
 * almost nothing else. That is exactly what the floor should report, and it is the
 * number a reader needs in order to see that a 149 us `/echo` row on this arm is
 * nearly all floor.
 *
 * Usage (started by scripts/deploy-lib.php, or by hand):
 *
 *     VOLUCER_RELAY_SOCKET=unix:/run/volucer-bench/floor-volucer-attach.sock \
 *         php deploy/volucer/attach-floor.php
 */

$sdk = (string) (getenv('VOLUCER_SDK') ?: dirname(__DIR__, 2) . '/vendor/sailantis/volucer-worker');
if (!is_file($sdk . '/autoload.php')) {
    fwrite(STDERR, "[floor-volucer] volucer-worker SDK not found at {$sdk} — set VOLUCER_SDK\n");
    exit(1);
}
require $sdk . '/autoload.php';

$relay = Volucer\Supervisor::relay($argv[1] ?? '');
if ($relay === null) {
    fwrite(
        STDERR,
        "usage: php deploy/volucer/attach-floor.php [unix:/path/to.sock|host:port]\n"
            . 'or set ' . Volucer\Supervisor::RELAY_ENV . " to the same relay the floor's config names\n",
    );
    exit(2);
}

if (str_starts_with($relay, '/')) {
    $relay = Volucer\Supervisor::UNIX_PREFIX . $relay;
}

// The handler a child calls. Deliberately the smallest thing that can answer: no
// autoloader, no adapter, no boot. The header mirrors what a real response carries so
// the client reads a well-formed reply, and nothing else is done.
$handler = static function (Volucer\Request $request): Volucer\Response {
    return new Volucer\Response(
        200,
        [['Content-Type', 'text/html; charset=utf-8']],
        'floor-volucer',
    );
};

$childrenArg = (string) (getenv('VOLUCER_SUPERVISOR_CHILDREN') ?: '');
$children    = 0;
if ($childrenArg !== '') {
    if (preg_match('/^\d+$/', $childrenArg) !== 1) {
        fwrite(STDERR, "[floor-volucer] VOLUCER_SUPERVISOR_CHILDREN must be a non-negative integer, got '{$childrenArg}'\n");
        exit(2);
    }
    $children = (int) $childrenArg;
}

Volucer\Supervisor::serve($relay, $handler, $children);
