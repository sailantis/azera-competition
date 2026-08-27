<?php
/**
 * CakePHP 5 adapter — stub.
 *
 * TODO: bootstrap a minimal CakePHP Http\Server + Router + ORM\Table +
 * View (.ctp) over the shared SQLite database and dispatch synthetic
 * requests via ServerRequestFactory.
 */

class CakePhpAdapter implements WebAppAdapter
{
    public function name(): string
    {
        return 'cakephp';
    }

    public function bootstrap(): void
    {
        throw new \RuntimeException('CakePhpAdapter not yet implemented');
    }

    public function dispatch(string $method, string $uri): string
    {
        throw new \RuntimeException('CakePhpAdapter not yet implemented');
    }
}