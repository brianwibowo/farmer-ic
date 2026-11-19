<?php

namespace App\Http\Requests\Admin;

use App\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondIncidentTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('admin_note'))) {
            $this->merge(['admin_note' => trim($this->input('admin_note'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'admin_note' => ['required', 'string', 'min:5', 'max:2000'],
            'severity' => ['nullable', Rule::in(IncidentSeverity::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'admin_note.required' => 'Arahan atau tanggapan admin wajib diisi.',
            'admin_note.min' => 'Arahan atau tanggapan minimal 5 karakter.',
        ];
    }
}
