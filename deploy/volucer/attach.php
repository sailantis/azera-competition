<?php

declare(strict_types=1);

/**
 * volucer `attach` backend — the prefork-supervisor arm.
 *
 * This process is the SUPERVISOR, not a worker: it listens on the relay and forks one
 * child per accepted connection. Volucer is the client here — it dials the relay
 * `workers` times and holds each connection for the life of a slot — which is the
 * exact inverse of the `worker` backend, where volucer spawns the process. That
 * inversion is why the harness must start THIS file first and volucer second.
 *
 * The framework is booted once by app.php, in this master, before the first fork, so
 * every child inherits the built container and the initialized class table and none
 * of them pays the boot. Children serve exactly one connection each and exit, and the
 * master forks a replacement on the next accept.
 *
 * `children = 0` means no cap on concurrent children, which is the right default for
 * this benchmark in a way it is NOT for a prefork pool: the client is a single
 * sequential HTTP loop, so only one request is ever in flight, and a cap would only
 * bound a concurrency the harness never creates. It is also what makes the arm
 * comparable — a cap of N children would look like `pm.max_children = N` beside a
 * pool stamped at 1. Set VOLUCER_SUPERVISOR_CHILDREN to pin it explicitly.
 *
 * Usage (started by scripts/deploy-lib.php, or by hand):
 *
 *     BENCH_APP=azera VOLUCER_RELAY_SOCKET=unix:/run/volucer-bench/azera.sock \
 *         php deploy/volucer/attach.php
 *
 * The relay address is read from the same variable volucer's `[backends.*] relay`
 * does, so the deployment writes it once. An argv[1] still wins, which is what makes
 * a hand-run supervisor possible without touching the environment.
 */

$handler = require __DIR__ . '/app.php';

$relay = Volucer\Supervisor::relay($argv[1] ?? '');

if ($relay === null) {
    fwrite(
        STDERR,
        "usage: php deploy/volucer/attach.php [unix:/path/to.sock|host:port]\n"
            . 'or set ' . Volucer\Supervisor::RELAY_ENV . " to the same relay volucer's `relay` names\n",
    );
    exit(2);
}

// Supervisor::serve() validates an ADDRESS ("unix:/path" or "host:port") and rejects a
// bare path with "a relay is either ...". A deployment that exports just the socket
// path is therefore a startup failure, and it is an easy one to make because the config
// file wants a path while this wants an address. Accept the bare path and add the
// scheme, so the single environment variable can hold whichever form the operator
// wrote.
if (str_starts_with($relay, '/')) {
    $relay = Volucer\Supervisor::UNIX_PREFIX . $relay;
}

// 0 = no cap (see the header). A non-numeric or negative value is a configuration
// mistake that would otherwise silently mean "no cap", so it is refused.
$childrenArg = (string) (getenv('VOLUCER_SUPERVISOR_CHILDREN') ?: '');
$children    = 0;
if ($childrenArg !== '') {
    if (preg_match('/^\d+$/', $childrenArg) !== 1) {
        fwrite(STDERR, "[volucer] VOLUCER_SUPERVISOR_CHILDREN must be a non-negative integer, got '{$childrenArg}'\n");
        exit(2);
    }
    $children = (int) $childrenArg;
}

Volucer\Supervisor::serve($relay, $handler, $children);
