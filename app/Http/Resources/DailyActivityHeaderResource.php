<?php

namespace App\Http\Resources;

use App\Models\DailyActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DailyActivityLog */
class DailyActivityHeaderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'farm_id' => $this->farm_id, 'work_date' => $this->work_date->toDateString(),
            'recorded_by' => $this->recorded_by,
            'recorder_name' => $this->whenLoaded('recorder', fn () => $this->recorder?->name),
        ];
    }
}
