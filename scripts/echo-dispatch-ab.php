<?php

declare(strict_types=1);

/**
 * A/B: what did routing through the framework's own front controller COST?
 *
 * WHY THIS EXISTS. The 2026-11-23 run put Phalcon behind `Mvc\Application`, the
 * dispatcher and the implicit view (previously: `$router->handle()` then
 * `new $class(...)` then `$action()`), and Azera behind `Kernel::handle()`
 * (previously `router()->match()` + `dispatcher()->dispatch()`). The published
 * `/echo` rows then moved by amounts smaller than the run-to-run spread, and one
 * of them moved in the "wrong" direction: Phalcon's volucer-attach `/echo` read
 * 0.1500 ms before and 0.1494 ms after, which cannot be a measurement of +6.8 us
 * of new work. Either the layer is cheaper than the figure that has been quoted
 * all along, or the row is floor-dominated enough to hide it.
 *
 * WHAT IS MEASURED. Three variants of the SAME request on each app, so the
 * difference between them is the dispatch layer and nothing else:
 *
 *   full    the adapter's real path (Application::handle / Kernel::handle)
 *   manual  the pre-refactor path (router + instantiate + call)
 *   bare    the constant handler, called directly: the lower bound
 *
 * `manual` is reconstructed from the code that was actually there before the
 * refactor (see Bootstrap::handle() in 08013dd^), not from a guess, so
 * `full − manual` is the layer's cost and `manual − bare` is what the router was
 * already costing.
 *
 * ORDER AND INTERLEAVING. One warm-up pass, then N rounds; inside a round every
 * variant of BOTH apps runs, and the variant order rotates each round. The VM's
 * noise is not symmetric in time, so a variant that always ran first would pick up
 * whatever the previous block left behind.
 *
 * Run on the bench VM:
 *   php -d opcache.enable_cli=1 -d opcache.jit=tracing -d opcache.jit_buffer_size=64M \
 *       scripts/echo-dispatch-ab.php --rounds=40 --iters=3000
 *
 * RESULT (bench VM, 2026-11-24, two runs agreeing to +/-0.04 us):
 *
 *   phalcon   full 3.93-3.95  manual 1.40-1.41  bare 0.13   layer = +2.52..2.56 us
 *   azera     full 2.86-2.90  manual 2.00       bare 0.26   layer = +0.86..0.90 us
 *
 * Read against the rows they came from: `/echo` prints 0.159 ms on RoadRunner
 * and 0.85 ms on PHP-FPM, so the entire framework layer is about 2% of one row
 * and well under 1% of the other. That is why the published `/echo` row did NOT
 * move when the front controller was introduced, and why it must not be read as
 * a framework comparison - it is the common floor. It also retires the "+6.8 us"
 * figure that had been quoted for `Application::handle()`: the real figure on
 * this VM is +2.5 us, and the earlier number came from an older app state
 * measured on a different host.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/WebAppAdapter.php';
require_once $root . '/adapters/BenchmarkAutoloader.php';
require_once $root . '/response-guard.php';
// The apps call benchViewGlobals() from Bootstrap::boot() (Phalcon) and from the
// request-context middleware (Azera), so this probe has to load it or the boot
// dies with "Call to undefined function". Same require list as every other
// harness entry point (see run.php).
require_once $root . '/bench-view-globals.php';

$opts   = getopt('', ['rounds::', 'iters::', 'uri::']);
$rounds = (int) ($opts['rounds'] ?? 40);
$iters  = (int) ($opts['iters'] ?? 2000);
$uri    = (string) ($opts['uri'] ?? '/echo');

/**
 * Time one callable, returning nanoseconds per call as
 * `['median' => float, 'trimmed' => float]`.
 *
 * The median of per-call times is what is reported, not the mean: a GC pause or
 * a scheduler slice lands in the mean and moves it by more than the effect being
 * measured. `hrtime()` is monotonic and does not go through the wall clock.
 *
 * @return array{median: float, trimmed: float}
 */
function timeIt(callable $fn, int $iters): array
{
    $samples = [];
    for ($i = 0; $i < $iters; $i++) {
        $t0 = hrtime(true);
        $fn();
        $samples[] = hrtime(true) - $t0;
    }
    sort($samples);
    $n = count($samples);

    // Trim 10% from each end before the mean, and keep the median too.
    $lo    = (int) floor($n * 0.10);
    $hi    = $n - $lo;
    $slice = array_slice($samples, $lo, max(1, $hi - $lo));

    return [
        'median'  => (float) $samples[(int) floor($n / 2)],
        'trimmed' => array_sum($slice) / count($slice),
    ];
}

/** @return array<string, array{median: float, trimmed: float}> */
function summarise(array $rounds): array
{
    $out = [];
    foreach ($rounds as $variant => $rows) {
        $medians  = array_column($rows, 'median');
        $trimmeds = array_column($rows, 'trimmed');
        sort($medians);
        sort($trimmeds);
        $out[$variant] = [
            'median'  => $medians[(int) floor(count($medians) / 2)],
            'trimmed' => $trimmeds[(int) floor(count($trimmeds) / 2)],
            'min'     => $medians[0],
            'max'     => $medians[count($medians) - 1],
        ];
    }

    return $out;
}

// --- Phalcon -----------------------------------------------------------------

