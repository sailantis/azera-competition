<?php

declare(strict_types=1);

namespace App\Phalcon\Model;

use Phalcon\Mvc\Model;

/**
 * The `items` row, mapped onto the shared benchmark table.
 *
 * `id` is the primary key and is NOT auto-generated here: the benchmark writes
 * fixed sentinel rows so the row count stays stable across runs, which is the
 * same upsert contract the other six apps implement.
 */
class Item extends Model
{
    public ?int $id = null;

    public string $title = '';

    public string $created_at = '';

    public function initialize(): void
    {
        $this->setSource('items');

        // Timestamps are written explicitly (the benchmark controls them), so
        // Phalcon must not try to maintain its own.
        $this->skipAttributesOnCreate(['id']);
    }
}
