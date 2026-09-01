<?php

declare(strict_types=1);

/**
 * Per-request counter holder (mirrors azera's RequestCounter + the
 * Spiral/CI4 demos): the endpoint shows before/after/reset values to
 * prove request-scoped semantics.
 */

namespace App\Cake\Support;

final class RequestCounter
{
    private int $count = 0;

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