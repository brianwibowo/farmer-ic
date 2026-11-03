<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('reschedule_reason'))) {
            $this->merge(['reschedule_reason' => trim($this->input('reschedule_reason'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'rescheduled_date' => ['required', 'date_format:Y-m-d'],
            'reschedule_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'rescheduled_date.required' => 'Tanggal baru wajib diisi.',
            'rescheduled_date.date_format' => 'Tanggal baru harus berformat YYYY-MM-DD.',
            'reschedule_reason.required' => 'Alasan penjadwalan ulang wajib diisi.',
            'reschedule_reason.min' => 'Alasan penjadwalan ulang minimal 5 karakter.',
        ];
    }

    /** @return array{rescheduled_date: string, reschedule_reason: string} */
    public function payload(): array
    {
        return [
            'rescheduled_date' => (string) $this->validated('rescheduled_date'),
            'reschedule_reason' => (string) $this->validated('reschedule_reason'),
        ];
    }
}
