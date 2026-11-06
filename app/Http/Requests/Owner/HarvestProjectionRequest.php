<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HarvestProjectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'required', Rule::in(['cycle', 'year'])],
            'farm_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
