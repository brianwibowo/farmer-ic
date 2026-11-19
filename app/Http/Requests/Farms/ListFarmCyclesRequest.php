<?php

namespace App\Http\Requests\Farms;

use Illuminate\Foundation\Http\FormRequest;

class ListFarmCyclesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:100000']];
    }
}
