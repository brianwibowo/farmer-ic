<?php

namespace App\Http\Requests\Sop;

use App\Models\SopTemplateTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSopTemplateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
        if ($this->has('is_required')) {
            $this->merge(['is_required' => filter_var($this->input('is_required'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'activity_type_id' => ['required', 'uuid', 'exists:activity_types,id'],
            'stage_code' => ['required', Rule::in(SopTemplateTask::STAGE_CODES)],
            'name' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'schedule_anchor' => ['required', Rule::in(SopTemplateTask::SCHEDULE_ANCHORS)],
            'offset_days' => ['required', 'integer', 'between:-3650,3650'],
            'tolerance_days' => ['required', 'integer', 'between:0,365'],
            'is_required' => ['sometimes', 'boolean'],
            ...array_fill_keys([
                'id', 'sop_template_version_id', 'version', 'status', 'position', 'created_at', 'updated_at',
            ], ['prohibited']),
        ];
    }
}
