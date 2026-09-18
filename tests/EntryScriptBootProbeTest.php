<?php

declare(strict_types=1);

namespace Azera\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The six FPM entry scripts must actually carry the boot probe, and the two
 * that are easy to get wrong must keep their framework-specific boundary.
 *
 * A regression here is silent: the report falls back to the legacy startup
 * proxy (warm GET /, i.e. a duplicate of the routing chart) with no error, so
 * nothing else in the suite would notice. These assertions are the only thing
 * standing between "the chart shows boot cost" and "the chart quietly shows
 * the routing number labelled as boot".
 */
final class EntryScriptBootProbeTest extends TestCase
{
    /** @return array<string,array{0:string}> */
    public static function entryScriptProvider(): array
    {
        $apps = ['azera', 'laravel', 'symfony', 'spiral', 'codeigniter', 'cakephp'];
        $out  = [];
        foreach ($apps as $app) {
            $out[$app] = [$app];
        }
        return $out;
    }

    private function source(string $app): string
    {
        $path = dirname(__DIR__) . "/public/index-{$app}.php";
        $this->assertFileExists($path);
        return (string) file_get_contents($path);
    }

    /**
     * @dataProvider entryScriptProvider
     */
    public function testEntryScriptStartsTheClockAndRecordsABootSample(string $app): void
    {
        $src = $this->source($app);
        $this->assertStringContainsString("require_once __DIR__ . '/../boot-probe.php'", $src, "{$app}: probe helper not required");
        $this->assertStringContainsString('boot_probe_start()', $src, "{$app}: clock never started");
        $this->assertStringContainsString("boot_probe_record('fpm', '{$app}')", $src, "{$app}: no boot sample recorded");
    }

    /**
     * @dataProvider entryScriptProvider
     */
    public function testClockStartsBeforeAnyFrameworkCodeLoads(string $app): void
    {
        $src   = $this->source($app);
        $start = strpos($src, 'boot_probe_start()');
        $this->assertNotFalse($start);

        // Anything that pulls framework code in must come AFTER the clock, or
        // the measurement silently excludes the autoloader.
        foreach ([
            "require __DIR__ . '/../vendor/autoload.php'",
            "require_once __DIR__ . '/../vendor/codeigniter4/framework/system/Common.php'",
        ] as $loader) {
            $at = strpos($src, $loader);
            if ($at !== false) {
                $this->assertGreaterThan(
                    $start,
                    $at,
                    "{$app}: {$loader} runs before the clock starts — boot would be under-measured"
                );
            }
        }
    }

    /**
     * The record() call must sit at the framework's boot-complete boundary,
     * before the request is handled — not after the response is sent.
     *
     * @dataProvider entryScriptProvider
     */
    public function testSampleIsRecordedBeforeTheResponseIsHandled(string $app): void
    {
        $src = $this->source($app);
        $rec = strpos($src, "boot_probe_record('fpm', '{$app}')");
        $this->assertNotFalse($rec);

        // The marker that starts serving the request in each entry script.
        $handlers = [
            'azera'       => '$ctx->dispatcher()->dispatch($route)->send()',
            'laravel'     => '$response = $kernel->handle($request)',
            'symfony'     => '$response = $kernel->handle($request)',
            'spiral'      => '$c->get(RouterInterface::class)->handle($request)',
            'codeigniter' => 'exit(Boot::bootWeb($paths))',
            'cakephp'     => '$response = $server->run()',
        ];
        $this->assertArrayHasKey($app, $handlers);
        $handle = strpos($src, $handlers[$app]);
        $this->assertNotFalse($handle, "{$app}: could not locate the request-handling call");
        $this->assertLessThan($handle, $rec, "{$app}: boot recorded after handling started");
    }

    public function testLaravelForcesTheBootBeforeRecording(): void
    {
        // Laravel boots LAZILY: handle() is what runs the service providers.
        // Without an explicit bootstrap() the probe would measure only the
        // app.php container build and understate Laravel's boot several-fold.
        $src  = $this->source('laravel');
        $boot = strpos($src, '$kernel->bootstrap()');
        $rec  = strpos($src, "boot_probe_record('fpm', 'laravel')");
        $this->assertNotFalse($boot, 'laravel: $kernel->bootstrap() missing — boot would be under-measured');
        $this->assertNotFalse($rec);
        $this->assertLessThan($rec, $boot, 'laravel: bootstrap() must run before the sample is recorded');
    }

    public function testCodeIgniterUsesItsBootCompleteEvent(): void
    {
        // Boot::bootWeb() fuses boot and dispatch, so the calling script has no
        // point to time. CI4's official boot-complete hook is 'pre_system'.
        $src = $this->source('codeigniter');
        $this->assertStringContainsString("Events::on('pre_system'", $src, 'codeigniter: pre_system hook missing');

        // The class is CodeIgniter\Events\Events. The plausible-looking
        // CodeIgniter\Events does NOT exist in CI4 4.x, and because the call
        // sits at file scope an uncaught Error there means every request 500s
        // (observed: 335-byte body, no sample ever written). Assert the real
        // class resolves, so a rename in a future CI4 release fails loudly here
        // instead of silently at runtime.
        $this->assertTrue(
            class_exists('CodeIgniter\\Events\\Events'),
            'CodeIgniter\\Events\\Events not autoloadable — the pre_system hook would fatal'
        );
        $this->assertStringNotContainsString(
            '\\CodeIgniter\\Events::',
            $src,
            'codeigniter: CodeIgniter\\Events does not exist; use CodeIgniter\\Events\\Events'
        );
    }

