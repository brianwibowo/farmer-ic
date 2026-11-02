<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $farm_id
 * @property string $variety_id
 * @property string|null $copied_from_version_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property string $created_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['farm_id', 'variety_id', 'copied_from_version_id', 'name', 'description', 'is_active', 'created_by'])]
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

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<CropVariety, $this> */
    public function variety(): BelongsTo
    {
        return $this->belongsTo(CropVariety::class, 'variety_id');
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
