<?php

namespace App\Http\Requests\Admin;

use App\Models\PlantingCycleTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('search'))) {
            $this->merge(['search' => trim($this->input('search'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'block_id' => ['nullable', 'uuid'],
            'subblok_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::in(PlantingCycleTask::STATUSES)],
            'category' => ['nullable', Rule::in(PlantingCycleTask::CATEGORIES)],
            'view' => ['nullable', Rule::in(['actionable', 'overdue', 'today', 'upcoming', 'completed', 'all'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
