<?php

declare(strict_types=1);

/**
 * Benchmark application kernel.
 *
 * A minimal, idiomatic Symfony HttpKernel that registers the bundles a real
 * Symfony app ships: FrameworkBundle (routing, http-kernel, DI, cache),
 * TwigBundle (templating), DoctrineBundle (ORM/DBAL over the shared SQLite
 * database) and ValidatorBundle. The container is built from the YAML
 * config in config/packages/*.yaml.
 *
 * This is the same structure a stock Symfony app ships, so cold/warm boot
 * costs reflect what Symfony users actually pay.
 */

namespace App\Symfony;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use App\Symfony\DependencyInjection\PersistentAppCachePass;

final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new DoctrineBundle(),
        ];
    }

    protected function build(ContainerBuilder $container): void
    {
        // Keep the app cache alive across the many synthetic requests the
        // benchmark harness dispatches in one process (see the pass docblock).
        $container->addCompilerPass(new PersistentAppCachePass());

        // Ensure the compiled container is invalidated when the compiler pass
        // (or this kernel) changes, so a stale container can never keep the
        // old reset behaviour on another machine.
        $container->addResource(new \Symfony\Component\Config\Resource\FileResource(__FILE__));
        $container->addResource(new \Symfony\Component\Config\Resource\FileResource(
            __DIR__ . '/DependencyInjection/PersistentAppCachePass.php'
        ));
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Pin the shared SQLite connection's pragmas after the kernel boots.
     *
     * WHY HERE. DBAL's SQLite drivers expose no `journal_mode` / `synchronous` /
     * `busy_timeout` connection options and no post-connect hook, so declaring
     * them under `doctrine.dbal` is a hard error ("Unrecognized options ... under
     * doctrine.dbal.connections.default"). Booting the kernel forces the
     * connection to be created, and `getNativeConnection()` hands back the live
     * PDO, which is the only handle that can run a PRAGMA.
     *
     * WHY NORMAL. SQLite defaults to `synchronous = FULL`, which fsyncs the WAL on
     * every commit. When the WAL passes `wal_autocheckpoint`, a checkpoint runs and
     * that one commit pays milliseconds instead of microseconds. Measured on the
     * bench VM over 600 write requests, the outlier rate across entrants varied 14x
     * (Phalcon 5/600, Azera 8/600, CakePHP 71/600) purely because Laravel pinned
     * NORMAL and the rest inherited FULL — the pack was comparing two storage
     * configurations, not two frameworks. See tests/SqlitePragmaParityTest.php.
     *
     * `getNativeConnection()` may not exist on every DBAL build, and a benchmark
     * app must not fail to boot over an optimisation, so the shape is guarded.
     */
    public function boot(): void
    {
        parent::boot();

        try {
            $connection = $this->getContainer()->get('doctrine.dbal.default_connection');
            if (!\method_exists($connection, 'getNativeConnection')) {
                return;
            }
            $pdo = $connection->getNativeConnection();
            if (!$pdo instanceof \PDO) {
                return;
            }
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (\Throwable) {
            // No Doctrine connection in this build, or it is not PDO-backed.
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $configDir = $this->getProjectDir() . '/config/packages';
        $loader->load($configDir . '/framework.yaml');
        $loader->load($configDir . '/twig.yaml');
        $loader->load($configDir . '/doctrine.yaml');
        $loader->load($configDir . '/validator.yaml');
        $loader->load($this->getProjectDir() . '/config/services.yaml');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import($this->getProjectDir() . '/config/routes.yaml');
    }
}