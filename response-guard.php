<?php

declare(strict_types=1);

/**
 * One definition of "this response body is an error page", shared by the
 * harness that measures requests and the transports that serve them.
 *
 * WHY THIS FILE EXISTS. The benchmark adapters return a BODY STRING and let
 * the transport turn it into a response, so the only place an adapter can
 * signal failure is the body itself. Every app does it by returning a
 * status-shaped string instead of a page: `Not Found`, `404 Not Found`,
 * `500 Error: ...`.
 *
 * That was checked in two places with two different rules, and they did not
 * agree:
 *
 *   - the client guard rejected `body === 'Not Found'` and bodies prefixed
 *     `'500 '`;
 *   - the volucer/RR transports derived the HTTP status from `'500 '` alone,
 *     so a 404 body went out as **200 OK**.
 *
 * The result was a benchmark that published numbers for a PHP-FPM app whose
 * routes did not exist: Phalcon 6 has no feature routes at all, its FPM arm
 * aborted the run on the first one, and its volucer arm reported
 * "250201 requests, 0 failed" while 11 of the 25 endpoints were 404s
 * (2026-11-21). A dataset must never contain error responses, and a guard that
 * only recognises ONE framework's spelling of "not found" is how that happens.
 *
 * Loaded by: scripts/bench-lib.php (the HTTP client), run-app.php,
 * smoke-app.php (in-process harness), deploy/volucer/app.php and
 * deploy/rr/worker.php (the transports), public/index-*.php (the FPM entry).
 * No side effects, no dependencies — it must stay safe to require from any of
 * them.
 */

/**
 * The HTTP status an adapter's body string represents.
 *
 * An adapter that returns a page means 200. A body that OPENS with a status
 * line (`404 Not Found`, `500 Error: ...`) is that status. `Not Found` with no
 * code is the 404 shorthand most of the apps use.
 *
 * Deliberately anchored at the start of the body: a rendered page that merely
 * mentions an error, or a JSON payload carrying `"error":"Not Found"`, must
 * stay a 200. Only a body that IS the error counts.
 */
function benchStatusFromBody(string $body): int
{
    // `404 Not Found`, `500 Error: ...`, `503 ...` — a status line, not prose.
    if (preg_match('/^([1-5]\d{2}) /', $body, $m) === 1) {
        return (int) $m[1];
    }

    // The bare shorthand: symfony, laravel, spiral, codeigniter, cake and
    // phalcon's controllers all return exactly this for a missing row.
    if ($body === 'Not Found' || str_starts_with($body, 'Not Found')) {
        return 404;
    }

    return 200;
}

/**
 * Whether a body is an error page the harness must refuse to time.
 *
 * Used by every guard site so the client and the transports cannot drift into
 * disagreeing about what "broken" means again.
 */
function isBenchErrorBody(string $body): bool
{
    return benchStatusFromBody($body) >= 400;
}

/**
 * The status of a response given BOTH what the framework reported and the body.
 *
 * A real status wins. Adapters report what their framework actually set
 * (`WebAppAdapter::lastStatus()`), and that is authoritative — it is the number a
 * browser would have received. The body rule below is the fallback, and it is
 * still needed in two places:
 *
 *   - the FPM entry scripts have no adapter to ask, so their body is all there is;
 *   - a body-only adapter (or an old one) has nothing else to report.
 *
 * Why this exists at all: sniffing the status out of the body was the only option
 * while `dispatch()` returned just a string, and it silently mislabelled every
 * framework that renders a real error page instead of a status-shaped string.
 * Cake's 404 arrived as "500 Cake\Routing\Exception\MissingRouteException: ..."
 * because the body carried no status — the guess then made a 404 look like a 500.
 */
function benchStatus(string $body, int $status = 0): int
{
    if ($status >= 100 && $status <= 599) {
        return $status;
    }

    return benchStatusFromBody($body);
}
