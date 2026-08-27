<?php
/**
 * Spiral adapter — stub.
 *
 * TODO: bootstrap a minimal Spiral Http + Router + Stempler views + Cycle
 * ORM/DBAL over the shared SQLite database and dispatch synthetic PSR-7
 * requests through Spiral\Http\Http.
 */

class SpiralAdapter implements WebAppAdapter
{
    public function name(): string
    {
        return 'spiral';
    }

    public function bootstrap(): void
    {
        throw new \RuntimeException('SpiralAdapter not yet implemented');
    }

    public function dispatch(string $method, string $uri): string
    {
        throw new \RuntimeException('SpiralAdapter not yet implemented');
    }
}