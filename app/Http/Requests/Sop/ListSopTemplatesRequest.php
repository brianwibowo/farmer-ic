<?php

namespace App\Http\Requests\Sop;

use Illuminate\Foundation\Http\FormRequest;

class ListSopTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'farm_id' => ['nullable', 'uuid'],
            'variety_id' => ['nullable', 'uuid'],
            'is_active' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
