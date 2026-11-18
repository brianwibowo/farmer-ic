<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPetugasReportsRequest extends FormRequest
{
    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'source' => ['nullable', Rule::in(['sop', 'mandiri'])],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'mine' => ['nullable', 'boolean'],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
        ];
    }
}
