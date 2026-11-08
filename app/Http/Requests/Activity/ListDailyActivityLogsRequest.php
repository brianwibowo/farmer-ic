<?php

namespace App\Http\Requests\Activity;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class ListDailyActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $farm = $this->route('farm');

        return $user !== null && $farm instanceof Farm
            && Gate::forUser($user)->allows('view', $farm);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'from_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'recorded_by' => ['sometimes', 'nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
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
