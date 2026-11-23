<?php

namespace App\Http\Requests\Blocks;

use App\Support\GeoPolygon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SaveFarmBlockBoundaryRequest extends FormRequest
{
    /** @var array{type: 'Polygon', coordinates: list<list<array{0: float, 1: float}>>}|null */
    private ?array $normalizedBoundary = null;

    public function authorize(): bool
    {
        return true; // The controller checks FarmBlockPolicy before calling the service.
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'boundary_geojson' => ['nullable', 'array'],
            'apply_area' => ['sometimes', 'boolean'],
            'return_to' => ['sometimes', 'nullable', 'string', Rule::in(['farm', 'blocks'])],
            ...array_fill_keys(['id', 'farm_id', 'team_id', 'created_by', 'mapped_area_ha', 'area_ha'], ['prohibited']),
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $boundary = $this->input('boundary_geojson');
            if ($boundary === null) {
                $this->normalizedBoundary = null;

                return;
            }

            try {
                $this->normalizedBoundary = GeoPolygon::normalize($boundary);
            } catch (InvalidArgumentException $exception) {
                $validator->errors()->add('boundary_geojson', $exception->getMessage());
            }
        }];
    }

    /** @return array{type: 'Polygon', coordinates: list<list<array{0: float, 1: float}>>}|null */
    public function normalizedBoundary(): ?array
    {
        return $this->normalizedBoundary;
    }
}
