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
     * The HTTP status the framework produced for the request just dispatched.
     *
     * The contract returns a BODY, so a status can only travel beside it. Before
     * this existed, every consumer guessed: `response-guard.php` classified a body
     * by its first line (`/^([1-5]\d{2}) /`) or a `Not Found` prefix. That worked
     * for adapters that hand-wrote status-shaped bodies and failed for the ones
     * whose framework rendered a real page — Cake's 404 arrived as
     * "500 Cake\Routing\Exception\MissingRouteException: ..." because the body
     * carried no status and nothing else did either.
     *
     * Adapters now report the status their framework actually set. The body rule
     * stays as a fallback for the FPM entry scripts, which have no adapter to ask.
     *
     * Must be 200 before the first dispatch(), and must be reset at the top of
     * every dispatch() so a throw cannot publish the previous request's status.
     */
    public function lastStatus(): int;

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
