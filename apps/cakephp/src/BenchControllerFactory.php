<?php

declare(strict_types=1);

/**
 * Worker-friendly ControllerFactory.
 *
 * Stock ControllerFactory::create() calls
 * `$container->addShared(ComponentRegistry::class, new ComponentRegistry(...))`
 * on EVERY request. League Container's add()/addShared() append a new
 * Definition per call (overwrite is opt-in), so the definitions array grows
 * by one entry (plus one retained object graph) per request in worker mode.
 *
 * This subclass registers the ComponentRegistry exactly once and then
 * reuses it; the remainder of create() mirrors the stock implementation.
 */

namespace App\Cake;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Controller\ControllerFactory;
use Cake\Http\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionNamedType;

final class BenchControllerFactory extends ControllerFactory
{
    private bool $componentRegistryRegistered = false;

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request The request to build a controller for.
     * @return \Cake\Controller\Controller
     * @throws \Cake\Http\Exception\MissingControllerException
     */
    public function create(ServerRequestInterface $request): Controller
    {
        assert($request instanceof ServerRequest);
        $className = $this->getControllerClass($request);
        if ($className === null) {
            throw $this->missingController($request);
        }

        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract()) {
            throw $this->missingController($request);
        }

        // Stock Cake registers this per request; the registry is stateless
        // for our app (no components / no loadComponent), so registering it
        // once per process is equivalent — and leak-free in worker mode.
        if (!$this->componentRegistryRegistered) {
            $this->container->addShared(
                ComponentRegistry::class,
                new ComponentRegistry(container: $this->container),
            );
            $this->componentRegistryRegistered = true;
        }

        // Get the controller from the container if defined.
        // The request is in the container by default.
        if ($this->container->has($className)) {
            return $this->container->get($className);
        }

        $components  = $this->container->get(ComponentRegistry::class);
        $constructor = $reflection->getConstructor();
        assert($constructor !== null);

        $hasComponents = false;
        foreach ($constructor->getParameters() as $parameter) {
            $paramType = $parameter->getType();
            if (
                $parameter->getName() === 'components' &&
                $paramType instanceof ReflectionNamedType &&
                $paramType->getName() === ComponentRegistry::class
            ) {
                $hasComponents = true;
                break;
            }
        }

        if ($hasComponents) {
            return $reflection->newInstance(request: $request, components: $components);
        }

        return $reflection->newInstance($request);
    }
}