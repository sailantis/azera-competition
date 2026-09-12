<?php

/**
 * RoadRunner per-app config generator.
 *
 * Instead of six hand-maintained .rr.yaml files, the orchestrator
 * (scripts/run-http.php) calls writeRrConfig($app, $port) to stamp out one
 * config per app from this single template. Pool: num_workers = 1 for SQLite
 * write parity with the in-process harness (one sequential client, one
 * worker — no lock contention), opcache on for warm-boot parity.
 */

return static function (): string {
    // RR resolves the worker command relative to its own cwd — which is NOT
    // guaranteed to be the repo root — so the template takes an absolute
    // {{ROOT}} and bakes it in at stamp time.
    $template = <<<'YAML'
    version: "3"

    server:
      command: "php {{ROOT}}/deploy/rr/worker.php"
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