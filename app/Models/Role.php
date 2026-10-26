<?php

namespace App\Models\Farmer;

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

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
