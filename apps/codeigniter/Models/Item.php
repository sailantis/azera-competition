<?php

declare(strict_types=1);

/**
 * Items model — the shared `items` table via CodeIgniter's Model (Query
 * Builder backed Active Record pattern).  Mirrors azera's Item model API:
 * find(id), a paginated list, and an upsert-style POST write path.
 */

namespace Ci4App\Models;

use CodeIgniter\Model;

final class Item extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['title', 'created_at'];
    protected $useAutoIncrement = true;
}