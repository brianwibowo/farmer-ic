<?php

namespace App\Http\Requests\Petugas;

use App\Models\FarmActivity;
use App\Models\PlantingCycleTask;
use App\Support\TaskActivityFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreTaskActivityRequest extends FormRequest
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
        // Early Policy delegation prevents validation from revealing another farm's
        // task schema. Controller deliberately repeats it at the HTTP boundary.
        $task = $this->route('task');

        return $task instanceof PlantingCycleTask && Gate::allows('recordTask', [FarmActivity::class, $task]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $task = $this->route('task');
        $fields = $task instanceof PlantingCycleTask ? TaskActivityFields::forTask($task) : [];
        $rules = [
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Jakarta')->toDateString()],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'worker_count' => ['nullable', 'integer', 'between:1,32767'],
            'daily_notes' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['required', 'array', 'min:1', 'max:5'],
            'photos.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'details' => ['required', 'array:'.implode(',', array_column($fields, 'key'))],
        ];
        foreach (['daily_activity_log_id', 'farm_id', 'team_id', 'task_id', 'planting_cycle_id', 'activity_category', 'activity_subcategory', 'category', 'subcategory', 'performed_by', 'recorded_by', 'status'] as $key) {
            $rules[$key] = ['prohibited'];
        }
        foreach ($fields as $field) {
            $checks = [empty($field['optional']) ? 'required' : 'nullable'];
            if ($field['type'] === 'number') {
                $checks = [...$checks, $field['step'] === '1' ? 'integer' : 'numeric', 'min:'.($field['key'] === 'moisture' || $field['key'] === 'height_cm' || $field['key'] === 'depth_cm' ? '0' : $field['step']), 'max:'.$field['max']];
                if ($field['step'] !== '1') {
                    $checks[] = 'decimal:0,'.strlen(explode('.', $field['step'])[1]);
                }
            } else {
                $checks = [...$checks, 'string', 'max:100'];
                if ($field['type'] === 'select') {
                    $checks[] = Rule::in($field['options']);
                }
            }
            $rules['details.'.$field['key']] = $checks;
        }

        // Existing report clients retain their legacy canonical-unit contract.
        // The current UI uses the taxonomy form contract with explicit units.
        $rules['schema'] = ['nullable', 'in:taxonomy'];
        if ($this->input('schema') === 'taxonomy' && $task instanceof PlantingCycleTask) {
            $rules = array_filter($rules, fn ($key) => $key !== 'details' && ! str_starts_with($key, 'details.'), ARRAY_FILTER_USE_KEY);
            $rules += \App\Support\VoluntaryActivityFields::rules(TaskActivityFields::activityCode($task) ?? '');
        }

        return $rules;
    }
}
