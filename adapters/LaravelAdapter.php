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
        // PSR-4 autoloader for the Laravel benchmark app namespace.
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\Laravel\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = __DIR__ . '/../apps/laravel/app/' . str_replace('\\', '/', $relative) . '.php';
            // Guard required when multiple adapters share one process.
            if (is_file($file)) {
                require $file;
            }
        });

        $this->bootApplication();
    }

    /**
     * Boot (or re-boot) the Laravel application + HTTP kernel.
     */
    private function bootApplication(): void
    {
        $this->app    = null;
        $this->kernel = null;

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
}