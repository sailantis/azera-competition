<?php

/**
 * Spiral adapter — boots a real Spiral Kernel (Http + Router + Stempler
 * views + Cycle ORM over the shared SQLite database) and dispatches
 * synthetic PSR-7 requests in-process.
 *
 * This is the idiomatic Spiral setup (Kernel + bootloaders + cycle-bridge),
 * the same structure a real Spiral app ships, so cold/warm boot costs
 * reflect what Spiral users actually pay.
 */

use App\Spiral\Kernel as BenchKernel;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Uri;
use Spiral\Boot\Environment;
use Spiral\Core\Container;

class SpiralAdapter implements WebAppAdapter
{
    private ?Container $container = null;

    /** Status of the most recent dispatch — see WebAppAdapter::lastStatus(). */
    private int $status = 200;

    public function name(): string
    {
        return 'spiral';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Spiral benchmark app namespace (shared,
        // idempotent loader — see BenchmarkAutoloader).
        BenchmarkAutoloader::map('App\\Spiral\\', __DIR__ . '/../apps/spiral/src');

        $root = \dirname(__DIR__) . '/';

        $this->clearForeignViewCache($root);

        $kernel = BenchKernel::create(
            directories: [
                'root' => $root,
                'app'  => $root . 'apps/spiral/',
            ],
            handleErrors: false,
        );

        $container = null;
        $kernelRef = $kernel;
        $kernel->bootstrapped(static function () use (&$container, $kernelRef): void {
            // AbstractKernel::$container is protected readonly — read it via
            // reflection once boot completes (no public accessor exists).
            $prop      = new \ReflectionProperty(\Spiral\Boot\AbstractKernel::class, 'container');
            $container = $prop->getValue($kernelRef);
        });

        $kernel->run(new Environment([
            'APP_DEBUG'               => false,
            'VIEW_CACHE'              => true,
            'CYCLE_SCHEMA_CACHE'      => true,
            'TOKENIZER_CACHE_TARGETS' => true,
        ]));

        \assert($container instanceof Container);
        $this->container = $container;
    }

    /**
     * Drop Stempler's compiled views when they were built from ANOTHER checkout.
     *
     * Stempler stores the absolute source path inside each compiled template. Move
     * or copy the repo and the cache still points at the old path, so every HTML
     * route dies with
     *
     *     Spiral\Files\Exception\FileNotFoundException:
     *     File 'C:/Users/.../azera-competition/apps/spiral/views/layouts/app.dark.php' not found
     *
     * even though the file exists right here. `runtime/` is gitignored, so this
     * only ever bites an existing working copy — which is exactly when it is least
     * obvious, and it looks like a framework failure rather than a stale cache.
     *
     * Symfony's adapter guards the same hazard (see clearStaleCache()); this is the
     * cheap equivalent, run once per boot: only the compiled view directory is
     * inspected, and only removed when its recorded paths do not live under this
     * root.
     */
    private function clearForeignViewCache(string $root): void
    {
        $dir = $root . 'runtime/cache/views';
        if (!is_dir($dir)) {
            return;
        }

        $current = str_replace('\\', '/', (string) realpath($root));

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.php')) {
                continue;
            }

            $src = (string) file_get_contents($file->getPathname());
            if (preg_match_all("#(['\"])([^'\"]+\.dark\.php)\\1#", $src, $m) === false) {
                continue;
            }

            foreach ($m[2] as $path) {
                $path = str_replace('\\', '/', $path);
                if (!str_starts_with($path, $current)) {
                    $this->rrmdir($dir);

                    return;
                }
            }
        }
    }

    /** Recursively delete a directory (best-effort, errors ignored). */
    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->container instanceof Container);

        $this->status = 200;

        $request = new ServerRequest(
            [],
            [],
            new Uri($uri),
            $method,
            'php://input',
            ['Host' => 'bench.local'],
        );

        try {
            // The framework's own PSR-15 dispatcher, in the scope its
            // HttpBootloader binds Http + the Request proxy to.
            //
            // This used to call RouterInterface::handle() directly, which skipped
            // the middleware pipeline, the CurrentRequest binding and
            // ErrorHandlerMiddleware — so a route miss escaped as
            // RouteNotFoundException and the only 404 the app could produce was
            // the hand-rolled one in the entry script. Http::handle() is what
            // Spiral's own workers call, and it returns byte-identical bodies on
            // every benchmark route (verified against this container), so the
            // shortcut bought nothing but an incomparable measurement.
            //
            // The scope is entered AND exited here: the child container is
            // created inside runScope() and destroyed in its finally. Spiral has
            // no public API to split scope enter/exit, so the scope lifecycle
            // stays part of the handle phase; the officially sanctioned
            // per-request teardown (FinalizerInterface::finalize(), which
            // Spiral's own dispatchers call per request/iteration) is what
            // cleanup() times.
            $response = $this->container->runScope(
                new \Spiral\Core\Scope(name: \Spiral\Framework\Spiral::Http->value),
                static function (Container $c) use ($request): \Psr\Http\Message\ResponseInterface {
                    return $c->get(\Spiral\Http\Http::class)->handle($request);
                },
            );
        } catch (\Throwable $e) {
            $this->status = 500;

            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }

        $this->status = $response->getStatusCode();

        return (string) $response->getBody();
    }

    public function lastStatus(): int
    {
        return $this->status;
    }

    /**
     * Spiral's officially sanctioned per-request teardown, as run by its own
     * workers: ConsoleDispatcher::serve()'s finally calls
     * FinalizerInterface::finalize() per request. The registered finalizers
     * are CycleOrmBootloader's (EntityManager::clean() + ORM heap clean) and
     * DisconnectsBootloader's (driver disconnects). http-scope services
     * (CookieQueue, SessionFactory) are already destroyed by the scope exit
     * in dispatch().
     */
    public function cleanup(): void
    {
        \assert($this->container instanceof Container);

        try {
            $this->container->get(\Spiral\Boot\FinalizerInterface::class)->finalize(false);
        } catch (\Throwable) {}
    }
}