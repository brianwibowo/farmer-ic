<?php

namespace App\Http\Requests\Petugas;

use App\Models\ActivityType;
use App\Support\VoluntaryActivityFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoluntaryActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $type = $this->route('type');

        return [
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'worker_count' => ['nullable', 'integer', 'between:1,32767'], 'daily_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cycle_id' => [$type instanceof ActivityType && $type->code === 'other' ? 'nullable' : 'required', 'uuid'],
            'photos' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'farm_id' => ['prohibited'], 'team_id' => ['prohibited'], 'performed_by' => ['prohibited'],
            'task_id' => ['prohibited'], 'planting_cycle_task_id' => ['prohibited'], 'daily_activity_log_id' => ['prohibited'],
            ...VoluntaryActivityFields::rules($type instanceof ActivityType ? $type->code : ''),
        ];
    }

    public function messages(): array
    {
        return \App\Support\PhotoUploadFeedback::messages($this->file('photos', []));
    }
}
