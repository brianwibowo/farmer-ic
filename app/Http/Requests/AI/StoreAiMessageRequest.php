<?php

namespace App\Http\Requests\AI;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAiMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $maxFiles = (int) config('ai.attachments.max_files', 3);
        $maxSize = (int) config('ai.attachments.max_size_kb', 10240);
        $mimes = implode(',', ['jpg', 'jpeg', 'png', 'webp', 'gif']);

        return [
            'message' => ['nullable', 'string', 'max:'.config('ai.limits.max_message_chars', 12000)],
            'conversation_id' => ['nullable', 'integer'],
            'is_infographic' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.$maxFiles],
            'attachments.*' => ['file', 'max:'.$maxSize, 'mimes:'.$mimes],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $message = trim((string) $this->input('message', ''));
            $files = $this->file('attachments', []);

            if ($message === '' && (! is_array($files) || count($files) === 0)) {
                $validator->errors()->add('message', 'Tulis pesan atau lampirkan gambar.');
            }
        });
    }
}
