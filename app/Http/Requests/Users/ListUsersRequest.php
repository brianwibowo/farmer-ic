<?php

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'role' => ['nullable', 'string', 'max:50'],
            'team_id' => ['nullable', 'integer'],
            'farm_id' => ['nullable', 'uuid'],
            'per_page' => ['sometimes', 'integer', Rule::in([15, 30, 50])],
        ];
    }
}
