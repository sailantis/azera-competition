<?php

declare(strict_types=1);

/**
 * Shared helpers for the HTTP benchmark client (scripts/http-bench.php).
 * stats()/trimmedMean() mirror run.php exactly (same semantics, same field
 * names) so real-deployment numbers are drop-in compatible with the report
 * generator.
 */

// --- Stats (mirrors run.php) -------------------------------------------------

function stats(array $values): array
{
    sort($values);
    $count  = count($values);
    $sum    = array_sum($values);
    $mean   = $sum / $count;
    $median = $values[(int) floor(($count - 1) / 2)];
    $p95Idx = max(0, min($count - 1, (int) floor($count * 0.95) - 1));
    return [
        'count'  => $count,
        'min'    => $values[0],
        'mean'   => $mean,
        'median' => $median,
        'p95'    => $values[$p95Idx],
    ];
}

function trimmedMean(array $values): float
{
    sort($values);
    $drop = max(1, (int) round(count($values) * 0.1));
    $kept = array_slice($values, $drop, count($values) - 2 * $drop);
    if (count($kept) === 0) {
        $kept = $values;
    }
    return array_sum($kept) / count($kept);
}

// --- Request parsing ----------------------------------------------------------

/**
 * Parse the --requests CSV ("METHOD URI,METHOD URI") into [method, uri]
 * pairs; falls back to the same default list as run.php.
 *
 * @return array<int, array{0:string, 1:string}>
 */
function parseRequestsArg(?string $raw): array
{
    $labels = $raw !== null && trim($raw) !== ''
        ? array_map('trim', explode(',', $raw))
        : [
            'GET /',
            'GET /items',
            'GET /items/1',
            'POST /items',
            'GET /items-qb',
            'GET /items-qb/1',
            'POST /items-qb',
            'GET /api/items',
            'GET /api/items/1',
            'POST /api/items',
            'GET /features/aop',
            'GET /features/cache',
            'GET /features/log',
            'GET /features/retry',
            'GET /features/pipeline',
            'GET /features/db-events',
            'GET /features/events',
            'GET /features/validation',
            'GET /features/config',
            'GET /features/request-scoped',
            'GET /features/rate-limit',
        ];

    return array_map(static function (string $r): array {
        $parts = explode(' ', $r, 2);
        return [strtoupper(trim($parts[0])), trim($parts[1] ?? '/')];
    }, $labels);
}

// --- HTTP layer (curl, keep-alive) ---------------------------------------------

/**
 * One request against the real server. Uses ONE reusable curl handle so the
 * TCP connection is kept alive across iterations — the client must add as
 * little overhead as possible (no per-request connect handshakes).
 *
 * @return string response body
 */
function httpRequest(string $baseUrl, string $method, string $uri): string
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL           => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP request failed ({$method} {$baseUrl}{$uri}): {$err}");
    }

    return (string) $body;
}

/**
 * One TIMED request. $connectMs (by ref) receives curl's connect/setup time
 * so the client overhead can be reported separately. Timing covers the full
 * round trip: send -> server -> full body received.
 *
 * @param-out float $connectMs
 * @return array{total_ms: float, connect_ms: float, status: int, body: string}
 */
function httpTimedRequest(string $baseUrl, string $method, string $uri, float &$connectMs = 0.0): array
{
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
    }

    curl_setopt_array($ch, [
        CURLOPT_URL           => $baseUrl . $uri,
        CURLOPT_CUSTOMREQUEST => $method,
    ]);

    $t0   = hrtime(true);
    $body = curl_exec($ch);
    $t1   = hrtime(true);

    if ($body === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP request failed ({$method} {$baseUrl}{$uri}): {$err}");
    }

    $info      = curl_getinfo($ch);
    $connectMs = (float) ($info['connect_time'] ?? 0.0) * 1000.0;

    return [
        'total_ms'   => ($t1 - $t0) / 1e6,
        'connect_ms' => $connectMs,
        'status'     => (int) ($info['http_code'] ?? 0),
        'body'       => (string) $body,
    ];
}

// --- Abort guard (mirrors benchRequest) ----------------------------------------

/**
 * A benchmark against a broken deployment must never produce numbers:
 * identical guard to run.php's benchRequest() — "Not Found" bodies or "500 "
 * prefixes abort the whole client with exit 1.
 */
function abortIfBroken(string $server, string $appKey, string $reqLabel, string $body): void
{
    if ($body === 'Not Found' || str_starts_with($body, '500 ')) {
        fwrite(STDERR, "[ABORT] {$server}/{$appKey} {$reqLabel}: broken response body\n");
        exit(1);
    }
}

// --- Result writer ---------------------------------------------------------------

/**
 * Write the per-(server, app) result JSON in the run.php shape:
 * { app, server, modes: { <mode>: { requests: [ ...stats ] } } }
 * run-http.php merges these into the combined real-deployments dataset.
 */
function writeHttpResults(
    ?string $outJsonPath,
    string $server,
    string $appKey,
    string $modeName,
    int $itersPerRun,
    int $runs,
    array $measured,
): void {
    if ($outJsonPath === null) {
        return;
    }

    $payload = [
        'app'    => $appKey,
        'server' => $server,
        'modes'  => [
            $modeName => [
                'iterations_per_run' => $itersPerRun,
                'runs'               => $runs,
                'requests'           => array_values($measured),
            ],
        ],
    ];

    $dir = dirname($outJsonPath);
    if ($dir !== '.' && !is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($outJsonPath, json_encode($payload, JSON_PRETTY_PRINT));
}