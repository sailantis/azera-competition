<?php

/**
 * Eloquent model over the shared `items` table.
 *
 * `created_at` is a plain TEXT column (mirrors the other apps), so
 * timestamps are disabled and set explicitly.
 */

namespace App\Laravel\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    public $timestamps = false;

    protected $table = 'items';

    protected $fillable = ['id', 'title', 'created_at'];
}