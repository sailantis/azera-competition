<?php

declare(strict_types=1);

/**
 * Generic RoadRunner worker for ALL benchmark apps.
 *
 * RoadRunner starts this script as the http pool worker and keeps the PHP
 * process resident (warm-start model: the framework boots once, then serves
 * every request — exactly what run.php's warm mode simulates in-process).
 *
 * The app under test is selected with the BENCH_APP environment variable
 * (wired in the generated .rr-<app>.yaml): the worker instantiates the same
 * WebAppAdapter the in-process harness uses, calls bootstrap() once, and
 * then loops dispatch()/cleanup() per request.
 *
 * Contract notes:
 *  - Adapters return ONLY the response body (never echo/header/exit), so the
 *    body maps 1:1 onto a PSR-7 response.
 *  - A body starting with "500 " marks a handler error (the harness's abort
 *    guard convention) — surfaced to RoadRunner as HTTP 500.
 *  - POST benchmark requests generate their own payloads inside the
 *    controllers, so an empty body on POST /items is expected and fine.
 *
 * API note: mirrors azera-roadrunner's RoadRunnerHttpWorker — the correct
 * class is Spiral\RoadRunner\Http\PSR7Worker (waitRequest() returns a PSR-7
 * ServerRequest or null on shutdown; respond() takes a PSR-7 Response).
 */

use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/WebAppAdapter.php';
require dirname(__DIR__, 2) . '/adapters/BenchmarkAutoloader.php';
require __DIR__ . '/bootstrap-worker.php';

// --- Resolve the app under test ---------------------------------------------

$adapter = createAdapter((string) env('BENCH_APP'));

/**
 * One-time framework boot. In the real warm model this cost is paid once per
 * worker lifetime, exactly like run.php's warm mode pays it outside the loop.
 */
$adapter->bootstrap();

// --- Resident request loop ---------------------------------------------------

$psr7 = new PSR7Worker(
    Worker::create(),
    new Psr17Factory(),
    new Psr17Factory(),
    new Psr17Factory(),
);

while (true) {
    try {
        $request = $psr7->waitRequest();
        if ($request === null) {
            break; // RoadRunner signalled shutdown
        }
    } catch (\Spiral\Goridge\Exception\GoridgeException | \Spiral\RoadRunner\Exception\RoadRunnerException $e) {
        // Transport failure (RR gone / pipe closed): exiting is the only sane
        // reaction. An unguarded catch-all here would spin at 100% CPU — a
        // dead transport never starts delivering requests again.
        break;
    } catch (\Throwable $e) {
        // Malformed request payload must not kill the worker.
        $psr7->respond(new Response(400, [], '400 Bad Request'));
        continue;
    }

    try {
        $uri = $request->getUri()->getPath()
            . ($request->getUri()->getQuery() !== '' ? '?' . $request->getUri()->getQuery() : '');
        $method = $request->getMethod();

        $body = $adapter->dispatch($method, $uri);
        $adapter->cleanup();

        $status = str_starts_with($body, '500 ') ? 500 : 200;
        $psr7->respond(new Response($status, ['Content-Type' => 'text/html; charset=utf-8'], $body));
    } catch (\Throwable $e) {
        // Send an error response and keep the worker alive.
        $psr7->respond(new Response(500, [], '500 ' . get_class($e) . ': ' . $e->getMessage()));
    }
}