<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecutiveSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'year' => ['sometimes', 'required', 'integer', 'between:2000,2100'],
            'cycle' => ['sometimes', 'required', Rule::when($this->input('cycle') !== 'all', ['uuid'])],
            'partner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'farm' => ['sometimes', 'nullable', 'uuid'],
            'commodity' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
