<?php
/**
 * Symfony adapter — stub.
 *
 * TODO: bootstrap a minimal Symfony HttpKernel + Routing + Twig + Doctrine
 * DBAL/ORM over the shared SQLite database and dispatch synthetic requests
 * via HttpKernel::handle(Request::create()).
 */

class SymfonyAdapter implements WebAppAdapter
{
    public function name(): string
    {
        return 'symfony';
    }

    public function bootstrap(): void
    {
        throw new \RuntimeException('SymfonyAdapter not yet implemented');
    }

    public function dispatch(string $method, string $uri): string
    {
        throw new \RuntimeException('SymfonyAdapter not yet implemented');
    }
}