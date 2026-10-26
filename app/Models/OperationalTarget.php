<?php

namespace App\Models;

use App\Models\Farmer\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $farm_id
 * @property string|null $planting_cycle_id
 * @property string $task_name
 * @property string $target_unit
 * @property string $target_value
 * @property string $completed_value
 * @property CarbonImmutable|null $target_date
 * @property string|null $status
 * @property string $created_by
 */
#[Fillable(['farm_id', 'planting_cycle_id', 'task_name', 'target_unit', 'target_value', 'completed_value', 'target_date', 'status', 'created_by'])]
class OperationalTarget extends FarmerModel
{
    protected $table = 'operational_targets';

    protected $attributes = ['completed_value' => '0.000'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'target_value' => 'decimal:3',
            'completed_value' => 'decimal:3',
            'target_date' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class, 'planting_cycle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
