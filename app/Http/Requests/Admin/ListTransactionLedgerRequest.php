<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class ListTransactionLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'farm_id' => ['sometimes', 'nullable', 'uuid'],
            'block_id' => ['sometimes', 'nullable', 'uuid'],
            'variety_id' => ['sometimes', 'nullable', 'uuid'],
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
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
