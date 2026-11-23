<?php

namespace App\Http\Requests\Sop;

use App\Models\SopTemplateTask;
use App\Support\ActivityInputLimits;
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
            $this->merge(['is_required' => filter_var($this->input('is_required'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE)]);
        }
        if ($this->has('hok') && $this->input('hok') === '') {
            $this->merge(['hok' => null]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'activity_category' => ['required', Rule::in(SopTemplateTask::categoryCodes())],
            'activity_subcategory' => ['prohibited'],
            'name' => ['required', 'string', 'max:200'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'offset_days' => ['required', 'integer', 'between:0,'.ActivityInputLimits::SOP_OFFSET],
            'tolerance_days' => ['required', 'integer', 'between:0,'.ActivityInputLimits::SOP_TOLERANCE],
            'hok' => ['nullable', 'integer', 'between:1,'.ActivityInputLimits::SOP_HOK],
            'is_required' => ['sometimes', 'required', Rule::in([true])],
            ...array_fill_keys([
                'id', 'sop_template_version_id', 'version', 'status', 'position', 'schedule_anchor',
                'activity_type_id', 'stage_code', 'created_at', 'updated_at',
            ], ['prohibited']),
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['hok' => 'estimasi HOK'];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'is_required.required' => 'Seluruh langkah SOP bersifat wajib dan tidak dapat dijadikan opsional.',
            'is_required.in' => 'Seluruh langkah SOP bersifat wajib dan tidak dapat dijadikan opsional.',
        ];
    }
}
