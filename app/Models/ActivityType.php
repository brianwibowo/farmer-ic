<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string|null $parent_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $detail_key
 * @property bool $is_selectable
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['parent_id', 'code', 'name', 'description', 'detail_key', 'is_selectable', 'sort_order', 'is_active'])]
class ActivityType extends FarmerModel
{
    protected $table = 'activity_types';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_selectable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ActivityType, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'activity_type_id');
    }
}
