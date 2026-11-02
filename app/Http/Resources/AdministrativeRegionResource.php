<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdministrativeRegionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if (! is_array($this->resource)) {
            throw new \LogicException('Region options must be serialized from a lookup result.');
        }

        return array_intersect_key($this->resource, array_flip(['code', 'name', 'parent_code', 'district', 'city', 'city_code', 'province', 'province_code']));
    }
}
