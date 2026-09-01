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

    public function name(): string
    {
        return 'symfony';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Symfony benchmark app namespace.
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\Symfony\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = __DIR__ . '/../apps/symfony/src/' . str_replace('\\', '/', $relative) . '.php';
            // Guard required when multiple adapters share one process.
            if (is_file($file)) {
                require $file;
            }
        });

        $this->bootKernel();
    }

    /**
     * Boot (or re-boot) the Symfony kernel.
     */
    private function bootKernel(): void
    {
        $this->kernel = null;

        // Ensure the runtime dirs Symfony requires exist (Twig cache +
        // Doctrine proxy/cache).
        $writable = __DIR__ . '/../writable/symfony';
        foreach (['', '/twig', '/doctrine', '/doctrine/proxies', '/doctrine/cache'] as $dir) {
            if (!is_dir($writable . $dir)) {
                @mkdir($writable . $dir, 0777, true);
            }
        }

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

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->kernel instanceof BenchKernel);

        try {
            $request = Request::create($uri, $method);
            $request->headers->set('HOST', 'bench.local');

            $response = $this->kernel->handle($request, HttpKernelInterface::MAIN_REQUEST, false);

            return (string) $response->getContent();
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            return 'Not Found';
        } catch (\Throwable $e) {
            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }
    }
}