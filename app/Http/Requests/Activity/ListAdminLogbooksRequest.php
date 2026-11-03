<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListAdminLogbooksRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => ['nullable', 'date_format:Y-m-d'],
            'recorded_by' => ['nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', Rule::in([15, 30, 50])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->hasAny(['from_date', 'to_date'])
                && $this->filled(['from_date', 'to_date']) && $this->input('to_date') < $this->input('from_date')) {
                $validator->errors()->add('to_date', 'Tanggal akhir tidak boleh sebelum tanggal awal.');
            }
        }];
    }
}
