<?php

namespace App\Http\Requests\Petugas;

use App\Models\FarmActivity;
use App\Models\PlantingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StorePlantingActivityRequest extends FormRequest
{
    /** @return array<string, string> */
    public function messages(): array
    {
        return \App\Support\PhotoUploadFeedback::messages($this->file('photos', [])) + [
            'photos.*.uploaded' => 'Foto gagal diunggah oleh server. Batas PHP saat ini: '.ini_get('upload_max_filesize').' per file dan '.ini_get('post_max_size').' per request. Jika foto memenuhi batas form (10 MB), hubungi pengelola untuk menyesuaikan konfigurasi PHP.',
            'photos.*.max' => 'Ukuran setiap foto maksimal 10 MB.',
        ];
    }

    public function authorize(): bool
    {
        $cycle = $this->route('cycle');

        // Reject cross-farm writes before validation; controller repeats the
        // composite ability at its explicit HTTP boundary.
        return $cycle instanceof PlantingCycle && Gate::allows('recordPlanting', [FarmActivity::class, $cycle]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'worker_count' => ['required', 'integer', 'between:1,32767'],
            'daily_notes' => ['nullable', 'string', 'max:5000'],
            'variety_id' => ['required', 'uuid'],
            'area_ha' => ['required', 'numeric', 'decimal:0,4', 'between:0.0001,99999999.9999'],
            'seed_name' => ['required', 'string', 'max:150'],
            'quantity_kg' => ['required', 'numeric', 'decimal:0,3', 'between:0.001,999999999.999'],
            'planting_pattern' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['required', 'array', 'list', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'schema' => ['nullable', 'in:taxonomy'],
        ];
        if ($this->input('schema') === 'taxonomy') {
            $rules['area_ha'] = ['prohibited'];
            $rules['quantity_kg'] = ['prohibited'];
            $rules['area'] = ['required', 'numeric', 'min:0.0001', 'max:99999999', $this->input('area_unit') === 'm²' ? 'integer' : 'decimal:0,4'];
            $rules['area_unit'] = ['required', 'in:m²,Ha'];
            $rules['quantity'] = ['required', 'numeric', 'min:0.001', 'max:999999999'];
            $rules['quantity_unit'] = ['required', 'in:kg,sak,butir'];
            $rules['bag_size'] = ['exclude_unless:quantity_unit,sak', 'required', 'numeric', 'min:0.001', 'max:99999'];
            $rules['depth_cm'] = ['nullable', 'numeric', 'min:0.01', 'max:1000'];
        }
        foreach (['daily_activity_log_id', 'farm_id', 'team_id', 'planting_cycle_id', 'task_id', 'activity_type_id', 'performed_by', 'recorded_by', 'status', 'planted_at', 'planned_planting_at', 'sop_template_version_id', 'activity_category', 'activity_subcategory'] as $key) {
            $rules[$key] = ['prohibited'];
        }

        return $rules;
    }
}
