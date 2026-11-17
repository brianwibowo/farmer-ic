<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'farm_id' => ['nullable', 'uuid'],
            'period' => ['sometimes', Rule::in(['7', '30'])],
            'activity_search' => ['nullable', 'string', 'max:200'],
            'activity_officer_id' => ['nullable', 'uuid'],
            'activity_per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'activity_page' => ['sometimes', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
