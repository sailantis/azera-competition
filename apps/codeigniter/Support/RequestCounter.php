<?php

declare(strict_types=1);

/**
 * Per-request counter holder (mirrors azera's RequestCounter +
 * spiral's ScopeState demo): the endpoint shows before/after/reset values
 * to prove request-scoped semantics.
 */

namespace Ci4App\Support;

final class RequestCounter
{
    private static ?RequestCounter $instance = null;

    private int $count = 0;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function count(): int
    {
        return $this->count;
    }

    public function increment(): int
    {
        return ++$this->count;
    }

    public function reset(): void
    {
        $this->count = 0;
    }
}