<?php
/**
 * Azera benchmark Item model.
 *
 * Maps to the shared SQLite `items` table and is used by BenchController
 * for the /items endpoints.
 */

namespace App\Models;

use Azera\Core\Model;

class Item extends Model
{
    /**
     * Explicit table name (default convention would pluralise to "items",
     * but we set it explicitly for clarity).
     */
    public function source(): string
    {
        return 'items';
    }

    public int $id;

    public string $title;

    public string $created_at;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getCreatedAt(): string
    {
        return $this->created_at;
    }

    public function setCreatedAt(string $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }
}