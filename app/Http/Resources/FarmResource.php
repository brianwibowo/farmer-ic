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
            'land_tenure' => $this->land_tenure,
            'land_tenure_party' => $this->getAttribute('land_tenure_party'),
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'district_code' => $this->getAttribute('district_code'),
            'province' => $this->province,
            'total_area_ha' => $this->total_area_ha,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'image_url' => $this->image_url,
            'default_sop_template_id' => $this->default_sop_template_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
