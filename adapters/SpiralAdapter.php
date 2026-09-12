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
        // PSR-4 autoloader for the Spiral benchmark app namespace (shared,
        // idempotent loader — see BenchmarkAutoloader).
        BenchmarkAutoloader::map('App\\Spiral\\', __DIR__ . '/../apps/spiral/src');

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
            // The scope is entered AND exited here: the child container is
            // created inside runScope() and destroyed in its finally. Spiral
            // has no public API to split scope enter/exit, so the scope
            // lifecycle stays part of the handle phase; the officially
            // sanctioned per-request teardown (FinalizerInterface::finalize(),
            // which Spiral's own dispatchers call per request/iteration) is
            // what cleanup() times.
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