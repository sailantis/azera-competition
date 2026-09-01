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
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__);
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