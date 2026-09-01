<?php

/**
 * Benchmark constants — sentinel IDs shared across controllers.
 *
 * Mirrors Spiral's constants: fixed sentinel row IDs keep the row count
 * stable across benchmark runs (find-before-write upsert semantics).
 */

namespace App\Laravel;

final class Benchmark
{
    public const SENTINEL_ORM_ID = 999999;
    public const SENTINEL_QB_ID = 999997;
    public const SENTINEL_API_ID = 999998;
    public const PAGE_SIZE = 20;
}