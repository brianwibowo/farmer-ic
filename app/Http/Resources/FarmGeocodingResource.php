<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmGeocodingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->resource['label'],
            'latitude' => $this->resource['latitude'],
            'longitude' => $this->resource['longitude'],
            'approximate' => $this->resource['approximate'],
            'matches_region' => $this->resource['matches_region'],
            'can_auto_select' => $this->resource['can_auto_select'],
            'search_stage' => $this->resource['search_stage'],
        ];
    }
}
