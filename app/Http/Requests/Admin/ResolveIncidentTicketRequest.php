<?php

namespace App\Http\Requests\Admin;

use App\Enums\IncidentSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveIncidentTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('resolution_note'))) {
            $this->merge(['resolution_note' => trim($this->input('resolution_note'))]);
        }
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'resolution_note' => ['required', 'string', 'min:5', 'max:2000'],
            'severity' => ['nullable', Rule::in(IncidentSeverity::values())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resolution_note.required' => 'Catatan penyelesaian wajib diisi.',
            'resolution_note.min' => 'Catatan penyelesaian minimal 5 karakter.',
        ];
    }
}