    public function testSpiralRecordsInsideTheBootstrappedCallback(): void
    {
        // The callback is the kernel's own boot-complete signal; recording
        // outside it would race Kernel::run().
        $src = $this->source('spiral');
        $cb  = strpos($src, '$kernel->bootstrapped(');
        $rec = strpos($src, "boot_probe_record('fpm', 'spiral')");
        $this->assertNotFalse($cb);
        $this->assertNotFalse($rec);
        $this->assertGreaterThan($cb, $rec);
    }

    public function testRoadRunnerWorkerRecordsWarmRecycleSamples(): void
    {
        // The RoadRunner band reports the WARM CYCLE RESET — the framework
        // re-initialised in an already-warm worker — not process start. Two
        // things must hold: the cold process-start sample is recorded under a
        // SEPARATE kind (so one autoload-heavy sample cannot contaminate the
        // cycle distribution), and the cycle samples are recorded BEFORE the
        // request loop.
        $src = (string) file_get_contents(dirname(__DIR__) . '/deploy/rr/worker.php');
        $this->assertStringContainsString('boot_probe_start()', $src);
        $this->assertStringContainsString(
            "boot_probe_record('rr-cold', \$benchApp, true)",
            $src,
            'RR worker must record the cold process-start boot under its own kind'
        );
        $this->assertStringContainsString(
            "boot_probe_record('rr', \$benchApp, true)",
            $src,
            'RR worker must record warm recycle samples'
        );

        $cold = strpos($src, "boot_probe_record('rr-cold', \$benchApp, true)");
        $warm = strpos($src, "boot_probe_record('rr', \$benchApp, true)");
        $loop = strpos($src, 'while (true) {');
        $this->assertNotFalse($cold);
        $this->assertNotFalse($warm);
        $this->assertNotFalse($loop);
        $this->assertLessThan($warm, $cold, 'the cold sample must be taken before the warm cycles');
        $this->assertLessThan($loop, $warm, 'warm cycles must finish before the request loop starts');
    }

    public function testWarmCyclesAreBoundedAndOverridable(): void
    {
        // A cycle loop that cannot be bounded is a hang risk in a resident
        // worker, and the count must be stated rather than buried: a run that
        // widens its sample should do so explicitly.
        $src = (string) file_get_contents(dirname(__DIR__) . '/deploy/rr/worker.php');
        $this->assertStringContainsString('BENCH_BOOT_CYCLES', $src, 'the cycle count must be overridable');
        $this->assertStringContainsString(
            'if ($bootCycles < 1)',
            $src,
            'a zero/negative cycle count must be clamped — the loop would otherwise never run'
        );
    }

    public function testRoadRunnerDoesNotStampBootOnItsLatencyRows(): void
    {
        // A RoadRunner worker boots BEFORE serving, so its boot is not inside
        // the timed rows. Stamping boot_ms there would double-count the boot in
        // every latency number. Only php-fpm rows carry boot_ms.
        $src = (string) file_get_contents(dirname(__DIR__) . '/scripts/http-bench.php');
        $this->assertStringContainsString("\$bootKind === 'fpm' && \$bootMs !== null", $src);
        $this->assertStringContainsString("\$bootKind    = (\$server === 'rr') ? 'rr' : 'fpm';", $src);
    }

    public function testCakePhpRecordsAfterItsRouteTableAndContainerExist(): void
    {
        // CakePHP's boot is DEFERRED past the application class: Router::reload()
        // builds an EMPTY RouteCollection, and the routes() hook only runs from
        // RoutingMiddleware INSIDE Server::run().
        //
        // Recording at the reload() line therefore measured a PARTIAL boot — no
        // routes, no container — while every other entry script recorded with its
        // route table already built. That made the published FPM boot band
        // compare cakephp's 0.263 ms of "files + Configure + Db::init" against
        // azera's 0.513 ms of "files + Configure + 118 routes + container", so
        // Cake appeared ~2x faster while the cold band showed the opposite.
        //
        // Measured warm (the FPM band's own regime) in temp/debug-boot-fair.php:
        // the omitted work cost 0.466 ms — MORE than azera's entire 0.336 ms
        // boot. A revert here is silent (the chart simply flips), so it is
        // pinned rather than left to a comment.
        $src = $this->source('cakephp');
        $rec = strpos($src, "boot_probe_record('fpm', 'cakephp')");
        $this->assertNotFalse($rec);

        foreach ([
            'Router::reload()',
            '$app->routes(',
            '$app->getContainer()',
        ] as $needed) {
            $at = strpos($src, $needed);
            $this->assertNotFalse($at, "cakephp: {$needed} is missing from the entry script");
            $this->assertLessThan($rec, $at, "cakephp: {$needed} must run before the boot sample");
        }

        // The route table must actually be BUILT, not merely reload()ed: an
        // equivalent-looking revert to reload()-then-record would satisfy the
        // ordering checks above while leaving 0 routes in the measured span.
        $this->assertStringContainsString(
            "Router::createRouteBuilder('/')",
            $src,
            'cakephp: the route table must be built before the sample, not left to Server::run()'
        );

        // ...and the sample must still sit before the request is handled.
        $run = strpos($src, '$response = $server->run()');
        $this->assertNotFalse($run);
        $this->assertLessThan($run, $rec, 'cakephp: boot recorded after serving started');
    }
}
