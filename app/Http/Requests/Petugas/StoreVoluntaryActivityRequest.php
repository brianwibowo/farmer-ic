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

    protected function prepareForValidation(): void
    {
        $type = $this->route('type');
        if ($type instanceof ActivityType && $type->code === 'incident') {
            $details = $this->input('details');
            if ($details === null || (is_array($details) && (! array_key_exists('incident_status', $details) || $details['incident_status'] === null || $details['incident_status'] === ''))) {
                $details = is_array($details) ? $details : [];
                $details['incident_status'] = 'Perlu penanganan';
                $this->merge(['details' => $details]);
            }
        }
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
            'worker_count' => [$code === 'incident' ? 'nullable' : 'required', 'integer', 'between:1,'.ActivityInputLimits::WORKER_COUNT],
            'notes' => ['nullable', 'string', 'max:5000'],
            'cycle_id' => $blockOnly
                ? ['prohibited']
                : [$optional ? 'nullable' : 'required', 'uuid'],
            'block_id' => $blockOnly
                ? ['required', 'uuid', 'prohibits:cycle_id']
                : ($optional ? ['nullable', 'uuid', 'prohibits:cycle_id'] : ['prohibited']),
            'photos' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:15360'],
            'farm_id' => ['prohibited'], 'team_id' => ['prohibited'], 'performed_by' => ['prohibited'],
            'task_id' => ['prohibited'], 'planting_cycle_task_id' => ['prohibited'], 'daily_activity_log_id' => ['prohibited'],
            'incident_ticket_id' => $code === 'incident' ? ['prohibited'] : ['nullable', 'uuid'],
            ...VoluntaryActivityFields::rules($code),
        ];
    }

    public function messages(): array
    {
        return \App\Support\PhotoUploadFeedback::messages($this->file('photos', []));
    }
}
