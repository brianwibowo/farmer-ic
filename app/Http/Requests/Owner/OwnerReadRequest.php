<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OwnerReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:150'],
            'city' => ['sometimes', 'nullable', 'string', 'max:150'],
            'farm_id' => ['sometimes', 'nullable', 'uuid'],
            'activity_type_id' => ['sometimes', 'nullable', 'uuid'],
            'performed_by' => ['sometimes', 'nullable', 'uuid'],
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', ...($this->filled('from_date') ? ['after_or_equal:from_date'] : [])],
            'role' => ['sometimes', 'nullable', Rule::in(['owner', 'admin', 'petugas'])],
            'period' => ['sometimes', Rule::in(['7', '30'])],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
