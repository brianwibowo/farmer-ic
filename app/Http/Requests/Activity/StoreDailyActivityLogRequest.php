<?php

namespace App\Http\Requests\Activity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDailyActivityLogRequest extends FormRequest
{
    // Temporary local/testing contract, matching the existing farm API.
    // recorded_by is a selected record reference, not authenticated authority.
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'worker_count' => ['required', 'integer', 'between:1,32767'],
            'recorded_by' => ['required', 'uuid', Rule::exists('users', 'id')->where('is_active', true)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'activities' => ['required', 'array', 'list', 'min:1', 'max:50'],
            'activities.*' => ['required', 'array:type,block_mapping,obstacle_status,obstacles,proposed_solution,action_taken,notes'],
            'activities.*.type' => ['required', Rule::in(['block_mapping'])],
            'activities.*.block_mapping' => ['required', 'array:block_name,length_m,width_m,row_spacing_cm,plant_spacing_cm,rows_per_bed'],
            'activities.*.block_mapping.block_name' => ['required', 'string', 'max:100'],
            'activities.*.block_mapping.length_m' => ['required', 'numeric', 'decimal:0,3', 'between:0.001,9999999.999'],
            'activities.*.block_mapping.width_m' => ['required', 'numeric', 'decimal:0,3', 'between:0.001,9999999.999'],
            'activities.*.block_mapping.row_spacing_cm' => ['nullable', 'required_with:activities.*.block_mapping.plant_spacing_cm', 'numeric', 'decimal:0,2', 'between:0.01,999999.99'],
            'activities.*.block_mapping.plant_spacing_cm' => ['nullable', 'required_with:activities.*.block_mapping.row_spacing_cm', 'numeric', 'decimal:0,2', 'between:0.01,999999.99'],
            'activities.*.block_mapping.rows_per_bed' => ['sometimes', 'nullable', 'integer', 'between:1,32767'],
            'activities.*.obstacle_status' => ['required', Rule::in(['none', 'reported'])],
            'activities.*.obstacles' => ['nullable', 'required_if:activities.*.obstacle_status,reported', 'prohibited_if:activities.*.obstacle_status,none', 'string', 'max:5000'],
            'activities.*.proposed_solution' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'activities.*.action_taken' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'activities.*.notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'id' => ['prohibited'],
            'farm_id' => ['prohibited'],
            'performed_by' => ['prohibited'],
            'created_by' => ['prohibited'],
            'status' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }
}
