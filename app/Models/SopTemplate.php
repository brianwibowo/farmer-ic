<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $team_id
 * @property string $commodity_id
 * @property string|null $copied_from_version_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['team_id', 'commodity_id', 'copied_from_version_id', 'name', 'description', 'is_active', 'created_by'])]
class SopTemplate extends FarmerModel
{
    protected $table = 'sop_templates';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /** @return BelongsTo<Commodity, $this> */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class, 'commodity_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<SopTemplateVersion, $this> */
    public function copiedFromVersion(): BelongsTo
    {
        return $this->belongsTo(SopTemplateVersion::class, 'copied_from_version_id');
    }

    /** @return HasMany<SopTemplateVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(SopTemplateVersion::class, 'sop_template_id');
    }
}
