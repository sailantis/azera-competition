<?php

declare(strict_types=1);

/**
 * volucer `worker` backend — the resident-worker arm.
 *
 * NOT part of the benchmark dataset. Volucer's `worker` and `attach` backends are both
 * resident and both load the same handler from app.php, so they measure the same thing
 * and differ only in who owns the process (volucer spawns it here; deploy/volucer/
 * attach.php supervises it there). The dataset keeps `attach` and `spawn_cgi` — one
 * resident arm, one cold — and adding `worker` back would put two rows measuring the
 * same model side by side.
 *
 * Kept runnable for spot checks:
 *
 *     BENCH_APP=azera VOLUCER_SDK=/path/to/volucer-worker \
 *         php deploy/volucer/worker.php
 *
 * with deploy/volucer/pool.template.toml as its volucer configuration. Note that
 * --servers no longer accepts the name and stampVolucerConfigs() does not render this
 * arm's config, so the harness will not start it for you.
 */

$handler = require __DIR__ . '/app.php';

Volucer\Worker::create()->run(
    static fn (Volucer\Request $request, Volucer\Worker $worker): Volucer\Response
        => $handler($request)
);
