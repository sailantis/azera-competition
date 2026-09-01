<?php

declare(strict_types=1);

/**
 * Items table — Cake Table ORM (hydration + save() = upsert via entity).
 */

namespace App\Cake\Model\Table;

use Cake\ORM\Table;

final class ItemsTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setTable('items');
        $this->setPrimaryKey('id');
        $this->setDisplayField('title');
    }
}