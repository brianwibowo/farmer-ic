<?php

namespace App\Http\Requests\Petugas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShowPetugasGardenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'block' => ['nullable', 'uuid'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'view' => ['sometimes', Rule::in(['running', 'history'])],
        ];
    }
}
