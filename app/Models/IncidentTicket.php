<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $farm_id
 * @property string $activity_id
 * @property string|null $planting_cycle_id
 * @property string|null $block_id
 * @property string|null $incident_category
 * @property string $severity
 * @property string $status
 * @property string|null $admin_note
 * @property string|null $responded_by
 * @property CarbonImmutable|null $responded_at
 * @property string|null $resolution_note
 * @property string|null $resolved_by
 * @property CarbonImmutable|null $resolved_at
 * @property string|null $resolved_activity_id
 * @property string|null $reopen_reason
 * @property string|null $reopened_by
 * @property CarbonImmutable|null $reopened_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'farm_id',
    'activity_id',
    'planting_cycle_id',
    'block_id',
    'incident_category',
    'severity',
    'status',
    'admin_note',
    'responded_by',
    'responded_at',
    'resolution_note',
    'resolved_by',
    'resolved_at',
    'resolved_activity_id',
    'reopen_reason',
    'reopened_by',
    'reopened_at',
])]
class IncidentTicket extends FarmerModel
{
    public const STATUSES = ['open', 'in_progress', 'resolved'];

    public const SEVERITIES = ['light', 'medium', 'emergency'];

    protected $table = 'incident_tickets';

    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:sP';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'responded_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'reopened_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_id');
    }

    /** @return BelongsTo<FarmActivity, $this> */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'activity_id');
    }

    /** @return BelongsTo<FarmActivity, $this> */
    public function resolvedActivity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'resolved_activity_id');
    }

    /** @return BelongsTo<PlantingCycle, $this> */
    public function plantingCycle(): BelongsTo
    {
        return $this->belongsTo(PlantingCycle::class, 'planting_cycle_id');
    }

    /** @return BelongsTo<FarmBlock, $this> */
    public function block(): BelongsTo
    {
        return $this->belongsTo(FarmBlock::class, 'block_id');
    }

    /** @return BelongsTo<User, $this> */
    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reopenedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reopened_by');
    }
}
