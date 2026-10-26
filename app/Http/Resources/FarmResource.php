<?php

namespace App\Http\Resources;

use App\Models\Farm;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Farm */
class FarmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'created_by' => $this->created_by,
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'province' => $this->province,
            'total_area_ha' => $this->total_area_ha,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->image_url,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
