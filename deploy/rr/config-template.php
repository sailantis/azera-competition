<?php

/**
 * RoadRunner per-app config generator.
 *
 * Instead of six hand-maintained .rr.yaml files, the orchestrator
 * (scripts/run-http.php) calls stampAllDeployConfigs() to stamp out one
 * config per app from this single template. Pool: num_workers = 1 for SQLite
 * write parity with the in-process harness (one sequential client, one
 * worker — no lock contention), opcache on for warm-boot parity.
 *
 * `{{PHP_CLI}}` is the INTERPRETER THE WORKER RUNS UNDER, and it is a
 * placeholder rather than a bare `php` on purpose. RoadRunner spawns this
 * command itself, so whatever the bare name resolves to on PATH becomes the
 * runtime of every RoadRunner number in the dataset — while FPM serves the
 * same apps out of a DIFFERENT, separately-packaged binary (`php-fpm<X.Y>`).
 * On the bench VM those two diverged once `/etc/alternatives/php` was moved
 * to 8.5 while the FPM pool stayed on 8.3, which put two PHP versions in one
 * dataset under a single `env.php_version`. run-http.php therefore stamps the
 * resolved binary in, the same way it stamps {{ROOT}}.
 */

return static function (): string {
    // RR resolves the worker command relative to its own cwd — which is NOT
    // guaranteed to be the repo root — so the template takes an absolute
    // {{ROOT}} and bakes it in at stamp time.
    $template = <<<'YAML'
    version: "3"

    server:
      command: "{{PHP_CLI}} -d opcache.enable_cli=1 -d opcache.validate_timestamps=0 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M {{ROOT}}/deploy/rr/worker.php"
      env:
        BENCH_APP: "{{APP}}"
        RR_REFRESH_SERVICES: "true"

    http:
      address: "127.0.0.1:{{PORT}}"
      pool:
        num_workers: 1
        max_jobs: 0
        supervisor:
          max_worker_memory: 512
        allocate_timeout: 60s
        destroy_timeout: 60s

    logs:
      mode: development
      level: error
    YAML;

    return $template;
};