<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $name
 * @property string|null $description
 */
#[Fillable(['name', 'description'])]
class Role extends FarmerModel
{
    protected $table = 'roles';

    /** @return HasMany<FarmUser, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(FarmUser::class, 'role_id');
    }
}
