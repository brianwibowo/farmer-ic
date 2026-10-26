<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Concerns\HasVisibleScope;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $role
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property int|null $current_team_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Collection<int, Farm> $farms
 */
#[Fillable(['name', 'email', 'password', 'current_team_id'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @return BelongsToMany<Farm, $this> */
    public function farms(): BelongsToMany
    {
        return $this->belongsToMany(Farm::class, 'farm_user', 'user_id', 'farm_id');
    }

    /** @return list<string> */
    public function assignedFarmIds(): array
    {
        if (! $this->relationLoaded('farms')) {
            if (! Schema::hasTable('farm_user') || ! Schema::hasTable('farms')) {
                $this->setRelation('farms', new Collection);
            } else {
                $this->load(['farms' => fn ($farms) => $farms->select('farms.id')]);
            }
        }

        return array_values(array_unique(array_map(strval(...), $this->farms->modelKeys())));
    }

    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, Notifiable;

    use HasVisibleScope;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
