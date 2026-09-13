<?php
/**
 * Laravel adapter — boots a real Laravel HTTP Kernel (Eloquent + Blade +
 * providers over the shared SQLite database) and dispatches synthetic
 * requests in-process.
 *
 * This is the idiomatic Laravel setup (Application builder + HTTP Kernel +
 * service providers), the same structure a real Laravel app ships, so
 * cold/warm boot costs reflect what Laravel users actually pay.
 */

use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;

class LaravelAdapter implements WebAppAdapter
{
    private ?\Illuminate\Foundation\Application $app = null;
    private ?HttpKernelContract $kernel = null;

    public function name(): string
    {
        return 'laravel';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Laravel benchmark app namespace (shared,
        // idempotent loader — see BenchmarkAutoloader).
        BenchmarkAutoloader::map('App\\Laravel\\', __DIR__ . '/../apps/laravel/app');

        $this->bootApplication();
    }

    /**
     * Boot (or re-boot) the Laravel application + HTTP kernel.
     */
    private function bootApplication(): void
    {
        // Laravel keeps GLOBAL static state across Application instances:
        // Container::getInstance(), Facade roots and the Eloquent model
        // resolvers all retain the previous app. In cold mode bootstrap()
        // runs once per timed run in the SAME process — without flushing,
        // the old container is retained and memory ratchets up by a full
        // Application instance per re-boot (2026-09-12: cold peak_mem
        // climbed 22 → 502 MB over the request sequence). Clear the static
        // handles first so the previous instance can actually be freed.
        //
        // Application::flush() (on the STILL-ALIVE old app) is the real
        // release: it empties the container bindings/instances AND clears
        // Facade::clearResolvedInstances() + Eloquent resolvers — the
        // statics that plain setInstance(null)+gc left alive, pinning the
        // old app graph INCLUDING its pooled SQLite PDO (2026-09-13: each
        // re-boot leaked 3 fds — db+wal+shm — until "Too many open files"
        // killed cold blocks past ~500 boots).
        if ($this->app !== null) {
            $this->app->flush();
        }
        \Illuminate\Container\Container::setInstance(null);

        $this->app    = null;
        $this->kernel = null;
        \gc_collect_cycles();

        // Ensure the runtime dirs Laravel requires exist (bootstrap cache +
        // writable storage). Required before Application::configure runs.
        $writable = __DIR__ . '/../writable/laravel';
        foreach (['', '/cache', '/views', '/framework/views', '/framework/cache'] as $dir) {
            if (!is_dir($writable . $dir)) {
                @mkdir($writable . $dir, 0777, true);
            }
        }
        if (!is_dir(__DIR__ . '/../apps/laravel/bootstrap/cache')) {
            @mkdir(__DIR__ . '/../apps/laravel/bootstrap/cache', 0777, true);
        }

        try {
            $this->app = require __DIR__ . '/../apps/laravel/bootstrap/app.php';
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Laravel bootstrap failed: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        if (!$this->app instanceof \Illuminate\Foundation\Application) {
            throw new \RuntimeException(
                'Laravel bootstrap did not return an Application instance'
            );
        }

        $this->kernel = $this->app->make(HttpKernelContract::class);

        // Warm the HTTP bootstrappers + load routes now (instead of on the
        // first dispatch) so bootstrap() measures the full boot cost.
        $this->kernel->handle(Request::create('/', 'GET'));
        $this->kernel->terminate(new Request(), new \Illuminate\Http\Response());
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->app !== null);
        \assert($this->kernel !== null);

        try {
            $request = Request::create($uri, $method);
            $request->headers->set('HOST', 'bench.local');

            $response = $this->kernel->handle($request);

            return (string) $response->getContent();
        } catch (\Throwable $e) {
            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }
    }

    /**
     * Laravel's HTTP kernel handles request-scoped state per handle() call —
     * the framework has no terminate()-style teardown in this in-process
     * setup (kernel->terminate() is a no-op without terminable middleware).
     * Nothing to do between requests.
     *
     * The OLD-APP teardown (fds, statics) lives in bootApplication() —
     * cleanup() runs between EVERY warm dispatch and must keep the kernel
     * alive (2026-09-13: nulling it here 500'd warm GET / with "Call to a
     * member function handle() on null").
     */
    public function cleanup(): void {}
}