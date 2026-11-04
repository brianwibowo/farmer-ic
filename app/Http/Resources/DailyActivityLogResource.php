<?php

namespace App\Http\Resources;

use App\Models\DailyActivityLog;
use App\Models\FarmActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DailyActivityLog */
class DailyActivityLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'farm_id' => $this->farm_id,
            'work_date' => $this->work_date->format('Y-m-d'),
            'start_time' => substr($this->start_time, 0, 5),
            'end_time' => $this->end_time === null ? null : substr($this->end_time, 0, 5),
            'timezone' => 'Asia/Jakarta',
            'recorded_by' => $this->recorded_by,
            'recorder_name' => $this->recorder?->name,
            'notes' => $this->notes,
            'activities' => $this->activities->map(fn (FarmActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->activityType?->code,
                'name' => $activity->activityType?->name,
                'position' => $activity->position,
                'worker_count' => $activity->worker_count,
                'notes' => $activity->description,
                'obstacle_status' => $activity->obstacle_status,
                'obstacles' => $activity->obstacles,
                'proposed_solution' => $activity->proposed_solution,
                'action_taken' => $activity->action_taken,
                'block_mapping' => $activity->blockMappingDetail?->only([
                    'form_version', 'block_id', 'block_name', 'length_m', 'width_m', 'row_spacing_cm', 'plant_spacing_cm', 'rows_per_bed',
                ]),
            ]),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
