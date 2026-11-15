<?php

declare(strict_types=1);

/**
 * Items entity — the hydrated row shape for CI4's Model layer.
 *
 * CI4 hydrates by `$returnType = <class-string>`: `BaseModel` asks the result
 * object for `getCustomResultObject(Item::class)`, which instantiates one
 * entity per row through `fetchObject()` (`new $className()` +
 * `injectRawData()`). That is the direct analogue of an Eloquent model or a
 * Doctrine entity, and it is the shape `/items` and `/api/items` are meant to
 * measure.
 *
 * `$dates = []` is deliberate, not an oversight. CI4's Entity defaults to
 * casting `created_at`/`updated_at`/`deleted_at` into `CodeIgniter\I18n\Time`
 * objects. The shared `items` table stores `created_at` as plain TEXT (every
 * app does the same), so the cast would turn the serialized field into
 *
 *     {"date":"2026-11-10 01:24:24.000000","timezone_type":3,"timezone":"UTC"}
 *
 * instead of the raw string the other five frameworks emit. That would change
 * the response body — and this benchmark compares response bodies byte-for-
 * byte across apps — for a reason that has nothing to do with hydration cost.
 * Disabling the date casting keeps `created_at` a string, so hydration is the
 * only difference between `/items` and `/items-fast`.
 */

namespace Ci4App\Entities;

use CodeIgniter\Entity\Entity;

final class Item extends Entity
{
    /**
     * Empty on purpose — see the class docblock.
     *
     * @var list<string>
     */
    protected $dates = [];

    /**
     * @var array<string, string>
     */
    protected $casts = [];
}
