<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $planting_cycle_id
 * @property string $planting_cycle_stage_id
 * @property string|null $sop_template_task_id
 * @property string $activity_category
 * @property string|null $activity_subcategory
 * @property string $name
 * @property string|null $instructions
 * @property int $offset_days
 * @property int $tolerance_days
 * @property CarbonImmutable|null $scheduled_date
 * @property CarbonImmutable|null $due_date
 * @property bool $is_required
 * @property string $status
 * @property CarbonImmutable|null $completed_at
 * @property string|null $completed_by
 * @property string|null $completion_note
 * @property string|null $skip_reason
 * @property CarbonImmutable|null $rescheduled_date
 * @property string|null $reschedule_reason
 * @property string|null $rescheduled_by
 * @property CarbonImmutable|null $rescheduled_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'planting_cycle_id', 'planting_cycle_stage_id', 'sop_template_task_id',
    'activity_category', 'activity_subcategory', 'name', 'instructions',
    'offset_days', 'tolerance_days', 'scheduled_date', 'due_date',
    'is_required', 'status', 'completed_at', 'completed_by', 'completion_note',
    'skip_reason', 'rescheduled_date', 'reschedule_reason', 'rescheduled_by', 'rescheduled_at',
])]
class PlantingCycleTask extends FarmerModel
{
    public const STATUSES = ['pending', 'in_progress', 'completed', 'skipped'];

    public const CATEGORIES = ['maintenance', 'harvest'];

    protected $table = 'planting_cycle_tasks';

    public $timestamps = true;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'offset_days' => 'integer',
            'tolerance_days' => 'integer',
            'scheduled_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'is_required' => 'boolean',
            'completed_at' => 'immutable_datetime',
            'rescheduled_date' => 'immutable_date',
            'rescheduled_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class);
    }

    /** @return BelongsTo<PlantingCycleStage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(PlantingCycleStage::class, 'planting_cycle_stage_id');
    }

    /** @return BelongsTo<SopTemplateTask, $this> */
    public function sourceTask(): BelongsTo
    {
        return $this->belongsTo(SopTemplateTask::class, 'sop_template_task_id');
    }

    /** @return HasMany<FarmActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(FarmActivity::class, 'planting_cycle_task_id');
    }

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rescheduledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function effectiveScheduledDate(): ?CarbonImmutable
    {
        return $this->rescheduled_date ?? $this->scheduled_date;
    }
}
