<?php

namespace App\Http\Requests\Petugas;

use Illuminate\Foundation\Http\FormRequest;

class ShowPetugasGardenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['block' => ['nullable', 'uuid'], 'page' => ['sometimes', 'integer', 'min:1']];
    }
}
