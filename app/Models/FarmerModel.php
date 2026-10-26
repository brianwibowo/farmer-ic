<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for UUID records in the PostgreSQL ERD schema.
 * Timestamp columns are enabled only by models whose tables contain them.
 */
abstract class FarmerModel extends Model
{
    use HasUuids;

    public $timestamps = false;
}
