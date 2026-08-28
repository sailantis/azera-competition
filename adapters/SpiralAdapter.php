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
use Spiral\Router\RouterInterface;

class SpiralAdapter implements WebAppAdapter
{
    private ?Container $container = null;

    public function name(): string
    {
        return 'spiral';
    }

    public function bootstrap(): void
    {
        // PSR-4 autoloader for the Spiral benchmark app namespace.
        spl_autoload_register(function (string $class): void {
            $prefix = 'App\\Spiral\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }
            $relative = substr($class, strlen($prefix));
            $file     = __DIR__ . '/../apps/spiral/src/' . str_replace('\\', '/', $relative) . '.php';
            // Guard required when multiple adapters share one process.
            if (is_file($file)) {
                require $file;
            }
        });

        $root = \dirname(__DIR__) . '/';

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

    public function dispatch(string $method, string $uri): string
    {
        \assert($this->container instanceof Container);

        $request = new ServerRequest(
            [],
            [],
            new Uri($uri),
            $method,
            'php://input',
            ['Host' => 'bench.local'],
        );

        try {
            // HttpBootloader binds Http + the Request proxy inside the 'http'
            // scope — dispatch within it (as the real HTTP dispatcher does).
            $response = $this->container->runScope(
                new \Spiral\Core\Scope(name: 'http'),
                static function (Container $c) use ($request): \Psr\Http\Message\ResponseInterface {
                    return $c->get(RouterInterface::class)->handle($request);
                },
            );
        } catch (\Throwable $e) {
            return '500 ' . \get_class($e) . ': ' . $e->getMessage();
        }

        return (string) $response->getBody();
    }
}