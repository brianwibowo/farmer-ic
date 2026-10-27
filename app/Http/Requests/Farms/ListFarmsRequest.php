<?php

namespace App\Http\Requests\Farms;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ListFarmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $team = $user?->currentTeam;

        return $user !== null && $team !== null
            && Gate::forUser($user)->allows('viewAny', [Farm::class, $team]);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:150'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
            'sort' => ['sometimes', Rule::in(['name', 'created_at', 'total_area_ha'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
