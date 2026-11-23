<?php

namespace App\Http\Requests\Petugas;

use App\Models\PlantingCycleTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'block_id' => ['nullable', 'uuid'],
            'subblok_id' => ['nullable', 'uuid'],
            'category' => ['nullable', Rule::in(PlantingCycleTask::CATEGORIES)],
            'view' => ['sometimes', Rule::in(['running', 'history'])],
            'status' => ['sometimes', Rule::in(['all', 'active', 'overdue', 'today', 'upcoming', 'completed'])],
            'per_page' => ['sometimes', 'integer', Rule::in([10, 20, 50])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
