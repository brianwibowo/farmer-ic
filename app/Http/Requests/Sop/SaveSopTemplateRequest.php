<?php

namespace App\Http\Requests\Sop;

use Illuminate\Foundation\Http\FormRequest;

class SaveSopTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'commodity_id' => ['required', 'uuid', 'exists:commodities,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
            ...array_fill_keys([
                'id', 'farm_id', 'team_id', 'copied_from_version_id', 'created_by',
                'created_at', 'updated_at', 'versions', 'status', 'version',
            ], ['prohibited']),
        ];
    }
}
