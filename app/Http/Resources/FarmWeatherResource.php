<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FarmWeatherResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'farmId' => $this->resource['farmId'],
            'status' => $this->resource['status'],
            'forecast' => $this->resource['forecast'],
        ];
    }

    public function withResponse(Request $request, \Illuminate\Http\JsonResponse $response): void
    {
        // Recheck the session and farm visibility on every poll, even with a warm provider cache.
        $response->headers->set('Cache-Control', 'private, no-store');
    }
}
