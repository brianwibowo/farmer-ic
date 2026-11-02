<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $sop_template_version_id
 * @property string $activity_type_id
 * @property string $stage_code
 * @property string $name
 * @property string|null $instructions
 * @property string $schedule_anchor
 * @property int $offset_days
 * @property int $tolerance_days
 * @property bool $is_required
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['sop_template_version_id', 'activity_type_id', 'stage_code', 'name', 'instructions', 'schedule_anchor', 'offset_days', 'tolerance_days', 'is_required'])]
class SopTemplateTask extends FarmerModel
{
    public const STAGE_CODES = ['land_cleaning', 'soil_preparation', 'basic_fertilizer', 'planting', 'maintenance', 'harvest'];

    public const SCHEDULE_ANCHORS = ['planting_date', 'stage_start'];

    protected $table = 'sop_template_tasks';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'tolerance_days' => 'integer',
            'is_required' => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<SopTemplateVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(SopTemplateVersion::class, 'sop_template_version_id');
    }

    /** @return BelongsTo<ActivityType, $this> */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class, 'activity_type_id');
    }
}
