<?php

namespace App\Models\Farmer;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['code', 'name', 'description'])]
class ActivityType extends FarmerModel
{
    protected $table = 'activity_types';

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'activity_type_id');
    }
}
