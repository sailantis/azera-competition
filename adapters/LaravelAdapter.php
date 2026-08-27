<?php
/**
 * Laravel adapter — stub.
 *
 * TODO: bootstrap a minimal Laravel app (Illuminate Container + Router +
 * Eloquent + Blade) over the shared SQLite database and dispatch synthetic
 * requests via Illuminate\Http\Request::create() through the kernel.
 */

class LaravelAdapter implements WebAppAdapter
{
    public function name(): string
    {
        return 'laravel';
    }

    public function bootstrap(): void
    {
        throw new \RuntimeException('LaravelAdapter not yet implemented');
    }

    public function dispatch(string $method, string $uri): string
    {
        throw new \RuntimeException('LaravelAdapter not yet implemented');
    }
}