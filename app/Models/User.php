<?php

namespace App\Models;

use App\Concerns\HasTeams;
use App\Concerns\HasVisibleScope;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * @property string $id
 * @property string $role_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property string|null $avatar_url
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $email_verified_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property int|null $current_team_id
 * @property-read Team|null $currentTeam
 * @property-read Collection<int, Team> $ownedTeams
 * @property-read Collection<int, Membership> $teamMemberships
 * @property-read Collection<int, Team> $teams
 * @property-read Farm|null $assignedFarm
 * @property string|null $remember_token
 */
#[Fillable(['name', 'email', 'password', 'avatar_url'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasTeams, HasUuids, HasVisibleScope, Notifiable, SoftDeletes;

    protected $table = 'users';

    public $timestamps = true;

    protected $hidden = ['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'];

    protected $attributes = ['is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'current_team_id' => 'integer',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'email_verified_at' => 'immutable_datetime',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'password' => 'hashed',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** @return BelongsTo<Farm, $this> */
    public function assignedFarm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'assigned_farm_id');
    }

    public function assignedFarmId(): ?string
    {
        $farmId = $this->getAttribute('assigned_farm_id');

        return $farmId === null ? null : (string) $farmId;
    }

    /** @return HasMany<Farm, $this> */
    public function ownedFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'owner_id');
    }

    /** @return HasMany<Farm, $this> */
    public function createdFarms(): HasMany
    {
        return $this->hasMany(Farm::class, 'created_by');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'performed_by');
    }

    /** @return HasMany<PlantObservation, $this> */
    public function observations(): HasMany
    {
        return $this->hasMany(PlantObservation::class, 'observed_by');
    }

    /** @return HasMany<FinancialTransaction, $this> */
    public function financialTransactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class, 'recorded_by');
    }

    /** @return HasMany<FinancialBudget, $this> */
    public function financialBudgets(): HasMany
    {
        return $this->hasMany(FinancialBudget::class, 'created_by');
    }

    /** @return HasMany<OperationalTarget, $this> */
    public function operationalTargets(): HasMany
    {
        return $this->hasMany(OperationalTarget::class, 'created_by');
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    /** @return HasMany<Notification, $this> */
    public function readNotifications(): HasMany
    {
        return $this->notifications()->where('is_read', true);
    }

    /** @return HasMany<Notification, $this> */
    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->where('is_read', false);
    }

    /** @return HasMany<AuditLog, $this> */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'user_id');
    }
}
