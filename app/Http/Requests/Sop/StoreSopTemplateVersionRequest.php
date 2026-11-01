<?php

namespace App\Http\Requests\Sop;

use Illuminate\Foundation\Http\FormRequest;

class StoreSopTemplateVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            ...array_fill_keys([
                'id', 'sop_template_id', 'version', 'status', 'created_by',
                'published_by', 'published_at', 'created_at', 'updated_at', 'tasks',
            ], ['prohibited']),
        ];
    }
}
