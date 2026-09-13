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
     *
     * COLD MODE NOTE: each boot opens a new SQLite PDO (db + WAL + shm = 3
     * fds) behind DatabaseManager's connection pool. Cold re-boots in ONE
     * process (in-process fallback + measureBoot's warm re-boots) would
     * leak those fds — 2026-09-13: "Too many open files" past ~500 boots.
     * Nilling the app/kernel references releases the pooled connection so
     * PHP closes the fds (the fork-based cold loop makes this moot — the
     * child dies after one request — but measureBoot still re-boots in
     * process, and Windows has no fork).
     */
    public function cleanup(): void
    {
        // Request-scoped state is discarded by the kernel per handle(); what
        // would OUTLIVE the request is the app container + its pooled DB
        // connection — release them so the next boot starts fd-clean.
        \Illuminate\Container\Container::setInstance(null);
        $this->app    = null;
        $this->kernel = null;
        \gc_collect_cycles();
    }
}