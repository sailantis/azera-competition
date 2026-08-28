<?php

declare(strict_types=1);

/**
 * Cycle ORM entity over the shared `items` table.
 *
 * Discovered by Spiral's Tokenizer + AnnotatedBootloader at boot and
 * compiled into the ORM schema.
 */

namespace App\Spiral\Entity;

use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;

#[Entity(table: 'items')]
class Item
{
    #[Column(type: 'primary')]
    #[Column(type: 'primary')] public int $id;

    #[Column(type: 'string')]
    #[Column(type: 'string')] public string $title;

    #[Column(type: 'string')]
    #[Column(type: 'string')] public string $created_at;

    public function __construct(string $title, string $createdAt)
    {
        $this->title      = $title;
        $this->created_at = $createdAt;
    }
}