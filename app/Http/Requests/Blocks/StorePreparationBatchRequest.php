<?php

namespace App\Http\Requests\Blocks;

use Illuminate\Foundation\Http\FormRequest;

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

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'reserved_area_ha' => ['required', 'numeric', 'decimal:0,4', 'between:0.0001,99999999.9999'],
            'subblock_label' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            ...array_fill_keys(['id', 'farm_id', 'team_id', 'block_id', 'planting_round_id', 'batch_number', 'created_by', 'status', 'current_stage', 'planted_at', 'planted_area_ha', 'variety_id', 'stages', 'created_at'], ['prohibited']),
        ];
    }
}
