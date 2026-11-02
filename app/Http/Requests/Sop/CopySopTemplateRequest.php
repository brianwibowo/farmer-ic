<?php

namespace App\Http\Requests\Sop;

use Illuminate\Foundation\Http\FormRequest;

class CopySopTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('target_farm_ids'))) {
            $this->merge(['target_farm_ids' => array_values(array_unique($this->input('target_farm_ids')))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'target_farm_ids' => ['required', 'array', 'min:1', 'max:50'],
            'target_farm_ids.*' => ['required', 'uuid', 'distinct', 'exists:farms,id'],
            ...array_fill_keys([
                'version', 'status', 'sop_template_id', 'created_by', 'created_at', 'updated_at',
            ], ['prohibited']),
        ];
    }
}
