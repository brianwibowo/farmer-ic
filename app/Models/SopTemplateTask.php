<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $sop_template_version_id
 * @property string $activity_category
 * @property string|null $activity_subcategory
 * @property string $name
 * @property string|null $instructions
 * @property int $offset_days
 * @property int $tolerance_days
 * @property int|null $hok
 * @property bool $is_required
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sop_template_version_id', 'activity_category', 'activity_subcategory', 'name', 'instructions', 'offset_days', 'tolerance_days', 'hok', 'is_required'])]
class SopTemplateTask extends FarmerModel
{
    /** @var array<string, string> */
    public const CATEGORIES = [
        'maintenance' => 'Perawatan',
        'harvest' => 'Panen',
    ];

    protected $table = 'sop_template_tasks';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'tolerance_days' => 'integer',
            'hok' => 'integer',
            'is_required' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return array<int, string> */
    public static function categoryCodes(): array
    {
        return array_keys(self::CATEGORIES);
    }

    /** @return BelongsTo<SopTemplateVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SopTemplateVersion::class, 'sop_template_version_id');
    }
}
