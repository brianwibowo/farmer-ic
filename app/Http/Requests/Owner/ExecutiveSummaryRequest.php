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
            'quarter' => ['sometimes', 'required', Rule::in(['all', 'Q1', 'Q2', 'Q3', 'Q4'])],
            'partner' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'farm' => ['sometimes', 'nullable', 'uuid'],
            'commodity' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
