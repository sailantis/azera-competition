<?php

declare(strict_types=1);

/**
 * Doctrine ORM entity over the shared `items` table.
 *
 * `created_at` is a plain TEXT column (mirrors the other apps), so it is
 * mapped as a plain string, not a datetime type.
 */

namespace App\Symfony\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'items')]
class Item
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    #[ORM\Id] #[ORM\Column(type: 'integer')] #[ORM\GeneratedValue] public int $id;

    #[ORM\Column(type: 'string')]
    #[ORM\Column(type: 'string')] public string $title;

    #[ORM\Column(type: 'string')]
    #[ORM\Column(type: 'string')] public string $created_at;

    public function __construct(string $title, string $createdAt)
    {
        $this->title      = $title;
        $this->created_at = $createdAt;
    }
}