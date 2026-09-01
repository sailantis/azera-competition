<?php

declare(strict_types=1);

/**
 * Static app config — the values the CI4 Benchmark config carries, kept in
 * one place for Cake (Configure-style static access via plain constants).
 */

namespace App\Cake;

final class Benchmark
{
    public const NAME = 'Azera Competition (CakePHP 5)';

    public const PAGE_SIZE = 20;

    public const SENTINEL_ORM_ID = 999999;

    public const SENTINEL_QB_ID = 999997;

    public const SENTINEL_API_ID = 999998;

    private function __construct() {}
}