BenchmarkAutoloader::map('App\\Phalcon\\', $root . '/apps/phalcon/src');
$phalcon = App\Phalcon\Bootstrap::boot($root . '/data/bench.sqlite');

$phalconVariants = [
    'full' => function () use ($phalcon, $uri): void {
        $phalcon->handle('GET', $uri);
        $phalcon->cleanup();
    },
    'manual' => function () use ($phalcon, $uri): void {
        // The pre-refactor path, verbatim in shape (08013dd^ Bootstrap::handle).
        // The old controllers named actions WITHOUT the `Action` suffix, so the
        // reconstruction carries the suffix translation a real dispatcher does.
        $di   = App\Phalcon\Bootstrap::di();
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $router = $di->getShared('router');
        $router->handle($path);

        $matched = $router->getMatchedRoute();
        $paths   = $matched->getPaths();
        $class   = \App\Phalcon\Controller\BenchController::class;
        $method  = (string) ($paths['action'] ?? 'index') . 'Action';

        $instance = new $class($di, [], $router->getParams());
        $instance->{$method}();

        $phalcon->cleanup();
    },
    'bare' => static function (): void {
        (new \App\Phalcon\Controller\BenchController())->echoAction();
    },
];

// --- Azera -------------------------------------------------------------------

BenchmarkAutoloader::map('App\\Azera\\', $root . '/apps/azera');
$ctx = App\Azera\Bootstrap::boot($root . '/data/bench.sqlite');

$azeaRequest = static function () use ($uri): Azera\Http\Request {
    return new Azera\Http\Request([
        'REQUEST_URI'     => $uri,
        'REQUEST_METHOD'  => 'GET',
        'SERVER_PROTOCOL' => 'HTTP/1.1',
        'HTTP_HOST'       => 'bench.local',
        'SCRIPT_NAME'     => '/index.php',
    ], []);
};

$azeraVariants = [
    'full' => function () use ($ctx, $azeaRequest): void {
        $ctx->kernel()->handle($azeaRequest());
        $ctx->clearRequestScope();
    },
    'manual' => function () use ($ctx, $azeaRequest, $uri): void {
        // The pre-refactor path: router()->match() + dispatcher()->dispatch().
        $route = $ctx->router()->match($uri);
        $ctx->dispatcher()->dispatch($route);
        $ctx->clearRequestScope();
    },
    'bare' => static function (): void {
        \Azera\Http\Response::text('bench');
    },
];

// --- Interleaved rounds -------------------------------------------------------

$apps = [
    'phalcon' => $phalconVariants,
    'azera'   => $azeraVariants,
];

$results = [];
foreach ($apps as $app => $variants) {
    foreach (array_keys($variants) as $name) {
        $results[$app][$name]           = [];
        $results[$app][$name . ':trimmed'] = [];
    }
}

// Warm-up: one pass over everything, discarded. First-call cost (autoloading,
// opcache cold paths, the proxy build) is not part of steady state.
foreach ($apps as $variants) {
    foreach ($variants as $fn) {
        timeIt($fn, min(200, $iters));
    }
}

$names = ['full', 'manual', 'bare'];
for ($round = 0; $round < $rounds; $round++) {
    // Rotate so no variant is permanently first (or last) within a round.
    $offset = $round % count($names);
    $order  = array_merge(array_slice($names, $offset), array_slice($names, 0, $offset));
    if ($round % 2 === 1) {
        $order = array_reverse($order);
    }

    foreach ($apps as $app => $variants) {
        foreach ($order as $name) {
            $t = timeIt($variants[$name], $iters);
            $results[$app][$name][]          = $t['median'];
            $results[$app][$name . ':trimmed'][] = $t['trimmed'];
        }
    }
}

// --- Report ------------------------------------------------------------------

printf("uri=%s  rounds=%d  iters=%d\n\n", $uri, $rounds, $iters);
printf("%-9s %-8s %10s %10s %10s %10s\n", 'app', 'variant', 'median us', 'trim us', 'min us', 'max us');

$summary = [];
foreach ($apps as $app => $variants) {
    foreach ($names as $name) {
        $med = $results[$app][$name];
        $tr  = $results[$app][$name . ':trimmed'];
        sort($med);
        sort($tr);
        $summary[$app][$name] = [
            'median'  => $med[(int) floor(count($med) / 2)],
            'trimmed' => $tr[(int) floor(count($tr) / 2)],
            'min'     => $med[0],
            'max'     => $med[count($med) - 1],
        ];
    }
}

foreach ($summary as $app => $rows) {
    foreach ($rows as $name => $r) {
        printf(
            "%-9s %-8s %10.3f %10.3f %10.3f %10.3f\n",
            $app, $name, $r['median'] / 1000, $r['trimmed'] / 1000, $r['min'] / 1000, $r['max'] / 1000,
        );
    }
}

echo "\n--- deltas (median of per-round medians), us ---\n";
foreach ($summary as $app => $rows) {
    $full   = $rows['full']['median'] / 1000;
    $manual = $rows['manual']['median'] / 1000;
    $bare   = $rows['bare']['median'] / 1000;
    printf(
        "%-9s front-controller over manual = %+7.3f   router+dispatch over bare = %+7.3f   total = %+7.3f\n",
        $app, $full - $manual, $manual - $bare, $full - $bare,
    );
}
