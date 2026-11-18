<?php

namespace App\Http\Requests\Blocks;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreparationBatchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('subblock_label'))) {
            $this->merge(['subblock_label' => strtoupper(trim($this->input('subblock_label')))]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return list<string> */
    protected function allowedReturnTargets(): array
    {
        return ['farm', 'blocks'];
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'return_to' => ['sometimes', 'nullable', Rule::in($this->allowedReturnTargets())],
            'subblock_label' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'planned_planting_at' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'farm_cycle_id' => [$this->isMethod('put') ? 'prohibited' : 'sometimes', 'nullable', 'uuid'],
            'create_farm_cycle' => [$this->isMethod('put') ? 'prohibited' : 'sometimes', 'boolean'],
            ...array_fill_keys([
                'id', 'farm_id', 'team_id', 'block_id', 'batch_number', 'cycle_number', 'created_by',
                'status', 'current_stage', 'planted_at', 'planted_area_ha',
                'reserved_area_ha', 'area_value', 'area_unit', 'variety_id', 'stages', 'created_at',
            ], ['prohibited']),
        ];
    }

    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            if ($this->boolean('create_farm_cycle') && $this->filled('farm_cycle_id')) {
                $validator->errors()->add('farm_cycle_id', 'Pilih siklus yang tersedia atau buat siklus baru.');
            }
        }];
    }
}
