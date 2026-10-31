<?php

namespace App\Http\Requests\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilterNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->toDateString();
        $this->merge([
            'status' => $this->input('status', 'unread'),
            'date_from' => $this->input('date_from', $today),
            'date_to' => $this->input('date_to', $today),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['all', 'unread'])],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'team_id' => ['nullable', 'integer', 'min:1'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'status.in' => 'Status notifikasi tidak valid.',
            'date_from.required' => 'Pilih tanggal awal.',
            'date_to.required' => 'Pilih tanggal akhir.',
            'date_from.date_format' => 'Format tanggal awal tidak valid.',
            'date_to.date_format' => 'Format tanggal akhir tidak valid.',
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
        ];
    }
}
