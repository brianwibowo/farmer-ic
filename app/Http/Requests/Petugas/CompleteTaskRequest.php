<?php

namespace App\Http\Requests\Petugas;

use App\Models\PlantingCycleTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CompleteTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $task instanceof PlantingCycleTask && Gate::allows('complete', $task);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:2000'],
            'status' => ['prohibited'],
            'completed_at' => ['prohibited'],
            'completed_by' => ['prohibited'],
            'completion_note' => ['prohibited'],
            'task_id' => ['prohibited'],
            'farm_id' => ['prohibited'],
        ];
    }
}
