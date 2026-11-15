<?php

declare(strict_types=1);

/**
 * Items model — the shared `items` table via CodeIgniter's Model (Query
 * Builder backed Active Record pattern).  Mirrors azera's Item model API:
 * find(id), a paginated list, and an upsert-style POST write path.
 *
 * `$returnType` is the entity class, i.e. this model HYDRATES. CI4 builds one
 * entity per row (`BaseModel` → `getCustomResultObject()` → `fetchObject()`),
 * which is what the `/items` and `/api/items` endpoints are meant to measure.
 *
 * It used to be `'object'` — CI4's *unhydrated* shape, plain `stdClass` rows.
 * That made CI4's `/items` and `/items-fast` return the identical row shape,
 * so its published `-fast` band was measuring the difference between
 * `findAll()` and an explicit `builder()->get()->getResultObject()` rather
 * than the cost of hydration. The benchmark's other five apps all hydrate on
 * `/items`, so CI4 was the outlier.
 *
 * The entity's `$dates = []` matters here: CI4 would otherwise cast
 * `created_at` into an I18n `Time` object and change the JSON body.
 */

namespace Ci4App\Models;

use Ci4App\Entities\Item as ItemEntity;
use CodeIgniter\Model;

final class Item extends Model
{
    protected $table = 'items';
    protected $primaryKey = 'id';
    protected $returnType = ItemEntity::class;
    protected $useSoftDeletes = false;
    protected $useTimestamps = false;
    protected $allowedFields = ['title', 'created_at'];
    protected $useAutoIncrement = true;
}