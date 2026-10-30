<?php

namespace App\Http\Requests\Blocks;

use Illuminate\Foundation\Http\FormRequest;

class SaveFarmBlockRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    public function authorize(): bool
    {
        return true; // The controller checks the policy before calling the service.
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $presence = $this->isMethod('patch') ? ['sometimes', 'required'] : ['required'];

        return [
            'code' => [...$presence, 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9_-]*$/'],
            'name' => [...$presence, 'string', 'max:150'],
            'area_ha' => [...$presence, 'numeric', 'decimal:0,4', 'between:0.0001,99999999.9999'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
            ...array_fill_keys(['id', 'farm_id', 'team_id', 'created_by', 'created_at', 'updated_at', 'status', 'current_stage', 'land_clearing_started_at', 'land_clearing_completed_at'], ['prohibited']),
        ];
    }
}
