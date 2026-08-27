<?php
/**
 * CodeIgniter 4 adapter — stub.
 *
 * TODO: bootstrap a minimal CI4 app (CodeIgniter::handleRequest + Router +
 * CI4 Model + CI4 View parser) over the shared SQLite database and dispatch
 * synthetic requests.
 */

class CodeIgniterAdapter implements WebAppAdapter
{
    public function name(): string
    {
        return 'codeigniter';
    }

    public function bootstrap(): void
    {
        throw new \RuntimeException('CodeIgniterAdapter not yet implemented');
    }

    public function dispatch(string $method, string $uri): string
    {
        throw new \RuntimeException('CodeIgniterAdapter not yet implemented');
    }
}