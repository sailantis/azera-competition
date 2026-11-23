<?php
/**
 * Symfony adapter — boots a real Symfony HttpKernel (FrameworkBundle +
 * Twig + Doctrine ORM/DBAL over the shared SQLite database) and dispatches
 * synthetic requests in-process.
 *
 * This is the idiomatic Symfony setup (Kernel + bundles + YAML config), the
 * same structure a real Symfony app ships, so cold/warm boot costs reflect
 * what Symfony users actually pay.
 */

use App\Symfony\Kernel as BenchKernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SymfonyAdapter implements WebAppAdapter
{
    private ?BenchKernel $kernel = null;

    /**
     * Request/response of the last dispatch() — terminate() needs the very
     * objects handle() saw (services_resetter resolves request-scoped
     * services from them). Nulled again by cleanup().
     */
    private ?Request $lastRequest = null;

    private ?\Symfony\Component\HttpFoundation\Response $lastResponse = null;

    /** Status of the most recent dispatch — see WebAppAdapter::lastStatus(). */
    private int $status = 200;

    public function name(): string
    {
        return 'symfony';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Symfony benchmark app namespace (shared,
        // idempotent loader — see BenchmarkAutoloader).
        BenchmarkAutoloader::map('App\\Symfony\\', __DIR__ . '/../apps/symfony/src');

        $this->bootKernel();
    }

    /**
     * Boot (or re-boot) the Symfony kernel.
     */
    private function bootKernel(): void
    {
        $this->kernel = null;

        // Tell Symfony this is a WEB app even when it runs under the CLI SAPI.
        //
        // `kernel.runtime_mode.web` comes from `%env(...APP_RUNTIME_MODE)%`, and
        // the compiled fallback is `in_array(PHP_SAPI, ['cli', ...]) ? 'web=0' :
        // 'web=1'`. The resident arms are a web app inside a CLI process, so
        // Symfony concluded "CLI" and `RuntimeModeErrorRendererSelector` picked
        // CliErrorRenderer — which dumps the raw exception object as the body.
        // The status was right but the body was not a page.
        //
        // Symfony's own WebTestCase does exactly this for the same reason
        // (`$_SERVER['APP_RUNTIME_MODE'] = 'web=1';`). Under FPM the value is
        // already web=1, so this only fixes the arm that needs it.
        $_SERVER['APP_RUNTIME_MODE'] ??= 'web=1';

        // Ensure the runtime dirs Symfony requires exist (Twig cache +
        // Doctrine proxy/cache).
        $writable = __DIR__ . '/../writable/symfony';
        foreach (['', '/twig', '/doctrine', '/doctrine/proxies', '/doctrine/cache'] as $dir) {
            if (!is_dir($writable . $dir)) {
                @mkdir($writable . $dir, 0777, true);
            }
        }

        // Guard against a stale compiled container / route cache. Symfony
        // invalidates its caches via file mtimes; a git operation (clone,
        // checkout, stash) resets mtimes in arbitrary order, and a run on a
        // machine where the cache was built from DIFFERENT app code can then
        // silently serve a 404 for every feature route (this poisoned the
        // 2026-09-12 symfony /features/* dataset rows with flat ~0.05 ms
        // 404 timings). If any tracked source file is newer than the route
        // cache, wipe the whole cache dir so the kernel rebuilds it.
        $this->clearStaleCache();

        try {
            $this->kernel = new BenchKernel('bench', false);
            $this->kernel->boot();
        } catch (\Throwable $e) {
            throw new \RuntimeException(
                'Symfony bootstrap failed: ' . $e->getMessage(),
                0,
                $e,
            );
        }

        // Warm the kernel now (instead of on the first dispatch) so
        // bootstrap() measures the full boot cost.
        $this->kernel->handle(Request::create('/', 'GET'));
    }

    /**
     * Drop the compiled container/route cache when any app source file or
     * config is newer than the cached route matcher. Cheap (a handful of
     * filemtime calls) and only ever triggers after a real code change or
     * a git operation — never mid-benchmark.
     */
    private function clearStaleCache(): void
    {
        $cacheDir = __DIR__ . '/../apps/symfony/var/cache/bench';
        $matcher  = $cacheDir . '/url_matching_routes.php';
        if (!is_file($matcher)) {
            return; // nothing cached yet — kernel will build fresh
        }

        $cacheMtime = filemtime($matcher);
        $roots      = [
            __DIR__ . '/../apps/symfony/config',
            __DIR__ . '/../apps/symfony/src',
        ];
        foreach ($roots as $root) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                /** @var \SplFileInfo $file */
                if ($file->isFile() && filemtime($file->getPathname()) > $cacheMtime) {
                    // Stale cache — remove it and stop looking.
                    $this->rrmdir($cacheDir);
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
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $file) {
            /** @var \SplFileInfo $file */
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($dir);
    }

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->kernel instanceof BenchKernel);

        $this->status = 200;

        try {
            $request = Request::create($uri, $method);
            $request->headers->set('HOST', 'bench.local');

            // $catch = true: let the kernel turn a NotFoundHttpException /
            // MethodNotAllowedHttpException into a response through
            // error_controller. Passing false made every 404 and 405 escape into
            // the catch below, where the best the adapter could do was invent a
            // 'Not Found' body or print the exception class — i.e. the app had no
            // working error page and the transport never saw a real status.
            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, true);
            $this->lastRequest  = $request;
            $this->lastResponse = $response;

            $this->status = $response->getStatusCode();

            return (string) $response->getContent();
        } catch (\Throwable $e) {
            $this->status = 500;

            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }
    }

    public function lastStatus(): int
    {
        return $this->status;
    }

    /**
     * Symfony expects a request/terminate lifecycle. In a long-lived
     * benchmark process the terminate phase triggers the services_resetter
     * (kernel.reset tagged services such as the Doctrine EntityManager and
     * request-scoped state). Without this, ORM identity-map state
     * accumulates and request-scoped services leak across dispatches, which
     * shows up as growing latency on longer-running servers.
     *
     * Needs the request/response of the dispatch it follows — kept via
     * lastRequest()/lastResponse() set in dispatch().
     */
    public function cleanup(): void
    {
        \assert($this->kernel instanceof BenchKernel);

        if ($this->lastRequest === null || $this->lastResponse === null) {
            return;
        }

        $this->kernel->terminate($this->lastRequest, $this->lastResponse);
        $this->lastRequest  = null;
        $this->lastResponse = null;
    }
}