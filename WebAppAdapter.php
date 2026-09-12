<?php
/**
 * Common interface implemented by every framework adapter.
 *
 * Each adapter bootstraps its framework once (warm mode) or per-iteration
 * (cold mode) and exposes a single dispatch() method that handles a synthetic
 * request and returns the response body as a string.
 */
interface WebAppAdapter
{
    /**
     * Human-readable framework name used in reports.
     */
    public function name(): string;

    /**
     * Bootstrap the framework: register autoload, build DI container,
     * configure routes, connect to SQLite, compile/cache templates.
     *
     * Called once before the timed loop in warm mode, or before every
     * iteration in cold mode.
     */
    public function bootstrap(): void;

    /**
     * Handle a synthetic request and return the response body.
     *
     * @param string $method HTTP method (GET, POST, ...)
     * @param string $uri    Request URI (path + optional query string)
     * @return string        Response body (the rendered output)
     */
    public function dispatch(string $method, string $uri): string;

    /**
     * Per-request teardown — the work a long-lived worker performs AFTER the
     * response has been sent and BEFORE the next request starts:
     * terminate()-style finalizers, request-scoped service resets,
     * per-request container/scope disposal.
     *
     * The harness times dispatch() and cleanup() separately but reports their
     * SUM as the request latency, so published numbers stay comparable with
     * datasets recorded before this split. The split only exposes where the
     * cost sits (handle vs teardown) without changing what is measured.
     */
    public function cleanup(): void;
}