<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base for UUID records in database/migrations/farmer_ic.
 * Timestamp columns are enabled only by models whose tables contain them.
 */
abstract class FarmerModel extends Model
{
    use HasUuids;

    public $timestamps = false;
}
