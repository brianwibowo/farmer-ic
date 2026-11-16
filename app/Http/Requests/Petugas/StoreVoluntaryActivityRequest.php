<?php

namespace App\Http\Requests\Petugas;

use App\Models\ActivityType;
use App\Services\Activity\ActivitySequenceService;
use App\Support\ActivityInputLimits;
use App\Support\VoluntaryActivityFields;
use Illuminate\Foundation\Http\FormRequest;

class StoreVoluntaryActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = $this->route('type');
        $code = $type instanceof ActivityType ? $type->code : '';
        $blockOnly = ActivitySequenceService::isBlockPreparation($code);
        $optional = in_array($code, ['incident', 'other'], true);

        return [
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'start_time' => ['nullable', 'date_format:H:i'], 'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'worker_count' => ['required', 'integer', 'between:1,'.ActivityInputLimits::WORKER_COUNT], 'daily_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cycle_id' => $blockOnly
                ? ['prohibited']
                : [$optional ? 'nullable' : 'required', 'uuid'],
            'block_id' => $blockOnly
                ? ['required', 'uuid', 'prohibits:cycle_id']
                : ($optional ? ['nullable', 'uuid', 'prohibits:cycle_id'] : ['prohibited']),
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
